<?php
// settings.php

function wc_3d_model_converter_settings_page() {
    add_options_page(
        '3D Model Converter Settings',
        '3D Model Converter',
        'manage_options',
        'wc-3d-model-converter-settings',
        'wc_3d_model_converter_settings_page_content'
    );
}
add_action('admin_menu', 'wc_3d_model_converter_settings_page');

function wc_3d_model_converter_settings_page_content() {
    ?>
    <div class="wrap">
        <h1>3D Model Converter Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wc_3d_model_converter_settings_group');
            do_settings_sections('wc-3d-model-converter-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function wc_3d_model_converter_register_settings() {
    register_setting(
        'wc_3d_model_converter_settings_group',
        'wc_3d_model_converter_settings',
        'wc_3d_model_converter_sanitize_settings'
    );

    add_settings_section(
        'wc_3d_model_converter_api_keys_section',
        'API Keys',
        'wc_3d_model_converter_api_keys_section_callback',
        'wc-3d-model-converter-settings'
    );

    add_settings_field(
        'imgbb_api_key',
        'IMGBB API Key',
        'wc_3d_model_converter_imgbb_api_key_field_callback',
        'wc-3d-model-converter-settings',
        'wc_3d_model_converter_api_keys_section'
    );

    add_settings_field(
        'meshy_api_key', 
        'Meshy API Key', 
        'wc_3d_model_converter_meshy_api_key_field_callback', 
        'wc-3d-model-converter-settings', 
        'wc_3d_model_converter_api_keys_section' 
    );
}
add_action('admin_init', 'wc_3d_model_converter_register_settings');

function wc_3d_model_converter_api_keys_section_callback() {
    echo '<p>Enter your API keys for the following services:</p>';
}

function wc_3d_model_converter_imgbb_api_key_field_callback() {
    $options = get_option('wc_3d_model_converter_settings');
    ?>
    <input type="text" name="wc_3d_model_converter_settings[imgbb_api_key]" value="<?php echo esc_attr( $options['imgbb_api_key'] ); ?>" />
    <?php
}

function wc_3d_model_converter_meshy_api_key_field_callback() {
    $options = get_option('wc_3d_model_converter_settings');
    ?>
    <input type="text" name="wc_3d_model_converter_settings[meshy_api_key]" value="<?php echo esc_attr( $options['meshy_api_key'] ); ?>" />
    <?php
}

function wc_3d_model_converter_sanitize_settings( $input ) {
    $new_input = array();

    // Explicitly set API keys to empty strings if the fields are empty
    $new_input['imgbb_api_key'] = isset( $input['imgbb_api_key'] ) ? sanitize_text_field( $input['imgbb_api_key'] ) : ''; 
    $new_input['meshy_api_key'] = isset( $input['meshy_api_key'] ) ? sanitize_text_field( $input['meshy_api_key'] ) : ''; 
    
    return $new_input;
}

// Display admin notice if API keys are not set
function wc_3d_model_converter_admin_notice() {
    $options = get_option('wc_3d_model_converter_settings');
    if (empty($options['imgbb_api_key']) || empty($options['meshy_api_key'])) {
        ?>
        <div class="notice notice-error"> 
            <p><strong>3D Model Converter:</strong> <span style="color: red;">Important!</span> Please configure your <a href="<?php echo admin_url('options-general.php?page=wc-3d-model-converter-settings'); ?>">API keys</a> to use the plugin.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'wc_3d_model_converter_admin_notice'); 

// Show the notice when the plugin is activated
function wc_3d_model_converter_activation_notice() {
    add_action('admin_notices', 'wc_3d_model_converter_admin_notice');
}
register_activation_hook( __FILE__, 'wc_3d_model_converter_activation_notice' );

// Clear API keys on plugin deactivation
function wc_3d_model_converter_clear_api_keys_on_deactivation() {
    delete_option('wc_3d_model_converter_settings');
}
add_action('deactivated_plugin', 'wc_3d_model_converter_clear_api_keys_on_deactivation');

// Clear API keys on plugin uninstallation (only if completely uninstalled)
function wc_3d_model_converter_uninstall() {
    delete_option('wc_3d_model_converter_settings');
}
register_uninstall_hook( __FILE__, 'wc_3d_model_converter_uninstall' );

?>
