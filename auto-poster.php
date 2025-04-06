<?php
/*
Plugin Name: Daily Auto Poster
Description: AI-powered auto-poster with bulk/manual publishing, metadata, and smart tagging.
Version: 3.3
*/

// Activation and deactivation
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('dap_generate_ai_post')) {
        wp_schedule_event(time(), 'twicedaily', 'dap_generate_ai_post');
    }
    if (!get_option('dap_affiliate_links')) {
        add_option('dap_affiliate_links', [
            'CeraVe Hydrating Cleanser' => 'https://amzn.to/your-cerave-link',
            'The Ordinary Niacinamide 10%' => 'https://amzn.to/your-niacinamide-link',
            'La Roche-Posay Sunscreen' => 'https://amzn.to/your-lrp-link',
        ]);
    }
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('dap_generate_ai_post');
});

add_action('dap_generate_ai_post', 'dap_create_ai_post');

function dap_get_affiliate_link($product_name, $category_slug = null)
{
    $all_links = get_option('dap_affiliate_links', []);

    // Check specific category group
    if ($category_slug && isset($all_links[$category_slug])) {
        $links = $all_links[$category_slug];
        if (isset($links[$product_name])) return $links[$product_name];
    }

    // Fallback: search all groups
    foreach ($all_links as $group) {
        if (isset($group[$product_name])) return $group[$product_name];
    }

    return 'https://amazon.com';
}

function dap_create_ai_post()
{
    $api_key = get_option('dap_openai_api_key');
    if (!$api_key) return;

    $topic_prompt = "Give me one trending skincare topic that young women are currently interested in. Just return the topic.";
    $sources_prompt = fn($topic) => "List 3 URLs where the topic '$topic' is being discussed. One per line.";
    $content_prompt = fn($topic) => "Write a 1000-word, SEO-optimized skincare blog post about \"{$topic}\".";
    $tags_prompt = fn($topic) => "Give 5 comma-separated tags for a skincare post about \"{$topic}\".";
    $recs_prompt = fn($topic) => "Give 3 recommended skincare products for the topic \"{$topic}\". Format: Product Name – Reason.";

    $topic = trim(dap_openai_prompt($api_key, $topic_prompt), "\"' \n\r\t");
    if (!$topic) return;

    $content = dap_openai_prompt($api_key, $content_prompt($topic));
    $sources = dap_openai_prompt($api_key, $sources_prompt($topic));
    $tags_raw = dap_openai_prompt($api_key, $tags_prompt($topic));
    $recommendations = dap_openai_prompt($api_key, $recs_prompt($topic));

    if (!$content) return;

    // Insert initial post
    $post_id = wp_insert_post([
        'post_title'   => ucfirst($topic),
        'post_content' => '',
        'post_status'  => 'draft',
        'post_author'  => 1,
    ]);

    // Get category slug (if assigned)
    $cats = get_the_category($post_id);
    $primary_category_slug = $cats[0]->slug ?? null;

    // Build recommendations HTML
    $recs_html = '';
    if ($recommendations) {
        $recs_html = "<h3>🛍️ Recommended Picks</h3><ul>";
        foreach (explode("\n", trim($recommendations)) as $line) {
            if (strpos($line, '–') !== false) {
                [$product, $desc] = explode('–', $line, 2);
                $product = trim($product);
                $desc = trim($desc);
                $link = dap_get_affiliate_link($product, $primary_category_slug);
                $recs_html .= "<li><a href='{$link}' target='_blank'>{$product}</a> – {$desc}</li>";
            }
        }
        $recs_html .= "</ul>";
    }

    // Combine content and recommendations
    $final_content = $content . "\n\n" . $recs_html;

    // 🖼️ Generate featured image and attach
    $image_prompt = "A beautiful, high-resolution featured image for a skincare blog post titled: \"$topic\". Make it look elegant and relevant.";
    $image_url = dap_generate_dalle_image($api_key, $image_prompt);

    if ($image_url) {
        $attachment_id = dap_attach_image_to_post($image_url, $post_id);
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    // Update post content
    wp_update_post([
        'ID' => $post_id,
        'post_content' => $final_content,
    ]);

    // Set tags and meta
    if ($tags_raw) {
        $tags = array_map('trim', explode(',', $tags_raw));
        wp_set_post_tags($post_id, $tags, false);
    }

    update_post_meta($post_id, '_dap_ai_generated', 1);
    update_post_meta($post_id, '_dap_ai_topic', $topic);
    update_post_meta($post_id, '_dap_ai_sources', trim($sources));

    return ucfirst($topic);
}

function dap_generate_dalle_image($api_key, $prompt)
{
    $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => json_encode([
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
        ]),
    ]);

    if (is_wp_error($response)) return false;

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['data'][0]['url'] ?? false;
}

function dap_attach_image_to_post($image_url, $post_id)
{
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) return false;

    $filename = basename(parse_url($image_url, PHP_URL_PATH));
    $file_array = [
        'name'     => $filename,
        'tmp_name' => $tmp,
    ];

    $id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($id)) return false;

    return $id;
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
    add_submenu_page(
        'dap-auto-poster',
        'Affiliate Links',
        'Affiliate Links',
        'manage_options',
        'dap-affiliate-links',
        'dap_affiliate_links_page'
    );
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
            const status = document.getElementById('dap_ai_status');

            let steps = [
                '🧠 Generating topic...',
                '✍️ Writing article...',
                '🔎 Finding sources...',
                '🏷️ Creating tags...',
                '🛍️ Building product list...',
                '🎨 Generating featured image...',
                '📎 Attaching image...',
                '✅ Finalizing post...'
            ];

            let stepIndex = 0;
            const stepInterval = setInterval(() => {
                if (stepIndex < steps.length) {
                    status.innerText = steps[stepIndex];
                    stepIndex++;
                }
            }, 3000); // ~3000ms per step

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=dap_generate_ai_post_now'
            }).then(res => res.json()).then(data => {
                clearInterval(stepInterval);
                if (data.success) {
                    status.innerText = '✅ Post created!';
                    location.reload();
                } else {
                    status.innerText = '❌ Failed: ' + data.data.message;
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

function dap_affiliate_links_page()
{
    if (!current_user_can('manage_options')) return;

    $links = get_option('dap_affiliate_links', []);

    // Save form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dap_links_nonce']) && wp_verify_nonce($_POST['dap_links_nonce'], 'save_dap_links')) {
        $products = $_POST['product'] ?? [];
        $urls = $_POST['url'] ?? [];

        $new_links = [];
        foreach ($products as $i => $name) {
            $name = trim($name);
            $url = esc_url_raw(trim($urls[$i]));
            if ($name && $url) {
                $new_links[$name] = $url;
            }
        }

        update_option('dap_affiliate_links', $new_links);
        echo '<div class="updated"><p>Affiliate links saved.</p></div>';
        $links = $new_links;
    }
?>

    <div class="wrap">
        <h1>Manage Affiliate Links</h1>
        <form method="post">
            <?php wp_nonce_field('save_dap_links', 'dap_links_nonce'); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Affiliate URL</th>
                    </tr>
                </thead>
                <tbody id="dap-links-body">
                    <?php foreach ($links as $name => $url): ?>
                        <tr>
                            <td><input type="text" name="product[]" value="<?php echo esc_attr($name); ?>" style="width: 100%;"></td>
                            <td><input type="url" name="url[]" value="<?php echo esc_url($url); ?>" style="width: 100%;"></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><input type="text" name="product[]" value="" style="width: 100%;"></td>
                        <td><input type="url" name="url[]" value="" style="width: 100%;"></td>
                    </tr>
                </tbody>
            </table>
            <p><button type="submit" class="button button-primary">Save Links</button></p>
        </form>
    </div>
<?php
}
