# AI Chat for Amazon Bedrock

A WordPress plugin that integrates Amazon Bedrock AI models into your website with conversation support.

## Description

AI Chat for Amazon Bedrock allows you to add AI-powered chat functionality to your WordPress site using Amazon's Bedrock service. This plugin supports multiple AI models including Claude, Titan, and Llama, and provides an easy-to-use interface for managing conversations.

### Features

- **AI Chat Widget**: Add an AI-powered chat widget to your website for visitors to interact with
- **Multiple AI Models**: Support for various Amazon Bedrock models including Claude, Titan, and Llama
- **Conversation Management**: Save and manage conversations between users and AI models
- **Shortcode Support**: Easily embed AI chat functionality anywhere on your site using shortcodes
- **Customizable Settings**: Configure AWS credentials, model parameters, and system prompts

## Installation

1. Upload the `ai-chat-for-amazon-bedrock` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your AWS credentials and settings in the AI Chat settings page
4. Add the chat widget to your site using the shortcode `[aichat_bedrock]`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- AWS account with access to Amazon Bedrock service

## Configuration

### AWS Credentials

To use this plugin, you need to have an AWS account with access to Amazon Bedrock. You'll need to provide:

1. AWS Access Key
2. AWS Secret Key
3. AWS Region where Amazon Bedrock is available

### AI Model Settings

You can configure the following settings for the AI models:

- Default Model: Select which AI model to use by default
- Max Tokens: Maximum number of tokens to generate in the response
- Temperature: Controls randomness (lower values are more focused, higher values are more creative)
- System Prompt: System prompt to guide the AI behavior

## Usage

### Basic Shortcode

Add the chat widget to any page or post using the shortcode:

```
[aichat_bedrock]
```

### Customized Shortcode

You can customize the chat widget with additional parameters:

```
[aichat_bedrock model="anthropic.claude-3-sonnet-20240229-v1:0" placeholder="Ask Claude..." button_text="Ask" welcome_message="Hello! I'm Claude. How can I assist you today?"]
```

## Frequently Asked Questions

### Which AI models are supported?

The plugin supports various Amazon Bedrock models including:
- Claude 3 Sonnet
- Claude 3 Haiku
- Claude Instant
- Amazon Titan Text Express
- Meta Llama 2

### How do I get AWS credentials for Amazon Bedrock?

You need to create an IAM user in your AWS account with permissions to access Amazon Bedrock. See the [AWS documentation](https://docs.aws.amazon.com/bedrock/latest/userguide/security-iam.html) for more details.

### Can I customize the appearance of the chat widget?

Yes, you can customize the appearance using CSS. The plugin includes basic styling, but you can override it with your own CSS rules.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Amazon Web Services
