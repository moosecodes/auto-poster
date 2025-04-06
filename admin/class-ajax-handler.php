<?php
/**
 * Handle AJAX requests
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Ajax_Handler {
    /**
     * @var DAP_Auto_Poster Auto poster instance
     */
    private $auto_poster;
    
    /**
     * @var DAP_Affiliate_Manager Affiliate manager instance
     */
    private $affiliate_manager;
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_dap_generate_ai_post_now', array($this, 'generate_ai_post'));
        add_action('wp_ajax_dap_publish_now', array($this, 'publish_posts'));
        add_action('wp_ajax_dap_publish_single', array($this, 'publish_single_post'));
        add_action('wp_ajax_dap_unpublish_single', array($this, 'unpublish_single_post'));
        add_action('wp_ajax_dap_save_affiliate_links', array($this, 'save_affiliate_links'));
        
        // Initialize needed instances
        $this->auto_poster = new DAP_Auto_Poster();
        $this->auto_poster->set_openai_api(new DAP_OpenAI_API());
        $this->affiliate_manager = new DAP_Affiliate_Manager();
        $this->auto_poster->set_affiliate_manager($this->affiliate_manager);
    }
    
    /**
     * Generate an AI post via AJAX
     */
    public function generate_ai_post() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $topic = $this->auto_poster->create_ai_post();
        
        if ($topic) {
            wp_send_json_success(array('topic' => $topic));
        } else {
            wp_send_json_error(array('message' => 'Failed to create post. Check API key.'));
        }
    }
    
    /**
     * Publish multiple posts via AJAX
     */
    public function publish_posts() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $count = isset($_POST['count']) ? intval($_POST['count']) : 1;
        $count = min($count, 10); // Limit to 10 posts max
        
        $published = 0;
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
            $published++;
        }
        
        wp_send_json_success(array('published' => $published));
    }
    
    /**
     * Publish a single post via AJAX
     */
    public function publish_single_post() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if ($post_id > 0) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ));
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
    }
    
    /**
     * Unpublish a single post via AJAX
     */
    public function unpublish_single_post() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if ($post_id > 0) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'draft'
            ));
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Invalid post ID'));
        }
    }
    
    /**
     * Save affiliate links via AJAX
     */
    public function save_affiliate_links() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
            return;
        }
        
        $links = isset($_POST['links']) ? $_POST['links'] : array();
        
        if (is_array($links)) {
            $this->affiliate_manager->save_affiliate_links($links);
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Invalid data format'));
        }
    }
}
