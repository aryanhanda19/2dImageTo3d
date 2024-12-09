jQuery(document).ready(function($) {
    var productId = $('#convert-to-3d-model').data('product-id');
    var modelUrl = $('#model-viewer').attr('src');  // Initial check if model exists
    var nonce = wc_3d_model_converter.nonce;

    // Disable the Generate Shortcode button by default
    $('#generate-shortcode-btn').prop('disabled', true);

    // If a model URL exists, enable the Generate Shortcode button
    if (modelUrl) {
        $('#generate-shortcode-btn').prop('disabled', false);
    }

    // Convert to 3D Model button click event
    // Convert to 3D Model button click event
    $('#convert-to-3d-model').on('click', function(e) {
        e.preventDefault();

        // Show the conversion progress bar and message
        $('#conversion-status').show();
        $('.progress-bar').css('width', '0%');
        $('.conversion-status-message').text('Starting conversion...');

        // AJAX request to start conversion
        $.ajax({
            url: wc_3d_model_converter.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_3d_model_converter',
                security: wc_3d_model_converter.nonce,
                product_id: productId,
                overwrite: true // Add this line to enable overwriting
            },
            success: function(response) {
                if (response.success) {
                    // Update the conversion status and start checking for completion
                    var taskId = response.data.task_id;
                    checkConversionStatus(productId, taskId);
                } else {
                    alert(response.data);
                    $('#conversion-status').hide();
                }
            },
            error: function() {
                alert('An error occurred while initiating the conversion.');
                $('#conversion-status').hide();
            }
        });
    });

    // Function to check conversion status
    function checkConversionStatus(productId, taskId) {
        $.ajax({
            url: wc_3d_model_converter.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_check_conversion_status',
                security: nonce,
                product_id: productId,
                task_id: taskId
            },
            success: function(response) {
                if (response.success) {
                    var status = response.data.status;
                    var progress = response.data.progress;
                    var modelPath = response.data.model_path;
                    var modelUrl = response.data.model_url;

                    // Update the progress bar
                    $('.progress-bar').css('width', progress + '%');
                    $('.conversion-status-message').text('Conversion in progress: ' + progress + '%');
                    $('#model-viewer').hide();
                    $('#model-viewer').attr('src', '');

                    if (status === 'SUCCEEDED') {

                        $("#download-dropdown").show();

                        // Show the "View 3D Model" button and enable download
                        $('#view-3d-btn').show().on('click', function(e) {
                            e.preventDefault();
                            document.getElementById("model-viewer").setAttribute("src", modelPath);
                            $('#model-viewer').show();
                            $('html, body').animate({
                                scrollTop: $('#model-viewer').offset().top
                            }, 500);
                        });
                        // Show the Regenerate button after successful conversion
                        $('#regenerate-model').show(); 
                        // Show the Save Model button
                        $('#save-model-btn').show();

                        // Enable the Generate Shortcode button after saving the model
                        $('#generate-shortcode-btn').prop('disabled', false);

                        // Populate the format selection dropdown with available model formats
                        var formatOptions = '';
                        $.each(response.data.model_urls, function(key, url) {
                            formatOptions += '<option value="' + key + '">' + key.toUpperCase() + '</option>';
                        });
                        $('#conversion-format').html(formatOptions); // Populate dropdown

                        // Enable the dropdown and download button
                        $('#conversion-format').prop('disabled', false);
                        $('#download-model-btn').prop('disabled', false).show().on('click', function(e) {
                            e.preventDefault();
                            var selectedFormat = $('#conversion-format').val(); // Get selected format

                            if (response.data.model_urls[selectedFormat]) {
                                window.location.href = response.data.model_urls[selectedFormat]; // Initiate download for selected format
                            } else {
                                alert("Selected format not available.");
                            }
                        });

                        // Update conversion status message
                        $('.conversion-status-message').text('Conversion complete!');
                    } else {
                        // Continue checking for completion
                        setTimeout(function() {
                            checkConversionStatus(productId, taskId);
                        }, 5000); // Check every 5 seconds
                    }
                } else {
                    alert(response.data);
                    $('#conversion-status').hide();
                }
            },
            error: function() {
                alert('An error occurred while checking the conversion status.');
                $('#conversion-status').hide();
            }
        });
    }

   

    // Handle the Save Model button click
       
    $('#save-model-btn').on('click', function(e) {
        e.preventDefault();

        var modelPath = document.getElementById("model-viewer").getAttribute("src");; // Get the model file path (GLB file)
        // console.log(modelPath);

        if (!modelPath) {
            alert('Please view the 3d model first before saving.');
            return;
        }

        // Check if the model already exists
        $.ajax({
            url: wc_3d_model_converter.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_check_model_exists',
                security: nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success && response.data.exists) {
                    // Model exists, ask for confirmation to overwrite
                    if (confirm("A 3D model already exists for this product. Do you want to overwrite it?")) {
                        saveModel(modelPath); // Call the saveModel function to proceed
                    }
                } else {
                    // Model doesn't exist, save directly
                    saveModel(modelPath);
                }
            },
            error: function() {
                console.error('Error checking if model exists');
            }
        });
    });

    function saveModel(modelPath) {
        console.log('Attempting to save model', { modelPath, productId });
    
        // Disable save button to prevent multiple clicks
        $('#save-model-btn').prop('disabled', true).text('Saving...');
    
        $.ajax({
            url: wc_3d_model_converter.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_save_3d_model',
                security: wc_3d_model_converter.nonce,
                product_id: productId,
                model_path: modelPath
            },
            success: function(response) {
                if (response.success) {
                    // Detailed success feedback
                    alert('Model saved successfully!\n\nLocation: ' + (response.data.model_url || 'Plugin models folder'));
                    
                    // Update UI elements
                    $('#save-model-btn')
                        .prop('disabled', false)
                        .text('Save Model')
                        .addClass('saved');
                    
                    // Enable shortcode generation
                    $('#generate-shortcode-btn').prop('disabled', false);
                } else {
                    // Clear feedback with error details
                    alert('Model save failed: ' + (response.data || 'Unknown error'));
                    $('#save-model-btn').prop('disabled', false).text('Save Model');
                }
                
                console.log('Model save response:', response);
            },
            error: function(xhr) {
                console.error('Save model error', xhr);
                alert('Error saving model. Please check browser console for details.');
                $('#save-model-btn').prop('disabled', false).text('Save Model');
            }
        });
    }
    
    $('#generate-shortcode-btn').on('click', function(e) {
        e.preventDefault();
        var shortcode = '[product_3d_model]'; // No need for product_id attribute
        prompt('Copy this shortcode to use on the product page:', shortcode); 
    });

    // Check if the model already exists when the page loads
    $.ajax({
        url: wc_3d_model_converter.ajax_url,
        type: 'POST',
        data: {
            action: 'wc_check_model_exists',
            security: nonce,
            product_id: productId
        },
        success: function(response) {
            if (response.success && response.data.exists) {
                // If the model exists, enable the Generate Shortcode button
                $('#generate-shortcode-btn').prop('disabled', false);
                // Optionally, you could also display a message indicating the model is already saved
            }
        },
        error: function() {
            console.error('Error checking if model exists');
        }
    });
    // Regenerate Model button click (Triggers the same conversion process as Convert to 3D Model)
$('#regenerate-model').on('click', function(e) { 
    e.preventDefault();
    
    // Hide all relevant elements before starting the conversion process
    $('#conversion-status').show(); // Show conversion status area
    $('.progress-bar').css('width', '0%'); // Reset progress bar
    $('.conversion-status-message').text('Starting conversion...'); // Update status message
    $('#model-viewer').hide(); // Hide model viewer
    $('#generate-shortcode-btn').prop('disabled', true); // Disable Shortcode button during conversion
    $('#save-model-btn').hide(); // Hide Save Model button
    $('#download-dropdown').hide(); // Hide download options
    $('#regenerate-model').hide(); // Hide regenerate button
    $('#view-3d-btn').hide(); // Hide "View 3D Model" button
    $('#download-model-btn').hide();
    // Trigger the Convert to 3D Model button click event to start the conversion again
    $('#convert-to-3d-model').trigger('click'); 
});

});
