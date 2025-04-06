<?php
/**
 * Manage affiliate links
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Affiliate_Manager {
    /**
     * Get an affiliate link for a product
     *
     * @param string $product_name Product name to find link for
     * @param string $category_slug Optional category slug for targeted links
     * @return string Affiliate link or default Amazon link
     */
    public function get_affiliate_link($product_name, $category_slug = null) {
        $all_links = get_option('dap_affiliate_links', []);
        
        // Check specific category group
        if ($category_slug && isset($all_links[$category_slug])) {
            $links = $all_links[$category_slug];
            if (isset($links[$product_name])) {
                return $links[$product_name];
            }
        }
        
        // Fallback: search all groups
        foreach ($all_links as $group) {
            if (is_array($group) && isset($group[$product_name])) {
                return $group[$product_name];
            }
        }
        
        return 'https://amazon.com';
    }
    
    /**
     * Save affiliate links
     *
     * @param array $links Array of affiliate links to save
     * @return bool Success status
     */
    public function save_affiliate_links($links) {
        return update_option('dap_affiliate_links', $links);
    }
    
    /**
     * Get all affiliate links
     *
     * @return array All affiliate links
     */
    public function get_all_affiliate_links() {
        return get_option('dap_affiliate_links', []);
    }
    
    /**
     * Add or update an affiliate link
     *
     * @param string $category Category/group name
     * @param string $product_name Product name
     * @param string $link Affiliate link
     * @return bool Success status
     */
    public function update_affiliate_link($category, $product_name, $link) {
        $all_links = $this->get_all_affiliate_links();
        
        if (!isset($all_links[$category])) {
            $all_links[$category] = [];
        }
        
        $all_links[$category][$product_name] = $link;
        
        return $this->save_affiliate_links($all_links);
    }
    
    /**
     * Delete an affiliate link
     *
     * @param string $category Category/group name
     * @param string $product_name Product name
     * @return bool Success status
     */
    public function delete_affiliate_link($category, $product_name) {
        $all_links = $this->get_all_affiliate_links();
        
        if (isset($all_links[$category]) && isset($all_links[$category][$product_name])) {
            unset($all_links[$category][$product_name]);
            
            // Remove category if empty
            if (empty($all_links[$category])) {
                unset($all_links[$category]);
            }
            
            return $this->save_affiliate_links($all_links);
        }
        
        return false;
    }
}
