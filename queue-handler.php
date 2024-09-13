<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_enqueue_keywords($keywords, $post_length, $context, $tone_style) {
    $queue = get_option('aiseo_keyword_queue', array());
    foreach ($keywords as $keyword) {
        $queue[] = array(
            'keyword' => $keyword,
            'post_length' => $post_length,
            'context' => $context,
            'tone_style' => $tone_style,
        );
    }
    update_option('aiseo_keyword_queue', $queue);
    
    // Trigger queue processing immediately
    aiseo_log("Triggering immediate queue processing");
    do_action('aiseo_process_queue');
}

function aiseo_process_queue() {
    // Ensure WordPress functions are available
    require_once(ABSPATH . 'wp-admin/includes/taxonomy.php');

    aiseo_log("Starting queue processing");
    update_option('aiseo_current_status', 'Processing queue');

    $queue = get_option('aiseo_keyword_queue', array());
    $api_key = get_option('aiseo_openai_api_key');
    $all_keywords = array_column($queue, 'keyword');

    foreach ($queue as $key => $item) {
        update_option('aiseo_current_process', "Processing keyword: {$item['keyword']}");
        aiseo_log("Processing keyword: " . $item['keyword']);
        
        try {
            update_option('aiseo_current_process', "Sending request to OpenAI for keyword: {$item['keyword']}");
            $response = aiseo_openai_request($api_key, $item['keyword'], $item['post_length'], $item['context'], $item['tone_style'], $all_keywords);
            $content = json_decode($response, true);
            
            aiseo_log("Decoded content for keyword '{$item['keyword']}': " . print_r($content, true));
            
            if (isset($content['titles']) && isset($content['content']) && isset($content['excerpt']) && isset($content['category']) && isset($content['tags'])) {
                update_option('aiseo_current_process', "Creating post for keyword: {$item['keyword']}");
                // Create or get the category
                $category_id = wp_create_category($content['category']);
                
                $post_content = aiseo_process_content($content['content']);
                
                $post_id = wp_insert_post([
                    'post_title' => $content['titles'][0], // Use the first title
                    'post_content' => $post_content,
                    'post_excerpt' => $content['excerpt'],
                    'post_status' => 'draft',
                    'post_type' => 'post',
                    'post_category' => array($category_id),
                ]);
                
                if ($post_id) {
                    wp_set_post_tags($post_id, $content['tags']);
                    add_post_meta($post_id, '_aiseo_generated', true);
                    aiseo_log("Added '_aiseo_generated' meta to post ID: " . $post_id);
                    add_post_meta($post_id, '_aiseo_alternate_titles', array_slice($content['titles'], 1));

                    // Rank Math integration
                    $focus_keyword = $item['keyword'];
                    $seo_title = $content['titles'][0];
                    $seo_description = $content['excerpt'];

                    // Use Rank Math's functions to update metadata
                    if (function_exists('RankMath\Helper\update_meta')) {
                        \RankMath\Helper\update_meta('focus_keyword', $focus_keyword, $post_id);
                        \RankMath\Helper\update_meta('title', $seo_title, $post_id);
                        \RankMath\Helper\update_meta('description', $seo_description, $post_id);
                    } else {
                        // Fallback if Rank Math functions are not available
                        update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
                        update_post_meta($post_id, 'rank_math_title', $seo_title);
                        update_post_meta($post_id, 'rank_math_description', $seo_description);
                    }

                    // Add FAQ block to post content
                    if (isset($content['faqs']) && is_array($content['faqs'])) {
                        $faq_content = "\n\n<h2>Frequently Asked Questions</h2>\n\n";
                        foreach ($content['faqs'] as $faq) {
                            $faq_content .= "<h3>" . esc_html($faq['question']) . "</h3>\n";
                            $faq_content .= "<p>" . esc_html($faq['answer']) . "</p>\n\n";
                        }
                        $updated_content = $post_content . $faq_content;
                        wp_update_post([
                            'ID' => $post_id,
                            'post_content' => $updated_content
                        ]);
                    }

                    aiseo_log("Post created successfully. Post ID: {$post_id}");
                    
                    // Remove the processed item from the queue
                    unset($queue[$key]);
                    update_option('aiseo_keyword_queue', $queue);
                    
                    aiseo_log("Removed processed keyword from queue: {$item['keyword']}");
                    
                    // Store the initial keywords for this post
                    add_post_meta($post_id, '_aiseo_initial_keywords', $all_keywords);
                } else {
                    throw new Exception("Failed to create post.");
                }
            } else {
                $missing_keys = array_diff(['titles', 'content', 'excerpt', 'category', 'tags'], array_keys($content));
                $error_message = "Invalid response format from OpenAI. Missing keys: " . implode(', ', $missing_keys);
                aiseo_log($error_message);
                throw new Exception($error_message);
            }
        } catch (Exception $e) {
            aiseo_log("Error processing keyword " . $item['keyword'] . ": " . $e->getMessage());
            update_option('aiseo_current_process', "Error processing keyword: {$item['keyword']} - " . $e->getMessage());
        }
    }

    update_option('aiseo_current_status', 'Queue processing completed');
    update_option('aiseo_current_process', 'No active process');
    aiseo_log("Queue processing completed");

    // After all posts have been processed, add internal links
    aiseo_add_internal_links_to_all_posts($all_keywords);
}

add_action('aiseo_process_queue', 'aiseo_process_queue');

if (!wp_next_scheduled('aiseo_process_queue')) {
    wp_schedule_event(time(), 'hourly', 'aiseo_process_queue');
}

// Add this new action
add_action('aiseo_process_queue', function() {
    $cron_url = plugin_dir_url(__FILE__) . 'aiseo-cron.php';
    wp_remote_get($cron_url);
});