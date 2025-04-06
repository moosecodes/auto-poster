<?php
/**
 * Plugin deactivation functionality
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Deactivator {
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('dap_generate_ai_post');
    }
}
