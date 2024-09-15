<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_create_keyword_index_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aiseo_keyword_index';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        keyword varchar(255) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY keyword (keyword)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Update the keyword index when a post is saved
function aiseo_update_keyword_index($post_id) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'aiseo_keyword_index';
    $keyword = get_post_meta($post_id, '_aiseo_primary_keyword', true);

    if ($keyword) {
        $wpdb->replace(
            $table_name,
            array(
                'post_id' => $post_id,
                'keyword' => $keyword
            ),
            array('%d', '%s')
        );
    }
    aiseo_log("Updating keyword index for post ID: $post_id");
    aiseo_log("Primary keyword: " . print_r($keyword, true));
    aiseo_log("Alternate keywords: " . print_r($alternate_keywords, true));
}

// Get keywords for linking
function aiseo_get_keywords_for_linking($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aiseo_keyword_index';
    
    $query = $wpdb->prepare(
        "SELECT keyword, post_id FROM $table_name WHERE post_id != %d",
        $post_id
    );
    
    $results = $wpdb->get_results($query, ARRAY_A);
    aiseo_log("Keywords retrieved for post ID $post_id: " . print_r($results, true));
    
    return $results;
}

// Add internal links to content
function aiseo_add_internal_links($content, $post_id = null) {
    // For now, just return the original content without modifications
    return $content;
}

// Hook functions
add_action('save_post', 'aiseo_update_keyword_index');
add_filter('the_content', 'aiseo_add_internal_links');

// Update existing posts
function aiseo_update_existing_posts() {
    aiseo_log("Starting update of existing posts");
    $posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
    aiseo_log("Found " . count($posts) . " posts to process");
    
    foreach ($posts as $post) {
        aiseo_log("Processing post ID: " . $post->ID);
        $post_content = $post->post_content;
        
        if (empty($post_content)) {
            aiseo_log("Empty content for post ID: " . $post->ID);
            continue;
        }
        
        $updated_content = aiseo_add_internal_links($post_content, $post->ID);
        
        if ($updated_content !== $post_content) {
            wp_update_post(array(
                'ID' => $post->ID,
                'post_content' => $updated_content
            ));
            aiseo_log("Updated post ID: " . $post->ID . " with internal links");
        } else {
            aiseo_log("No changes made to post ID: " . $post->ID);
        }
    }
    
    aiseo_log("Finished updating existing posts");
}

// Add an admin action to trigger the update
function aiseo_handle_update_existing_posts() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    aiseo_update_existing_posts();
    wp_redirect(admin_url('admin.php?page=ai-seo-writer&updated=true'));
    exit;
}
add_action('admin_post_aiseo_update_existing_posts', 'aiseo_handle_update_existing_posts');


