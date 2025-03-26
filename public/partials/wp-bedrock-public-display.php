<?php
/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/public/partials
 */
?>

<div class="aichat-bedrock-container" data-model="<?php echo esc_attr($model); ?>">
    <div class="aichat-bedrock-messages">
        <?php if (!empty($atts['welcome_message'])) : ?>
            <div class="aichat-bedrock-message aichat-bedrock-message-assistant">
                <div class="aichat-bedrock-message-content">
                    <?php echo wp_kses_post(nl2br($atts['welcome_message'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="aichat-bedrock-input-container">
        <textarea class="aichat-bedrock-input" placeholder="<?php echo esc_attr($atts['placeholder']); ?>"></textarea>
        <button class="aichat-bedrock-send-button">
            <?php echo esc_html($atts['button_text']); ?>
        </button>
    </div>
    
    <div class="aichat-bedrock-status">
        <span class="aichat-bedrock-status-indicator"></span>
        <span class="aichat-bedrock-status-text"></span>
    </div>
    
    <div class="aichat-bedrock-footer">
        <span class="aichat-bedrock-powered-by">
            <?php _e('Powered by', 'ai-chat-for-amazon-bedrock'); ?> 
            <a href="https://aws.amazon.com/bedrock/" target="_blank">Amazon Bedrock</a>
        </span>
    </div>
</div>
