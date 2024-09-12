<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_add_reprocess_metabox() {
    add_meta_box(
        'aiseo_reprocess_metabox',
        'AI SEO Writer - Reprocess Post',
        'aiseo_reprocess_metabox_callback',
        'post',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'aiseo_add_reprocess_metabox');

function aiseo_reprocess_metabox_callback($post) {
    wp_nonce_field('aiseo_reprocess_post', 'aiseo_reprocess_nonce');
    ?>
    <p>
        <label for="aiseo_additional_prompt">Additional Prompting for OpenAI:</label>
        <textarea id="aiseo_additional_prompt" name="aiseo_additional_prompt" rows="4" style="width: 100%;"></textarea>
    </p>
    <p>
        <button type="button" id="aiseo_reprocess_button" class="button button-primary">Reprocess Post</button>
    </p>
    <div id="aiseo_reprocess_result"></div>
    <script>
    jQuery(document).ready(function($) {
        $('#aiseo_reprocess_button').click(function() {
            var additionalPrompt = $('#aiseo_additional_prompt').val();
            var postContent = wp.editor.getContent('content');
            var postId = $('#post_ID').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aiseo_reprocess_post',
                    nonce: $('#aiseo_reprocess_nonce').val(),
                    post_id: postId,
                    content: postContent,
                    additional_prompt: additionalPrompt
                },
                beforeSend: function() {
                    $('#aiseo_reprocess_result').html('Processing... This may take a moment.');
                },
                success: function(response) {
                    console.log('AISEO reprocessing response:', response);
                    if (response.success) {
                        if (wp.data && wp.data.select('core/editor')) {
                            // Gutenberg editor
                            wp.data.dispatch('core/editor').editPost({ content: response.data.content });
                            $('#aiseo_reprocess_result').html('Post reprocessed successfully. The content has been updated in the editor.');
                        } else if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                            // TinyMCE editor
                            tinyMCE.get('content').setContent(response.data.content);
                            $('#aiseo_reprocess_result').html('Post reprocessed successfully. The content has been updated in the editor.');
                        } else {
                            // Fallback for other editors
                            $('#content').val(response.data.content);
                            $('#aiseo_reprocess_result').html('Post reprocessed successfully. Please refresh the page to see the updated content in the editor.');
                        }
                    } else {
                        $('#aiseo_reprocess_result').html('Error: ' + (response.data ? response.data.message : 'Unknown error occurred.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AISEO reprocessing error:', textStatus, errorThrown);
                    $('#aiseo_reprocess_result').html('An error occurred. Please try again. Error: ' + textStatus);
                }
            });
        });
    });
    </script>
    <?php
}

function aiseo_reprocess_post() {
    check_ajax_referer('aiseo_reprocess_post', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $post_id = intval($_POST['post_id']);
    $content = wp_kses_post($_POST['content']);
    $additional_prompt = sanitize_textarea_field($_POST['additional_prompt']);

    aiseo_log("Starting reprocessing for post ID: " . $post_id);
    aiseo_log("Additional prompt: " . $additional_prompt);

    $api_key = get_option('aiseo_openai_api_key');
    $prompt = [
        'content' => $content,
        'additional_prompt' => $additional_prompt
    ];

    try {
        $response = aiseo_openai_request($api_key, $prompt, '', '', '');
        $new_content = json_decode($response, true);

        aiseo_log("Received response from OpenAI: " . print_r($new_content, true));

        if (isset($new_content['content'])) {
            $processed_content = aiseo_process_content($new_content['content']);

            // Add FAQ block to post content
            if (isset($new_content['faqs']) && is_array($new_content['faqs'])) {
                aiseo_log("FAQs found in response. Adding to content.");
                $faq_content = "\n\n<h2>Frequently Asked Questions</h2>\n\n";
                foreach ($new_content['faqs'] as $faq) {
                    $faq_content .= "<!-- wp:rank-math/faq-block {\"questions\":[{\"id\":\"" . uniqid() . "\",\"title\":\"" . esc_attr($faq['question']) . "\",\"content\":\"" . esc_attr($faq['answer']) . "\",\"visible\":true}]} /-->\n\n";
                }
                $processed_content .= $faq_content;
            } else {
                aiseo_log("No FAQs found in the response.");
            }

            $post_data = [
                'ID' => $post_id,
                'post_content' => $processed_content
            ];
            wp_update_post($post_data);
            aiseo_log("Post ID " . $post_id . " reprocessed successfully. Content length: " . strlen($processed_content));
            wp_send_json_success(['content' => $processed_content]);
        } else {
            aiseo_log("Invalid response from OpenAI for post ID " . $post_id . ". Response: " . print_r($new_content, true));
            wp_send_json_error(['message' => 'Invalid response from OpenAI.']);
        }
    } catch (Exception $e) {
        aiseo_log("Error reprocessing post ID " . $post_id . ": " . $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}
add_action('wp_ajax_aiseo_reprocess_post', 'aiseo_reprocess_post');