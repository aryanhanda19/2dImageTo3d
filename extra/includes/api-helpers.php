<?php
// includes/api-helpers.php

// Function to upload an image to ImgBB
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

// Function to initiate a 3D model conversion with Meshy API
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

// Function to check the conversion status from Meshy API
function check_meshy_conversion_status($job_id) {
    $api_url = 'https://api.meshy.ai/v1/image-to-3d/' . $job_id;
    $response = wp_remote_get($api_url, [
        'headers' => ['Authorization' => 'Bearer ' . MESHY_API_KEY],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response));
}
?>
