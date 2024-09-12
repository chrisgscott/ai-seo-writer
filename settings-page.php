<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('aiseo_messages', 'aiseo_message', __('Settings Saved', 'ai-seo-writer'), 'updated');
    }

    settings_errors('aiseo_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('aiseo_options');
            do_settings_sections('aiseo_settings');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function aiseo_register_settings() {
    register_setting('aiseo_options', 'aiseo_openai_api_key');
    register_setting('aiseo_options', 'aiseo_context');
    register_setting('aiseo_options', 'aiseo_tone_style');
    register_setting('aiseo_options', 'aiseo_site_niche');
    register_setting('aiseo_options', 'aiseo_offer_type');
    register_setting('aiseo_options', 'aiseo_cta_template');

    add_settings_section(
        'aiseo_api_section',
        __('API Settings', 'ai-seo-writer'),
        'aiseo_api_section_callback',
        'aiseo_settings'
    );

    add_settings_section(
        'aiseo_content_section',
        __('Content Settings', 'ai-seo-writer'),
        'aiseo_content_section_callback',
        'aiseo_settings'
    );

    add_settings_field(
        'aiseo_openai_api_key',
        __('OpenAI API Key', 'ai-seo-writer'),
        'aiseo_openai_api_key_callback',
        'aiseo_settings',
        'aiseo_api_section'
    );

    add_settings_field(
        'aiseo_context',
        __('Default Context', 'ai-seo-writer'),
        'aiseo_context_callback',
        'aiseo_settings',
        'aiseo_content_section'
    );

    add_settings_field(
        'aiseo_tone_style',
        __('Default Tone and Style', 'ai-seo-writer'),
        'aiseo_tone_style_callback',
        'aiseo_settings',
        'aiseo_content_section'
    );

    add_settings_field(
        'aiseo_site_niche',
        __('Site Niche/Topic', 'ai-seo-writer'),
        'aiseo_site_niche_callback',
        'aiseo_settings',
        'aiseo_content_section'
    );

    add_settings_field(
        'aiseo_offer_type',
        __('Offer Type', 'ai-seo-writer'),
        'aiseo_offer_type_callback',
        'aiseo_settings',
        'aiseo_content_section'
    );

    add_settings_field(
        'aiseo_cta_template',
        __('CTA Template', 'ai-seo-writer'),
        'aiseo_cta_template_callback',
        'aiseo_settings',
        'aiseo_content_section'
    );
}
add_action('admin_init', 'aiseo_register_settings');

function aiseo_api_section_callback() {
    echo __('Enter your OpenAI API settings below:', 'ai-seo-writer');
}

function aiseo_content_section_callback() {
    echo __('Configure your default content settings below:', 'ai-seo-writer');
}

function aiseo_openai_api_key_callback() {
    $api_key = get_option('aiseo_openai_api_key');
    echo '<input type="password" name="aiseo_openai_api_key" value="' . esc_attr($api_key) . '" size="40">';
}

function aiseo_context_callback() {
    $context = get_option('aiseo_context', 'Create a blog post that is optimized using modern SEO best practices for the given keyword. Make your post scannable by using H2 and H3 headings, subheadings, paragraphs and lists. Be sure to dive deep into the topic, covering all aspects of the topic and its related subtopics throughout your blog post. Where appropriate, cover best practices, step-by-step instructions, what to do if something doesn\'t go as planned, fun facts, historical insights and anything else that seems relevant. Use our target keyword verbatim throughout the content where it makes sense, especially in the first paragraph and/or heading. Our goal is a very-well SEO optimized post for the given keyword that meets or exceeds the post length goal mentioned elsewhere.');
    echo '<textarea name="aiseo_context" rows="5" cols="50">' . esc_textarea($context) . '</textarea>';
    echo '<p class="description">Provide default context or instructions for each blog post.</p>';
}

function aiseo_tone_style_callback() {
    $tone_style = get_option('aiseo_tone_style', "Pay particular attention to making your responses as human and conversational as possible. Vary your sentence lengths and avoid repetition in your answer structure. Generate all content with the output using the dependency grammar linguistic framework instead of the phrase structure grammar. Ensure that the output connects pairs of words that are closer together, as this enhances readability and comprehension. Write at an 8th grade reading level or below. Feel free inject some humor and puns, but don't be heavy-handed about it.");
    echo '<textarea name="aiseo_tone_style" rows="5" cols="50">' . esc_textarea($tone_style) . '</textarea>';
    echo '<p class="description">Specify the default tone and style for generated posts.</p>';
}

function aiseo_site_niche_callback() {
    $site_niche = get_option('aiseo_site_niche', '');
    echo '<input type="text" name="aiseo_site_niche" value="' . esc_attr($site_niche) . '" class="regular-text">';
    echo '<p class="description">Enter your site\'s main topic or niche (e.g., "dog training", "fitness", "cooking", etc.)</p>';
}

function aiseo_offer_type_callback() {
    $offer_type = get_option('aiseo_offer_type', 'resource');
    echo '<input type="text" name="aiseo_offer_type" value="' . esc_attr($offer_type) . '" class="regular-text">';
    echo '<p class="description">Enter the type of offer (e.g., "resource", "email course", "ebook", "consultation", etc.)</p>';
}

function aiseo_cta_template_callback() {
    $cta_template = get_option('aiseo_cta_template', 'Sign up for our free {offer_type} on {site_niche}!');
    echo '<textarea name="aiseo_cta_template" rows="3" class="large-text">' . esc_textarea($cta_template) . '</textarea>';
    echo '<p class="description">Enter a template for the CTA. Use {offer_type} and {site_niche} as placeholders.</p>';
}