<?php
/**
 * Main admin page template
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>
<div class="wrap">
    <h1>Daily Auto Poster</h1>

    <h2>Generate AI Post</h2>
    <button class="button button-primary" id="dap-generate-post">Generate AI Post</button>
    <span id="dap_ai_status" style="margin-left:10px;"></span>

    <h2>Bulk Publish Drafts</h2>
    <input type="number" id="dap_post_count" min="1" max="10" value="3" style="width:60px;">
    <button class="button" id="dap-bulk-publish">Publish Drafts</button>
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
                <button class='button dap-publish-single' data-id='{$post->ID}'>Publish</button>
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
                <button class='button dap-unpublish-single' data-id='{$post->ID}'>Unpublish</button>
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
