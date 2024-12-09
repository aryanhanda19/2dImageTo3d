WooCommerce 3D Model Converter Plugin
Description
The WooCommerce 3D Model Converter plugin integrates with WooCommerce to allow users to convert product images into 3D models. Once the conversion is complete, the plugin enables the display of the 3D model on the product page and the generation of shortcodes for use in any page or post.

Features
Convert Product Image to 3D Model: Adds a "Convert to 3D Model" button in the product admin panel.
3D Model Display: Displays 3D models on the product page using the <model-viewer> HTML element.
File Conversion: Converts product images into 3D models (formats include GLB, FBX, OBJ, USDZ).
Shortcode Generation: Generates shortcodes to easily embed 3D models in pages or posts.
AJAX Integration: Handles all conversions and status updates using AJAX.
Automatic Cleanup: Scheduled cleanup of temporary files older than one hour.
Error Logging: Detailed error logs for troubleshooting.
Installation
Upload the Plugin:

Download and extract the plugin files.
Upload the plugin folder to your WordPress site’s wp-content/plugins directory.
Activate the Plugin:

Go to your WordPress admin dashboard.
Navigate to Plugins > Installed Plugins.
Find WooCommerce 3D Model Converter and click Activate.
Dependencies:

This plugin requires WooCommerce to be installed and activated.
The plugin uses external APIs to convert images to 3D models. Make sure you have a valid API key for both ImgBB (image upload) and Meshy (3D conversion).
Usage
Admin Panel (Product Edit Screen)
After installing the plugin, you will see a "Convert to 3D Model" button on the product edit screen in the WooCommerce admin.
Clicking the button will trigger an image-to-3D conversion. Once the conversion starts, a progress bar will display the current status.
Product Page (Frontend)
Once the conversion is complete, a 3D model viewer will be displayed on the product page, allowing customers to view the product in 3D.
Use the shortcode [product_3d_model] to embed the 3D model on any page or post.
Conversion Status
The plugin tracks the conversion status. If the conversion fails or is in progress, the admin panel will display appropriate messages.
AJAX Functionality
Convert to 3D Model: Triggers the image-to-3D conversion process.
Check Conversion Status: Check the current status of the 3D model conversion.
Download and Save 3D Model: Saves the final 3D model on your server and makes it available for download.
Example Usage of Shortcode
To display the 3D model on a post or page, use the shortcode like so:

html
Copy code
[product_3d_model]
This shortcode will check if a 3D model exists for the current product and display it.

Files and Directories
Plugin Structure:
plugin.php: Main plugin file that hooks into WooCommerce.
admin.js: JavaScript used to handle the frontend interactions in the product admin screen.
admin.css: Styling for the product admin page.
frontend.php: Template to display the 3D model button on the product page.
models/: Directory where the 3D models are stored.
temp/: Temporary storage for 3D models during processing.
API Keys
The plugin requires an API key for ImgBB and Meshy services to convert images into 3D models:
ImgBB API Key: Used to upload product images.
Meshy API Key: Used to convert the uploaded image into a 3D model.
Frequently Asked Questions
How do I get the API keys?
ImgBB: Sign up on ImgBB and generate an API key here.
Meshy: Sign up for a Meshy account and get an API key from Meshy AI.
Can I customize the 3D model format?
Yes, you can modify the $formats array in the admin panel PHP code to include the desired model formats such as FBX, OBJ, GLB, etc.

How do I clean up old 3D models?
The plugin automatically removes models that are older than 1 hour. This cleanup process runs every hour.

How do I display the 3D model viewer?
Once the model is converted, it will automatically be displayed on the product page. You can also use the [product_3d_model] shortcode to manually embed it on other pages or posts.

The 3D model is not showing on the product page. What should I do?
Ensure that the conversion process has completed. You can check the status via the admin panel.
Verify that the model file is saved in the correct directory (models/).
Changelog
Version 1.0.1 (December 2024)
Initial release of WooCommerce 3D Model Converter.
Integrates image-to-3D model conversion via external API.
Allows display of 3D models on product pages.
Credits
Author: Your Name
Libraries/Tools:
Model Viewer: Google Model Viewer
ImgBB: ImgBB API
Meshy: Meshy AI API
This README provides installation, usage instructions, and explains the plugin's functionality. Let me know if you need any additional sections or changes!
