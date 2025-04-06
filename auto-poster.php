<?php
/*
Plugin Name: Daily Auto Poster
Description: Posts 3 blog drafts per day automatically.
Version: 1.0
*/

register_activation_hook(__FILE__, 'dap_activate');
function dap_activate()
{
    if (!wp_next_scheduled('dap_daily_event')) {
        wp_schedule_event(time(), 'daily', 'dap_daily_event');
    }
}

register_deactivation_hook(__FILE__, 'dap_deactivate');
function dap_deactivate()
{
    wp_clear_scheduled_hook('dap_daily_event');
}

add_action('dap_daily_event', 'dap_post_three_drafts');
function dap_post_three_drafts()
{
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

add_action('admin_menu', 'dap_register_admin_page');

function dap_register_admin_page()
{
    add_menu_page(
        'Auto Poster',            // Page title
        'Auto Poster',            // Menu title
        'manage_options',         // Capability
        'dap-auto-poster',        // Menu slug
        'dap_admin_page_html',    // Callback function
        'dashicons-schedule',     // Icon
        80                        // Position
    );
}

function dap_admin_page_html()
{
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['dap_publish_now'])) {
        dap_post_three_drafts(); // manually run post function
        echo '<div class="updated"><p><strong>3 Drafts Published!</strong></p></div>';
    }

?>
    <div class="wrap">
        <h1>Auto Poster Dashboard</h1>
        <form method="post">
            <p>Click the button below to publish 3 draft posts immediately.</p>
            <input type="submit" name="dap_publish_now" class="button button-primary" value="Publish Now">
        </form>
    </div>
<?php
}
