<?php
/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/public/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ai-chat-bedrock-container" style="width: <?php echo esc_attr( $atts['width'] ); ?>;">
    <div class="ai-chat-bedrock-header">
        <h3><?php echo esc_html( $atts['title'] ); ?></h3>
    </div>
    
    <div class="ai-chat-bedrock-messages" style="height: <?php echo esc_attr( $atts['height'] ); ?>;">
        <div class="ai-chat-bedrock-welcome-message">
            <div class="ai-chat-bedrock-message ai-message">
                <div class="ai-chat-bedrock-avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3a3 3 0 0 1 3 3v1h1a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h1v-1a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v1h2v-1a1 1 0 0 0-1-1z" fill="currentColor"/></svg>
                </div>
                <div class="ai-chat-bedrock-message-content">
                    <?php 
                    $options = get_option( 'ai_chat_bedrock_settings' );
                    $welcome_message = isset( $options['welcome_message'] ) ? $options['welcome_message'] : __( 'Hello! How can I help you today?', 'ai-chat-bedrock' );
                    echo esc_html( $welcome_message ); 
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="ai-chat-bedrock-input">
        <form class="ai-chat-bedrock-form" onsubmit="return false;">
            <div class="ai-chat-bedrock-input-container">
                <textarea class="ai-chat-bedrock-textarea" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" rows="3"></textarea>
                <button type="button" class="ai-chat-bedrock-send-button button button-primary"><?php echo esc_html( $atts['button_text'] ); ?></button>
            </div>
            <div class="ai-chat-bedrock-buttons">
                <button type="button" class="ai-chat-bedrock-clear button button-secondary"><?php echo esc_html( $atts['clear_text'] ); ?></button>
            </div>
        </form>
    </div>
    
    <div class="ai-chat-bedrock-footer">
        <small><?php esc_html_e( 'Powered by Amazon Bedrock', 'ai-chat-bedrock' ); ?></small>
    </div>
</div>
