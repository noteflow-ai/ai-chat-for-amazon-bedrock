<?php
/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Create necessary database tables and set default options.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Create chat history table
		global $wpdb;
		$table_name = $wpdb->prefix . 'ai_chat_bedrock_history';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			session_id varchar(50) NOT NULL,
			message text NOT NULL,
			response text NOT NULL,
			timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Set default options
		$default_options = array(
			'aws_region' => 'us-east-1',
			'model_id' => 'anthropic.claude-3-sonnet-20240229-v1:0',
			'max_tokens' => 1000,
			'temperature' => 0.7,
			'chat_title' => 'Chat with AI',
			'placeholder_text' => 'Ask me anything...',
			'welcome_message' => 'Hello! How can I help you today?',
			'enable_streaming' => true,
			'enable_history' => true,
			'max_history' => 10,
		);

		add_option( 'ai_chat_bedrock_settings', $default_options );
	}
}
