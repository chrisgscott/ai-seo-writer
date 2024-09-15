<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';



function aiseo_process_content($content) {
    // Convert Markdown to HTML
    $parsedown = new Parsedown();
    $html = $parsedown->text($content);

    // Ensure no H1 tags are present (convert to H2 if found)
    $html = preg_replace('/<h1>(.*?)<\/h1>/i', '<h2>$1</h2>', $html);

    return $html;
}

function aiseo_openai_request($api_key, $keyword, $post_length = '', $context = '', $tone_style = '', $all_keywords = []) {
    $url = 'https://api.openai.com/v1/chat/completions';
    $site_niche = get_option('aiseo_site_niche', '');
    $offer_type = get_option('aiseo_offer_type', 'resource');
    $cta_template = get_option('aiseo_cta_template', 'Sign up for our free {offer_type} on {site_niche}!');

    $cta_instruction = "A custom call-to-action (CTA) text for a free {$offer_type} related to {$site_niche}, tailored to the specific content of this post. Use this template if suitable: '{$cta_template}'";

    // Fetch existing keywords from other posts
    global $wpdb;
    $existing_keywords = $wpdb->get_col("
        SELECT DISTINCT name 
        FROM {$wpdb->terms} t 
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
        WHERE tt.taxonomy = 'aiseo_keyword' 
        LIMIT 20
    ");

    $keyword_suggestion = implode(', ', $existing_keywords);

    // Modify the prompt to include existing keywords
    $keyword_instruction = "Additionally, try to naturally incorporate some of the following keywords into the content, if relevant: " . implode(', ', $all_keywords);

    if (is_array($keyword) && isset($keyword['content'])) {
        // This is a reprocessing request
        $prompt = "Reprocess and improve the following blog post content. {$keyword['additional_prompt']} {$keyword_instruction}\n\nOriginal content:\n{$keyword['content']}

        Please provide the following in your response:
        1. The main content of the blog post in Markdown format
        2. An array of 3-5 frequently asked questions (FAQs) related to the topic, each with a 'question' and 'answer' field
        3. {$cta_instruction}

        Format your response as a JSON object with the following keys: content, faqs, custom_cta";
    } else {
        // This is a new post request
        $prompt = "Generate a detailed blog post about '{$keyword}'. The post should be no less than {$post_length} words long. {$context} {$tone_style} {$keyword_instruction}

        Please provide the following in your response:
        1. An array of 3-5 SEO-friendly titles
        2. The main content of the blog post in Markdown format
        3. A meta description of about 155 characters
        4. A suggested category for the post
        5. An array of 5-7 relevant tags
        6. An array of 3-5 frequently asked questions (FAQs) related to the topic, each with a 'question' and 'answer' field
        7. {$cta_instruction}

        Format your response as a JSON object with the following keys: titles, content, excerpt, category, tags, faqs, custom_cta";
    }

    $data = array(
        'model' => 'gpt-4o-mini',
        'messages' => array(
            array('role' => 'system', 'content' => 'You are a helpful assistant that generates well-structured, detailed blog posts with SEO metadata.'),
            array('role' => 'user', 'content' => $prompt)
        ),
        'functions' => [
            [
                'name' => 'generate_blog_post',
                'description' => 'Generate a blog post with titles, content, excerpt, category, and tags',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'titles' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'An array of titles for the blog post'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'The full blog post content in Markdown format'
                        ],
                        'excerpt' => [
                            'type' => 'string',
                            'description' => 'A brief excerpt of the blog post'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Suggested category for the post'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'An array of tags for the blog post'
                        ],
                        'keywords' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'An array of SEO keywords related to the topic'
                        ],
                        'faqs' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'question' => ['type' => 'string'],
                                    'answer' => ['type' => 'string']
                                ]
                            ],
                            'description' => 'An array of FAQs related to the blog post'
                        ],
                        'custom_cta' => [
                            'type' => 'string',
                            'description' => 'A custom call-to-action (CTA) text for a free resource or email course related to {$site_niche}'
                        ]
                    ],
                    'required' => ['titles', 'content', 'excerpt', 'category', 'tags', 'keywords', 'faqs', 'custom_cta']
                ]
            ]
        ],
        'function_call' => ['name' => 'generate_blog_post'],
        'temperature' => 0.7
    );

    aiseo_log("Sending request to OpenAI for " . (is_array($keyword) ? "reprocessing" : "keyword: " . $keyword));

    $args = array(
        'body'        => json_encode($data),
        'headers'     => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'timeout'     => 120  // Increase timeout to 120 seconds
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        aiseo_log("Error sending request to OpenAI API: " . $error_message);
        throw new Exception('Error sending request to OpenAI API: ' . $error_message);
    }

    $body = wp_remote_retrieve_body($response);
    aiseo_log("Raw OpenAI response: " . substr($body, 0, 1000) . "...");

    $result = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "Error decoding JSON from OpenAI response: " . json_last_error_msg();
        aiseo_log($error_message);
        throw new Exception($error_message);
    }

    if (!isset($result['choices'][0]['message']['function_call']['arguments'])) {
        $error_message = "Invalid response format from OpenAI";
        aiseo_log($error_message . ". Response: " . print_r($result, true));
        throw new Exception($error_message);
    }

    $content = json_decode($result['choices'][0]['message']['function_call']['arguments'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_message = "Error decoding JSON from OpenAI content: " . json_last_error_msg();
        aiseo_log($error_message);
        throw new Exception($error_message);
    }

    aiseo_log("Decoded OpenAI content: " . print_r($content, true));

    return json_encode($content);
}