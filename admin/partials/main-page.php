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

  <h2>Scheduled Posts</h2>
  <style>
    .dap-post-row {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      gap: 12px;
    }

    .dap-post-thumb {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid #ccc;
    }

    .dap-post-info {
      flex-grow: 1;
    }
  </style>

  <ul>
    <?php
    $drafts = get_posts([
      'post_status' => ['draft', 'future'],
      'meta_key' => '_dap_ai_generated',
      'meta_value' => 1,
      'posts_per_page' => 10,
      'orderby' => 'date',
      'order' => 'ASC'
    ]);
    foreach ($drafts as $post) {
      $topic = get_post_meta($post->ID, '_dap_ai_topic', true);
      $thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail');
      $scheduled = get_post_field('post_date', $post->ID);
    ?>
      <li class="dap-post-row">
        <?php if ($thumb): ?>
          <img src="<?= esc_url($thumb) ?>" class="dap-post-thumb" alt="thumbnail" />
        <?php endif; ?>
        <div class="dap-post-info">
<?php
$datetime = date('Y-m-d\TH:i', strtotime($scheduled));
?>
<br>
<input type="datetime-local" class="dap-schedule-input" data-id="<?= $post->ID ?>" value="<?= esc_attr($datetime) ?>">
<button class="button dap-update-schedule" data-id="<?= $post->ID ?>">Update</button>
<span class="dap-schedule-status" id="status-<?= $post->ID ?>" style="margin-left: 10px;"></span>
          <strong>[<?= $post->ID ?>] <?= esc_html($post->post_title) ?></strong>
          <?php if ($topic): ?><br><em><?= esc_html($topic) ?></em><?php endif; ?>
          <?php $cats = get_the_category($post->ID);
          if (!empty($cats)): ?>
            <br><small>Category: <?= esc_html($cats[0]->name) ?></small>
          <?php endif; ?>
        </div>
        <button class="button dap-publish-single" data-id="<?= $post->ID ?>">Publish Now</button>
      </li>
    <?php } ?>
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
      $cats = get_the_category($post->ID);
      if (!empty($cats)) {
        echo "<br><strong>Category:</strong> " . esc_html($cats[0]->name);
      }
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

  <h3>Automation Status</h3>
<?php
$next = wp_next_scheduled('dap_daily_generate_post');
$last = get_option('_dap_last_generated_time', null);
?>
<p><strong>Last Generated:</strong> <?= $last ? date('M j, Y @ g:ia', $last) : 'Never' ?></p>
<p><strong>Next Scheduled:</strong> <?= $next ? date('M j, Y @ g:ia', $next) : 'Not scheduled' ?></p>

<h3>Default Topic</h3>
<textarea id="dap_topic" rows="2" style="width: 100%; max-width: 500px;"><?= esc_textarea(get_option('dap_default_topic', '')) ?></textarea>
<br>
<button class="button" id="dap-save-topic">Save Topic</button>
<span id="dap_topic_status" style="margin-left: 10px;"></span>

<hr>
  <h3>OpenAI Settings</h3>
  <form method="post" action="options.php">
    <?php
    settings_fields('dap_openai_settings');
    do_settings_sections('dap_openai_settings');
    $key = get_option('dap_openai_api_key');
    ?>
    <input type="password" id="dap_api_key" name="dap_openai_api_key" value=" echo esc_attr($key); " style="width: 400px;" placeholder="Enter your OpenAI API key">
    <label><input type="checkbox" id="dap_toggle_key"> Show Key</label>
    <?php submit_button('Save API Key'); ?>
  </form>
</div>
