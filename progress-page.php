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
        <button id="aiseo-clear-queue" class="button button-secondary">Clear Queue and Processed Keywords</button>
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
        $('#aiseo-clear-queue').click(function() {
            if (confirm('Are you sure you want to clear the queue and processed keywords? This action cannot be undone.')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'aiseo_clear_queue_and_keywords',
                        nonce: '<?php echo wp_create_nonce('aiseo-ajax-nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Queue and processed keywords cleared successfully.');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        });

        function updateProgress() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'aiseo_get_progress',
                    nonce: aiseoData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#current-status').text(response.data.status);
                        $('#next-scheduled').text(response.data.next_scheduled);
                        $('#queue-list').html(response.data.queue);
                        $('#processed-list').html(response.data.processed);
                        $('#current-process').text(response.data.current_process);
                    }
                    setTimeout(updateProgress, 5000); // Update every 5 seconds
                },
                error: function() {
                    console.log('Error updating progress');
                    setTimeout(updateProgress, 5000);
                }
            });
        }

        // Start the update process
        updateProgress();
    });
    </script>
    <?php
}