<?php
/**
 * Provide a admin area view for testing the chat interface
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="ai-chat-bedrock-test-container">
        <div class="ai-chat-bedrock-test-info">
            <p><?php esc_html_e( 'Use this page to test your Amazon Bedrock chat integration with your current settings.', 'ai-chat-bedrock' ); ?></p>
            <p><?php esc_html_e( 'This is how the chat will appear to your users when you add it to your site using the shortcode.', 'ai-chat-bedrock' ); ?></p>
        </div>
        
        <div class="ai-chat-bedrock-test-chat">
            <?php 
            // Get settings
            $options = get_option( 'ai_chat_bedrock_settings' );
            $chat_title = isset( $options['chat_title'] ) ? $options['chat_title'] : 'Chat with AI';
            
            // Display the chat interface
            echo '<div id="ai-chat-bedrock-test-interface">';
            echo do_shortcode( '[ai_chat_bedrock title="' . esc_attr( $chat_title ) . '" width="600px" height="500px"]' );
            echo '</div>';
            ?>
        </div>
        
        <div class="ai-chat-bedrock-test-settings">
            <h3><?php esc_html_e( 'Current Settings', 'ai-chat-bedrock' ); ?></h3>
            <?php
            $options = get_option( 'ai_chat_bedrock_settings' );
            
            // Display current model settings
            $model_id = isset( $options['model_id'] ) ? $options['model_id'] : 'anthropic.claude-3-sonnet-20240229-v1:0';
            $max_tokens = isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000;
            $temperature = isset( $options['temperature'] ) ? $options['temperature'] : 0.7;
            $streaming = isset( $options['enable_streaming'] ) && $options['enable_streaming'] === 'on' ? 'Enabled' : 'Disabled';
            
            // Get model name for display
            $model_name = 'Unknown Model';
            if ( strpos( $model_id, 'anthropic.claude-3-sonnet' ) !== false ) {
                $model_name = 'Claude 3 Sonnet';
            } elseif ( strpos( $model_id, 'anthropic.claude-3-haiku' ) !== false ) {
                $model_name = 'Claude 3 Haiku';
            } elseif ( strpos( $model_id, 'anthropic.claude-3-opus' ) !== false ) {
                $model_name = 'Claude 3 Opus';
            } elseif ( strpos( $model_id, 'anthropic.claude-3-5-sonnet' ) !== false ) {
                $model_name = 'Claude 3.5 Sonnet';
            } elseif ( strpos( $model_id, 'anthropic.claude-3-7-sonnet' ) !== false ) {
                $model_name = 'Claude 3.7 Sonnet';
            } elseif ( strpos( $model_id, 'amazon.titan' ) !== false ) {
                $model_name = 'Amazon Titan';
            } elseif ( strpos( $model_id, 'amazon.nova' ) !== false ) {
                $model_name = 'Amazon Nova';
            } elseif ( strpos( $model_id, 'meta.llama2' ) !== false ) {
                $model_name = 'Meta Llama 2';
            } elseif ( strpos( $model_id, 'meta.llama3' ) !== false ) {
                $model_name = 'Meta Llama 3';
            } elseif ( strpos( $model_id, 'mistral' ) !== false ) {
                $model_name = 'Mistral';
            } elseif ( strpos( $model_id, 'deepseek' ) !== false ) {
                $model_name = 'DeepSeek';
            }
            ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Model', 'ai-chat-bedrock' ); ?></th>
                    <td><?php echo esc_html( $model_name ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Max Tokens', 'ai-chat-bedrock' ); ?></th>
                    <td><?php echo esc_html( $max_tokens ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Temperature', 'ai-chat-bedrock' ); ?></th>
                    <td><?php echo esc_html( $temperature ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Streaming', 'ai-chat-bedrock' ); ?></th>
                    <td><?php echo esc_html( $streaming ); ?></td>
                </tr>
            </table>
            
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '-settings' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Change Settings', 'ai-chat-bedrock' ); ?></a>
            </p>
        </div>
    </div>
</div>
