=== AI Chat for Amazon Bedrock ===
Contributors: glay
Tags: ai, chat, mcp, bedrock, claude
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.6
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate Amazon Bedrock AI models into your WordPress site for AI-powered chat functionality.

== Description ==

AI Chat for Amazon Bedrock allows you to easily integrate Amazon Bedrock's powerful AI models into your WordPress site. This plugin provides a customizable chat interface that your visitors can use to interact with various AI models available on Amazon Bedrock.

= Features =

* Support for multiple Amazon Bedrock models:
  * Claude 3 (Sonnet, Haiku, Opus)
  * Claude 3.5 Sonnet
  * Claude 3.7 Sonnet
  * Amazon Titan
  * Amazon Nova
  * Meta Llama 2 & 3
  * Mistral
  * DeepSeek R1
* Customizable chat interface
* Streaming responses for a more interactive experience
* System prompt customization
* Easy setup with AWS credentials
* Shortcode for embedding the chat interface anywhere on your site
* Admin test interface to try different models and settings

= Requirements =

* WordPress 5.0 or higher
* PHP 7.2 or higher
* AWS account with access to Amazon Bedrock
* AWS credentials with permissions to use Amazon Bedrock

== Installation ==

1. Upload the `ai-chat-for-amazon-bedrock` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'AI Chat Bedrock' settings page
4. Enter your AWS credentials and configure the plugin settings
5. Use the shortcode `[ai_chat_bedrock]` to add the chat interface to any page or post

== Frequently Asked Questions ==

= Do I need an AWS account to use this plugin? =

Yes, you need an AWS account with access to Amazon Bedrock. You'll need to set up AWS credentials with permissions to use the Bedrock service.

= Which AI models are supported? =

The plugin supports all AI models available on Amazon Bedrock, including Claude 3 (Sonnet, Haiku, Opus), Claude 3.5, Claude 3.7, Amazon Titan, Amazon Nova, Meta Llama 2 & 3, Mistral, and DeepSeek R1.

= How do I customize the chat interface? =

You can customize the chat interface through the plugin settings page. You can set the chat title, welcome message, system prompt, and other settings.

= Does the plugin support streaming responses? =

Yes, the plugin supports streaming responses for a more interactive chat experience. You can enable or disable streaming in the plugin settings.

= How do I add the chat interface to my site? =

You can add the chat interface to any page or post using the shortcode `[ai_chat_bedrock]`.

== Screenshots ==

1. Chat interface
2. Admin settings page
3. Test interface

== Changelog ==

= 1.0.6 =
* Fixed internationalization issues with text domain
* Improved security with proper escaping of output
* Added ABSPATH checks to prevent direct file access
* Used more unique function name prefixes
* Fixed readme.txt tag issues

= 1.0.5 =
* Added support for Claude 3.5, Claude 3.7, and DeepSeek R1 models
* Added support for Amazon Nova models
* Fixed streaming response issues
* Improved error handling and debugging
* Fixed chat history issues
* Added customizable welcome message

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.6 =
This update improves security, fixes internationalization issues, and makes the plugin more compliant with WordPress coding standards.

= 1.0.5 =
This update adds support for the latest AI models and fixes several issues with streaming responses and chat history.

== Privacy Policy ==

This plugin does not collect any personal data. However, the conversations between users and the AI are processed through Amazon Bedrock, and AWS's privacy policy applies to that data. Please refer to AWS's privacy policy for more information.
