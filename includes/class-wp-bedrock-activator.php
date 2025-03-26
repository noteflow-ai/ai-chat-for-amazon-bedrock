<?php
/**
 * Fired during plugin activation
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/includes
 */

namespace AICHAT_AMAZON_BEDROCK;

class WP_Bedrock_Activator {

    /**
     * Create necessary database tables and initialize plugin settings
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main settings table
        $table_name = $wpdb->prefix . 'aichat_bedrock';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Create tables one by one
            $sql1 = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                option_name varchar(191) NOT NULL,
                option_value longtext NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY option_name (option_name)
            ) $charset_collate;";
            
            // Execute the first SQL statement
            $wpdb->query($sql1);
            
            // Verify table was created
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                error_log('Failed to create table: ' . $table_name);
                return;
            }
            
            // Conversations table
            $table_conversations = $wpdb->prefix . 'aichat_bedrock_conversations';
            $sql2 = "CREATE TABLE $table_conversations (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                title varchar(255) NOT NULL,
                model varchar(100) NOT NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) $charset_collate;";
            
            // Execute the second SQL statement
            $wpdb->query($sql2);
            
            // Messages table
            $table_messages = $wpdb->prefix . 'aichat_bedrock_messages';
            $sql3 = "CREATE TABLE $table_messages (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                conversation_id mediumint(9) NOT NULL,
                role varchar(50) NOT NULL,
                content longtext NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY conversation_id (conversation_id)
            ) $charset_collate;";
            
            // Execute the third SQL statement
            $wpdb->query($sql3);
            
            // Set default options if they don't exist
            $current_time = current_time('mysql');
            $default_options = [
                'aws_region' => 'us-east-1',
                'default_model' => 'anthropic.claude-3-sonnet-20240229-v1:0',
                'max_tokens' => '4096',
                'temperature' => '0.7',
                'top_p' => '0.9',
                'system_prompt' => 'You are a helpful AI assistant powered by Amazon Bedrock.',
            ];
            
            // Insert default options directly
            foreach ($default_options as $option_name => $option_value) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO $table_name 
                    (option_name, option_value, created_at, updated_at) 
                    VALUES (%s, %s, %s, %s)",
                    $option_name,
                    $option_value,
                    $current_time,
                    $current_time
                ));
            }
        }
    }
}
