<?php
/**
 * Plugin Name: Backend 3D Model Converter
 * Description: WooCommerce backend plugin for 3D model conversion using the Meshy API.
 * Version: 1.0
 * Author: Aryan Handa
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'config.php'; // Include your API keys config file
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Style.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'backend-3d-converter.php';

// Enqueue scripts
// Enqueue scripts
function backend_3d_model_converter_enqueue($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // Enqueue model-viewer script with compatibility handling
        wp_enqueue_script('model-viewer', 'https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js', [], null, false);
        
        // Inline JavaScript for AJAX functionality
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                $('#convert-to-3d-model').on('click', function() {
                    var productId = $('#post_ID').val();
                    var statusContainer = $('#conversion-status');
                    var progressBar = statusContainer.find('.progress-bar');
                    
                    // Start conversion
                    $.post(backend3DConverter.ajax_url, {
                        action: 'backend_convert_image_to_3d_model',
                        security: backend3DConverter.nonce,
                        product_id: productId
                    }, function(response) {
                        if (response.success) {
                            statusContainer.show();
                            checkConversionStatus(productId);
                        } else {
                            alert(response.data.message);
                        }
                    });
                });

                function checkConversionStatus(productId) {
                    $.post(backend3DConverter.ajax_url, {
                        action: 'backend_check_conversion_status',
                        security: backend3DConverter.nonce,
                        product_id: productId
                    }, function(response) {
                        if (response.success && response.data.status === 'completed') {
                            $('#model-viewer model-viewer').attr('src', response.data.model_url);
                            $('#model-viewer').show();
                            $('#download-3d-model').attr('href', response.data.model_url).show();
                        } else if (response.success && response.data.status === 'in_progress') {
                            progressBar.css('width', response.data.progress + '%');
                            setTimeout(function() { checkConversionStatus(productId); }, 3000);
                        } else {
                            alert(response.data.message);
                        }
                    });
                }
            });
        ");
        wp_localize_script('jquery', 'backend3DConverter', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('backend_3d_converter_nonce')
        ]);
    }
}
add_action('admin_enqueue_scripts', 'backend_3d_model_converter_enqueue');

// Register AJAX actions
add_action('wp_ajax_backend_convert_image_to_3d_model', 'backend_convert_image_to_3d_model');
add_action('wp_ajax_backend_check_conversion_status', 'backend_check_conversion_status');

// Add metabox for 3D model conversion
function add_3d_converter_metabox() {
    add_meta_box(
        '3d_converter_box',
        __('Convert Product Image to 3D Model', 'textdomain'),
        'render_3d_converter_metabox',
        'product',
        'side'
    );
}
add_action('add_meta_boxes', 'add_3d_converter_metabox');

// Render metabox
function render_3d_converter_metabox($post) {
    echo '<button id="convert-to-3d-model" class="button" disabled>Convert to 3D Model</button>';
    echo '<div id="conversion-status" style="display:none;">
            <div class="progress-bar-container" style="width: 100%; background-color: #ddd;">
                <div class="progress-bar" style="width:0; height: 15px; background-color: #4CAF50;"></div>
            </div>
            <span class="conversion-status-message"></span>
          </div>';
    echo '<div id="model-viewer" style="display:none; margin-top: 15px;">
            <model-viewer src="" ar auto-rotate camera-controls></model-viewer>
            <button id="download-3d-model" class="button" style="display:none; margin-top: 10px;">Download 3D Model</button>
          </div>';
}

// Conversion process
function backend_convert_image_to_3d_model() {
    check_ajax_referer('backend_3d_converter_nonce', 'security');

    $product_id = intval($_POST['product_id']);
    $thumbnail_id = get_post_thumbnail_id($product_id);

    if (!$thumbnail_id) {
        wp_send_json_error(['message' => 'No product image found.']);
    }

    $image_path = get_attached_file($thumbnail_id);
    $imgbb_url = upload_image_to_imgbb($image_path);

    if (!$imgbb_url) {
        wp_send_json_error(['message' => 'Failed to upload image.']);
    }

    $conversion_response = mesh_3d_api_call($imgbb_url);

    if ($conversion_response && isset($conversion_response->result)) {
        update_post_meta($product_id, 'conversion_job_id', $conversion_response->result);
        wp_send_json_success(['message' => 'Conversion started...']);
    } else {
        wp_send_json_error(['message' => 'Meshy API error.']);
    }
}

// Check conversion status
function backend_check_conversion_status() {
    check_ajax_referer('backend_3d_converter_nonce', 'security');

    $product_id = intval($_POST['product_id']);
    $job_id = get_post_meta($product_id, 'conversion_job_id', true);

    if (!$job_id) {
        wp_send_json_error(['message' => 'No job ID found.']);
    }

    $api_url = 'https://api.meshy.ai/v1/image-to-3d/' . $job_id;
    $response = wp_remote_get($api_url, [
        'headers' => ['Authorization' => 'Bearer ' . MESHY_API_KEY],
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'Error checking status.']);
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    if (isset($data->status) && $data->status === 'SUCCEEDED') {
        update_post_meta($product_id, 'model_url', $data->model_url);
        wp_send_json_success(['status' => 'completed', 'model_url' => $data->model_url]);
    } elseif (isset($data->progress)) {
        wp_send_json_success(['status' => 'in_progress', 'progress' => $data->progress]);
    } else {
        wp_send_json_error(['message' => 'Unexpected response.']);
    }
}

// Upload image to ImgBB
function upload_image_to_imgbb($image_path) {
    $api_url = 'https://api.imgbb.com/1/upload?key=' . IMGBB_API_KEY;
    $image_data = base64_encode(file_get_contents($image_path));

    $response = wp_remote_post($api_url, [
        'body' => ['image' => $image_data],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    return $response_body['data']['url'] ?? false;
}

// Meshy 3D API call
function mesh_3d_api_call($imgbb_url) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d';
    $response = wp_remote_post($api_url, [
        'body' => json_encode(['image_url' => $imgbb_url]),
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . MESHY_API_KEY,
        ],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response));
}
?>
