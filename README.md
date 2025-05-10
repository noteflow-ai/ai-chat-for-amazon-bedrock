# AI Chat for Amazon Bedrock

WordPress plugin to integrate Amazon Bedrock AI models into your WordPress site for AI-powered chat functionality.

## Description

AI Chat for Amazon Bedrock allows you to easily integrate Amazon Bedrock's powerful AI models into your WordPress site. This plugin provides a customizable chat interface that your visitors can use to interact with various AI models available on Amazon Bedrock.

## Features

- **Multiple Model Support**: Works with Claude 3 (Sonnet, Haiku, Opus), Claude 3.5, Claude 3.7, Amazon Titan, Amazon Nova, Meta Llama 2 & 3, Mistral, and DeepSeek R1
- **Streaming Responses**: Real-time streaming of AI responses for a more interactive experience
- **Customizable Interface**: Easily customize the chat title, welcome message, and appearance
- **System Prompt**: Set a system prompt to guide the AI's behavior
- **Shortcode Integration**: Add the chat interface anywhere with a simple shortcode
- **Admin Test Interface**: Test different models and settings directly from the admin panel
- **MCP Support**: Model Context Protocol (MCP) integration to extend AI capabilities with external tools

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- AWS account with access to Amazon Bedrock
- AWS credentials with permissions to use Amazon Bedrock

## Installation

1. Upload the `ai-chat-for-amazon-bedrock` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'AI Chat Bedrock' settings page
4. Enter your AWS credentials and configure the plugin settings
5. Use the shortcode `[ai_chat_bedrock]` to add the chat interface to any page or post

## Configuration

### AWS Settings

- **AWS Region**: Select the AWS region where Amazon Bedrock is available
- **AWS Access Key**: Enter your AWS Access Key
- **AWS Secret Key**: Enter your AWS Secret Key

### Model Settings

- **Model ID**: Select the Amazon Bedrock model to use
- **Max Tokens**: Set the maximum number of tokens for the AI response
- **Temperature**: Control the randomness of the AI responses
- **Enable Streaming**: Toggle streaming responses on/off

### Chat Settings

- **System Prompt**: Set the system prompt to guide the AI's behavior
- **Chat Title**: Set the title displayed above the chat interface
- **Welcome Message**: Set the welcome message displayed when the chat is first loaded
- **Debug Mode**: Enable/disable debug mode for troubleshooting

### MCP Settings

- **Enable MCP**: Enable/disable Model Context Protocol integration
- **MCP Servers**: Add, remove, and manage MCP servers
- **MCP Tools**: View and use tools provided by MCP servers

## Usage

Add the chat interface to any page or post using the shortcode:

```
[ai_chat_bedrock]
```

## Development

### File Structure

- `admin/` - Admin-specific functionality
- `includes/` - Core plugin files
- `languages/` - Internationalization files
- `public/` - Public-facing functionality

### Hooks

The plugin provides several actions and filters for developers to extend its functionality.

## Model Context Protocol (MCP)

MCP is an open protocol that standardizes how applications provide context to LLMs. This plugin supports MCP integration, allowing you to extend the AI's capabilities with external tools and resources.

### Adding MCP Servers

1. Go to the 'MCP Settings' page
2. Enable MCP integration
3. Add MCP servers by providing a name and URL
4. View and manage available tools from each server

### Using MCP Tools

Once MCP servers are registered, their tools become available to the AI model during chat interactions. The AI can use these tools to perform actions like retrieving information, processing data, or interacting with external systems.

## Changelog

### 1.0.7
- Added Model Context Protocol (MCP) support
- Added MCP client functionality
- Added MCP server management interface
- Added MCP tools integration with AI chat

### 1.0.6
- Added support for Claude 3.5, Claude 3.7, and DeepSeek R1 models
- Added support for Amazon Nova models
- Fixed streaming response issues
- Improved error handling and debugging
- Fixed chat history issues
- Added customizable welcome message

### 1.0.0
- Initial release

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Glay](https://github.com/noteflow-ai)
