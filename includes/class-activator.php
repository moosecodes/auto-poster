<?php
/**
 * Plugin activation functionality
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Activator {
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Schedule event if not already scheduled
        if (!wp_next_scheduled('dap_generate_ai_post')) {
            wp_schedule_event(time(), 'twicedaily', 'dap_generate_ai_post');
        }
        
        // Initialize affiliate links if they don't exist
        if (!get_option('dap_affiliate_links')) {
            add_option('dap_affiliate_links', array(
                'skincare' => array(
                    'CeraVe Hydrating Cleanser' => 'https://amzn.to/your-cerave-link',
                    'The Ordinary Niacinamide 10%' => 'https://amzn.to/your-niacinamide-link',
                    'La Roche-Posay Sunscreen' => 'https://amzn.to/your-lrp-link',
                )
            ));
        }
    }
}
