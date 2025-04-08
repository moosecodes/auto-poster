<?php
/**
 * Admin page functionality
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Admin {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Add menu pages
        add_action('admin_menu', array($this, 'add_menu_pages'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            'Auto Poster', 
            'Auto Poster', 
            'manage_options', 
            'dap-auto-poster', 
            array($this, 'render_main_page'), 
            'dashicons-schedule', 
            80
        );
        
        add_submenu_page(
            'dap-auto-poster',
            'Affiliate Links',
            'Affiliate Links',
            'manage_options',
            'dap-affiliate-links',
            array($this, 'render_affiliate_links_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register all settings under one group
        register_setting('dap_openai_settings', 'dap_openai_api_key');
        register_setting('dap_openai_settings', 'dap_topic');
        register_setting('dap_openai_settings', 'dap_image_style_prompt');
        register_setting('dap_openai_settings', 'dap_publish_delay_hours');
        register_setting('dap_openai_settings', 'dap_cron_frequency');
    }
    
    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_dap-auto-poster' !== $hook && 'auto-poster_page_dap-affiliate-links' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'dap-admin-js',
            DAP_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            DAP_VERSION,
            true
        );
        
        wp_localize_script('dap-admin-js', 'dapAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dap-admin-nonce')
        ));
    }
    
    /**
     * Render main admin page
     */
    public function render_main_page() {
        include DAP_PLUGIN_DIR . 'admin/partials/main-page.php';
    }
    
    /**
     * Render affiliate links page
     */
    public function render_affiliate_links_page() {
        include DAP_PLUGIN_DIR . 'admin/partials/affiliate-links-page.php';
    }
}