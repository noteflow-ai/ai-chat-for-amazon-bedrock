<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'ai_chat_bedrock_settings' );

// Delete any transients that might have been set
delete_transient( 'ai_chat_bedrock_cache' );

// If you used custom post types, you might want to delete them here
// For example:
// global $wpdb;
// $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'ai_chat_history'" );
// $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT id FROM {$wpdb->posts})" );
