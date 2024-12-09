<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Function to display the 3D model and shortcode button
function display_3d_model_button() {
    global $product;
    $product_id = $product->get_id();

    // Get the model URL from the product's meta data
    $model_url = get_post_meta($product_id, '_3d_model_url', true);

    // Check if the model exists
    if ($model_url) {
        // Debugging: Output the model URL to check if it's correct
        echo ""; 

        ?>
        <div id="model-viewer-section">
            <model-viewer alt="3D Model" src="<?php echo esc_url($model_url); ?>" ar auto-rotate camera-controls style="width: 100%; height: 400px;"></model-viewer>

            <iframe src="<?php echo esc_url($model_url); ?>" width="100%" height="400px" style="display: none;"></iframe> 
        </div>
        <div id="model-interaction-buttons" style="margin-top: 10px;">
            <button id="generate-shortcode-btn" class="button" disabled data-product-id="<?php echo $product_id; ?>">Generate Shortcode</button>
        </div>

        <script>
            // JavaScript to check if model-viewer loaded successfully
            const modelViewer = document.querySelector('model-viewer');
            const iframeFallback = document.querySelector('iframe');

            if (modelViewer.canActivateAR) { 
                // AR is supported, assume model loaded fine
                console.log('Model Viewer loaded successfully!'); 
            } else {
                // Model Viewer likely failed, show the iframe fallback
                console.warn('Model Viewer failed to load. Using iframe fallback.');
                modelViewer.style.display = 'none';
                iframeFallback.style.display = 'block';
            }
        </script>
        <?php
    } else {
        echo '<p>No 3D model available for this product.</p>';
    }
}

// Hook to display 3D model and buttons on the product page
add_action('woocommerce_single_product_summary', 'display_3d_model_button', 35);
?>