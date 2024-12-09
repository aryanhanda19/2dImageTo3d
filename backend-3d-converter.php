<?php
/**
 * Plugin Name: WooCommerce 3D Model Converter
 * Description: Adds a "Convert to 3D Model" feature in the WooCommerce product edit screen.
 * Version: 1.0.1
 * Author: Your Name
 */


if (!defined('ABSPATH')) exit; // Exit if accessed directly


define('PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));  // Path to the plugin directory
define('PLUGIN_URL', plugin_dir_url(__FILE__));        // URL to the plugin directory (useful for frontend)


// Hook to display the 3D model on the product page
add_action( 'woocommerce_single_product_summary', 'your_plugin_display_3d_model_button', 35 );

function your_plugin_display_3d_model_button() {
    // Include the frontend file to check for the model and display the button
    include( plugin_dir_path( __FILE__ ) . 'frontend.php' );
}
// Enqueue scripts and styles for the admin panel
function wc_3d_model_converter_admin_enqueue($hook) {
    global $post;
    if (($hook === 'post.php' || $hook === 'post-new.php') && $post->post_type === 'product') {
        wp_enqueue_script('jquery'); // Add this line 
        wp_enqueue_script('wc-3d-model-converter', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], '1.0', true);
        wp_enqueue_style('wc_3d_model_converter-style', plugin_dir_url(__FILE__) . 'admin.css', [], '1.0');
        // Enqueue Font Awesome (this line is correct)
        wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css' ); 

        wp_localize_script('wc-3d-model-converter', 'wc_3d_model_converter', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_3d_model_converter_nonce'),
        ]);
    }
    if ( is_product() ) { 
        wp_enqueue_script('wc-3d-model-converter-frontend', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], '1.0', true); 
        wp_localize_script('wc-3d-model-converter-frontend', 'wc_3d_model_converter', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_3d_model_converter_nonce'),
        ]);
    }
}
add_action('admin_enqueue_scripts', 'wc_3d_model_converter_admin_enqueue');

// Add metabox in the WooCommerce product edit screen
function wc_3d_model_converter_add_metabox() {
    add_meta_box(
        'wc_3d_model_converter',
        'Convert to 3D Model',
        'wc_3d_model_converter_metabox_content',
        'product',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'wc_3d_model_converter_add_metabox');

// Metabox content
function wc_3d_model_converter_metabox_content($post) {
    $product_id = $post->ID;
    error_log($product_id);
    $formats = ['glb', 'fbx', 'obj', 'usdz']; // Example formats; you can modify this list

    echo '<div id="wc-3d-model-converter">';

    // Button Section
    echo '<div class="button-section">';
    echo '<button id="convert-to-3d-model" class="button button-primary" data-product-id="' . esc_attr($product_id) . '">Convert to 3D Model</button>';
    echo '<button id="regenerate-model" class="button" style="display:none;"></button>';
    echo '<button id="view-3d-btn" class="button" style="display:none;"></button>';
    echo '<button id="save-model-btn" class="button" style="display:none;"></button>';
    
    echo '<div class="download-section">';
    echo '<button id="download-model-btn" class="button" style="display:none;" disabled></button>';
    echo '<div class="download-dropdown" id="download-dropdown">
            <select id="conversion-format" name="conversion-format">
            ' . implode('', array_map(fn($format) => "<option value='{$format}'>" . strtoupper($format) . "</option>", $formats)) . '
            </select>
          </div>';
    echo '</div>';
    echo '</div>'; // Close button-section div
    
    $file_url = plugin_dir_url(__FILE__) . 'models/product_' . $product_id . '.glb';
    $file_path = plugin_dir_path(__FILE__) . 'models/product_' . $product_id . '.glb';
    $exist = file_exists($file_path);

    echo '<div id="model-viewer-section">';

    echo '<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"></script>';

    echo '<model-viewer id="model-viewer" alt="3D Model" src="' . ($exist ? esc_url($file_url) : '') . '" ar auto-rotate camera-controls style="width: 100%; height: 400px; display: ' . ($exist ? 'block' : 'none') . '"></model-viewer>';

    echo '</div>';
    // Conversion Status Section
    echo '<div id="conversion-status" style="margin-top: 10px; display: none;">
            <div class="progress-bar-container">
                <div class="progress-bar"></div>
            </div>
            <span class="conversion-status-message"></span>
          </div>
          <br>';

    // Shortcode Section
    echo '<div id="shortcode-section">';
    echo '<button id="generate-shortcode-btn" class="button">Generate Shortcode</button>';
    echo '<div id="shortcode-output" style="margin-top: 10px; display: none;">
            <label for="generated-shortcode">Generated Shortcode:</label>
            <textarea id="generated-shortcode" rows="3" cols="50" readonly></textarea>
          </div>';
    echo '</div>'; // Close shortcode-section div

    // 3D Model Viewer Section
    // $model_url = get_post_meta($product_id, '_3d_model_url', true); // Check if the model exists
    /* if ($model_url) {
        echo '<div id="model-viewer-section">';
        echo '<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"></script>';
        echo '<model-viewer id="model-viewer" alt="3D Model" src="' . esc_url($model_url) . '" ar auto-rotate camera-controls style="width: 100%; height: 400px;"></model-viewer>';
        echo '</div>';
    } */


    


    echo '</div>'; // Close wc-3d-model-converter div
}



// AJAX handler for initiating 3D conversion
function wc_3d_model_converter_ajax() {
    check_ajax_referer('wc_3d_model_converter_nonce', 'security');

    $product_id = intval($_POST['product_id']);

    if (!$product_id || get_post_status($product_id) !== 'publish') {
        wp_send_json_error('Invalid or unpublished product.');
    }

    $thumbnail_id = get_post_thumbnail_id($product_id);
    if (!$thumbnail_id) {
        wp_send_json_error('Product image not found.');
    }

    $image_path = get_attached_file($thumbnail_id);
    $imgbb_url = wc_upload_to_imgbb($image_path);
    if (!$imgbb_url) {
        wp_send_json_error('Failed to upload image to ImgBB.');
    }

    $conversion_response = wc_3d_converter_api_call($imgbb_url); // Pass the format to the API call
    error_log('Conversion response: ' . json_encode($conversion_response));
    if (is_array($conversion_response) && isset($conversion_response['result'])) {
        $task_id = $conversion_response['result'];
        update_post_meta($product_id, '_3d_conversion_status', 'in_progress');
        update_post_meta($product_id, '_3d_conversion_task_id', $task_id);

        wp_send_json_success(['message' => 'Conversion started. Please wait...', 'status' => 'in_progress']);
    } else {
        wp_send_json_error('Error initiating conversion.');
    }
}

add_action('wp_ajax_wc_3d_model_converter', 'wc_3d_model_converter_ajax');

// Check conversion status via AJAX
function wc_check_conversion_status_ajax() {
    check_ajax_referer('wc_3d_model_converter_nonce', 'security');
    $plugin_dir = plugin_dir_path(__FILE__);

    $product_id = intval($_POST['product_id']);
    $task_id = get_post_meta($product_id, '_3d_conversion_task_id', true);
    if (!$task_id) {
        error_log("Task ID not found for product ID: $product_id.");
        wp_send_json_error('Task ID not found.');
    }

    $status_response = wc_check_conversion_status($task_id);

    if (!$status_response) {
        error_log("Error occurred while checking conversion status for Task ID: $task_id.");
        wp_send_json_error('An error occurred while checking conversion status.');
    }

    if ($status_response['status'] === 'SUCCEEDED') {
        $model_url = $status_response['model_url'] ?? '';
        $temp_path = $plugin_dir . 'temp/model.glb'; // Define the temp path
        $temp_url = plugin_dir_url(__FILE__) . 'temp/model.glb'; // Public URL
        
        // Save file temporarily
        $response_body = wp_remote_get($model_url);
        if (!is_wp_error($response_body)) {
            file_put_contents($temp_path, wp_remote_retrieve_body($response_body));
        }
    
        update_post_meta($product_id, '_3d_model_url', $model_url);
        update_post_meta($product_id, '_3d_conversion_status', 'SUCCEEDED');
        update_post_meta($product_id, '_3d_model_temp_url', $temp_url);  // Add the temp URL for access
    
        wp_send_json_success([
            'status' => 'SUCCEEDED',
            'model_url' => $model_url,
            'model_urls' => $status_response['model_urls'], 
            'model_path' => $temp_url,  // Include the public temp file path
            'progress' => 100
        ]);
    }
     elseif ($status_response['status'] === 'IN_PROGRESS' || $status_response['status'] === 'PENDING') {
        wp_send_json_success([
            'status' => 'IN_PROGRESS',
            'progress' => $status_response['progress'] ?? 0
        ]);
    } else {
        update_post_meta($product_id, '_3d_conversion_status', 'FAILED');
        error_log("Conversion failed for Task ID: $task_id. Status: " . $status_response['status']);
        wp_send_json_error('Conversion failed or unknown status.');
    }
}
add_action('wp_ajax_wc_check_conversion_status', 'wc_check_conversion_status_ajax');

// Upload image to ImgBB
function wc_upload_to_imgbb($image_path) {
    $api_key = '67098e2dbdf822fb2f5bcb43666c880b';
    $api_url = 'https://api.imgbb.com/1/upload?key=' . $api_key;
    $image_data = file_get_contents($image_path);
    $base64_image = base64_encode($image_data);

    $response = wp_remote_post($api_url, [
        'body' => ['image' => $base64_image],
    ]);

    if (is_wp_error($response)) {
        error_log("ImgBB API error: " . $response->get_error_message());
        return false;
    }
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    return $response_body['data']['url'] ?? false;
}

// 3D Conversion API call
function wc_3d_converter_api_call($image_url) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d';
    $api_key = 'msy_k20kiYfFssOk5gbLCU48PMAB4Rhfo8HkvvZG'; // Replace with your actual API key

    // Modify the API call to include the format
    $response = wp_remote_post($api_url, [
        'body' => json_encode([
            'image_url' => $image_url,
            
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        error_log("Error in 3D conversion API request: " . $response->get_error_message());
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}
/* function wc_check_model_exists_ajax() {
    check_ajax_referer('wc_3d_model_converter_nonce', 'security');
    $product_id = intval($_POST['product_id']);
    $model_url = get_post_meta($product_id, '_3d_model_url', true);
    wp_send_json_success(['exists' => !empty($model_url)]);
}
add_action('wp_ajax_wc_check_model_exists', 'wc_check_model_exists_ajax'); */

// Check 3D Conversion status
// Declare the function first
function wc_check_conversion_status($task_id) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d/' . $task_id;
    $api_key = 'msy_k20kiYfFssOk5gbLCU48PMAB4Rhfo8HkvvZG';

    $response = wp_remote_get($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log("Error checking conversion status: " . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    error_log("Status check response body: " . $body);

    return json_decode($body, true);
}
// Create temp directory and ensure it's writable
function wc_3d_create_temp_dir() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $temp_dir = $plugin_dir . 'temp'; // Folder for temporary files
    
    // Create temp directory if it doesn't exist
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }

    // Ensure temp directory is writable
    if (!is_writable($temp_dir)) {
        chmod($temp_dir, 0755); // Make it writable
    }
}
register_activation_hook(__FILE__, 'wc_3d_create_temp_dir');

// Scheduled cleanup of temp files
function wc_3d_cleanup_temp_files() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $temp_dir = $plugin_dir . 'temp';
    if (is_dir($temp_dir)) {
        // Iterate through all GLB files in the 'temp' directory
        foreach (glob("$temp_dir/*.glb") as $file) {
            // Check if the file is older than 1 hour
            if (filemtime($file) < time() - 3600) {
                // Delete the file if it's older than 1 hour
                unlink($file);
            }
        }
    }
}
add_action('wc_3d_cleanup_event', 'wc_3d_cleanup_temp_files');

// Schedule cleanup event
if (!wp_next_scheduled('wc_3d_cleanup_event')) {
    wp_schedule_event(time(), 'hourly', 'wc_3d_cleanup_event');
}

function wc_ensure_models_dir() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $models_dir = $plugin_dir . 'models/'; // Folder where the models are stored
    
    // Create the folder if it doesn't exist
    if (!file_exists($models_dir)) {
        mkdir($models_dir, 0755, true); // Create the directory with correct permissions
    }

    // Ensure the directory is writable
    if (!is_writable($models_dir)) {
        chmod($models_dir, 0755);  // Set the folder to be writable
    }

    return $models_dir; // Return the directory path
}

// Remove duplicate action registrations
// add_action('wp_ajax_wc_save_3d_model', 'wc_save_3d_model');
add_action('wp_ajax_wc_check_model_exists', 'wc_check_model_exists');
function wc_check_model_exists() {
    $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
    
    // Ensure the models directory exists
    $models_dir = wc_ensure_models_dir();

    error_log("Models directory: " . $models_dir);
    error_log("Product ID: " . $product_id);
    
    // Set the correct model file path
    $model_file_path = $models_dir . 'product_' . $product_id . '.glb';
    error_log("Model file path: " . $model_file_path);

    // Check if the model file exists
    if (file_exists($model_file_path)) {
        wp_send_json_success(['exists' => true]);
    } else {
        wp_send_json_success(['exists' => false]);
    }

    wp_die();
}
function product_3d_model_shortcode( $atts ) {

    // Get the ID of the current product page  
    $product_id = get_the_ID();  

    // Construct the expected model file path  
    $upload_dir = plugin_dir_path(__FILE__);  
    $model_path = $upload_dir . 'models/product_' . $product_id . '.glb';  

    if ( file_exists( $model_path ) ) { // Check if the model file exists  
        // Get the URL of the model file  
        $model_url = plugin_dir_url(__FILE__) . 'models/product_' . $product_id . '.glb';  

        // Return the model viewer HTML with the model URL  
        return '<div id="model-viewer-section">
        <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"></script>  
        <model-viewer alt="3D Model" src="' . esc_url( $model_url ) . '" ar auto-rotate camera-controls style="width: 100%; height: 400px;"></model-viewer>  
        </div>';  
    } else {  
        return ''; // Return an empty string if no model is found  
    }  
}
// Register the shortcode
add_shortcode('product_3d_model', 'product_3d_model_shortcode');


// Handle saving the model via AJAX
// add_action('wp_ajax_wc_save_3d_model', 'wc_save_3d_model');
function wc_save_3d_model() {
    check_ajax_referer('wc_3d_model_converter_nonce', 'security');
    
    // Validate inputs
    if (!isset($_POST['product_id']) || !isset($_POST['model_path'])) {
        wp_send_json_error('Missing parameters');
    }

    $product_id = intval($_POST['product_id']);
    $model_url = sanitize_text_field($_POST['model_path']);
    
    // Ensure models directory exists and is writable
    $models_dir = wc_ensure_models_dir();

    $file_name = 'product_' . $product_id . '.glb'; // Standardize the file naming
    $destination_path = $models_dir . $file_name;

    // Download the file from URL
    $response = wp_remote_get($model_url);
    if (is_wp_error($response)) {
        wp_send_json_error('Failed to download model: ' . $response->get_error_message());
        return;
    }

    $file_contents = wp_remote_retrieve_body($response);

    // Save the file
    if (file_put_contents($destination_path, $file_contents) !== false) {
        $destination_url = plugin_dir_url(__FILE__) . 'models/' . $file_name;
        update_post_meta($product_id, '_3d_model_url', $destination_url); // Save model URL to product meta

        wp_send_json_success([
            'message' => 'Model saved successfully',
            'model_url' => $destination_url
        ]);
    } else {
        wp_send_json_error('Failed to save model');
    }

    wp_die();
}
add_action('wp_ajax_wc_save_3d_model', 'wc_save_3d_model');

// Ensure models folder exists and is writable
// Ensure the models folder exists and is writable
function wc_3d_create_models_dir() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $models_folder = $plugin_dir . 'models'; // Folder where the models will be stored
    
    // Check if the folder exists, if not, create it
    // Create the folder if it doesn't exist
    if (!file_exists($models_folder)) {
        mkdir($models_folder, 0755, true); // Create the directory with correct permissions
        mkdir($models_folder, 0755, true); // Create with correct permissions
    }

    // Verify and set the directory to be writable
    // Ensure the directory is writable
    if (!is_writable($models_folder)) {
        chmod($models_folder, 0755);  // Set the folder to be writable
    }
}
register_activation_hook(__FILE__, 'wc_3d_create_models_dir');

/* function wc_save_3d_model_handler() {
    error_log('Save Model Ajax Called');
    
    // Verify nonce
    if (!check_ajax_referer('wc_3d_model_converter_nonce', 'security', false)) {
        error_log('Nonce verification failed');
        wp_send_json_error('Security check failed');
    }

    // Validate inputs
    if (!isset($_POST['product_id']) || !isset($_POST['model_path'])) {
        error_log('Missing product_id or model_path');
        wp_send_json_error('Missing parameters');
    }

    $product_id = intval($_POST['product_id']);
    $model_path = sanitize_text_field($_POST['model_path']);

    error_log('Save Model - Product ID: ' . $product_id);
    error_log('Save Model - Model Path: ' . $model_path);

    // Rest of your save logic
} */
/* function wc_check_upload_directory_writability() {
    $upload_dir = wp_upload_dir();
    $models_dir = $upload_dir['basedir'] . '/3d-models/';

    // Create directory if not exists
    if (!file_exists($models_dir)) {
        wp_mkdir_p($models_dir);
    }

    // Check writability
    if (!is_writable($models_dir)) {
        // Try to change permissions
        chmod($models_dir, 0755);
    }

    // Verify writability
    if (!is_writable($models_dir)) {
        error_log('3D Models directory is not writable: ' . $models_dir);
        return false;
    }

    return true;
} */
/* function wc_get_model_url($filename) {
    $upload_dir = wp_upload_dir();
    
    // Check if we're on localhost
    $is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);

    if ($is_localhost) {
        // For local development, use absolute path
        return site_url('/wp-content/uploads/3d-models/' . $filename);
    } else {
        // For live site, use full URL from upload directory
        return $upload_dir['baseurl'] . '/3d-models/' . $filename;
    }
} */
?>