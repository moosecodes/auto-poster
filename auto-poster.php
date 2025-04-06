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
        <button class="button" id="dap_undo_btn" style="margin-left:10px;">Undo</button>
        <span id="dap_loader" style="display:none;">⏳ Publishing...</span>
        <div id="dap_publish_result" style="margin-top:15px;"></div>

        <hr>

        <h2>Next 3 Drafts</h2>
        <ul id="dap_upcoming_drafts"><?php
                                        $drafts = get_posts([
                                            'post_status' => 'draft',
                                            'posts_per_page' => 3,
                                            'orderby' => 'date',
                                            'order' => 'ASC'
                                        ]);
                                        foreach ($drafts as $post) {
                                            echo "<li><strong>{$post->post_title}</strong></li>";
                                        }
                                        ?></ul>

        <h2>10 Recent Published Posts</h2>
        <ul id="dap_recent_posts"><?php
                                    $recent = get_posts([
                                        'post_status' => 'publish',
                                        'posts_per_page' => 10,
                                        'orderby' => 'date',
                                        'order' => 'DESC'
                                    ]);
                                    foreach ($recent as $post) {
                                        echo "<li>{$post->post_title} <em>(" . get_the_date('', $post) . ")</em></li>";
                                    }
                                    ?></ul>
    </div>


    <script>
        const publishBtn = document.getElementById('dap_publish_btn');
        const undoBtn = document.getElementById('dap_undo_btn');
        const countInput = document.getElementById('dap_post_count');
        const loader = document.getElementById('dap_loader');
        const result = document.getElementById('dap_publish_result');

        function showLoader() {
            loader.style.display = 'inline-block';
            result.innerHTML = '';
        }

        function hideLoader() {
            loader.style.display = 'none';
        }

        publishBtn.addEventListener('click', () => {
            const count = countInput.value;
            showLoader();
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_publish_now&count=${count}`
                })
                .then(res => res.json())
                .then(data => {
                    hideLoader();
                    result.innerHTML = `<strong>${data.published} post(s) published.</strong>`;
                    location.reload(); // Refresh post previews
                });
        });

        undoBtn.addEventListener('click', () => {
            showLoader();
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_undo_publish`
                })
                .then(res => res.json())
                .then(data => {
                    hideLoader();
                    result.innerHTML = `<strong>${data.undone} post(s) moved back to drafts.</strong>`;
                    location.reload(); // Refresh post previews
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

    $published_ids = [];

    foreach ($drafts as $draft) {
        wp_update_post([
            'ID' => $draft->ID,
            'post_status' => 'publish',
        ]);
        $published_ids[] = $draft->ID;
    }

    dap_store_last_published($published_ids);

    return count($drafts);
}

function dap_store_last_published($ids)
{
    set_transient('dap_last_published_ids', $ids, 3600); // store for 1 hour
}

function dap_undo_last_publish()
{
    $ids = get_transient('dap_last_published_ids');
    if (!$ids || !is_array($ids)) return 0;

    foreach ($ids as $id) {
        wp_update_post([
            'ID' => $id,
            'post_status' => 'draft',
        ]);
    }

    delete_transient('dap_last_published_ids');
    return count($ids);
}

add_action('wp_ajax_dap_undo_publish', 'dap_ajax_undo_publish');
function dap_ajax_undo_publish()
{
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $undone = dap_undo_last_publish();
    wp_send_json(['undone' => $undone]);
}
