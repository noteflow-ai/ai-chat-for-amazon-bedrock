<?php
/**
 * Admin dashboard page for AI Chat for Amazon Bedrock
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2>Welcome to AI Chat for Amazon Bedrock</h2>
        <p>This plugin allows you to integrate Amazon Bedrock AI models into your WordPress site.</p>
        
        <h3>Getting Started</h3>
        <ol>
            <li>Configure your <a href="<?php echo admin_url('admin.php?page=ai-chat-for-amazon-bedrock-settings'); ?>">AWS credentials and settings</a></li>
            <li>Add the chat interface to any page using the shortcode: <code>[aichat_bedrock]</code></li>
            <li>View and manage saved <a href="<?php echo admin_url('admin.php?page=ai-chat-for-amazon-bedrock-conversations'); ?>">conversations</a></li>
        </ol>
        
        <h3>Features</h3>
        <ul>
            <li>Connect to Amazon Bedrock AI models</li>
            <li>Configure AI parameters (temperature, max tokens, etc.)</li>
            <li>Customize system prompts</li>
            <li>Save and manage conversations</li>
            <li>Shortcode for embedding chat interface on any page</li>
        </ul>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h3>Shortcode Usage</h3>
        <p>Use the following shortcode to add the chat interface to any page or post:</p>
        <pre>[aichat_bedrock]</pre>
    </div>
</div>
