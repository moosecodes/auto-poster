<?php
/**
 * Plugin Name: Daily Auto Poster
 * Description: AI-powered auto-poster with bulk/manual publishing, metadata, and smart tagging.
 * Version: 3.3
 * Author: Your Name
 * Text Domain: daily-auto-poster
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('DAP_VERSION', '3.3');
define('DAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DAP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once DAP_PLUGIN_DIR . 'includes/class-activator.php';
require_once DAP_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once DAP_PLUGIN_DIR . 'includes/class-auto-poster.php';
require_once DAP_PLUGIN_DIR . 'includes/class-openai-api.php';
require_once DAP_PLUGIN_DIR . 'includes/class-affiliate-manager.php';
require_once DAP_PLUGIN_DIR . 'admin/class-admin.php';
require_once DAP_PLUGIN_DIR . 'admin/class-ajax-handler.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('DAP_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('DAP_Deactivator', 'deactivate'));

// Initialize the plugin
function run_daily_auto_poster() {
    // Initialize main classes
    $plugin_admin = new DAP_Admin();
    $auto_poster = new DAP_Auto_Poster();
    $ajax_handler = new DAP_Ajax_Handler();
    
    // Set up OpenAI API instance for other classes to use
    $openai_api = new DAP_OpenAI_API();
    $auto_poster->set_openai_api($openai_api);
    
    // Set up affiliate manager
    $affiliate_manager = new DAP_Affiliate_Manager();
    $auto_poster->set_affiliate_manager($affiliate_manager);
    
    // Hook into WordPress
    add_action('dap_generate_ai_post', array($auto_poster, 'create_ai_post'));
}

run_daily_auto_poster();

// Custom schedule interval: hourly
add_filter('cron_schedules', function($schedules) {
    $schedules['hourly'] = array(
        'interval' => 3600,
        'display'  => __('Once Hourly')
    );
    return $schedules;
});

// Cron hook: generate and schedule post
add_action('dap_generate_ai_post', function () {
    $poster = new DAP_Auto_Poster();
    $poster->set_openai_api(new DAP_OpenAI_API());
    $poster->set_affiliate_manager(new DAP_Affiliate_Manager());
    $poster->create_ai_post();
});
