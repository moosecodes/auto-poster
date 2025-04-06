<?php
/**
 * Main class handling post generation functionality
 */

if (!defined('WPINC')) {
    die;
}

class DAP_Auto_Poster {
    /**
     * @var DAP_OpenAI_API OpenAI API instance
     */
    private $openai_api;
    
    /**
     * @var DAP_Affiliate_Manager Affiliate manager instance
     */
    private $affiliate_manager;
    
    /**
     * Set the OpenAI API instance
     *
     * @param DAP_OpenAI_API $openai_api
     */
    public function set_openai_api($openai_api) {
        $this->openai_api = $openai_api;
    }
    
    /**
     * Set the affiliate manager instance
     *
     * @param DAP_Affiliate_Manager $affiliate_manager
     */
    public function set_affiliate_manager($affiliate_manager) {
        $this->affiliate_manager = $affiliate_manager;
    }
    
    /**
     * Create an AI-generated post
     *
     * @return string|bool The post title if successful, false on failure
     */
    public function create_ai_post() {
        $api_key = get_option('dap_openai_api_key');
        if (!$api_key || !$this->openai_api) {
            return false;
        }
        
        $this->openai_api->set_api_key($api_key);
        
        // Define prompts
        $topic_prompt = "Give me one trending skincare topic that young women are currently interested in. Just return the topic.";
        $sources_prompt = fn($topic) => "List 3 URLs where the topic '$topic' is being discussed. One per line.";
        $content_prompt = fn($topic) => "Write a 1000-word, SEO-optimized skincare blog post about \"{$topic}\".";
        $tags_prompt = fn($topic) => "Give 5 comma-separated tags for a skincare post about \"{$topic}\".";
        $recs_prompt = fn($topic) => "Give 3 recommended skincare products for the topic \"{$topic}\". Format: Product Name – Reason.";
        
        // Generate topic
        $topic = trim($this->openai_api->prompt($topic_prompt), "\"' \n\r\t");
        if (!$topic) {
            return false;
        }
        
        // Generate content and metadata
        $content = $this->openai_api->prompt($content_prompt($topic));
        $sources = $this->openai_api->prompt($sources_prompt($topic));
        $tags_raw = $this->openai_api->prompt($tags_prompt($topic));
        $recommendations = $this->openai_api->prompt($recs_prompt($topic));
        
        if (!$content) {
            return false;
        }
        
        // Insert initial post
        $post_id = wp_insert_post([
            'post_title'   => ucfirst($topic),
            'post_content' => '',
            'post_status'  => 'draft',
            'post_author'  => 1,
        ]);
        
        // Get category slug (if assigned)
        $cats = get_the_category($post_id);
        $primary_category_slug = $cats[0]->slug ?? null;
        
        // Build recommendations HTML
        $recs_html = $this->build_recommendations_html($recommendations, $primary_category_slug);
        
        // Combine content and recommendations
        $final_content = $content . "\n\n" . $recs_html;
        
        // Generate featured image and attach
        $image_prompt = "A beautiful, high-resolution featured image for a skincare blog post titled: \"$topic\". Make it look elegant and relevant.";
        $image_url = $this->openai_api->generate_dalle_image($image_prompt);
        
        if ($image_url) {
            $attachment_id = $this->attach_image_to_post($image_url, $post_id);
            if ($attachment_id) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
        
        // Update post content
        wp_update_post([
            'ID' => $post_id,
            'post_content' => $final_content,
        ]);
        
        // Set tags and meta
        if ($tags_raw) {
            $tags = array_map('trim', explode(',', $tags_raw));
            wp_set_post_tags($post_id, $tags, false);
        }
        
        update_post_meta($post_id, '_dap_ai_generated', 1);
        update_post_meta($post_id, '_dap_ai_topic', $topic);
        update_post_meta($post_id, '_dap_ai_sources', trim($sources));
        
        return ucfirst($topic);
    }
    
    /**
     * Build recommendations HTML with affiliate links
     *
     * @param string $recommendations Raw recommendations text
     * @param string $category_slug Category slug for targeted affiliate links
     * @return string Formatted HTML with affiliate links
     */
    private function build_recommendations_html($recommendations, $category_slug = null) {
        if (!$recommendations || !$this->affiliate_manager) {
            return '';
        }
        
        $recs_html = "<h3>🛍️ Recommended Picks</h3><ul>";
        foreach (explode("\n", trim($recommendations)) as $line) {
            if (strpos($line, '–') !== false) {
                [$product, $desc] = explode('–', $line, 2);
                $product = trim($product);
                $desc = trim($desc);
                $link = $this->affiliate_manager->get_affiliate_link($product, $category_slug);
                $recs_html .= "<li><a href='{$link}' target='_blank'>{$product}</a> – {$desc}</li>";
            }
        }
        $recs_html .= "</ul>";
        
        return $recs_html;
    }
    
    /**
     * Attach image to a post
     *
     * @param string $image_url URL of the image to attach
     * @param int $post_id Post ID to attach the image to
     * @return int|bool Attachment ID if successful, false otherwise
     */
    private function attach_image_to_post($image_url, $post_id) {
        $tmp = download_url($image_url);
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];
        
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            return false;
        }
        
        return $id;
    }
}
