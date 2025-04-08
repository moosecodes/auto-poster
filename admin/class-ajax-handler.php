<?php
/**
 * Handle AJAX requests
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Ajax_Handler {

    public function __construct() {
        add_action('wp_ajax_dap_generate_ai_post_now', array($this, 'generate_ai_post'));
        add_action('wp_ajax_dap_publish_now', array($this, 'publish_posts'));
        add_action('wp_ajax_dap_publish_single', array($this, 'publish_single_post'));
        add_action('wp_ajax_dap_unpublish_single', array($this, 'unpublish_single_post'));
        add_action('wp_ajax_dap_save_affiliate_links', array($this, 'save_affiliate_links'));
        add_action('wp_ajax_dap_update_schedule', array($this, 'update_schedule'));
        add_action('wp_ajax_dap_save_topic', array($this, 'save_topic'));

        $this->auto_poster = new DAP_Auto_Poster();
        $this->auto_poster->set_openai_api(new DAP_OpenAI_API());
        $this->affiliate_manager = new DAP_Affiliate_Manager();
        $this->auto_poster->set_affiliate_manager($this->affiliate_manager);
    }

    public function generate_ai_post() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        $topic_input = sanitize_text_field($_POST['topic'] ?? '');
        $topic = $this->auto_poster->create_ai_post($topic_input);

        if ($topic) {
            wp_send_json_success(array('topic' => $topic));
        } else {
            wp_send_json_error(array('message' => 'Failed to create post. Check API key.'));
        }
    }

    public function publish_posts() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        $count = isset($_POST['count']) ? intval($_POST['count']) : 1;
        $count = min($count, 10);

        $draft_posts = get_posts(array(
            'post_status' => 'draft',
            'meta_key' => '_dap_ai_generated',
            'meta_value' => 1,
            'posts_per_page' => $count,
            'orderby' => 'date',
            'order' => 'ASC'
        ));

        foreach ($draft_posts as $post) {
            wp_update_post(array(
                'ID' => $post->ID,
                'post_status' => 'publish'
            ));
        }

        wp_send_json_success(array('published' => count($draft_posts)));
    }

    public function publish_single_post() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if ($post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ));
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
    }

    public function unpublish_single_post() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if ($post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
    }

    public function save_affiliate_links() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }

        $links = $_POST['links'] ?? array();
        if (is_array($links)) {
            update_option('dap_affiliate_links', array_map('sanitize_text_field', $links));
            wp_send_json_success(array('message' => 'Links saved'));
        } else {
            wp_send_json_error(array('message' => 'Invalid data format'));
        }
    }

    public function update_schedule() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $datetime = sanitize_text_field($_POST['datetime'] ?? '');

        if (!$post_id || !$datetime) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        $gmt = get_gmt_from_date($datetime);
        wp_update_post([
            'ID' => $post_id,
            'post_date' => $datetime,
            'post_date_gmt' => $gmt,
            'post_status' => 'future',
        ]);

        wp_send_json_success(['message' => 'Schedule updated']);
    }

    public function save_topic() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        update_option('dap_default_topic', $topic);
        wp_send_json_success(['message' => 'Topic saved']);
    }
}
