<?php
/**
 * OpenAI API integration
 */

if (!defined('WPINC')) {
    die;
}

class DAP_OpenAI_API {
    /**
     * @var string OpenAI API key
     */
    private $api_key;
    
    /**
     * Set the API key
     *
     * @param string $api_key OpenAI API key
     */
    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * Send a prompt to OpenAI and get a response
     *
     * @param string $prompt The prompt to send
     * @return string|bool Response text or false on failure
     */
    public function prompt($prompt) {
        if (!$this->api_key) {
            return false;
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? false;
    }
    
    /**
     * Generate an image using DALL-E
     *
     * @param string $prompt The image prompt
     * @return string|bool Image URL or false on failure
     */
    public function generate_dalle_image($prompt) {
        if (!$this->api_key) {
            return false;
        }
        
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'][0]['url'] ?? false;
    }
}
