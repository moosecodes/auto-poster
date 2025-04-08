<?php
/**
 * Plugin activation functionality
 */

class DAP_Activator {
    /**
     * Plugin activation
     */
    public static function activate() {
        // Set default options if they don't exist
        if (!get_option('dap_topic')) {
            update_option('dap_topic', 'skincare');
        }
        
        if (!get_option('dap_image_style_prompt')) {
            update_option('dap_image_style_prompt', 'Create a stunning, emotionally resonant image that represents the essence of [TOPIC]. It should be visually striking and spark curiosity instantly.');
        }
        
        if (!get_option('dap_publish_delay_hours')) {
            update_option('dap_publish_delay_hours', 24);
        }
        
        if (!get_option('dap_cron_frequency')) {
            update_option('dap_cron_frequency', 'hourly');
        }
        
        if (!get_option('dap_default_topic')) {
            update_option('dap_default_topic', 'skincare and beauty products');
        }
        
        // Schedule the cron job
        if (!wp_next_scheduled('dap_generate_ai_post')) {
            wp_schedule_event(time(), get_option('dap_cron_frequency', 'hourly'), 'dap_generate_ai_post');
        }
    }
}