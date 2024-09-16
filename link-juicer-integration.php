<?php
function aiseo_add_keywords_to_link_juicer($post_id) {
    // Get AI SEO Keywords
    $ai_seo_keywords = wp_get_post_terms($post_id, 'aiseo_keyword', array('fields' => 'names'));
    
    aiseo_log("AI SEO Keywords for post ID $post_id: " . implode(', ', $ai_seo_keywords));
    
    if (!empty($ai_seo_keywords) && !is_wp_error($ai_seo_keywords)) {
        // Get existing Link Juicer keywords
        $existing_keywords = get_post_meta($post_id, '_ilj_keywords', true);
        aiseo_log("Existing Link Juicer keywords for post ID $post_id: " . print_r($existing_keywords, true));
        
        if (!is_array($existing_keywords)) {
            $existing_keywords = array();
        }
        
        // Merge existing keywords with AI SEO Keywords
        $new_keywords = array_unique(array_merge($existing_keywords, $ai_seo_keywords));
        
        // Update Link Juicer keywords only if there are changes
        if ($new_keywords !== $existing_keywords) {
            $update_result = update_post_meta($post_id, '_ilj_keywords', $new_keywords);
            if ($update_result) {
                aiseo_log("Successfully added AI SEO Keywords to Link Juicer for post ID " . $post_id);
            } else {
                aiseo_log("Failed to add AI SEO Keywords to Link Juicer for post ID " . $post_id);
            }
        } else {
            aiseo_log("No changes needed for Link Juicer keywords for post ID " . $post_id);
        }
        
        aiseo_log("New Link Juicer keywords for post ID $post_id: " . print_r($new_keywords, true));
        aiseo_log("Update result for post ID $post_id: " . var_export($update_result, true));
        aiseo_log("New keywords for post ID $post_id: " . implode(', ', $new_keywords));
    } else {
        aiseo_log("No AI SEO Keywords found for post ID " . $post_id);
    }
}

function aiseo_verify_link_juicer_keywords() {
    $posts = get_posts(array(
        'post_type' => 'post',
        'numberposts' => -1,
        'post_status' => array('publish', 'draft')
    ));

    foreach ($posts as $post) {
        $link_juicer_keywords = get_post_meta($post->ID, '_ilj_keywords', true);
        aiseo_log("Verified Link Juicer keywords for post ID " . $post->ID . ": " . print_r($link_juicer_keywords, true));
    }
}