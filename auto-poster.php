<?php
/*
Plugin Name: Daily Auto Poster
Description: AI-powered auto-poster with bulk/manual publishing, metadata, and smart tagging.
Version: 3.3
*/

// Activation and deactivation
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('dap_generate_ai_post')) {
        wp_schedule_event(time(), 'hourly', 'dap_generate_ai_post');
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('dap_generate_ai_post');
});

add_action('dap_generate_ai_post', 'dap_create_ai_post');

function dap_create_ai_post()
{
    $api_key = get_option('dap_openai_api_key');
    if (!$api_key) return;

    $topic_prompt = "Give me one trending skincare topic that young women are currently interested in. Just return the topic.";
    $sources_prompt = fn($topic) => "List 3 URLs where the topic '$topic' is being discussed. One per line.";
    $content_prompt = fn($topic) => "Write a 1000-word, SEO-optimized skincare blog post about \"{$topic}\".";
    $tags_prompt = fn($topic) => "Give 5 comma-separated tags for a skincare post about \"{$topic}\".";

    $topic = dap_openai_prompt($api_key, $topic_prompt);
    if (!$topic) return;

    $content = dap_openai_prompt($api_key, $content_prompt($topic));
    $sources = dap_openai_prompt($api_key, $sources_prompt($topic));
    $tags_raw = dap_openai_prompt($api_key, $tags_prompt($topic));

    if (!$content) return;

    $post_id = wp_insert_post([
        'post_title'   => ucfirst(trim($topic)),
        'post_content' => $content,
        'post_status'  => 'draft',
        'post_author'  => 1,
    ]);

    update_post_meta($post_id, '_dap_ai_generated', 1);
    update_post_meta($post_id, '_dap_ai_topic', trim($topic));
    update_post_meta($post_id, '_dap_ai_sources', trim($sources));

    if ($tags_raw) {
        $tags = array_map('trim', explode(',', $tags_raw));
        wp_set_post_tags($post_id, $tags, false);
    }

    return ucfirst($topic);
}

function dap_openai_prompt($api_key, $prompt)
{
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
        ]),
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['choices'][0]['message']['content'] ?? false;
}

add_action('admin_menu', function () {
    add_menu_page('Auto Poster', 'Auto Poster', 'manage_options', 'dap-auto-poster', 'dap_admin_page', 'dashicons-schedule', 80);
});

add_action('admin_init', function () {
    register_setting('dap_openai_settings', 'dap_openai_api_key');
});

function dap_admin_page()
{
?>
    <div class="wrap">
        <h1>Daily Auto Poster</h1>

        <h2>Generate AI Post</h2>
        <button class="button button-primary" onclick="dapGenerateAiPost()">Generate AI Post</button>
        <span id="dap_ai_status" style="margin-left:10px;"></span>

        <h2>Bulk Publish Drafts</h2>
        <input type="number" id="dap_post_count" min="1" max="10" value="3" style="width:60px;">
        <button class="button" onclick="dapBulkPublish()">Publish Drafts</button>
        <span id="dap_bulk_status" style="margin-left:10px;"></span>

        <h2>Drafts</h2>
        <ul>
            <?php
            $drafts = get_posts([
                'post_status' => 'draft',
                'meta_key' => '_dap_ai_generated',
                'meta_value' => 1,
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'ASC'
            ]);
            foreach ($drafts as $post) {
                $topic = get_post_meta($post->ID, '_dap_ai_topic', true);
                echo "<li>
                    <button class='button' onclick='dapPublishSingle({$post->ID})'>Publish</button>
                    <strong>[{$post->ID}] {$post->post_title}</strong>";
                if ($topic) echo " <em>– {$topic}</em>";
                echo "</li>";
            }
            ?>
        </ul>

        <h2>Published Posts</h2>
        <ul>
            <?php
            $published = get_posts([
                'post_status' => 'publish',
                'meta_key' => '_dap_ai_generated',
                'meta_value' => 1,
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            foreach ($published as $post) {
                $topic = get_post_meta($post->ID, '_dap_ai_topic', true);
                $sources = get_post_meta($post->ID, '_dap_ai_sources', true);
                $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);

                echo "<li>
                    <button class='button' onclick='dapUnpublishSingle({$post->ID})'>Unpublish</button>
                    <strong>[{$post->ID}]</strong> <a href='" . get_permalink($post->ID) . "' target='_blank'>{$post->post_title}</a>";
                if ($topic) echo " <em>– {$topic}</em>";
                if ($tags) echo "<br><strong>Tags:</strong> " . implode(', ', $tags);
                if ($sources) {
                    echo "<br><strong>Sources:</strong><ul>";
                    foreach (explode("\n", $sources) as $src) {
                        $src = trim($src);
                        if ($src) echo "<li><a href='{$src}' target='_blank'>{$src}</a></li>";
                    }
                    echo "</ul>";
                }
                echo "</li><br>";
            }
            ?>
        </ul>

        <hr>
        <h3>OpenAI Settings</h3>
        <form method="post" action="options.php">
            <?php
            settings_fields('dap_openai_settings');
            do_settings_sections('dap_openai_settings');
            $key = get_option('dap_openai_api_key');
            ?>
            <input type="password" id="dap_api_key" name="dap_openai_api_key" value="<?php echo esc_attr($key); ?>" style="width: 400px;" placeholder="Enter your OpenAI API key">
            <label><input type="checkbox" id="dap_toggle_key"> Show Key</label>
            <?php submit_button('Save API Key'); ?>
        </form>
    </div>

    <script>
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";

        const toggleKey = document.getElementById('dap_toggle_key');
        const keyInput = document.getElementById('dap_api_key');

        if (toggleKey) {
            toggleKey.addEventListener('change', () => {
                keyInput.type = toggleKey.checked ? 'text' : 'password';
            });
        }

        function dapGenerateAiPost() {
            document.getElementById('dap_ai_status').innerText = '⏳ Generating...';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=dap_generate_ai_post_now'
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    document.getElementById('dap_ai_status').innerText = '✅ Post created!';
                    location.reload();
                } else {
                    document.getElementById('dap_ai_status').innerText = '❌ Failed: ' + data.data.message;
                }
            });
        }

        function dapBulkPublish() {
            const count = document.getElementById('dap_post_count').value;
            document.getElementById('dap_bulk_status').innerText = '⏳ Publishing...';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=dap_publish_now&count=${count}`
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    document.getElementById('dap_bulk_status').innerText = `✅ Published ${data.data.published} draft(s)`;
                    location.reload();
                } else {
                    document.getElementById('dap_bulk_status').innerText = '❌ Failed to publish drafts';
                }
            });
        }

        function dapPublishSingle(postId) {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=dap_publish_single_post&post_id=${postId}`
            }).then(res => res.json()).then(data => {
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }

        function dapUnpublishSingle(postId) {
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=dap_unpublish_single_post&post_id=${postId}`
            }).then(res => res.json()).then(data => {
                if (data.success) location.reload();
                else alert('Error: ' + data.message);
            });
        }

        function dapUnpublishSingle(postId) {
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=dap_unpublish_single_post&post_id=${postId}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert('Error: ' + data.message);
                });
        }
    </script>
<?php }

add_action('wp_ajax_dap_generate_ai_post_now', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
    $title = dap_create_ai_post();
    $title ? wp_send_json_success(['title' => $title]) : wp_send_json_error(['message' => 'AI post generation failed']);
});

add_action('wp_ajax_dap_publish_now', function () {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $count = isset($_POST['count']) ? intval($_POST['count']) : 3;
    $published = dap_post_n_drafts($count);
    wp_send_json_success(['published' => $published]);
});

function dap_post_n_drafts($count = 3)
{
    $drafts = get_posts([
        'post_status' => 'draft',
        'posts_per_page' => $count,
        'orderby' => 'date',
        'order' => 'ASC',
    ]);

    foreach ($drafts as $draft) {
        wp_update_post(['ID' => $draft->ID, 'post_status' => 'publish']);
    }

    return count($drafts);
}

add_action('wp_ajax_dap_publish_single_post', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error(['message' => 'Invalid post ID']);
    wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
    wp_send_json_success(['id' => $post_id]);
});

add_action('wp_ajax_dap_unpublish_single_post', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error(['message' => 'Invalid post ID']);
    wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
    wp_send_json_success(['id' => $post_id]);
});
