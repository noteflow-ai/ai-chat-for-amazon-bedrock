<?php
/**
 * Admin settings page for AI Chat for Amazon Bedrock
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if table exists, if not create it
global $wpdb;
$table_name = $wpdb->prefix . 'aichat_bedrock';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;

if (!$table_exists) {
    require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
    AICHAT_AMAZON_BEDROCK\WP_Bedrock_Activator::activate();
}

// Set default values
$default_values = [
    'aws_region' => 'us-east-1',
    'aws_access_key' => '',
    'aws_secret_key' => '',
    'default_model' => 'anthropic.claude-3-sonnet-20240229-v1:0',
    'max_tokens' => '4096',
    'temperature' => '0.7',
    'system_prompt' => 'You are a helpful AI assistant powered by Amazon Bedrock.',
];

// Get settings from database if table exists
if ($table_exists) {
    $aws_region = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'aws_region'
        )
    );

    $aws_access_key = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'aws_access_key'
        )
    );

    $aws_secret_key = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'aws_secret_key'
        )
    );

    $default_model = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'default_model'
        )
    );

    $max_tokens = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'max_tokens'
        )
    );

    $temperature = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'temperature'
        )
    );

    $system_prompt = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM $table_name WHERE option_name = %s",
            'system_prompt'
        )
    );
} else {
    // Use default values if table doesn't exist
    extract($default_values);
}

// Use default values if any setting is empty
$aws_region = $aws_region ?: $default_values['aws_region'];
$aws_access_key = $aws_access_key ?: $default_values['aws_access_key'];
$aws_secret_key = $aws_secret_key ?: $default_values['aws_secret_key'];
$default_model = $default_model ?: $default_values['default_model'];
$max_tokens = $max_tokens ?: $default_values['max_tokens'];
$temperature = $temperature ?: $default_values['temperature'];
$system_prompt = $system_prompt ?: $default_values['system_prompt'];
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <input type="hidden" name="action" value="aichat_bedrock_save_settings">
        <?php wp_nonce_field('aichat_bedrock_settings_nonce', 'aichat_bedrock_settings_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="aws_region">AWS Region</label></th>
                <td>
                    <select name="aws_region" id="aws_region">
                        <option value="us-east-1" <?php selected($aws_region, 'us-east-1'); ?>>US East (N. Virginia)</option>
                        <option value="us-east-2" <?php selected($aws_region, 'us-east-2'); ?>>US East (Ohio)</option>
                        <option value="us-west-1" <?php selected($aws_region, 'us-west-1'); ?>>US West (N. California)</option>
                        <option value="us-west-2" <?php selected($aws_region, 'us-west-2'); ?>>US West (Oregon)</option>
                        <option value="eu-west-1" <?php selected($aws_region, 'eu-west-1'); ?>>EU (Ireland)</option>
                        <option value="eu-central-1" <?php selected($aws_region, 'eu-central-1'); ?>>EU (Frankfurt)</option>
                        <option value="ap-northeast-1" <?php selected($aws_region, 'ap-northeast-1'); ?>>Asia Pacific (Tokyo)</option>
                        <option value="ap-southeast-1" <?php selected($aws_region, 'ap-southeast-1'); ?>>Asia Pacific (Singapore)</option>
                        <option value="ap-southeast-2" <?php selected($aws_region, 'ap-southeast-2'); ?>>Asia Pacific (Sydney)</option>
                    </select>
                    <p class="description">Select the AWS region where Amazon Bedrock is available.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aws_access_key">AWS Access Key</label></th>
                <td>
                    <input type="text" name="aws_access_key" id="aws_access_key" value="<?php echo esc_attr($aws_access_key); ?>" class="regular-text">
                    <p class="description">Enter your AWS Access Key with permissions to use Amazon Bedrock.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="aws_secret_key">AWS Secret Key</label></th>
                <td>
                    <input type="password" name="aws_secret_key" id="aws_secret_key" value="<?php echo esc_attr($aws_secret_key); ?>" class="regular-text">
                    <p class="description">Enter your AWS Secret Key.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="default_model">Default AI Model</label></th>
                <td>
                    <select name="default_model" id="default_model">
                        <option value="anthropic.claude-3-sonnet-20240229-v1:0" <?php selected($default_model, 'anthropic.claude-3-sonnet-20240229-v1:0'); ?>>Claude 3 Sonnet</option>
                        <option value="anthropic.claude-3-haiku-20240307-v1:0" <?php selected($default_model, 'anthropic.claude-3-haiku-20240307-v1:0'); ?>>Claude 3 Haiku</option>
                        <option value="anthropic.claude-instant-v1" <?php selected($default_model, 'anthropic.claude-instant-v1'); ?>>Claude Instant</option>
                        <option value="amazon.titan-text-express-v1" <?php selected($default_model, 'amazon.titan-text-express-v1'); ?>>Amazon Titan Text</option>
                        <option value="meta.llama2-13b-chat-v1" <?php selected($default_model, 'meta.llama2-13b-chat-v1'); ?>>Meta Llama 2 Chat (13B)</option>
                    </select>
                    <p class="description">Select the default AI model to use.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="max_tokens">Max Tokens</label></th>
                <td>
                    <input type="number" name="max_tokens" id="max_tokens" value="<?php echo esc_attr($max_tokens); ?>" class="small-text" min="100" max="8192">
                    <p class="description">Maximum number of tokens to generate in the response.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="temperature">Temperature</label></th>
                <td>
                    <input type="number" name="temperature" id="temperature" value="<?php echo esc_attr($temperature); ?>" class="small-text" min="0" max="1" step="0.1">
                    <p class="description">Controls randomness. Lower values are more deterministic, higher values more creative.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="system_prompt">System Prompt</label></th>
                <td>
                    <textarea name="system_prompt" id="system_prompt" rows="5" cols="50"><?php echo esc_textarea($system_prompt); ?></textarea>
                    <p class="description">The system prompt that defines the AI assistant's behavior.</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save Settings'); ?>
    </form>
</div>
