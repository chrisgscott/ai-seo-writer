<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_admin_page() {
    // Check if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aiseo_nonce']) && wp_verify_nonce($_POST['aiseo_nonce'], 'aiseo_generate_posts')) {
        // Process form submission
        $keywords = explode(',', sanitize_textarea_field($_POST['keywords']));
        $post_length = sanitize_text_field($_POST['post_length']);
        $context = sanitize_textarea_field($_POST['context']);
        $tone_style = sanitize_textarea_field($_POST['tone_style']);

        // Generate posts
        aiseo_generate_posts($keywords, $post_length, $context, $tone_style);

        // Redirect to the progress page
        wp_redirect(admin_url('admin.php?page=aiseo-progress'));
        exit;
    }

    // Default values
    $default_post_length = "1200";
    $default_context = "Create a blog post that is optimized using modern SEO best practices for the given keyword. Make your post scannable by using H2 and H3 headings, subheadings, paragraphs and lists. Be sure to dive deep into the topic, covering all aspects of the topic and its related subtopics throughout your blog post. Where appropriate, cover best practices, step-by-step instructions, what to do if something doesn't go as planned, fun facts, historical insights and anything else that seems relevant. Use our target keyword verbatim throughout the content where it makes sense, especially in the first paragraph and/or heading. Our goal is a very-well SEO optimized post for the given keyword that meets or exceeds the post length goal mentioned elsewhere.";
    $default_tone_style = "Pay particular attention to making your responses as human and conversational as possible. Vary your sentence lengths and avoid repetition in your answer structure.

Generate all content with the output using the dependency grammar linguistic framework instead of the phrase structure grammar. Ensure that the output connects pairs of words that are closer together, as this enhances readability and comprehension.

Write at an 8th grade reading level or below. Feel free inject some humor and puns, but don't be heavy-handed about it.";

    ?>
    <div class="wrap">
        <h1>AI SEO Writer</h1>
        <form method="post" action="">
            <?php wp_nonce_field('aiseo_generate_posts', 'aiseo_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="keywords">Keywords</label></th>
                    <td>
                        <textarea name="keywords" id="keywords" rows="5" cols="50" required></textarea>
                        <p class="description">Enter keywords separated by commas. A separate blog post will be generated for each keyword.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_length">Post Length</label></th>
                    <td>
                        <input type="text" name="post_length" id="post_length" value="<?php echo esc_attr($default_post_length); ?>" required>
                        <p class="description">Specify the desired word count range for each generated post.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="context">Context</label></th>
                    <td>
                        <textarea name="context" id="context" rows="5" cols="50"><?php echo esc_textarea($default_context); ?></textarea>
                        <p class="description">Provide additional context or instructions for each blog post. This context will be applied to each keyword individually.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tone_style">Tone and Style</label></th>
                    <td>
                        <textarea name="tone_style" id="tone_style" rows="10" cols="50"><?php echo esc_textarea($default_tone_style); ?></textarea>
                        <p class="description">You can modify these instructions to customize the tone and style of each generated post. These instructions will be applied to each keyword individually.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Generate Posts'); ?>
        </form>
    </div>
    <?php
}

remove_action('admin_init', 'aiseo_handle_form_submission');
