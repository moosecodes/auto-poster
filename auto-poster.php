<?php
/*
Plugin Name: Daily Auto Poster
Description: Posts 3 blog drafts per day automatically.
Version: 1.0
*/

register_activation_hook(__FILE__, 'dap_activate');
function dap_activate() {
    if (!wp_next_scheduled('dap_daily_event')) {
        wp_schedule_event(time(), 'daily', 'dap_daily_event');
    }
}

register_deactivation_hook(__FILE__, 'dap_deactivate');
function dap_deactivate() {
    wp_clear_scheduled_hook('dap_daily_event');
}

add_action('dap_daily_event', 'dap_post_three_drafts');
function dap_post_three_drafts() {
    $drafts = get_posts([
        'post_status' => 'draft',
        'posts_per_page' => 3,
        'orderby' => 'date',
        'order' => 'ASC',
    ]);

    foreach ($drafts as $draft) {
        wp_update_post([
            'ID' => $draft->ID,
            'post_status' => 'publish',
        ]);
    }
}

