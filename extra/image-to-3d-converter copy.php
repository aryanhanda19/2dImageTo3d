<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Enqueue jQuery script
function image_to_3d_converter_enqueue() {
    wp_enqueue_script('jquery');
}
add_action('wp_enqueue_scripts', 'image_to_3d_converter_enqueue');

// Add the "Convert to 3D Model" button on the product page
function image_to_3d_converter_button() {
    global $product;
    if (is_product()) {
        echo '<div class="image-to-3d-converter">';
        echo '<button id="convert-to-3d-model" class="button alt convert-3d-btn" data-product-id="' . esc_attr($product->get_id()) . '">Convert to 3D Model</button>';
        echo '<div id="conversion-status" class="conversion-status" style="display:none;">
                <div class="progress-bar-container">
                    <div class="progress-bar"></div>
                </div>
                <span class="conversion-status-message"></span>
                <button id="view-3d-model" class="button alt" style="display:none;">View 3D Model</button>
                <button id="download-3d-model" class="button alt" style="display:none;">Download 3D Model</button>
              </div>';
        echo '<div id="model-viewer" class="model-viewer" style="display:none;"></div>'; // Container for the model viewer
        echo '</div>';
    }
}
add_action('woocommerce_single_product_summary', 'image_to_3d_converter_button', 35);

// Enqueue custom styles
function image_to_3d_converter_custom_styles() {
    ?>
    <style>
        /* Scoped styles for the plugin */
        .image-to-3d-converter .convert-3d-btn {
            background-color: #3498db;
            color: #fff;
            padding: 12px 24px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .image-to-3d-converter .convert-3d-btn:hover {
            background-color: #2980b9;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        .image-to-3d-converter .conversion-status {
            margin-top: 15px;
            width: 100%;
            max-width: 400px;
            padding: 8px;
            text-align: center;
            font-weight: bold;
            color: #333;
        }

        .image-to-3d-converter .progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            height: 20px;
            margin-bottom: 8px;
        }

        .image-to-3d-converter .progress-bar {
            height: 100%;
            background-color: #4caf50;
            width: 0%;
            transition: width 0.4s ease;
        }

        .image-to-3d-converter .conversion-status-message {
            display: block;
            color: #555;
        }

        .image-to-3d-converter .model-viewer {
            width: 300px; /* Set a fixed width for the viewer */
            height: 300px; /* Set a fixed height for the viewer */
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 15px;
            position: relative;
            background-color: #f9f9f9;
        }

        /* Additional styles for the buttons */
        .image-to-3d-converter .button.alt {
            background-color: #e67e22;
            color: #fff;
            padding: 10px 20px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .image-to-3d-converter .button.alt:hover {
            background-color: #d35400;
        }
    </style>
    <?php
}
add_action('wp_head', 'image_to_3d_converter_custom_styles');

// AJAX handler to start the 3D conversion process
function convert_image_to_3d_model() {
    check_ajax_referer('image_to_3d_nonce', 'security');

    $product_id = intval($_POST['product_id']);
    $thumbnail_id = get_post_thumbnail_id($product_id);

    if (!$thumbnail_id) {
        wp_send_json_error('Product image not found.');
        wp_die();
    }

    $image_path = get_attached_file($thumbnail_id);
    $imgbb_url = upload_image_to_imgbb($image_path);

    if (!$imgbb_url) {
        wp_send_json_error('Failed to upload image to ImgBB.');
        wp_die();
    }

    enqueue_conversion_job($imgbb_url);
    wp_send_json_success('Image uploaded. Conversion in progress...');
    wp_die();
}
add_action('wp_ajax_convert_image_to_3d_model', 'convert_image_to_3d_model');
add_action('wp_ajax_nopriv_convert_image_to_3d_model', 'convert_image_to_3d_model');

// Enqueue a conversion job
function enqueue_conversion_job($imgbb_url) {
    $conversion_response = image_to_3d_converter_api_call($imgbb_url);

    if ($conversion_response && isset($conversion_response->result)) {
        update_option('current_conversion_job_id', $conversion_response->result);
        error_log('Conversion job enqueued: ' . $conversion_response->result);
    } else {
        error_log('Meshy API error: ' . print_r($conversion_response, true));
    }
}

// Upload image to ImgBB and return URL
function upload_image_to_imgbb($image_path) {
    $api_key = '67098e2dbdf822fb2f5bcb43666c880b';
    $api_url = 'https://api.imgbb.com/1/upload?key=' . $api_key;
    $image_data = file_get_contents($image_path);
    $base64_image = base64_encode($image_data);

    $response = wp_remote_post($api_url, [
        'body' => ['image' => $base64_image],
    ]);

    if (is_wp_error($response)) {
        error_log('ImgBB API error: ' . $response->get_error_message());
        return false;
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    return $response_body['data']['url'] ?? false;
}

// Call Meshy API for 3D conversion
function image_to_3d_converter_api_call($imgbb_url) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d';
    $api_key = 'msy_8X7Q77GZUOmDwQKHj27G9Lg1FsEN4mysUZWn'; // Updated API key

    $response = wp_remote_post($api_url, [
        'body' => json_encode(['image_url' => $imgbb_url]),
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Meshy API error: ' . $response->get_error_message());
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response));
}

// Check the status of the conversion
function check_conversion_status() {
    $job_id = get_option('current_conversion_job_id');

    if (!$job_id) {
        wp_send_json_error('Job ID not provided.');
        return;
    }

    $api_url = 'https://api.meshy.ai/v1/image-to-3d/' . $job_id;
    $api_key = 'msy_8X7Q77GZUOmDwQKHj27G9Lg1FsEN4mysUZWn'; // Updated API key

    $response = wp_remote_get($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Meshy API status error: ' . $response->get_error_message());
        wp_send_json_error('Meshy API status error.');
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response));

    if (isset($data->progress)) {
        if ($data->status === 'SUCCEEDED') {
            update_option('conversion_download_links', $data->model_url);
            wp_send_json_success(['status' => 'completed', 'model_url' => $data->model_url]);
        } else {
            wp_send_json_success(['status' => 'in_progress', 'progress' => $data->progress]);
        }
    } else {
        wp_send_json_error('Invalid response from Meshy API.');
    }
}
add_action('wp_ajax_check_conversion_status', 'check_conversion_status');
add_action('wp_ajax_nopriv_check_conversion_status', 'check_conversion_status');

// AJAX handler to view and download the 3D model
function view_and_download_model() {
    check_ajax_referer('image_to_3d_nonce', 'security');

    $model_url = get_option('conversion_download_links');
    if (!$model_url) {
        wp_send_json_error('No model URL found.');
        return;
    }

    wp_send_json_success(['model_url' => $model_url]);
}
add_action('wp_ajax_view_and_download_model', 'view_and_download_model');
add_action('wp_ajax_nopriv_view_and_download_model', 'view_and_download_model');

// Enqueue custom script to handle the button actions
function image_to_3d_converter_script() {
    ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"></script>
    <script>
        jQuery(document).ready(function($) {
            $('#convert-to-3d-model').click(function(e) {
                e.preventDefault();
                var productId = $(this).data('product-id');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'convert_image_to_3d_model',
                        security: '<?php echo wp_create_nonce('image_to_3d_nonce'); ?>',
                        product_id: productId
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#conversion-status').show();
                            $('.conversion-status-message').text(response.data);
                            checkConversionStatus();
                        } else {
                            alert(response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            });


            function checkConversionStatus() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'check_conversion_status',
                        security: '<?php echo wp_create_nonce('image_to_3d_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data.status === 'completed') {

                                console.log("response.data.model_url = ", response.data.model_url);

                                // Show the 3D model in the viewer
                                display3DModel(response.data.model_url);

                                // Show the "View" and "Download" buttons
                                $('#view-3d-model').show();
                                $('#view-3d-model').click(function() {
                                    $('#model-viewer').toggle(); // Toggle the model viewer visibility
                                });
                                $('#download-3d-model')
                                    .show()
                                    .attr('onclick', 'window.open("' + response.data.model_url + '", "_blank")');
                            } else {
                                $('.progress-bar').css('width', response.data.progress + '%');
                                $('.conversion-status-message').text('Conversion progress: ' + response.data.progress + '%');
                                setTimeout(checkConversionStatus, 5000);
                            }
                        } else {
                            alert(response.data);
                        }
                    },
                    error: function() {
                        alert('An error occurred while checking conversion status.');
                    }
                });
            }

            function display3DModel(modelUrl) {

                console.log("modelUrl = ", modelUrl);

                $('#model-viewer').html(`
                    <model-viewer src="${modelUrl}"
                        alt="3D Model"
                        ar
                        auto-rotate
                        camera-controls
                        style="width: 100%; height: 100%;">
                    </model-viewer>
                `);
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'image_to_3d_converter_script');