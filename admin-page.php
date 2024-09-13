<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_admin_page() {
    $success_message = '';

    // Check if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aiseo_nonce']) && wp_verify_nonce($_POST['aiseo_nonce'], 'aiseo_generate_posts')) {
        // Process form submission
        $keywords = isset($_POST['aiseo_keywords']) ? sanitize_textarea_field($_POST['aiseo_keywords']) : '';
        $keywords = preg_split('/[\n,]+/', $keywords);
        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords);

        $post_length = sanitize_text_field($_POST['post_length']);

        // Get context and tone_style from settings
        $context = get_option('aiseo_context', '');
        $tone_style = get_option('aiseo_tone_style', '');

        // Generate posts
        aiseo_generate_posts($keywords, $post_length, $context, $tone_style);

        // Set success message
        $success_message = count($keywords) . " keywords have been added to the queue for processing.";
        aiseo_log("Form submitted successfully: " . $success_message);
    }

    // Default value for post length
    $default_post_length = "1200";

    ?>
    <div class="wrap">
        <h1>Generate AI SEO Content</h1>
        <?php if ($success_message): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success_message); ?></p>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <?php wp_nonce_field('aiseo_generate_posts', 'aiseo_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="keywords">Keywords</label></th>
                    <td>
                        <textarea name="aiseo_keywords" rows="5" cols="50" placeholder="Enter keywords (one per line or comma-separated)"><?php echo esc_textarea(get_option('aiseo_keywords', '')); ?></textarea>
                        <p class="description">Enter keywords separated by commas. A separate blog post will be generated for each keyword.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="post_length">Post Length</label></th>
                    <td>
                        <input type="text" name="post_length" id="post_length" value="<?php echo esc_attr($default_post_length); ?>" required>
                        <p class="description">Specify the desired word count for each generated post.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Generate Posts'); ?>
        </form>
    </div>
    <?php
}

remove_action('admin_init', 'aiseo_handle_form_submission');
