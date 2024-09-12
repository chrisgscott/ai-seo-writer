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

        // Get context and tone_style from settings
        $context = get_option('aiseo_context', '');
        $tone_style = get_option('aiseo_tone_style', '');

        // Generate posts
        aiseo_generate_posts($keywords, $post_length, $context, $tone_style);

        // Redirect to the progress page
        wp_redirect(admin_url('admin.php?page=aiseo-progress'));
        exit;
    }

    // Default value for post length
    $default_post_length = "1200";

    ?>
    <div class="wrap">
        <h1>Generate AI SEO Content</h1>
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
