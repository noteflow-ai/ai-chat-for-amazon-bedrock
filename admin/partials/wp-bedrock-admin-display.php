<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="aichat-bedrock-admin-container">
        <div class="aichat-bedrock-welcome-panel">
            <h2><?php _e('Welcome to AI Chat for Amazon Bedrock', 'ai-chat-for-amazon-bedrock'); ?></h2>
            <p><?php _e('This plugin allows you to integrate Amazon Bedrock AI models into your WordPress site.', 'ai-chat-for-amazon-bedrock'); ?></p>
            
            <div class="aichat-bedrock-quick-links">
                <a href="<?php echo admin_url('admin.php?page=' . $this->plugin_name . '-settings'); ?>" class="button button-primary">
                    <?php _e('Configure Settings', 'ai-chat-for-amazon-bedrock'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=' . $this->plugin_name . '-conversations'); ?>" class="button">
                    <?php _e('View Conversations', 'ai-chat-for-amazon-bedrock'); ?>
                </a>
            </div>
        </div>
        
        <div class="aichat-bedrock-features">
            <h2><?php _e('Features', 'ai-chat-for-amazon-bedrock'); ?></h2>
            
            <div class="aichat-bedrock-feature-grid">
                <div class="aichat-bedrock-feature">
                    <h3><?php _e('AI Chat Widget', 'ai-chat-for-amazon-bedrock'); ?></h3>
                    <p><?php _e('Add an AI-powered chat widget to your website for visitors to interact with.', 'ai-chat-for-amazon-bedrock'); ?></p>
                </div>
                
                <div class="aichat-bedrock-feature">
                    <h3><?php _e('Multiple AI Models', 'ai-chat-for-amazon-bedrock'); ?></h3>
                    <p><?php _e('Support for various Amazon Bedrock models including Claude, Titan, and Llama.', 'ai-chat-for-amazon-bedrock'); ?></p>
                </div>
                
                <div class="aichat-bedrock-feature">
                    <h3><?php _e('Conversation Management', 'ai-chat-for-amazon-bedrock'); ?></h3>
                    <p><?php _e('Save and manage conversations between users and AI models.', 'ai-chat-for-amazon-bedrock'); ?></p>
                </div>
                
                <div class="aichat-bedrock-feature">
                    <h3><?php _e('Shortcode Support', 'ai-chat-for-amazon-bedrock'); ?></h3>
                    <p><?php _e('Easily embed AI chat functionality anywhere on your site using shortcodes.', 'ai-chat-for-amazon-bedrock'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="aichat-bedrock-getting-started">
            <h2><?php _e('Getting Started', 'ai-chat-for-amazon-bedrock'); ?></h2>
            
            <ol>
                <li><?php _e('Configure your AWS credentials in the Settings page', 'ai-chat-for-amazon-bedrock'); ?></li>
                <li><?php _e('Select your preferred AI model and customize settings', 'ai-chat-for-amazon-bedrock'); ?></li>
                <li><?php _e('Add the chat widget to your site using the shortcode [aichat_bedrock]', 'ai-chat-for-amazon-bedrock'); ?></li>
                <li><?php _e('Start chatting with Amazon Bedrock AI models!', 'ai-chat-for-amazon-bedrock'); ?></li>
            </ol>
        </div>
    </div>
</div>
