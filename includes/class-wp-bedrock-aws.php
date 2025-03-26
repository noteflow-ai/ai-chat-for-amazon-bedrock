<?php
/**
 * AWS Bedrock API integration
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/includes
 */

namespace AICHAT_AMAZON_BEDROCK;

class WP_Bedrock_AWS {

    /**
     * Get AWS credentials from database
     *
     * @return array AWS credentials
     */
    public static function get_credentials() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aichat_bedrock';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
            WP_Bedrock_Activator::activate();
        }
        
        $aws_region = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'aws_region'
            )
        );
        
        $aws_access_key = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'aws_access_key'
            )
        );
        
        $aws_secret_key = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'aws_secret_key'
            )
        );
        
        return [
            'region' => $aws_region ?: 'us-east-1',
            'access_key' => $aws_access_key,
            'secret_key' => $aws_secret_key
        ];
    }
    
    /**
     * Get model settings from database
     *
     * @return array Model settings
     */
    public static function get_model_settings() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aichat_bedrock';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
            WP_Bedrock_Activator::activate();
        }
        
        $default_model = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'default_model'
            )
        );
        
        $max_tokens = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'max_tokens'
            )
        );
        
        $temperature = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'temperature'
            )
        );
        
        $system_prompt = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM $table_name WHERE option_name = %s",
                'system_prompt'
            )
        );
        
        return [
            'model' => $default_model ?: 'anthropic.claude-3-sonnet-20240229-v1:0',
            'max_tokens' => intval($max_tokens ?: 4096),
            'temperature' => floatval($temperature ?: 0.7),
            'system_prompt' => $system_prompt ?: 'You are a helpful AI assistant powered by Amazon Bedrock.'
        ];
    }
    
    /**
     * Send a message to Amazon Bedrock
     *
     * @param array $messages Array of message objects with role and content
     * @return string|WP_Error Response from the API or error
     */
    public static function send_message($messages) {
        $credentials = self::get_credentials();
        $settings = self::get_model_settings();
        
        // Check if credentials are set
        if (empty($credentials['access_key']) || empty($credentials['secret_key'])) {
            return new \WP_Error('missing_credentials', 'AWS credentials are not configured.');
        }
        
        // Debug information
        error_log('Using AWS Region: ' . $credentials['region']);
        error_log('Using Model: ' . $settings['model']);
        
        try {
            return self::send_message_manual($messages, $credentials, $settings);
        } catch (\Exception $e) {
            error_log('Bedrock API Error: ' . $e->getMessage());
            return new \WP_Error('api_error', 'API Error: ' . $e->getMessage());
        }
    }

    /**
     * Send message using manual HTTP request
     */
    private static function send_message_manual($messages, $credentials, $settings) {
        // Prepare the request based on the model
        $model_id = $settings['model'];
        $endpoint = 'https://bedrock-runtime.' . $credentials['region'] . '.amazonaws.com/model/' . $model_id . '/invoke';
        
        // Format request based on model
        $request_body = self::format_request_body($messages, $settings);
        $request_json = json_encode($request_body);
        
        // AWS Signature V4
        $method = 'POST';
        $service = 'bedrock';
        $host = 'bedrock-runtime.' . $credentials['region'] . '.amazonaws.com';
        $region = $credentials['region'];
        $amz_date = gmdate('Ymd\THis\Z');
        $date_stamp = gmdate('Ymd');
        
        // Step 1: Create a canonical request
        $canonical_uri = '/model/' . $model_id . '/invoke';
        $canonical_querystring = '';
        $canonical_headers = "content-type:application/json\nhost:" . $host . "\nx-amz-date:" . $amz_date . "\n";
        $signed_headers = 'content-type;host;x-amz-date';
        $payload_hash = hash('sha256', $request_json);
        $canonical_request = $method . "\n" . $canonical_uri . "\n" . $canonical_querystring . "\n" . $canonical_headers . "\n" . $signed_headers . "\n" . $payload_hash;
        
        // Step 2: Create a string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';
        $string_to_sign = $algorithm . "\n" . $amz_date . "\n" . $credential_scope . "\n" . hash('sha256', $canonical_request);
        
        // Step 3: Calculate the signature
        $signing_key = self::getSignatureKey($credentials['secret_key'], $date_stamp, $region, $service);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Step 4: Add the signature to the request
        $authorization_header = $algorithm . ' ' . 'Credential=' . $credentials['access_key'] . '/' . $credential_scope . ', ' . 'SignedHeaders=' . $signed_headers . ', ' . 'Signature=' . $signature;
        
        // Debug information
        error_log('Canonical Request: ' . str_replace("\n", "\\n", $canonical_request));
        error_log('String to Sign: ' . str_replace("\n", "\\n", $string_to_sign));
        
        // Make the request
        $response = wp_remote_post($endpoint, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Amz-Date' => $amz_date,
                'Authorization' => $authorization_header
            ],
            'body' => $request_json,
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('Bedrock API Error Response: ' . $response_body);
            return new \WP_Error('api_error', 'API Error: ' . $response_code . ' - ' . $response_body);
        }
        
        $response_data = json_decode($response_body, true);
        
        // Extract response based on model
        return self::extract_response($response_data, $model_id);
    }

    /**
     * Format request body based on model
     */
    private static function format_request_body($messages, $settings) {
        $model_id = $settings['model'];
        
        if (strpos($model_id, 'anthropic.claude') !== false) {
            // Claude format
            return [
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => $settings['max_tokens'],
                'temperature' => $settings['temperature'],
                'system' => $settings['system_prompt'],
                'messages' => $messages
            ];
        } elseif (strpos($model_id, 'amazon.titan') !== false) {
            // Amazon Titan format
            $formatted_messages = [];
            foreach ($messages as $message) {
                $formatted_messages[] = $message['role'] . ': ' . $message['content'];
            }
            
            return [
                'inputText' => implode("\n\n", $formatted_messages),
                'textGenerationConfig' => [
                    'maxTokenCount' => $settings['max_tokens'],
                    'temperature' => $settings['temperature'],
                    'stopSequences' => []
                ]
            ];
        } elseif (strpos($model_id, 'meta.llama') !== false) {
            // Llama format
            $prompt = '';
            foreach ($messages as $message) {
                if ($message['role'] === 'user') {
                    $prompt .= "Human: " . $message['content'] . "\n\n";
                } elseif ($message['role'] === 'assistant') {
                    $prompt .= "Assistant: " . $message['content'] . "\n\n";
                }
            }
            $prompt .= "Assistant: ";
            
            return [
                'prompt' => $prompt,
                'max_gen_len' => $settings['max_tokens'],
                'temperature' => $settings['temperature'],
                'top_p' => 0.9
            ];
        }
        
        return [];
    }

    /**
     * Extract response based on model
     */
    private static function extract_response($response_data, $model_id) {
        if (strpos($model_id, 'anthropic.claude') !== false) {
            return isset($response_data['content'][0]['text']) ? $response_data['content'][0]['text'] : 'No response from AI';
        } elseif (strpos($model_id, 'amazon.titan') !== false) {
            return isset($response_data['results'][0]['outputText']) ? $response_data['results'][0]['outputText'] : 'No response from AI';
        } elseif (strpos($model_id, 'meta.llama') !== false) {
            return isset($response_data['generation']) ? $response_data['generation'] : 'No response from AI';
        }
        
        return 'Unsupported model response format';
    }
    
    /**
     * Helper function to create a signature key for AWS Signature V4
     */
    public static function getSignatureKey($key, $dateStamp, $regionName, $serviceName) {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        return $kSigning;
    }
}
