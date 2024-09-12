<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
use Parsedown;

function aiseo_generate_posts($keywords, $post_length, $context, $tone_style) {
    aiseo_log("Enqueueing keywords: " . implode(', ', $keywords));
    aiseo_enqueue_keywords($keywords, $post_length, $context, $tone_style);
    
    // Set a transient with the success message
    set_transient('aiseo_success_message', count($keywords) . " keywords have been added to the queue for processing.", 60);
}

function aiseo_process_content($content) {
    // Convert Markdown to HTML
    $parsedown = new Parsedown();
    $html = $parsedown->text($content);

    // Ensure no H1 tags are present (convert to H2 if found)
    $html = preg_replace('/<h1>(.*?)<\/h1>/i', '<h2>$1</h2>', $html);

    return $html;
}

function aiseo_openai_request($api_key, $keyword, $post_length, $context, $tone_style) {
    $url = 'https://api.openai.com/v1/chat/completions';

    $prompt = "Generate a detailed blog post about '{$keyword}'. The post should be no less than {$post_length} words long. {$context} {$tone_style}

    Please provide the following in your response:
    1. An array of 3-5 SEO-friendly titles
    2. The main content of the blog post in Markdown format
    3. A meta description of about 155 characters
    4. A suggested category for the post
    5. An array of 5-7 relevant tags
    6. An array of 3-5 frequently asked questions (FAQs) related to the topic, each with a 'question' and 'answer' field

    Format your response as a JSON object with the following keys: titles, content, excerpt, category, tags, faqs";

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
                        ]
                    ],
                    'required' => ['titles', 'content', 'excerpt', 'category', 'tags']
                ]
            ]
        ],
        'function_call' => ['name' => 'generate_blog_post'],
        'temperature' => 0.7
    );

    aiseo_log("Sending request to OpenAI for keyword: " . $keyword);

    $args = array(
        'body'        => json_encode($data),
        'headers'     => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'timeout'     => 60
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