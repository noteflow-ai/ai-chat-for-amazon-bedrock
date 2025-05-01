<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="ai-chat-bedrock-admin-content">
        <div class="ai-chat-bedrock-welcome">
            <h2><?php esc_html_e( 'Welcome to AI Chat for Amazon Bedrock', 'ai-chat-bedrock' ); ?></h2>
            <p><?php esc_html_e( 'This plugin allows you to integrate Amazon Bedrock AI models into your WordPress site for AI-powered chat functionality.', 'ai-chat-bedrock' ); ?></p>
        </div>
        
        <div class="ai-chat-bedrock-cards">
            <div class="ai-chat-bedrock-card">
                <h3><?php esc_html_e( 'Getting Started', 'ai-chat-bedrock' ); ?></h3>
                <ol>
                    <li><?php esc_html_e( 'Configure your AWS credentials in the Settings page', 'ai-chat-bedrock' ); ?></li>
                    <li><?php esc_html_e( 'Choose your preferred AI model and settings', 'ai-chat-bedrock' ); ?></li>
                    <li><?php esc_html_e( 'Add the chat interface to any page using the shortcode', 'ai-chat-bedrock' ); ?></li>
                </ol>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '-settings' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Go to Settings', 'ai-chat-bedrock' ); ?></a>
            </div>
            
            <div class="ai-chat-bedrock-card">
                <h3><?php esc_html_e( 'Using the Shortcode', 'ai-chat-bedrock' ); ?></h3>
                <p><?php esc_html_e( 'Add the chat interface to any page or post using this shortcode:', 'ai-chat-bedrock' ); ?></p>
                <code>[ai_chat_bedrock]</code>
                <p><?php esc_html_e( 'You can customize the appearance with these attributes:', 'ai-chat-bedrock' ); ?></p>
                <pre>[ai_chat_bedrock 
  title="Chat with our AI"
  placeholder="Ask me anything..."
  button_text="Send"
  clear_text="Clear"
  width="400px"
  height="600px"
]</pre>
            </div>
            
            <div class="ai-chat-bedrock-card">
                <h3><?php esc_html_e( 'Test the Chat', 'ai-chat-bedrock' ); ?></h3>
                <p><?php esc_html_e( 'Try out the chat interface with your current settings before adding it to your site.', 'ai-chat-bedrock' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->plugin_name . '-test' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Test Chat Interface', 'ai-chat-bedrock' ); ?></a>
            </div>
        </div>
        
        <div class="ai-chat-bedrock-info">
            <h3><?php esc_html_e( 'About Amazon Bedrock', 'ai-chat-bedrock' ); ?></h3>
            <p><?php esc_html_e( 'Amazon Bedrock is a fully managed service that offers a choice of high-performing foundation models (FMs) from leading AI companies like AI21 Labs, Anthropic, Cohere, Meta, Stability AI, and Amazon via a single API.', 'ai-chat-bedrock' ); ?></p>
            <p><?php esc_html_e( 'This plugin allows you to leverage these powerful AI models directly within your WordPress site to create engaging chat experiences for your visitors.', 'ai-chat-bedrock' ); ?></p>
            <p><a href="https://aws.amazon.com/bedrock/" target="_blank"><?php esc_html_e( 'Learn more about Amazon Bedrock', 'ai-chat-bedrock' ); ?></a></p>
        </div>
    </div>
</div>
