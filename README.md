# AI Chat for Amazon Bedrock

A WordPress plugin that integrates Amazon Bedrock AI models into your WordPress site for AI-powered chat functionality.

## Description

AI Chat for Amazon Bedrock allows you to add an AI-powered chat interface to your WordPress site using Amazon's Bedrock service. This plugin supports various AI models including Claude, Amazon Titan, and Meta's Llama 2.

### Features

- Easy integration with Amazon Bedrock
- Support for multiple AI models
- Customizable chat interface
- Streaming responses for a more interactive experience
- Shortcode for easy placement anywhere on your site
- Admin interface for testing and configuration
- Responsive design that works on mobile and desktop

## Installation

1. Upload the `ai-chat-for-amazon-bedrock` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your AWS credentials and settings in the plugin's settings page
4. Add the chat interface to any page or post using the shortcode `[ai_chat_bedrock]`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- AWS account with access to Amazon Bedrock
- AWS credentials with appropriate permissions

## Configuration

### AWS Credentials

To use this plugin, you need AWS credentials with permissions to access Amazon Bedrock. Make sure your AWS user has the following permissions:

- `bedrock:InvokeModel`
- `bedrock:InvokeModelWithResponseStream` (for streaming responses)

### Plugin Settings

1. Navigate to the plugin settings page (AI Chat Bedrock > Settings)
2. Enter your AWS credentials (Access Key and Secret Key)
3. Select your preferred AWS region where Bedrock is available
4. Choose an AI model from the available options
5. Configure model parameters like max tokens and temperature
6. Set up the system prompt to guide the AI's behavior
7. Save your settings

## Usage

### Basic Shortcode

Add the chat interface to any page or post using this shortcode:

```
[ai_chat_bedrock]
```

### Customized Shortcode

You can customize the appearance with these attributes:

```
[ai_chat_bedrock 
  title="Chat with our AI"
  placeholder="Ask me anything..."
  button_text="Send"
  clear_text="Clear"
  width="400px"
  height="600px"
]
```

## Supported Models

- Claude 3 Sonnet (Anthropic)
- Claude 3 Haiku (Anthropic)
- Claude Instant (Anthropic)
- Amazon Titan Text Express
- Meta Llama 2 13B

## Troubleshooting

### Common Issues

1. **Connection Errors**: Make sure your AWS credentials are correct and have the necessary permissions.
2. **Streaming Not Working**: Some server configurations may not support streaming responses. Try disabling streaming in the settings.
3. **Model Not Available**: Ensure you've selected a region where your chosen model is available.

### Debug Mode

Enable debug mode in the plugin settings to log API requests and responses to your WordPress error log.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

- Developed by Your Name
- Uses the Amazon Bedrock API for AI functionality

## Support

For support, please create an issue on the plugin's GitHub repository or contact the developer directly.

## Changelog

### 1.0.0
- Initial release
