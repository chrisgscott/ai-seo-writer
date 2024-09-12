<?php
// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

function aiseo_progress_page() {
    ?>
    <div class="wrap">
        <h1>AI SEO Writer Progress</h1>
        <?php
        // Display success message if set
        $success_message = get_transient('aiseo_success_message');
        if ($success_message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
            delete_transient('aiseo_success_message');
        }
        ?>
        <div id="aiseo-progress-container">
            <h2>Current Status: <span id="current-status">Loading...</span></h2>
            <p>Next scheduled processing: <span id="next-scheduled">Loading...</span></p>
            
            <h3>Queue</h3>
            <ul id="queue-list">
                <li>Loading queue...</li>
            </ul>

            <h3>Processed Keywords</h3>
            <ul id="processed-list">
                <li>Loading processed keywords...</li>
            </ul>

            <h3>Current Process</h3>
            <pre id="current-process">Loading current process...</pre>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function updateProgress() {
            $.ajax({
                url: ajaxurl,
                data: {
                    action: 'aiseo_get_progress'
                },
                success: function(response) {
                    $('#current-status').text(response.current_status);
                    $('#next-scheduled').text(response.next_scheduled);
                    
                    // Update queue
                    var queueHtml = '';
                    $.each(response.queue, function(index, item) {
                        queueHtml += '<li>' + item.keyword + '</li>';
                    });
                    $('#queue-list').html(queueHtml || '<li>Queue is empty</li>');

                    // Update processed keywords
                    var processedHtml = '';
                    $.each(response.processed, function(keyword, postId) {
                        processedHtml += '<li>' + keyword + ' - <a href="post.php?post=' + postId + '&action=edit">Edit Post</a></li>';
                    });
                    $('#processed-list').html(processedHtml || '<li>No keywords have been processed yet</li>');

                    // Update current process
                    $('#current-process').text(response.current_process);

                    // Schedule next update
                    setTimeout(updateProgress, 5000); // Update every 5 seconds
                }
            });
        }

        // Start the update process
        updateProgress();
    });
    </script>
    <?php
}

// AJAX handler for progress updates
add_action('wp_ajax_aiseo_get_progress', 'aiseo_ajax_get_progress');
function aiseo_ajax_get_progress() {
    $queue = get_option('aiseo_keyword_queue', array());
    $processed = get_option('aiseo_processed_keywords', array());
    $current_status = get_option('aiseo_current_status', 'Idle');
    $next_scheduled = wp_next_scheduled('aiseo_process_queue');
    $current_process = get_option('aiseo_current_process', 'No active process');

    wp_send_json(array(
        'current_status' => $current_status,
        'next_scheduled' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled',
        'queue' => $queue,
        'processed' => $processed,
        'current_process' => $current_process
    ));
}