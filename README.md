# AI Chat for Amazon Bedrock WordPress Plugin

A WordPress plugin that integrates Amazon Bedrock AI models into your website, allowing visitors to chat with AI assistants.

## Features

- Chat interface for website visitors
- Support for multiple Amazon Bedrock models:
  - Claude 3 Sonnet
  - Claude 3 Haiku
  - Claude Instant
  - Amazon Titan Text Express
  - Meta Llama 2
- Streaming responses for a more interactive experience
- Customizable system prompts
- Chat history management
- Markdown formatting support
- Admin test interface
- Shortcode for embedding chat on any page

## Installation

1. Upload the plugin files to the `/wp-content/plugins/ai-chat-for-amazon-bedrock` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your AWS credentials and settings in the plugin settings page

## Configuration

### AWS Settings

1. Go to the plugin settings page (AI Chat Bedrock > Settings)
2. Enter your AWS Region, Access Key, and Secret Key
3. Select the model you want to use
4. Configure model parameters (max tokens, temperature)
5. Enable streaming for real-time responses

### Chat Settings

1. Customize the system prompt to guide the AI's behavior
2. Set the chat title displayed to users
3. Enable debug mode if needed for troubleshooting

## Usage

### Shortcode

Add the chat interface to any page or post using the shortcode:

```
[ai_chat_bedrock]
```

### Customizing the Chat Interface

You can customize the appearance of the chat interface using CSS. The plugin adds the following CSS classes:

- `.ai-chat-bedrock-container`: The main chat container
- `.ai-chat-bedrock-messages`: The messages container
- `.ai-chat-bedrock-message`: Individual message container
- `.ai-chat-bedrock-user-message`: User message styling
- `.ai-chat-bedrock-ai-message`: AI message styling

## Technical Details

### Streaming Implementation

The plugin uses Server-Sent Events (EventSource) for streaming responses from Amazon Bedrock. The implementation follows a two-step process:

1. First, a POST request is sent to initialize the streaming session
2. Then, an EventSource connection is established to receive the streaming data

This approach ensures compatibility with WordPress AJAX and avoids common issues with EventSource connections.

### Error Handling

The plugin includes robust error handling:

- Automatic fallback to regular AJAX if streaming fails
- Detailed error messages for users
- Debug logging for troubleshooting
- JSON parsing error recovery

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- AWS account with access to Amazon Bedrock
- AWS credentials with appropriate permissions

## Troubleshooting

If you encounter issues with the plugin:

1. Enable debug mode in the plugin settings
2. Check the browser console for JavaScript errors
3. Check the WordPress error log for PHP errors
4. Verify your AWS credentials and permissions
5. Ensure your server supports Server-Sent Events

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Your Name]
