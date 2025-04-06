<?php
/*
Plugin Name: Daily Auto Poster
Description: AI-powered auto-poster with manual controls, metadata, and smart tagging.
Version: 3.1
*/

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'dap_activate_ai');
function dap_activate_ai()
{
    if (!wp_next_scheduled('dap_generate_ai_post')) {
        wp_schedule_event(time(), 'hourly', 'dap_generate_ai_post');
    }
}

register_deactivation_hook(__FILE__, 'dap_deactivate');
function dap_deactivate()
{
    wp_clear_scheduled_hook('dap_generate_ai_post');
}

// Hook for generating post
add_action('dap_generate_ai_post', 'dap_create_ai_post');

function dap_create_ai_post()
{
    $api_key = get_option('dap_openai_api_key');
    if (!$api_key) return;

    $topic_prompt = "Give me one trending skincare topic that young women are currently interested in. Just return the topic, nothing else.";
    $sources_prompt = function ($topic) {
        return "List 3 sources where this topic ('{$topic}') is being discussed online. Respond in plain text with one URL per line.";
    };
    $post_prompt = function ($topic) {
        return "Write a 1000-word, SEO-optimized blog post about \"{$topic}\" for a skincare blog. Make it informative, friendly, and engaging.";
    };

    $tags_prompt = function ($topic) {
        return "Based on the topic \"{$topic}\", suggest 5 relevant tags or keywords for a skincare blog. Separate each tag with a comma.";
    };


    $topic = dap_openai_prompt($api_key, $topic_prompt);
    if (!$topic) return;

    $content = dap_openai_prompt($api_key, $post_prompt($topic));
    $sources = dap_openai_prompt($api_key, $sources_prompt($topic));
    $tags_raw = dap_openai_prompt($api_key, $tags_prompt($topic));

    if (!$content) return;

    $post_id = wp_insert_post([
        'post_title'   => ucfirst($topic),
        'post_content' => $content,
        'post_status'  => 'draft',
        'post_author'  => 1,
        'post_category' => [1],
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

// Admin page registration
add_action('admin_menu', 'dap_register_admin_page');
function dap_register_admin_page()
{
    add_menu_page('Auto Poster', 'Auto Poster', 'manage_options', 'dap-auto-poster', 'dap_admin_page_html', 'dashicons-schedule', 80);
}

add_action('admin_init', 'dap_register_settings');
function dap_register_settings()
{
    register_setting('dap_openai_settings', 'dap_openai_api_key');
}

// Admin dashboard output
function dap_admin_page_html()
{
    if (!current_user_can('manage_options')) return;
    $api_key = get_option('dap_openai_api_key');
?>
    <div class="wrap">
        <h1>Auto Poster Dashboard</h1>

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
                if ($topic) echo " – <em>{$topic}</em>";
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

                echo "<li><strong>[{$post->ID}]</strong> <a href='" . get_permalink($post->ID) . "' target='_blank'>{$post->post_title}</a>";
                if ($topic) echo " – <em>{$topic}</em>";
                if ($tags) echo "<br><strong>Tags:</strong> " . implode(', ', $tags);
                if ($sources) {
                    echo "<br><strong>Sources:</strong><ul>";
                    foreach (explode("\n", $sources) as $src) {
                        $src = trim($src);
                        if ($src) echo "<li><a href='{$src}' target='_blank'>{$src}</a></li>";
                    }
                    echo "</ul>";
                }
                echo " <button class='button' onclick='dapUnpublishSingle({$post->ID})'>Unpublish</button>";
                echo "</li><br>";
            }
            ?>
        </ul>

        <form method="post" action="options.php">
            <?php settings_fields('dap_openai_settings');
            do_settings_sections('dap_openai_settings'); ?>
            <input type="password" id="dap_api_key" name="dap_openai_api_key" value="<?php echo esc_attr($api_key); ?>" style="width: 400px;" placeholder="Enter OpenAI API key">
            <label><input type="checkbox" id="dap_toggle_key"> Show Key</label>
            <?php submit_button('Save API Key'); ?>
        </form>
    </div>

    <script>
        const toggleKey = document.getElementById('dap_toggle_key');
        const keyInput = document.getElementById('dap_api_key');
        if (toggleKey) {
            toggleKey.addEventListener('change', () => {
                keyInput.type = toggleKey.checked ? 'text' : 'password';
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
    </script>
<?php }

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
