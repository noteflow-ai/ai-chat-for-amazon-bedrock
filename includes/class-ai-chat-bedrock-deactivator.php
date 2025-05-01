<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * Clean up any necessary data on deactivation.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Cleanup tasks if needed
		// Note: We don't delete the database table or options here
		// to preserve user data in case of accidental deactivation
	}
}
