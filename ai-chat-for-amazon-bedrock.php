<?php
/**
 * Plugin Name: AI Chat for Amazon Bedrock
 * Plugin URI: https://github.com/noteflow-ai/ai-chat-for-amazon-bedrock
 * Description: Integrate Amazon Bedrock AI models into your WordPress site for AI-powered chat functionality.
 * Version: 1.0.6
 * Author: Glay
 * Author URI: https://github.com/noteflow-ai
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: ai-chat-for-amazon-bedrock
 * Domain Path: /languages
 *
 * @package AI_Chat_Bedrock
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'AI_CHAT_BEDROCK_VERSION', '1.0.7' );
define( 'AI_CHAT_BEDROCK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_CHAT_BEDROCK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function aicfab_activate_plugin() {
	require_once AI_CHAT_BEDROCK_PLUGIN_DIR . 'includes/class-ai-chat-bedrock-activator.php';
	AI_Chat_Bedrock_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function aicfab_deactivate_plugin() {
	require_once AI_CHAT_BEDROCK_PLUGIN_DIR . 'includes/class-ai-chat-bedrock-deactivator.php';
	AI_Chat_Bedrock_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'aicfab_activate_plugin' );
register_deactivation_hook( __FILE__, 'aicfab_deactivate_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require AI_CHAT_BEDROCK_PLUGIN_DIR . 'includes/class-ai-chat-bedrock.php';

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
function aicfab_run_plugin() {
	$plugin = new AI_Chat_Bedrock();
	$plugin->run();
}
aicfab_run_plugin();
