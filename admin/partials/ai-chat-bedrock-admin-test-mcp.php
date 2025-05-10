<?php
/**
 * Provide a admin area view for testing MCP functionality
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://github.com/noteflow-ai
 * @since      1.0.7
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <h2><?php esc_html_e('Test WordPress MCP Server', 'ai-chat-for-amazon-bedrock'); ?></h2>
    
    <p><?php esc_html_e('This page allows you to test the WordPress MCP Server functionality. You can register the local WordPress MCP server and test its tools.', 'ai-chat-for-amazon-bedrock'); ?></p>
    
    <div class="ai-chat-bedrock-mcp-test-section">
        <h3><?php esc_html_e('1. Register WordPress MCP Server', 'ai-chat-for-amazon-bedrock'); ?></h3>
        
        <p><?php esc_html_e('First, enable MCP in the settings and register the WordPress MCP Server:', 'ai-chat-for-amazon-bedrock'); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Enable MCP', 'ai-chat-for-amazon-bedrock'); ?></th>
                <td>
                    <input type="checkbox" id="ai_chat_bedrock_enable_mcp_test" <?php checked(get_option('ai_chat_bedrock_enable_mcp', false)); ?>>
                    <p class="description"><?php esc_html_e('Enable Model Context Protocol integration.', 'ai-chat-for-amazon-bedrock'); ?></p>
                </td>
            </tr>
        </table>
        
        <div id="ai-chat-bedrock-register-wp-mcp-server" class="<?php echo get_option('ai_chat_bedrock_enable_mcp', false) ? '' : 'hidden'; ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Server Name', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="text" id="ai_chat_bedrock_wp_mcp_server_name" value="wordpress" class="regular-text">
                        <p class="description"><?php esc_html_e('A unique name to identify the WordPress MCP server.', 'ai-chat-for-amazon-bedrock'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Server URL', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="url" id="ai_chat_bedrock_wp_mcp_server_url" value="<?php echo esc_url(rest_url('ai-chat-bedrock/v1')); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('The URL of the WordPress REST API.', 'ai-chat-for-amazon-bedrock'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p>
                <button type="button" id="ai_chat_bedrock_register_wp_mcp_server" class="button button-primary">
                    <?php esc_html_e('Register WordPress MCP Server', 'ai-chat-for-amazon-bedrock'); ?>
                </button>
            </p>
        </div>
    </div>
    
    <div class="ai-chat-bedrock-mcp-test-section">
        <h3><?php esc_html_e('2. Test WordPress MCP Tools', 'ai-chat-for-amazon-bedrock'); ?></h3>
        
        <p><?php esc_html_e('After registering the WordPress MCP Server, you can test its tools here:', 'ai-chat-for-amazon-bedrock'); ?></p>
        
        <div id="ai-chat-bedrock-test-wp-mcp-tools">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Select Tool', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <select id="ai_chat_bedrock_wp_mcp_tool" class="regular-text">
                            <option value="search_posts"><?php esc_html_e('Search Posts', 'ai-chat-for-amazon-bedrock'); ?></option>
                            <option value="get_post"><?php esc_html_e('Get Post', 'ai-chat-for-amazon-bedrock'); ?></option>
                            <option value="get_categories"><?php esc_html_e('Get Categories', 'ai-chat-for-amazon-bedrock'); ?></option>
                            <option value="get_tags"><?php esc_html_e('Get Tags', 'ai-chat-for-amazon-bedrock'); ?></option>
                            <option value="get_site_info"><?php esc_html_e('Get Site Info', 'ai-chat-for-amazon-bedrock'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <!-- Tool parameters will be dynamically loaded here -->
            <div id="ai-chat-bedrock-wp-mcp-tool-params"></div>
            
            <p>
                <button type="button" id="ai_chat_bedrock_test_wp_mcp_tool" class="button button-primary">
                    <?php esc_html_e('Test Tool', 'ai-chat-for-amazon-bedrock'); ?>
                </button>
            </p>
            
            <div id="ai-chat-bedrock-wp-mcp-tool-result" class="hidden">
                <h4><?php esc_html_e('Tool Result', 'ai-chat-for-amazon-bedrock'); ?></h4>
                <pre id="ai-chat-bedrock-wp-mcp-tool-result-content"></pre>
            </div>
        </div>
    </div>
    
    <div class="ai-chat-bedrock-mcp-test-section">
        <h3><?php esc_html_e('3. Test AI Chat with WordPress MCP', 'ai-chat-for-amazon-bedrock'); ?></h3>
        
        <p><?php esc_html_e('Test the AI chat with WordPress MCP integration. Ask questions about your WordPress content.', 'ai-chat-for-amazon-bedrock'); ?></p>
        
        <div class="ai-chat-bedrock-test-chat">
            <div class="ai-chat-bedrock-test-chat-header">
                <h4><?php esc_html_e('AI Chat with WordPress MCP', 'ai-chat-for-amazon-bedrock'); ?></h4>
            </div>
            
            <div id="ai-chat-bedrock-test-chat-messages" class="ai-chat-bedrock-test-chat-messages"></div>
            
            <div class="ai-chat-bedrock-test-chat-input">
                <textarea id="ai-chat-bedrock-test-chat-input" placeholder="<?php esc_attr_e('Type your message here...', 'ai-chat-for-amazon-bedrock'); ?>"></textarea>
                <button type="button" id="ai-chat-bedrock-test-chat-send" class="button button-primary">
                    <?php esc_html_e('Send', 'ai-chat-for-amazon-bedrock'); ?>
                </button>
            </div>
        </div>
        
        <p class="description">
            <?php esc_html_e('Example questions to try:', 'ai-chat-for-amazon-bedrock'); ?>
        </p>
        <ul class="ul-disc">
            <li><?php esc_html_e('What are the recent posts on this site?', 'ai-chat-for-amazon-bedrock'); ?></li>
            <li><?php esc_html_e('Show me information about this WordPress site.', 'ai-chat-for-amazon-bedrock'); ?></li>
            <li><?php esc_html_e('What categories are available on this site?', 'ai-chat-for-amazon-bedrock'); ?></li>
            <li><?php esc_html_e('Find posts about [topic].', 'ai-chat-for-amazon-bedrock'); ?></li>
        </ul>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Tool parameter templates
    const toolParams = {
        search_posts: `
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Search Query', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="text" id="param_query" class="regular-text" placeholder="<?php esc_attr_e('Enter search keywords', 'ai-chat-for-amazon-bedrock'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Category', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="text" id="param_category" class="regular-text" placeholder="<?php esc_attr_e('Category name or ID (optional)', 'ai-chat-for-amazon-bedrock'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tag', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="text" id="param_tag" class="regular-text" placeholder="<?php esc_attr_e('Tag name or ID (optional)', 'ai-chat-for-amazon-bedrock'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Author', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="text" id="param_author" class="regular-text" placeholder="<?php esc_attr_e('Author name or ID (optional)', 'ai-chat-for-amazon-bedrock'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Limit', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="number" id="param_limit" class="small-text" value="5" min="1" max="20">
                    </td>
                </tr>
            </table>
        `,
        get_post: `
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Post ID', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="number" id="param_id" class="regular-text" placeholder="<?php esc_attr_e('Enter post ID', 'ai-chat-for-amazon-bedrock'); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Post Slug', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="text" id="param_slug" class="regular-text" placeholder="<?php esc_attr_e('Or enter post slug', 'ai-chat-for-amazon-bedrock'); ?>">
                    </td>
                </tr>
            </table>
        `,
        get_categories: `
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Hide Empty', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="checkbox" id="param_hide_empty">
                        <label for="param_hide_empty"><?php esc_html_e('Hide empty categories', 'ai-chat-for-amazon-bedrock'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Limit', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="number" id="param_limit" class="small-text" value="10" min="1" max="100">
                    </td>
                </tr>
            </table>
        `,
        get_tags: `
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Hide Empty', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="checkbox" id="param_hide_empty">
                        <label for="param_hide_empty"><?php esc_html_e('Hide empty tags', 'ai-chat-for-amazon-bedrock'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Limit', 'ai-chat-for-amazon-bedrock'); ?></th>
                    <td>
                        <input type="number" id="param_limit" class="small-text" value="10" min="1" max="100">
                    </td>
                </tr>
            </table>
        `,
        get_site_info: `
            <p class="description"><?php esc_html_e('No parameters required for this tool.', 'ai-chat-for-amazon-bedrock'); ?></p>
        `
    };

    // Toggle MCP settings visibility
    $('#ai_chat_bedrock_enable_mcp_test').on('change', function() {
        const isEnabled = $(this).is(':checked');
        $('#ai-chat-bedrock-register-wp-mcp-server').toggleClass('hidden', !isEnabled);
        
        // Save the setting
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chat_bedrock_save_option',
                nonce: ai_chat_bedrock_admin.nonce,
                option_name: 'ai_chat_bedrock_enable_mcp',
                option_value: isEnabled ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.settings_saved, 'success');
                } else {
                    AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
            }
        });
    });

    // Register WordPress MCP Server
    $('#ai_chat_bedrock_register_wp_mcp_server').on('click', function() {
        const serverName = $('#ai_chat_bedrock_wp_mcp_server_name').val().trim();
        const serverUrl = $('#ai_chat_bedrock_wp_mcp_server_url').val().trim();
        
        if (!serverName || !serverUrl) {
            AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.missing_fields, 'error');
            return;
        }
        
        $(this).prop('disabled', true).text('<?php esc_html_e('Registering...', 'ai-chat-for-amazon-bedrock'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chat_bedrock_register_mcp_server',
                nonce: ai_chat_bedrock_admin.mcp_nonce,
                server_name: serverName,
                server_url: serverUrl
            },
            success: function(response) {
                $('#ai_chat_bedrock_register_wp_mcp_server').prop('disabled', false).text('<?php esc_html_e('Register WordPress MCP Server', 'ai-chat-for-amazon-bedrock'); ?>');
                
                if (response.success) {
                    AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                } else {
                    AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                }
            },
            error: function() {
                $('#ai_chat_bedrock_register_wp_mcp_server').prop('disabled', false).text('<?php esc_html_e('Register WordPress MCP Server', 'ai-chat-for-amazon-bedrock'); ?>');
                AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
            }
        });
    });

    // Load tool parameters when tool is selected
    $('#ai_chat_bedrock_wp_mcp_tool').on('change', function() {
        const tool = $(this).val();
        $('#ai-chat-bedrock-wp-mcp-tool-params').html(toolParams[tool] || '');
    }).trigger('change');

    // Test WordPress MCP tool
    $('#ai_chat_bedrock_test_wp_mcp_tool').on('click', function() {
        const tool = $('#ai_chat_bedrock_wp_mcp_tool').val();
        const params = {};
        
        // Collect parameters based on tool
        switch (tool) {
            case 'search_posts':
                params.query = $('#param_query').val();
                params.category = $('#param_category').val();
                params.tag = $('#param_tag').val();
                params.author = $('#param_author').val();
                params.limit = $('#param_limit').val();
                break;
            case 'get_post':
                params.id = $('#param_id').val();
                params.slug = $('#param_slug').val();
                break;
            case 'get_categories':
            case 'get_tags':
                params.hide_empty = $('#param_hide_empty').is(':checked');
                params.limit = $('#param_limit').val();
                break;
            case 'get_site_info':
                // No parameters needed
                break;
        }
        
        $(this).prop('disabled', true).text('<?php esc_html_e('Testing...', 'ai-chat-for-amazon-bedrock'); ?>');
        
        // Call the tool directly via REST API
        const serverName = $('#ai_chat_bedrock_wp_mcp_server_name').val().trim();
        const serverUrl = $('#ai_chat_bedrock_wp_mcp_server_url').val().trim();
        const toolUrl = serverUrl + '/mcp/tools/' + tool;
        
        $.ajax({
            url: toolUrl,
            type: 'POST',
            data: JSON.stringify(params),
            contentType: 'application/json',
            success: function(response) {
                $('#ai_chat_bedrock_test_wp_mcp_tool').prop('disabled', false).text('<?php esc_html_e('Test Tool', 'ai-chat-for-amazon-bedrock'); ?>');
                
                // Display the result
                $('#ai-chat-bedrock-wp-mcp-tool-result').removeClass('hidden');
                $('#ai-chat-bedrock-wp-mcp-tool-result-content').text(JSON.stringify(response, null, 2));
            },
            error: function(xhr) {
                $('#ai_chat_bedrock_test_wp_mcp_tool').prop('disabled', false).text('<?php esc_html_e('Test Tool', 'ai-chat-for-amazon-bedrock'); ?>');
                
                // Display the error
                $('#ai-chat-bedrock-wp-mcp-tool-result').removeClass('hidden');
                $('#ai-chat-bedrock-wp-mcp-tool-result-content').text('Error: ' + xhr.status + ' ' + xhr.statusText);
            }
        });
    });

    // Test chat functionality
    const $chatMessages = $('#ai-chat-bedrock-test-chat-messages');
    const $chatInput = $('#ai-chat-bedrock-test-chat-input');
    const $chatSend = $('#ai-chat-bedrock-test-chat-send');
    let chatHistory = [];
    
    // Add welcome message
    $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">Welcome! Ask me questions about your WordPress site content.</div></div>');
    
    // Send message
    $chatSend.on('click', sendChatMessage);
    $chatInput.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatMessage();
        }
    });
    
    function sendChatMessage() {
        const message = $chatInput.val().trim();
        
        if (!message) {
            return;
        }
        
        // Add user message to chat
        $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-user">' + escapeHtml(message) + '</div></div>');
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
        
        // Clear input
        $chatInput.val('');
        
        // Add loading indicator
        const $loadingMessage = $('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant"><span class="ai-chat-bedrock-loading"></span>Thinking...</div></div>');
        $chatMessages.append($loadingMessage);
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
        
        // Disable send button
        $chatSend.prop('disabled', true);
        
        // Send message to AI
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ai_chat_bedrock_message',
                nonce: ai_chat_bedrock_admin.nonce,
                message: message,
                history: JSON.stringify(chatHistory)
            },
            success: function(response) {
                // Remove loading indicator
                $loadingMessage.remove();
                
                if (response.success) {
                    // Check if response contains tool calls
                    if (response.tool_calls && response.tool_calls.length > 0) {
                        // Show tool usage message
                        const toolName = response.tool_calls[0].name.split('___')[1];
                        const toolMessage = response.data.message || 'Using WordPress tools to find information...';
                        
                        const $toolMessage = $('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">' + 
                            formatMessage(toolMessage) + 
                            '<div class="ai-chat-bedrock-tool-indicator">' +
                            '<span class="ai-chat-bedrock-tool-name">Using tool: ' + escapeHtml(toolName) + '</span>' +
                            '<div class="ai-chat-bedrock-tool-loading"><span class="ai-chat-bedrock-loading"></span></div>' +
                            '</div></div></div>');
                        
                        $chatMessages.append($toolMessage);
                        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
                        
                        // Process tool results
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ai_chat_bedrock_tool_results',
                                nonce: ai_chat_bedrock_admin.nonce,
                                tool_calls: JSON.stringify(response.tool_calls),
                                original_message: message,
                                history: JSON.stringify(chatHistory)
                            },
                            success: function(finalResponse) {
                                // Update tool indicator
                                $toolMessage.find('.ai-chat-bedrock-tool-indicator').html(
                                    '<div class="ai-chat-bedrock-tool-result">' +
                                    '<span class="ai-chat-bedrock-tool-success">✓ Tool used successfully</span>' +
                                    '</div>'
                                );
                                
                                if (finalResponse.success) {
                                    // Add final AI response
                                    const finalMessage = finalResponse.data.message || 'Here are the results.';
                                    $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">' + 
                                        formatMessage(finalMessage) + '</div></div>');
                                    
                                    // Update chat history
                                    chatHistory.push({ role: 'user', content: message });
                                    chatHistory.push({ role: 'assistant', content: toolMessage });
                                    chatHistory.push({ role: 'assistant', content: finalMessage });
                                } else {
                                    // Show error
                                    const errorMessage = finalResponse.data.message || 'Error processing tool results.';
                                    $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">Error: ' + 
                                        escapeHtml(errorMessage) + '</div></div>');
                                }
                                
                                // Scroll to bottom
                                $chatMessages.scrollTop($chatMessages[0].scrollHeight);
                            },
                            error: function() {
                                // Update tool indicator with error
                                $toolMessage.find('.ai-chat-bedrock-tool-indicator').html(
                                    '<div class="ai-chat-bedrock-tool-result">' +
                                    '<span class="ai-chat-bedrock-tool-error">✗ Tool error</span>' +
                                    '</div>'
                                );
                                
                                // Show error message
                                $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">Error: Could not process tool results.</div></div>');
                                
                                // Scroll to bottom
                                $chatMessages.scrollTop($chatMessages[0].scrollHeight);
                            }
                        });
                    } else {
                        // Regular response without tool calls
                        const aiMessage = response.data.message || 'Sorry, I couldn\'t process your request.';
                        $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">' + formatMessage(aiMessage) + '</div></div>');
                        
                        // Update chat history
                        chatHistory.push({ role: 'user', content: message });
                        chatHistory.push({ role: 'assistant', content: aiMessage });
                    }
                    
                    // Limit history size
                    if (chatHistory.length > 20) {
                        chatHistory = chatHistory.slice(-20);
                    }
                } else {
                    // Show error message
                    const errorMessage = response.data.message || 'An error occurred.';
                    $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">Error: ' + escapeHtml(errorMessage) + '</div></div>');
                }
                
                // Scroll to bottom
                $chatMessages.scrollTop($chatMessages[0].scrollHeight);
                
                // Enable send button
                $chatSend.prop('disabled', false);
            },
            error: function() {
                // Remove loading indicator
                $loadingMessage.remove();
                
                // Show error message
                $chatMessages.append('<div class="ai-chat-bedrock-message"><div class="ai-chat-bedrock-message-assistant">Error: Could not connect to the server.</div></div>');
                
                // Scroll to bottom
                $chatMessages.scrollTop($chatMessages[0].scrollHeight);
                
                // Enable send button
                $chatSend.prop('disabled', false);
            }
        });
    }
    
    // Helper functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatMessage(text) {
        // Convert line breaks to <br>
        text = text.replace(/\n/g, '<br>');
        
        // Simple markdown-like formatting
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
        text = text.replace(/`(.*?)`/g, '<code>$1</code>');
        
        return text;
    }
});
</script>

<style>
/* Additional styles for the test page */
.ai-chat-bedrock-mcp-test-section {
    margin-top: 30px;
    padding: 20px;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
}

#ai-chat-bedrock-wp-mcp-tool-result {
    margin-top: 20px;
    padding: 15px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 3px;
}

#ai-chat-bedrock-wp-mcp-tool-result-content {
    max-height: 300px;
    overflow: auto;
    background-color: #f1f1f1;
    padding: 10px;
    border-radius: 3px;
    font-family: monospace;
    white-space: pre-wrap;
}
</style>
