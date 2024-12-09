jQuery(document).ready(function($) {
    // Disable the convert button if the product is not published
    $('#convert-to-3d-model').on('click', function(event) {
        // Prevent the default form submission and event propagation
        event.preventDefault();
        event.stopPropagation();
    
        console.log('convert-to-3d-model clicked');
    
        var product_id = $('#post_ID').val();
        
        // Check if the product is published
        if ($('#post_status').val() !== 'publish') {
            alert('Please publish the product before converting to 3D model.');
            // Enable the button again in case the user has to publish the product first
            $(this).prop('disabled', false);
            return;
        }
    
        // Disable the button to avoid multiple clicks
        $(this).prop('disabled', true);
    
        // Call your function to start the conversion
        start_conversion(product_id);
    });

    // Function to start conversion
    function start_conversion(product_id) {
        $('#conversion-status').show();
        $('#conversion-status .progress-bar').css('width', '0%'); // Reset progress bar

        $.ajax({
            url: backend3DConverter.ajax_url,
            type: 'POST',
            data: {
                action: 'backend_convert_image_to_3d_model',
                product_id: product_id,
                security: backend3DConverter.nonce,
            },
            success: function(response) {
                if (response.success) {
                    show_conversion_status("Conversion started...");
                } else {
                    alert('Error: ' + response.data.message);
                    $('#convert-to-3d-model').prop('disabled', false);
                }
            },
            error: function() {
                alert('An error occurred while initiating the conversion.');
                $('#convert-to-3d-model').prop('disabled', false);
            }
        });
    }

    // Show conversion status and initiate polling
    function show_conversion_status(message) {
        $('#conversion-status .conversion-status-message').text(message);
        poll_conversion_status();
    }

    // Poll conversion status
    function poll_conversion_status() {
        var product_id = $('#post_ID').val();
        var interval = setInterval(function() {
            $.ajax({
                url: backend3DConverter.ajax_url,
                type: 'POST',
                data: {
                    action: 'backend_check_conversion_status',
                    product_id: product_id,
                    security: backend3DConverter.nonce,
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.status === 'completed') {
                            $('#model-viewer').show();
                            $('#model-viewer model-viewer').attr('src', response.data.model_url);
                            $('#download-3d-model').attr('href', response.data.model_url).show();
                            $('#conversion-status .conversion-status-message').text('Conversion completed!');
                            $('#convert-to-3d-model').prop('disabled', false); // Re-enable the button after completion
                            clearInterval(interval); // Stop polling once conversion is done
                        } else if (response.data.status === 'in_progress') {
                            var progress = response.data.progress;
                            $('#conversion-status .progress-bar').css('width', progress + '%');
                            $('#conversion-status .conversion-status-message').text('Conversion in progress... ' + progress + '%');
                        }
                    } else {
                        alert('Error: ' + response.data.message);
                        $('#convert-to-3d-model').prop('disabled', false);
                        clearInterval(interval); // Stop polling on error
                    }
                },
                error: function() {
                    alert('An error occurred while checking the conversion status.');
                    $('#convert-to-3d-model').prop('disabled', false);
                    clearInterval(interval); // Stop polling on error
                }
            });
        }, 5000); // Poll every 5 seconds
    }
});

