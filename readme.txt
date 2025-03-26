=== AI Chat for Amazon Bedrock ===
Contributors: amazonwebservices
Tags: ai, chatbot, amazon, bedrock, claude, llama, titan, chat, conversation
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Add AI-powered chat functionality to your WordPress site using Amazon Bedrock AI models.

== Description ==

AI Chat for Amazon Bedrock allows you to integrate Amazon's powerful AI models into your WordPress site with an easy-to-use chat interface. This plugin supports multiple AI models from Amazon Bedrock including Claude, Titan, and Llama.

### Key Features

* **AI Chat Widget**: Add an AI-powered chat widget to your website for visitors to interact with
* **Multiple AI Models**: Support for various Amazon Bedrock models including Claude, Titan, and Llama
* **Conversation Management**: Save and manage conversations between users and AI models
* **Shortcode Support**: Easily embed AI chat functionality anywhere on your site using shortcodes
* **Customizable Settings**: Configure AWS credentials, model parameters, and system prompts

### Use Cases

* **Customer Support**: Provide instant answers to common customer questions
* **Content Assistance**: Help users find information on your website
* **Educational Tools**: Create interactive learning experiences
* **Creative Writing**: Generate ideas, stories, or content suggestions
* **Product Recommendations**: Suggest products based on user preferences

### Requirements

* WordPress 5.0 or higher
* PHP 7.4 or higher
* AWS account with access to Amazon Bedrock service

== Installation ==

1. Upload the `ai-chat-for-amazon-bedrock` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'AI Chat' menu in your WordPress admin
4. Configure your AWS credentials and settings
5. Add the chat widget to your site using the shortcode `[aichat_bedrock]`

== Frequently Asked Questions ==

= Which AI models are supported? =

The plugin supports various Amazon Bedrock models including:
* Claude 3 Sonnet
* Claude 3 Haiku
* Claude Instant
* Amazon Titan Text Express
* Meta Llama 2

= How do I get AWS credentials for Amazon Bedrock? =

You need to create an IAM user in your AWS account with permissions to access Amazon Bedrock. See the [AWS documentation](https://docs.aws.amazon.com/bedrock/latest/userguide/security-iam.html) for more details.

= How do I add the chat widget to my site? =

You can add the chat widget to any page or post using the shortcode `[aichat_bedrock]`. You can also customize it with additional parameters:

`[aichat_bedrock model="anthropic.claude-3-sonnet-20240229-v1:0" placeholder="Ask Claude..." button_text="Ask" welcome_message="Hello! I'm Claude. How can I assist you today?"]`

= Can I customize the appearance of the chat widget? =

Yes, you can customize the appearance using CSS. The plugin includes basic styling, but you can override it with your own CSS rules.

= Does this plugin store conversations? =

Yes, conversations are stored in your WordPress database so you can review them later. Each conversation is linked to the user who initiated it.

= Is this plugin GDPR compliant? =

The plugin stores conversation data in your WordPress database. You should include information about this data collection in your privacy policy to ensure GDPR compliance.

== Screenshots ==

1. AI Chat widget on the frontend
2. Admin settings page
3. Conversation management interface
4. Chat in action with Claude AI

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of AI Chat for Amazon Bedrock.
