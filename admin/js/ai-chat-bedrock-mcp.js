/**
 * MCP functionality for the admin area.
 *
 * @link       https://github.com/noteflow-ai
 * @since      1.0.7
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin/js
 */

(function($) {
    'use strict';

    /**
     * MCP Admin functionality.
     */
    const AIChatBedrockMCP = {
        /**
         * Initialize the MCP admin functionality.
         */
        init: function() {
            console.log('Initializing MCP admin functionality');
            this.bindEvents();
            this.loadServers();
        },

        /**
         * Bind events for MCP admin functionality.
         */
        bindEvents: function() {
            console.log('Binding MCP events');
            
            // Toggle MCP settings visibility
            $('#ai_chat_bedrock_enable_mcp').on('change', this.toggleMCPSettings);
            
            // Add MCP server
            $('#ai_chat_bedrock_add_mcp_server').on('click', this.addServer);
            
            // Server actions (delegated events)
            $('#ai-chat-bedrock-mcp-servers-table').on('click', '.ai-chat-bedrock-remove-server', this.removeServer);
            $('#ai-chat-bedrock-mcp-servers-table').on('click', '.ai-chat-bedrock-view-tools', this.viewTools);
            $('#ai-chat-bedrock-mcp-servers-table').on('click', '.ai-chat-bedrock-refresh-tools', this.refreshTools);
            
            // Modal close button
            $('.ai-chat-bedrock-modal-close').on('click', this.closeModal);
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('ai-chat-bedrock-modal')) {
                    $('.ai-chat-bedrock-modal').hide();
                }
            });
        },

        /**
         * Toggle MCP settings visibility.
         */
        toggleMCPSettings: function() {
            const isEnabled = $(this).is(':checked');
            $('#ai-chat-bedrock-mcp-servers-section').toggleClass('hidden', !isEnabled);
            
            console.log('Toggle MCP settings:', isEnabled);
            
            // 添加调试信息
            console.log('Using nonce:', ai_chat_bedrock_admin.nonce);
            
            // 保存设置
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ai_chat_bedrock_save_option',
                    nonce: ai_chat_bedrock_admin.nonce,
                    option_name: 'ai_chat_bedrock_enable_mcp',
                    option_value: isEnabled ? '1' : '0'
                },
                success: function(response) {
                    console.log('AJAX success response:', response);
                    if (response.success) {
                        AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.settings_saved, 'success');
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
                    console.error('Response text:', xhr.responseText);
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }
            });
        },

        /**
         * Load registered MCP servers.
         */
        loadServers: function() {
            console.log('Loading MCP servers');
            
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'ai_chat_bedrock_get_mcp_servers',
                    nonce: ai_chat_bedrock_admin.mcp_nonce
                },
                success: function(response) {
                    console.log('MCP servers response:', response);
                    
                    if (response && response.success) {
                        // Check what data structure we're getting
                        console.log('Response data structure:', typeof response.data);
                        
                        let serversData = {};
                        
                        // Handle different possible data structures
                        if (response.data && response.data.servers) {
                            // If data.servers exists, use it
                            serversData = response.data.servers;
                        } else if (Array.isArray(response.data)) {
                            // If data is an array, convert to object
                            console.log('Converting array to object');
                            response.data.forEach(function(server, index) {
                                serversData['server_' + index] = server;
                            });
                        } else if (typeof response.data === 'object') {
                            // If data is already an object, use it directly
                            serversData = response.data;
                        }
                        
                        console.log('Processed servers data:', serversData);
                        AIChatBedrockMCP.renderServers(serversData);
                    } else {
                        const errorMsg = response && response.data && response.data.message ? 
                            response.data.message : ai_chat_bedrock_admin.i18n.ajax_error;
                        AIChatBedrockAdmin.showNotice(errorMsg, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }
            });
        },

        /**
         * Render MCP servers table.
         * 
         * @param {Object} servers The servers data.
         */
        renderServers: function(servers) {
            console.log('Rendering MCP servers:', servers);
            
            const $table = $('#ai-chat-bedrock-mcp-servers-table tbody');
            $table.empty();
            
            if (!servers || Object.keys(servers).length === 0) {
                $table.append('<tr><td colspan="5">' + ai_chat_bedrock_admin.i18n.no_servers + '</td></tr>');
                return;
            }
            
            // Get the template and check if it exists
            let template = $('#ai-chat-bedrock-mcp-server-row-template').html();
            
            // If template is not found, create a default one
            if (!template) {
                console.warn('Server row template not found, using default template');
                template = `
                    <tr data-server-name="{{server_name}}">
                        <td>{{server_name}}</td>
                        <td>{{server_url}}</td>
                        <td>
                            <span class="ai-chat-bedrock-server-status {{status_class}}">
                                {{status_text}}
                            </span>
                        </td>
                        <td>
                            <button type="button" class="button ai-chat-bedrock-view-tools" data-server="{{server_name}}">
                                View Tools
                            </button>
                            <button type="button" class="button ai-chat-bedrock-refresh-tools" data-server="{{server_name}}">
                                Refresh
                            </button>
                        </td>
                        <td>
                            <button type="button" class="button ai-chat-bedrock-remove-server" data-server="{{server_name}}">
                                Remove
                            </button>
                        </td>
                    </tr>
                `;
            }
            
            for (const serverName in servers) {
                const server = servers[serverName];
                if (!server || typeof server !== 'object') {
                    console.error('Invalid server data for:', serverName);
                    continue;
                }
                
                const status = server.status || 'unknown';
                const statusClass = status === 'available' ? 'available' : 'unavailable';
                const statusText = status === 'available' ? ai_chat_bedrock_admin.i18n.available : ai_chat_bedrock_admin.i18n.unavailable;
                
                try {
                    let html = template
                        .replace(/{{server_name}}/g, serverName)
                        .replace(/{{server_url}}/g, server.url || '')
                        .replace(/{{status_class}}/g, statusClass)
                        .replace(/{{status_text}}/g, statusText);
                    
                    $table.append(html);
                } catch (error) {
                    console.error('Error rendering server row:', error);
                }
            }
        },

        /**
         * Add a new MCP server.
         */
        addServer: function() {
            console.log('Add server button clicked');
            
            // Try different possible field IDs
            const $serverName = $('#ai_chat_bedrock_server_name, #ai_chat_bedrock_mcp_server_name');
            const $serverUrl = $('#ai_chat_bedrock_server_url, #ai_chat_bedrock_mcp_server_url');
            
            console.log('Server name field found:', $serverName.length > 0);
            console.log('Server URL field found:', $serverUrl.length > 0);
            
            // Check if elements exist
            if (!$serverName.length || !$serverUrl.length) {
                console.error('Server name or URL input fields not found');
                console.log('Available form fields:', $('input[type="text"], input[type="url"]').map(function() {
                    return this.id;
                }).get());
                AIChatBedrockAdmin.showNotice('Form fields not found', 'error');
                return;
            }
            
            const serverName = $serverName.val() ? $serverName.val().trim() : '';
            const serverUrl = $serverUrl.val() ? $serverUrl.val().trim() : '';
            
            if (!serverName || !serverUrl) {
                AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.missing_fields, 'error');
                return;
            }
            
            $('#ai_chat_bedrock_add_mcp_server').prop('disabled', true).text(ai_chat_bedrock_admin.i18n.adding);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ai_chat_bedrock_register_mcp_server',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName,
                    server_url: serverUrl
                },
                success: function(response) {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', false).text(ai_chat_bedrock_admin.i18n.add_server);
                    
                    if (response.success) {
                        // Clear form fields
                        $serverName.val('');
                        $serverUrl.val('');
                        
                        // Show success message
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        
                        // Reload servers
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', false).text(ai_chat_bedrock_admin.i18n.add_server);
                    console.error('AJAX error details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }
            });
        },

        /**
         * Remove an MCP server.
         */
        removeServer: function() {
            const serverName = $(this).data('server');
            
            if (!confirm(ai_chat_bedrock_admin.i18n.confirm_remove_server)) {
                return;
            }
            
            $(this).prop('disabled', true).text(ai_chat_bedrock_admin.i18n.removing);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_unregister_mcp_server',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        
                        // Reload servers
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }
            });
        },

        /**
         * View tools for an MCP server.
         */
        viewTools: function() {
            const serverName = $(this).data('server');
            
            $(this).prop('disabled', true).text(ai_chat_bedrock_admin.i18n.loading_tools);
            
            console.log('Viewing tools for server:', serverName);
            console.log('Using MCP nonce:', ai_chat_bedrock_admin.mcp_nonce);
            
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                dataType: 'json',
                data: {
                    action: 'ai_chat_bedrock_discover_mcp_tools',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName
                },
                success: function(response) {
                    $(this).prop('disabled', false).text(ai_chat_bedrock_admin.i18n.view_tools);
                    console.log('View tools response:', response);
                    
                    if (response.success) {
                        AIChatBedrockMCP.renderTools(serverName, response.data);
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    $(this).prop('disabled', false).text(ai_chat_bedrock_admin.i18n.view_tools);
                    console.error('AJAX error details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }.bind(this)
            });
        },

        /**
         * Refresh tools for an MCP server.
         */
        refreshTools: function() {
            const serverName = $(this).data('server');
            
            console.log('Refreshing tools for server:', serverName);
            console.log('Using MCP nonce:', ai_chat_bedrock_admin.mcp_nonce);
            
            $(this).prop('disabled', true).text(ai_chat_bedrock_admin.i18n.refreshing);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'ai_chat_bedrock_discover_mcp_tools',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName,
                    refresh: true
                },
                success: function(response) {
                    console.log('Refresh tools response:', response);
                    
                    $(this).prop('disabled', false).text(ai_chat_bedrock_admin.i18n.refresh);
                    
                    if (response.success) {
                        // Reload servers to update status
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                }.bind(this),
                error: function(xhr, status, error) {
                    $(this).prop('disabled', false).text(ai_chat_bedrock_admin.i18n.refresh);
                    console.error('AJAX error details:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }.bind(this)
            });
        },

        /**
         * Render tools for an MCP server.
         * 
         * @param {string} serverName The server name.
         * @param {Object} data The tools data.
         */
        renderTools: function(serverName, data) {
            const $modal = $('#ai-chat-bedrock-mcp-tools-modal');
            const $content = $modal.find('.ai-chat-bedrock-modal-content');
            
            // Set modal title
            $modal.find('.ai-chat-bedrock-modal-title').text(ai_chat_bedrock_admin.i18n.tools_for + ' ' + serverName);
            
            // Clear content
            $content.empty();
            
            // Check if tools exist
            if (!data.tools || data.tools.length === 0) {
                $content.append('<p>' + ai_chat_bedrock_admin.i18n.no_tools + '</p>');
                $modal.show();
                return;
            }
            
            // Render tools
            const template = $('#ai-chat-bedrock-mcp-tool-item-template').html();
            
            for (const tool of data.tools) {
                let html = template
                    .replace(/{{name}}/g, tool.name)
                    .replace(/{{description}}/g, tool.description);
                
                const $tool = $(html);
                const $params = $tool.find('.ai-chat-bedrock-mcp-tool-parameters');
                
                // Add parameters
                if (tool.parameters && tool.parameters.properties && Object.keys(tool.parameters.properties).length > 0) {
                    const $paramsList = $('<ul class="ai-chat-bedrock-mcp-tool-parameters-list"></ul>');
                    
                    for (const paramName in tool.parameters.properties) {
                        const param = tool.parameters.properties[paramName];
                        const required = tool.parameters.required && tool.parameters.required.includes(paramName);
                        
                        $paramsList.append(
                            '<li>' +
                            '<strong>' + paramName + '</strong>' +
                            (required ? ' <span class="required">*</span>' : '') +
                            ': ' + param.description +
                            ' (' + param.type + ')' +
                            '</li>'
                        );
                    }
                    
                    $params.append($paramsList);
                } else {
                    $params.append('<p>' + ai_chat_bedrock_admin.i18n.no_parameters + '</p>');
                }
                
                $content.append($tool);
            }
            
            // Show modal
            $modal.show();
        },

        /**
         * Close modal.
         */
        closeModal: function() {
            $('.ai-chat-bedrock-modal').hide();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        console.log('Document ready, initializing MCP');
        AIChatBedrockMCP.init();
    });

})(jQuery);
