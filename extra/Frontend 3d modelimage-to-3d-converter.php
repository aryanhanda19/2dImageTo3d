<?php
/**
 * Plugin Name: WooCommerce 3D Model Converter
 * Description: Adds a "Convert to 3D Model" feature in the WooCommerce product edit screen.
 * Version: 1.0.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Enqueue scripts and styles for the admin panel
function wc_3d_model_converter_admin_enqueue($hook) {
    global $post;
    if (($hook === 'post.php' || $hook === 'post-new.php') && $post->post_type === 'product') {
        wp_enqueue_script('wc-3d-model-converter', plugin_dir_url(__FILE__) . 'admin.js', ['jquery'], '1.0', true);
        wp_enqueue_style('wc-3d-model-converter-style', plugin_dir_url(__FILE__) . 'admin.css', [], '1.0');

        wp_localize_script('wc-3d-model-converter', 'wc_3d_model_converter', [
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
    $conversion_status = get_post_meta($product_id, '_3d_conversion_status', true);
    $model_url = get_post_meta($product_id, '_3d_model_url', true);

    echo '<div id="wc-3d-model-converter">';
    echo '<button id="convert-to-3d-model" class="button button-primary" data-product-id="' . esc_attr($product_id) . '">Convert to 3D Model</button>';
    echo '<div id="conversion-status" style="margin-top: 10px; display:none;">
            <div class="progress-bar-container">
              <div class="progress-bar"></div>
            </div>
            <span class="conversion-status-message"></span>
          </div>';
    
    if ($model_url) {
        echo '<div id="model-viewer-section" style="margin-top: 20px;">
                <model-viewer src="' . esc_url($model_url) . '" alt="3D Model" ar auto-rotate camera-controls></model-viewer>
                <a href="' . esc_url($model_url) . '" target="_blank" class="button button-secondary" style="margin-top: 10px;">Download 3D Model</a>
              </div>';
    }
    echo '</div>';
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

    $conversion_response = wc_3d_converter_api_call($imgbb_url);
    if ($conversion_response) {
        update_post_meta($product_id, '_3d_conversion_status', 'in_progress');
        update_post_meta($product_id, '_3d_conversion_job_id', $conversion_response);
        wp_send_json_success(['message' => 'Conversion started. Please wait...', 'status' => 'in_progress']);
    } else {
        wp_send_json_error('Error initiating conversion. Check debug logs for details.');
    }
}

// Check conversion status via AJAX
function wc_check_conversion_status_ajax() {
    check_ajax_referer('wc_3d_model_converter_nonce', 'security');

    $product_id = intval($_POST['product_id']);
    $job_id = get_post_meta($product_id, '_3d_conversion_job_id', true);
    if (!$job_id) {
        wp_send_json_error('Job ID not found.');
    }

    $status_response = wc_check_conversion_status($job_id);

    if (!$status_response) {
        wp_send_json_error('An error occurred while checking conversion status.');
    }

    if (isset($status_response->status) && $status_response->status === 'completed') {
        $model_url = $status_response->model_url ?? '';
        update_post_meta($product_id, '_3d_model_url', $model_url);
        update_post_meta($product_id, '_3d_conversion_status', 'completed');
        wp_send_json_success(['status' => 'completed', 'model_url' => $model_url]);
    } elseif (isset($status_response->status) && $status_response->status === 'in_progress') {
        wp_send_json_success(['status' => 'in_progress', 'progress' => $status_response->progress ?? 0]);
    } else {
        update_post_meta($product_id, '_3d_conversion_status', 'failed');
        wp_send_json_error('Conversion failed or unknown status.');
    }
}

// Upload image to ImgBB
function wc_upload_to_imgbb($image_path) {
    $api_key = '67098e2dbdf822fb2f5bcb43666c880b';
    $api_url = 'https://api.imgbb.com/1/upload?key=' . $api_key;
    $image_data = file_get_contents($image_path);
    $base64_image = base64_encode($image_data);

    $response = wp_remote_post($api_url, [
        'body' => ['image' => $base64_image],
    ]);

    if (is_wp_error($response)) return false;
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    return $response_body['data']['url'] ?? false;
}

// Call 3D model conversion API
function wc_3d_converter_api_call($image_url) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d'; // Replace with your 3D model conversion API URL
    $api_key = 'msy_SsYD2ko6J0yCQmz5dhVrdo7pil2oHhcr7DfL'; // Replace with your actual API key

    // Prepare the body and headers for the request
    $body = json_encode([
        'image_url' => $image_url,
        'enable_pbr' => true,  // Optional: Enable PBR maps (metallic, roughness, normal)
        'ai_model' => 'meshy-4', // Optional: Hard surface model for best results with product images
        'topology' => 'triangle', // Optional: Use triangle topology for products (instead of quads)
        'target_polycount' => 30000, // Optional: Target number of polygons (you can adjust based on your needs)
    ]);

    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    ];

    // Perform the request
    $response = wp_remote_post($api_url, [
        'body'    => $body,
        'headers' => $headers,
    ]);

    // Check for any errors with the request
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("3D Model Conversion API Error: $error_message"); // Log the error message
        return false;
    }

    // Retrieve and log the response body
    $body = wp_remote_retrieve_body($response);
    error_log("3D Model Conversion API Response: $body"); // Log the response body

    // Decode the JSON response
    $data = json_decode($body);

    // Check if a job ID is returned
    if (isset($data->id)) {
        return $data->id; // Return job_id for tracking conversion status
    }

    error_log("API did not return a valid job_id. Response: $body"); // Log if job_id is missing
    return false;
}

// Check conversion status
function wc_check_conversion_status($job_id) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d/' . $job_id; // Use correct URL for status check
    $api_key = 'msy_SsYD2ko6J0yCQmz5dhVrdo7pil2oHhcr7DfL'; // Your actual API key

    $response = wp_remote_get($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Error checking 3D conversion status: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (isset($data->status)) {
        return $data;
    }

    error_log('API response did not include status information: ' . $body);
    return false;
}

// Hooking AJAX actions
add_action('wp_ajax_wc_3d_model_converter', 'wc_3d_model_converter_ajax');
add_action('wp_ajax_wc_check_conversion_status', 'wc_check_conversion_status_ajax');

