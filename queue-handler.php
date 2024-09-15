<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_enqueue_keywords($keywords, $post_length, $context, $tone_style, $bypass_cooldown = false) {
    if (!$bypass_cooldown) {
        $last_enqueue = get_transient('aiseo_last_enqueue');
        if ($last_enqueue && (time() - $last_enqueue) < 300) { // 5 minutes cooldown
            aiseo_log("Enqueue attempt too soon. Skipping.");
            return;
        }
    }

    set_transient('aiseo_last_enqueue', time(), 3600);

    $queue = get_option('aiseo_keyword_queue', array());
    $processed_keywords = get_option('aiseo_processed_keywords', array());
    $enqueued = false;

    foreach ($keywords as $keyword) {
        if (!in_array($keyword, $processed_keywords) && !in_array($keyword, array_column($queue, 'keyword'))) {
            $queue[] = array(
                'keyword' => $keyword,
                'post_length' => $post_length,
                'context' => $context,
                'tone_style' => $tone_style,
            );
            $enqueued = true;
            aiseo_log("Enqueued keyword: {$keyword}");
        } else {
            aiseo_log("Keyword '{$keyword}' has already been processed or queued. Skipping.");
        }
    }

    if ($enqueued) {
        update_option('aiseo_keyword_queue', $queue);
        aiseo_log("Keywords enqueued. Current queue size: " . count($queue));
        
        // Trigger immediate queue processing
        wp_schedule_single_event(time(), 'aiseo_process_queue');
        aiseo_log("Scheduled immediate queue processing");
    }
}

function aiseo_process_queue() {
    if (get_transient('aiseo_queue_processing')) {
        aiseo_log("Queue is already being processed. Exiting.");
        return;
    }

    set_transient('aiseo_queue_processing', true, 15 * MINUTE_IN_SECONDS);

    require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');

    aiseo_log("Starting queue processing");
    update_option('aiseo_current_status', 'Processing queue');

    $queue = get_option('aiseo_keyword_queue', array());
    $api_key = get_option('aiseo_openai_api_key');
    $all_keywords = array_column($queue, 'keyword');
    $processed_keywords = get_option('aiseo_processed_keywords', array());

    aiseo_log("Queue processing triggered. Current queue size: " . count($queue));

    foreach ($queue as $key => $item) {
        if (in_array($item['keyword'], $processed_keywords)) {
            aiseo_log("Keyword '{$item['keyword']}' has already been processed. Skipping.");
            unset($queue[$key]);
            continue;
        }

        update_option('aiseo_current_process', "Processing keyword: {$item['keyword']}");
        aiseo_log("Processing keyword: " . $item['keyword']);
        
        try {
            $post_id = aiseo_process_single_keyword($api_key, $item, $all_keywords);
            
            if ($post_id) {
                unset($queue[$key]);
                $processed_keywords[] = $item['keyword'];
                aiseo_log("Processed keyword: {$item['keyword']}. Post ID: {$post_id}");
            } else {
                throw new Exception("Failed to create post.");
            }
        } catch (Exception $e) {
            aiseo_log("Error processing keyword " . $item['keyword'] . ": " . $e->getMessage());
            update_option('aiseo_current_process', "Error processing keyword: {$item['keyword']} - " . $e->getMessage());
        }
    }

    update_option('aiseo_keyword_queue', $queue);
    update_option('aiseo_processed_keywords', $processed_keywords);
    delete_transient('aiseo_queue_processing');

    update_option('aiseo_current_status', 'Queue processing completed');
    update_option('aiseo_current_process', 'No active process');
    aiseo_log("Queue processing completed");

    aiseo_log("Starting internal linking process for all posts");
    aiseo_add_internal_links_to_all_posts($all_keywords);
}

function aiseo_process_single_keyword($api_key, $item, $all_keywords) {
    update_option('aiseo_current_process', "Sending request to OpenAI for keyword: {$item['keyword']}");
    $response = aiseo_openai_request($api_key, $item['keyword'], $item['post_length'], $item['context'], $item['tone_style'], $all_keywords);
    $content = json_decode($response, true);
    
    aiseo_log("Decoded content for keyword '{$item['keyword']}': " . print_r($content, true));
    
    if (!aiseo_validate_content($content)) {
        throw new Exception("Invalid response format from OpenAI.");
    }

    return aiseo_create_post($content, $item['keyword'], $all_keywords);
}

function aiseo_validate_content($content) {
    $required_keys = ['titles', 'content', 'excerpt', 'category', 'tags'];
    return !array_diff($required_keys, array_keys($content));
}

function aiseo_create_post($content, $keyword, $all_keywords) {
    $category_id = wp_create_category($content['category']);
    $post_content = aiseo_process_content($content['content']);
    
    $post_slug = aiseo_create_slug_from_keyword($keyword);
    
    $post_id = wp_insert_post([
        'post_title' => $content['titles'][0],
        'post_content' => $post_content,
        'post_excerpt' => $content['excerpt'],
        'post_status' => 'draft',
        'post_type' => 'post',
        'post_category' => array($category_id),
        'post_name' => $post_slug, // Set the post slug
    ]);
    
    if (!$post_id) {
        return false;
    }

    wp_set_post_tags($post_id, $content['tags']);
    add_post_meta($post_id, '_aiseo_generated', true);
    add_post_meta($post_id, '_aiseo_alternate_titles', array_slice($content['titles'], 1));
    add_post_meta($post_id, '_aiseo_primary_keyword', $keyword);
    // Add all keywords to the aiseo_keyword taxonomy
    wp_set_object_terms($post_id, $all_keywords, 'aiseo_keyword', true);

    aiseo_update_seo_metadata($post_id, $keyword, $content['titles'][0], $content['excerpt']);
    aiseo_add_faq_to_post($post_id, $content['faqs']);

    return $post_id;
}

function aiseo_update_seo_metadata($post_id, $focus_keyword, $seo_title, $seo_description) {
    if (function_exists('RankMath\Helper\update_meta')) {
        \RankMath\Helper\update_meta('focus_keyword', $focus_keyword, $post_id);
        \RankMath\Helper\update_meta('title', $seo_title, $post_id);
        \RankMath\Helper\update_meta('description', $seo_description, $post_id);
    } else {
        update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
        update_post_meta($post_id, 'rank_math_title', $seo_title);
        update_post_meta($post_id, 'rank_math_description', $seo_description);
    }
}

function aiseo_add_faq_to_post($post_id, $faqs) {
    if (!isset($faqs) || !is_array($faqs)) {
        return;
    }

    $faq_content = "\n\n<h2>Frequently Asked Questions</h2>\n\n";
    foreach ($faqs as $faq) {
        $faq_content .= "<h3>" . esc_html($faq['question']) . "</h3>\n";
        $faq_content .= "<p>" . esc_html($faq['answer']) . "</p>\n\n";
    }

    $post = get_post($post_id);
    $updated_content = $post->post_content . $faq_content;
    wp_update_post([
        'ID' => $post_id,
        'post_content' => $updated_content
    ]);
}

add_action('aiseo_process_queue', 'aiseo_process_queue');

if (!wp_next_scheduled('aiseo_process_queue')) {
    wp_schedule_event(time(), 'hourly', 'aiseo_process_queue');
}

// Add this new action
add_action('aiseo_process_queue', function() {
    $cron_url = plugin_dir_url(__FILE__) . 'aiseo-cron.php';
    wp_remote_get($cron_url, array('timeout' => 0.01, 'blocking' => false));
});

function aiseo_cleanup_processed_keywords() {
    $processed_keywords = get_option('aiseo_processed_keywords', array());
    $limit = 1000; // Adjust this number as needed
    
    if (count($processed_keywords) > $limit) {
        $processed_keywords = array_slice($processed_keywords, -$limit);
        update_option('aiseo_processed_keywords', $processed_keywords);
    }
}

if (!wp_next_scheduled('aiseo_daily_cleanup')) {
    wp_schedule_event(time(), 'daily', 'aiseo_daily_cleanup');
}
add_action('aiseo_daily_cleanup', 'aiseo_cleanup_processed_keywords');

// Initialize the processed keywords option if it doesn't exist
add_option('aiseo_processed_keywords', array());

function aiseo_create_slug_from_keyword($keyword) {
    // Convert to lowercase
    $slug = strtolower($keyword);
    
    // Replace spaces with hyphens
    $slug = str_replace(' ', '-', $slug);
    
    // Remove any character that is not a letter, number, or hyphen
    $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
    
    // Remove multiple hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Trim hyphens from the beginning and end
    $slug = trim($slug, '-');
    
    // Ensure the slug is unique
    $original_slug = $slug;
    $counter = 1;
    while (get_page_by_path($slug, OBJECT, 'post')) {
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// Update existing post slugs
function aiseo_update_existing_post_slugs() {
    aiseo_log("Starting update of existing post slugs");
    $posts = get_posts(array(
        'post_type' => 'post',
        'numberposts' => -1,
        'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
        'fields' => 'ids' // This will return an array of post IDs
    ));
    aiseo_log("Found " . count($posts) . " posts to process");
    aiseo_log("Post IDs found: " . implode(', ', $posts));
    
    $updated_count = 0;
    
    foreach ($posts as $post_id) {
        $post = get_post($post_id);
        aiseo_log("Processing post ID: " . $post_id . ", Status: " . $post->post_status . ", Title: '" . $post->post_title . "'");
        
        $primary_keyword = get_post_meta($post_id, '_aiseo_primary_keyword', true);
        
        if (empty($primary_keyword)) {
            aiseo_log("No primary keyword found for post ID: " . $post_id . ". Skipping.");
            continue;
        }
        
        $new_slug = aiseo_create_slug_from_keyword($primary_keyword);
        
        if ($new_slug !== $post->post_name) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_name' => $new_slug
            ));
            $updated_count++;
            aiseo_log("Updated slug for post ID: " . $post_id . " to: " . $new_slug);
        } else {
            aiseo_log("Slug already matches keyword for post ID: " . $post_id . ". Skipping.");
        }
    }
    
    aiseo_log("Finished updating existing post slugs. Updated " . $updated_count . " posts.");
    return $updated_count;
}
