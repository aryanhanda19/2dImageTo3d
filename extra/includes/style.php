<?php
function image_to_3d_converter_custom_styles() {
    ?>
    <style>
        #convert-to-3d-model {
            background-color: #007cba;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        #convert-to-3d-model:hover {
            background-color: #005fa3;
        }

        #conversion-status {
            margin-top: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f5f5f5;
            display: none;
        }

        .progress-bar-container {
            width: 100%;
            height: 15px;
            background-color: #ddd;
            border-radius: 5px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-bar {
            width: 0%;
            height: 100%;
            background-color: #4caf50;
            transition: width 0.3s ease;
        }

        .conversion-status-message {
            margin-top: 10px;
            font-weight: bold;
        }

        #model-viewer {
            margin-top: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            display: none;
        }

        #download-3d-model {
            margin-top: 10px;
            background-color: #4caf50;
            color: white;
            padding: 10px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        #download-3d-model:hover {
            background-color: #45a049;
        }
    </style>
    <?php
}
add_action('admin_head', 'image_to_3d_converter_custom_styles');
