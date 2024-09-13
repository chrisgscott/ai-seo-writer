<?php
// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Check if the queue is already being processed
if (!get_transient('aiseo_queue_processing')) {
    // Set a transient to indicate that queue processing has started
    set_transient('aiseo_queue_processing', true, 15 * MINUTE_IN_SECONDS);

    aiseo_log("AISEO: Starting queue processing via cron.");
    
    $queue = get_option('aiseo_keyword_queue', array());
    if (!empty($queue)) {
        aiseo_process_queue();
    } else {
        aiseo_log("AISEO: Queue is empty. No processing needed.");
    }

    delete_transient('aiseo_queue_processing');
    aiseo_log("AISEO: Queue processing completed via cron.");
} else {
    aiseo_log("AISEO: Queue is already being processed. Skipping this cron execution.");
}