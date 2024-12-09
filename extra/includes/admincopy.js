jQuery(document).ready(function ($) {
    const conversionButton = $('#convert-to-3d-model');
    const conversionStatus = $('#conversion-status');
    const progressBar = $('.progress-bar');
    const statusMessage = $('.conversion-status-message');
    let pollingInterval;

    function checkConversionStatus(productId) {
        $.ajax({
            url: wc_3d_model_converter.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_check_conversion_status',
                security: wc_3d_model_converter.nonce,
                product_id: productId
            },
            success: function (response) {
                if (response.success) {
                    const { status, progress, model_url, model_path } = response.data;
                    progressBar.width(progress + '%');
                    statusMessage.text(status === 'SUCCEEDED' ? 'Conversion complete!' : `Conversion in progress... ${progress}%`);
    
                    if (status === 'SUCCEEDED') {
                        progressBar.width('100%');
                        if (model_url) {
                            $('#model-viewer-section').html(`
                                <model-viewer src="${model_path}" alt="3D Model" ar auto-rotate camera-controls></model-viewer>
                                <a href="${model_url}" target="_blank" class="button button-secondary">Download 3D Model</a>
                            `);
                            clearInterval(pollingInterval); // Stop polling on success
                        }
                    } else if (status === 'IN_PROGRESS' || status === 'PENDING') {
                        setTimeout(() => checkConversionStatus(productId), 5000); // Retry after 5 seconds
                    } else {
                        alert('Conversion failed. Status: ' + status);
                        clearInterval(pollingInterval); // Stop polling on failure
                    }
                } else {
                    alert(response.data || 'Conversion failed. Please try again.');
                    clearInterval(pollingInterval);
                }
            },
            error: function () {
                alert('An error occurred while checking conversion status.');
                clearInterval(pollingInterval);
            }
        });
    }

    // Prevent form submission and handle AJAX request
    conversionButton.click(function (e) {
        e.preventDefault(); // Prevent page refresh

        const productId = $(this).data('product-id');
        conversionStatus.show();
        progressBar.width('0%');
        statusMessage.text('Starting conversion...');

        $.ajax({
            url: wc_3d_model_converter.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_3d_model_converter',
                security: wc_3d_model_converter.nonce,
                product_id: productId
            },
            success: function (response) {
                if (response.success) {
                    statusMessage.text(response.data.message);
                    pollingInterval = setInterval(() => checkConversionStatus(productId), 5000);
                } else {
                    statusMessage.text(response.data || 'Conversion failed. Please try again.');
                }
            },
            error: function () {
                statusMessage.text('An error occurred. Please try again.');
            }
        });
    });
});
