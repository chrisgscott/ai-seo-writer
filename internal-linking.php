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
}

// Add internal links to content
function aiseo_add_internal_links($content) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'aiseo_keyword_index';
    $post_id = get_the_ID();

    // Get all keywords except the current post's
    $keywords = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT keyword, post_id FROM $table_name WHERE post_id != %d",
            $post_id
        ),
        ARRAY_A
    );

    // Sort keywords by length (longest first) to avoid partial matches
    usort($keywords, function($a, $b) {
        return strlen($b['keyword']) - strlen($a['keyword']);
    });

    $links_added = 0;
    $max_links = 5;

    foreach ($keywords as $keyword_data) {
        if ($links_added >= $max_links) break;

        $keyword = $keyword_data['keyword'];
        $link_post_id = $keyword_data['post_id'];
        $link_url = get_permalink($link_post_id);

        // Use preg_replace_callback to add the link
        $pattern = '/(?<!<a[^>]*>)(?<!<h[1-6]>)\b(' . preg_quote($keyword, '/') . ')\b(?![^<]*<\/a>)(?![^<]*<\/h[1-6]>)/i';
        $content = preg_replace_callback($pattern, function($matches) use ($link_url, &$links_added) {
            $links_added++;
            return '<a href="' . esc_url($link_url) . '">' . $matches[1] . '</a>';
        }, $content, 1);
    }

    return $content;
}

// Hook functions
add_action('save_post', 'aiseo_update_keyword_index');
add_filter('the_content', 'aiseo_add_internal_links');

// Update existing posts
function aiseo_update_existing_posts() {
    $posts = get_posts(array(
        'post_type' => 'post',
        'posts_per_page' => -1,
        'meta_key' => '_aiseo_primary_keyword'
    ));

    foreach ($posts as $post) {
        aiseo_update_keyword_index($post->ID);
    }
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

