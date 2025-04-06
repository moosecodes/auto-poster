<?php
/*
Plugin Name: Daily Auto Poster
Description: Posts drafts daily using AI with admin control and stats.
Version: 2.0
*/

register_deactivation_hook(__FILE__, 'dap_deactivate');
function dap_deactivate()
{
    wp_clear_scheduled_hook('dap_daily_event');
    wp_clear_scheduled_hook('dap_generate_ai_post');
}

register_activation_hook(__FILE__, 'dap_activate_ai');
function dap_activate_ai()
{
    if (!wp_next_scheduled('dap_generate_ai_post')) {
        wp_schedule_event(time(), 'hourly', 'dap_generate_ai_post');
    }
}

add_action('dap_generate_ai_post', 'dap_create_ai_post');

function dap_create_ai_post()
{
    $api_key = get_option('dap_openai_api_key');
    if (!$api_key) return;

    $topic_prompt = "Give me one trending skincare topic that young women are currently interested in. Just return the topic, nothing else.";
    $post_prompt = function ($topic) {
        return "Write a 1000-word, SEO-optimized blog post about \"\$topic\" for a skincare blog. Make it informative, friendly, and engaging.";
    };

    $topic = dap_openai_prompt($api_key, $topic_prompt);
    if (!$topic) return;

    $content = dap_openai_prompt($api_key, $post_prompt($topic));
    if (!$content) return;

    $post_id = wp_insert_post([
        'post_title'   => ucfirst($topic),
        'post_content' => $content,
        'post_status'  => 'draft',
        'post_author'  => 1,
        'post_category' => [1],
    ]);

    return ucfirst($topic);
}

function dap_openai_prompt($api_key, $prompt)
{
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model'    => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.8,
        ]),
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['choices'][0]['message']['content'] ?? false;
}

add_action('admin_menu', 'dap_register_admin_page');
function dap_register_admin_page()
{
    add_menu_page(
        'Auto Poster',
        'Auto Poster',
        'manage_options',
        'dap-auto-poster',
        'dap_admin_page_html',
        'dashicons-schedule',
        80
    );
}

add_action('admin_init', 'dap_register_settings');
function dap_register_settings()
{
    register_setting('dap_openai_settings', 'dap_openai_api_key');
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
        <ul>
            <?php
            $drafts = get_posts([
                'post_status' => 'draft',
                'posts_per_page' => 3,
                'orderby' => 'date',
                'order' => 'ASC'
            ]);
            foreach ($drafts as $post) {
                echo "<li><strong>{$post->post_title}</strong></li>";
            }
            ?>
        </ul>

        <h2>10 Recent Published Posts</h2>
        <ul>
            <?php
            $recent = get_posts([
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            foreach ($recent as $post) {
                echo "<li>{$post->post_title} <em>(" . get_the_date('', $post) . ")</em></li>";
            }
            ?>
        </ul>

        <hr>

        <h2>AI Tools</h2>
        <p>Click below to generate a blog post using AI and save it as a draft:</p>
        <button class="button button-secondary" id="dap_ai_generate_btn">Generate AI Post</button>
        <span id="dap_ai_loader" style="display:none;">🧠 Generating...</span>
        <div id="dap_ai_result" style="margin-top:15px;"></div>

        <h3>OpenAI Settings</h3>
        <form method="post" action="options.php">
            <?php
            settings_fields('dap_openai_settings');
            do_settings_sections('dap_openai_settings');
            $current_key = get_option('dap_openai_api_key');
            ?>
            <input type="text" name="dap_openai_api_key" value="<?php echo esc_attr($current_key); ?>" style="width: 400px;" placeholder="Enter your OpenAI API key">
            <?php submit_button('Save API Key'); ?>
        </form>


        <?php
        $credits = dap_get_openai_credits();
        if ($credits) {
            echo "<p><strong>Remaining OpenAI Credits:</strong> \${$credits}</p>";
        } else {
            echo "<p style='color: red;'>Could not fetch usage info. Check your API key.</p>";
        }
        ?>
    </div>

    <script>
        const publishBtn = document.getElementById('dap_publish_btn');
        const undoBtn = document.getElementById('dap_undo_btn');
        const countInput = document.getElementById('dap_post_count');
        const loader = document.getElementById('dap_loader');
        const result = document.getElementById('dap_publish_result');

        publishBtn.addEventListener('click', () => {
            const count = countInput.value;
            loader.style.display = 'inline-block';
            result.innerHTML = '';
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_publish_now&count=${count}`
                })
                .then(res => res.json())
                .then(data => {
                    loader.style.display = 'none';
                    result.innerHTML = `<strong>${data.published} post(s) published.</strong>`;
                    location.reload();
                });
        });

        undoBtn.addEventListener('click', () => {
            loader.style.display = 'inline-block';
            result.innerHTML = '';
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_undo_publish`
                })
                .then(res => res.json())
                .then(data => {
                    loader.style.display = 'none';
                    result.innerHTML = `<strong>${data.undone} post(s) moved back to drafts.</strong>`;
                    location.reload();
                });
        });

        document.getElementById('dap_ai_generate_btn').addEventListener('click', () => {
            document.getElementById('dap_ai_loader').style.display = 'inline-block';
            document.getElementById('dap_ai_result').innerHTML = '';
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_generate_ai_post_now`
                })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('dap_ai_loader').style.display = 'none';
                    if (data.success) {
                        document.getElementById('dap_ai_result').innerHTML = `<strong>AI post created:</strong> "${data.title}"`;
                        location.reload();
                    } else {
                        document.getElementById('dap_ai_result').innerHTML = `<span style='color:red;'>Error: ${data.message}</span>`;
                    }
                });
        });
    </script>
<?php }

add_action('wp_ajax_dap_publish_now', 'dap_ajax_publish_now');
function dap_ajax_publish_now()
{
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
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
        wp_update_post(['ID' => $draft->ID, 'post_status' => 'publish']);
        $published_ids[] = $draft->ID;
    }

    dap_store_last_published($published_ids);
    return count($drafts);
}

function dap_store_last_published($ids)
{
    set_transient('dap_last_published_ids', $ids, 3600);
}

function dap_undo_last_publish()
{
    $ids = get_transient('dap_last_published_ids');
    if (!$ids || !is_array($ids)) return 0;

    foreach ($ids as $id) {
        wp_update_post(['ID' => $id, 'post_status' => 'draft']);
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

add_action('wp_ajax_dap_generate_ai_post_now', 'dap_ajax_generate_ai_post_now');
function dap_ajax_generate_ai_post_now()
{
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
    $title = dap_create_ai_post();
    if ($title) {
        wp_send_json_success(['title' => $title]);
    } else {
        wp_send_json_error(['message' => 'Failed to generate post.']);
    }
}

function dap_get_openai_credits()
{
    $key = get_option('dap_openai_api_key');
    if (!$key) return false;

    $response = wp_remote_get('https://api.openai.com/v1/dashboard/billing/credit_grants', [
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ]
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return isset($body['total_available']) ? round($body['total_available'], 2) : false;
}
