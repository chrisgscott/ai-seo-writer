<?php
/*
Plugin Name: AI SEO Writer
Description: Generate SEO-optimized blog posts from keywords using OpenAI API.
Version: 1.0
Author: Your Name
*/

// Load Composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Include the admin page
require_once plugin_dir_path(__FILE__) . 'admin-page.php';
// Include the settings page
require_once plugin_dir_path(__FILE__) . 'settings-page.php';

// Include the OpenAI integration
require_once plugin_dir_path(__FILE__) . 'openai-integration.php';

// Include the queue handler
require_once plugin_dir_path(__FILE__) . 'queue-handler.php';

// Include the progress page
require_once plugin_dir_path(__FILE__) . 'progress-page.php';

// Include the post reprocessor
require_once plugin_dir_path(__FILE__) . 'post-reprocessor.php';

function aiseo_enqueue_admin_scripts($hook) {
    if ('ai-seo-writer_page_ai-seo-writer-progress' === $hook) {
        wp_enqueue_script('jquery');
        
        // Enqueue custom JavaScript if you have any
        wp_enqueue_script('aiseo-progress-js', plugin_dir_url(__FILE__) . 'js/progress.js', array('jquery'), '1.0', true);
        
        // You can also localize the script to pass PHP variables to JavaScript
        wp_localize_script('aiseo-progress-js', 'aiseoData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiseo-ajax-nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'aiseo_enqueue_admin_scripts');

function aiseo_log($message) {
    $log_file = plugin_dir_path(__FILE__) . 'aiseo_log.txt';
    $timestamp = current_time('mysql');
    $log_message = "[{$timestamp}] {$message}\n";
    error_log($log_message, 3, $log_file);
}

// Add this function to your existing plugin file
function aiseo_plugin_deactivation() {
    delete_option('aiseo_current_status');
    wp_clear_scheduled_hook('aiseo_process_queue');
}
register_deactivation_hook(__FILE__, 'aiseo_plugin_deactivation');

function aiseo_add_admin_menu() {
    add_menu_page(
        'AI SEO Writer',
        'AI SEO Writer',
        'manage_options',
        'ai-seo-writer',
        'aiseo_admin_page',
        'dashicons-edit'
    );

    add_submenu_page(
        'ai-seo-writer',
        'Settings',
        'Settings',
        'manage_options',
        'ai-seo-writer-settings',
        'aiseo_settings_page'
    );

    add_submenu_page(
        'ai-seo-writer',
        'Progress',
        'Progress',
        'manage_options',
        'ai-seo-writer-progress',
        'aiseo_progress_page'
    );
}
add_action('admin_menu', 'aiseo_add_admin_menu');

function aiseo_clear_queue() {
    delete_option('aiseo_keyword_queue');
    delete_option('aiseo_current_status');
    delete_option('aiseo_next_process_time');
    echo "Queue cleared successfully.";
    exit;
}
//add_action('admin_init', 'aiseo_clear_queue');