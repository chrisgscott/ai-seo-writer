<?php
/*
Plugin Name: AI SEO Writer
Description: Generate SEO-optimized blog posts from keywords using OpenAI API.
Version: 1.1
Author: Mosaic
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'internal-linking.php';

function aiseo_init() {
    aiseo_log("Initializing AI SEO Writer plugin");
    // Load Composer autoloader
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

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
    
    add_action('admin_enqueue_scripts', 'aiseo_enqueue_admin_scripts');
    add_action('admin_menu', 'aiseo_add_admin_menu');
    add_action('init', 'aiseo_register_keyword_taxonomy', 0);
    add_action('wp_ajax_aiseo_clear_queue_and_keywords', 'aiseo_clear_queue_and_keywords');
    aiseo_log("AI SEO Writer plugin initialized");
}

add_action('plugins_loaded', 'aiseo_init');

function aiseo_enqueue_admin_scripts($hook) {
    aiseo_log("Enqueuing admin scripts for hook: $hook");
    if ('ai-seo-writer_page_ai-seo-writer-progress' === $hook) {
        wp_enqueue_script('jquery');
        
        wp_localize_script('jquery', 'aiseoData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aiseo-ajax-nonce')
        ));

        add_action('admin_footer', 'aiseo_print_progress_js');
        aiseo_log("Admin scripts enqueued for progress page");
    }
}

function aiseo_print_progress_js() {
    aiseo_log("Printing progress JavaScript");
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function updateProgress() {
            console.log("Updating progress...");
            $.ajax({
                url: aiseoData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aiseo_get_progress',
                    nonce: aiseoData.nonce
                },
                success: function(response) {
                    console.log("Progress update received:", response);
                    if (response.success) {
                        $('#current-status').text(response.data.current_status);
                        $('#next-scheduled').text(response.data.next_scheduled);
                        $('#queue-list').html(response.data.queue_list);
                        $('#processed-list').html(response.data.processed_list);
                        $('#current-process').text(response.data.current_process);
                    }
                },
                complete: function() {
                    setTimeout(updateProgress, 5000); // Update every 5 seconds
                }
            });
        }

        // Start the update process
        updateProgress();
    });
    </script>
    <?php
    aiseo_log("Progress JavaScript printed");
}

function aiseo_log($message) {
    $log_file = plugin_dir_path(__FILE__) . 'aiseo_log.txt';
    $timestamp = current_time('mysql');
    $log_message = "[{$timestamp}] {$message}\n";
    error_log($log_message, 3, $log_file);
}

function aiseo_plugin_deactivation() {
    aiseo_log("Deactivating AI SEO Writer plugin");
    delete_option('aiseo_current_status');
    wp_clear_scheduled_hook('aiseo_process_queue');
    aiseo_log("AI SEO Writer plugin deactivated");
}
register_deactivation_hook(__FILE__, 'aiseo_plugin_deactivation');

function aiseo_add_admin_menu() {
    aiseo_log("Adding admin menu items");
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
    aiseo_log("Admin menu items added");
}

function aiseo_register_keyword_taxonomy() {
    aiseo_log("Registering AI SEO Keyword taxonomy");
    $labels = array(
        'name'              => _x('AI SEO Keywords', 'taxonomy general name'),
        'singular_name'     => _x('AI SEO Keyword', 'taxonomy singular name'),
        'search_items'      => __('Search Keywords'),
        'all_items'         => __('All Keywords'),
        'edit_item'         => __('Edit Keyword'),
        'update_item'       => __('Update Keyword'),
        'add_new_item'      => __('Add New Keyword'),
        'new_item_name'     => __('New Keyword Name'),
        'menu_name'         => __('AI SEO Keywords'),
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'aiseo-keyword'),
        'show_in_rest'      => true,
        'public'            => false, // Set to false to hide from frontend
    );

    register_taxonomy('aiseo_keyword', array('post'), $args);
    aiseo_log("AI SEO Keyword taxonomy registered");
}

function aiseo_clear_queue_and_keywords() {
    aiseo_log("Clearing queue and processed keywords");
    check_ajax_referer('aiseo-ajax-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        aiseo_log("Permission denied for clearing queue and keywords");
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    delete_option('aiseo_keyword_queue');
    delete_option('aiseo_processed_keywords');
    delete_option('aiseo_current_status');
    delete_option('aiseo_next_process_time');

    aiseo_log("Queue and processed keywords cleared successfully");
    wp_send_json_success(['message' => 'Queue and processed keywords cleared successfully.']);
}

function aiseo_get_progress() {
    check_ajax_referer('aiseo-ajax-nonce', 'nonce');

    $status = get_option('aiseo_current_status', 'No active process');
    $next_scheduled = wp_next_scheduled('aiseo_process_queue');
    $queue = get_option('aiseo_keyword_queue', array());
    $processed = get_option('aiseo_processed_keywords', array());
    $current_process = get_option('aiseo_current_process', 'No active process');

    wp_send_json_success(array(
        'status' => $status,
        'next_scheduled' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled',
        'queue' => '<li>' . implode('</li><li>', array_column($queue, 'keyword')) . '</li>',
        'processed' => '<li>' . implode('</li><li>', $processed) . '</li>',
        'current_process' => $current_process
    ));
}
add_action('wp_ajax_aiseo_get_progress', 'aiseo_get_progress');

register_activation_hook(__FILE__, 'aiseo_create_keyword_index_table');

function aiseo_manual_update_existing_posts() {
    aiseo_log("Starting manual update of existing posts");
    aiseo_update_existing_posts();
    aiseo_log("Finished manual update of existing posts");
}
add_action('admin_init', 'aiseo_manual_update_existing_posts');

function aiseo_update_post_slugs_ajax() {
    check_ajax_referer('aiseo-update-slugs-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $updated_count = aiseo_update_existing_post_slugs();

    wp_send_json_success(['updated_count' => $updated_count]);
}
add_action('wp_ajax_aiseo_update_post_slugs', 'aiseo_update_post_slugs_ajax');

function aiseo_remove_cta_ajax() {
    check_ajax_referer('aiseo-remove-cta-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $updated_count = aiseo_remove_cta_from_posts();

    wp_send_json_success(['updated_count' => $updated_count]);
}
add_action('wp_ajax_aiseo_remove_cta', 'aiseo_remove_cta_ajax');

function aiseo_remove_duplicate_content_ajax() {
    check_ajax_referer('aiseo-remove-duplicate-content-nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $updated_count = aiseo_remove_duplicate_content();

    wp_send_json_success(['updated_count' => $updated_count]);
}
add_action('wp_ajax_aiseo_remove_duplicate_content', 'aiseo_remove_duplicate_content_ajax');

function aiseo_update_post_title_ajax() {
    check_ajax_referer('aiseo_update_title', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $post_id = intval($_POST['post_id']);
    $new_title = sanitize_text_field($_POST['new_title']);

    $updated = wp_update_post([
        'ID' => $post_id,
        'post_title' => $new_title
    ]);

    if ($updated) {
        wp_send_json_success(['message' => 'Title updated successfully.']);
    } else {
        wp_send_json_error(['message' => 'Failed to update title.']);
    }
}
add_action('wp_ajax_aiseo_update_post_title', 'aiseo_update_post_title_ajax');