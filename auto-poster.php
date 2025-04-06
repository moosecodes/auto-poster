<?php
/*
Plugin Name: Daily Auto Poster
Description: Posts 3 blog drafts per day automatically.
Version: 1.0
*/

// Activate this block to auto publish on plugin activation
// register_activation_hook(__FILE__, 'dap_activate');
// function dap_activate()
// {
//     if (!wp_next_scheduled('dap_daily_event')) {
//         wp_schedule_event(time(), 'daily', 'dap_daily_event');
//     }
// }

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

?>
    <div class="wrap">
        <h1>Auto Poster Dashboard</h1>
        <p>Select how many drafts to publish:</p>
        <input type="number" id="dap_post_count" min="1" max="20" value="3" style="width: 60px;">
        <button class="button button-primary" id="dap_publish_btn">Publish Now</button>
        <div id="dap_publish_result" style="margin-top:15px;"></div>
    </div>

    <script>
        document.getElementById('dap_publish_btn').addEventListener('click', function() {
            const count = document.getElementById('dap_post_count').value;

            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_publish_now&count=${count}`
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('dap_publish_result').innerHTML =
                        `<strong>${data.published} post(s) published.</strong>`;
                });
        });
    </script>
<?php
}

add_action('wp_ajax_dap_publish_now', 'dap_ajax_publish_now');

function dap_ajax_publish_now()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $count = isset($_POST['count']) ? intval($_POST['count']) : 3;
    $published = dap_post_n_drafts($count);

    wp_send_json(['published' => $published]);
}

function dap_post_n_drafts($count = 3)
{
    $drafts = get_posts([
        'post_status' => 'draft',
        'posts_per_page' => $count,
        'orderby' => 'date',
        'order' => 'ASC',
    ]);

    foreach ($drafts as $draft) {
        wp_update_post([
            'ID' => $draft->ID,
            'post_status' => 'publish',
        ]);
    }

    return count($drafts);
}
