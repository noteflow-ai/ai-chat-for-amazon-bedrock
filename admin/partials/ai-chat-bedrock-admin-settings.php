<?php
/**
 * Provide a admin area view for the plugin settings
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
    
    <form method="post" action="options.php">
        <?php
        settings_fields( 'ai_chat_bedrock_settings' );
        do_settings_sections( 'ai_chat_bedrock_settings' );
        submit_button();
        ?>
    </form>
    
    <div class="ai-chat-bedrock-settings-info">
        <h3><?php esc_html_e( 'AWS Credentials', 'ai-chat-bedrock' ); ?></h3>
        <p><?php esc_html_e( 'To use this plugin, you need AWS credentials with permissions to access Amazon Bedrock. Make sure your AWS user has the following permissions:', 'ai-chat-bedrock' ); ?></p>
        <ul>
            <li><code>bedrock:InvokeModel</code></li>
            <li><code>bedrock:InvokeModelWithResponseStream</code> (for streaming responses)</li>
        </ul>
        
        <h3><?php esc_html_e( 'Model Selection', 'ai-chat-bedrock' ); ?></h3>
        <p><?php esc_html_e( 'Choose a model that best fits your needs. Different models have different capabilities and pricing:', 'ai-chat-bedrock' ); ?></p>
        <ul>
            <li><strong>Claude 3 Sonnet</strong>: <?php esc_html_e( 'Balanced model with strong reasoning and creative capabilities', 'ai-chat-bedrock' ); ?></li>
            <li><strong>Claude 3 Haiku</strong>: <?php esc_html_e( 'Faster, more economical model for simpler tasks', 'ai-chat-bedrock' ); ?></li>
            <li><strong>Amazon Titan</strong>: <?php esc_html_e( 'Amazon\'s own foundation model with good general capabilities', 'ai-chat-bedrock' ); ?></li>
            <li><strong>Meta Llama 2</strong>: <?php esc_html_e( 'Open model with good performance for various tasks', 'ai-chat-bedrock' ); ?></li>
        </ul>
        
        <h3><?php esc_html_e( 'System Prompt', 'ai-chat-bedrock' ); ?></h3>
        <p><?php esc_html_e( 'The system prompt helps define the AI\'s behavior and personality. Use it to give the AI specific instructions about how to respond.', 'ai-chat-bedrock' ); ?></p>
        <p><?php esc_html_e( 'Example system prompts:', 'ai-chat-bedrock' ); ?></p>
        <ul>
            <li><?php esc_html_e( 'You are a helpful customer service assistant for our company. Be friendly, concise, and helpful.', 'ai-chat-bedrock' ); ?></li>
            <li><?php esc_html_e( 'You are a knowledgeable product expert. Help customers understand our products and make recommendations.', 'ai-chat-bedrock' ); ?></li>
            <li><?php esc_html_e( 'You are a technical support specialist. Help users troubleshoot issues with our software.', 'ai-chat-bedrock' ); ?></li>
        </ul>
    </div>
</div>
