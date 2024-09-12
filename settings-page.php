<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Add error/update messages
    if (isset($_GET['settings-updated'])) {
        add_settings_error('aiseo_messages', 'aiseo_message', __('Settings Saved', 'ai-seo-writer'), 'updated');
    }

    // Show error/update messages
    settings_errors('aiseo_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('aiseo_options');
            do_settings_sections('aiseo_settings');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function aiseo_register_settings() {
    register_setting('aiseo_options', 'aiseo_openai_api_key');

    add_settings_section(
        'aiseo_settings_section',
        __('API Settings', 'ai-seo-writer'),
        'aiseo_settings_section_callback',
        'aiseo_settings'
    );

    add_settings_field(
        'aiseo_openai_api_key',
        __('OpenAI API Key', 'ai-seo-writer'),
        'aiseo_openai_api_key_callback',
        'aiseo_settings',
        'aiseo_settings_section'
    );
}
add_action('admin_init', 'aiseo_register_settings');

function aiseo_settings_section_callback() {
    echo __('Enter your OpenAI API settings below:', 'ai-seo-writer');
}

function aiseo_openai_api_key_callback() {
    $api_key = get_option('aiseo_openai_api_key');
    echo '<input type="password" name="aiseo_openai_api_key" value="' . esc_attr($api_key) . '" size="40">';
}