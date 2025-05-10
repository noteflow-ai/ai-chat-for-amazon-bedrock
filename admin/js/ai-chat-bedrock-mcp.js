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
            this.bindEvents();
            this.loadServers();
        },

        /**
         * Bind events for MCP admin functionality.
         */
        bindEvents: function() {
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
        },

        /**
         * Load registered MCP servers.
         */
        loadServers: function() {
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_chat_bedrock_get_mcp_servers',
                    nonce: ai_chat_bedrock_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.servers) {
                        AIChatBedrockMCP.renderServers(response.data.servers);
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
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
            const $tbody = $('#ai-chat-bedrock-mcp-servers-table tbody');
            $tbody.empty();
            
            if (Object.keys(servers).length === 0) {
                $tbody.html('<tr class="no-items"><td colspan="5">' + ai_chat_bedrock_admin.i18n.no_servers + '</td></tr>');
                return;
            }
            
            const template = $('#ai-chat-bedrock-mcp-server-row-template').html();
            
            $.each(servers, function(name, server) {
                const statusClass = server.available ? 'status-available' : 'status-unavailable';
                const statusText = server.available ? ai_chat_bedrock_admin.i18n.available : ai_chat_bedrock_admin.i18n.unavailable;
                
                let html = template
                    .replace(/{{server_name}}/g, name)
                    .replace(/{{server_url}}/g, server.url)
                    .replace(/{{status_class}}/g, statusClass)
                    .replace(/{{status_text}}/g, statusText);
                
                $tbody.append(html);
            });
        },

        /**
         * Add a new MCP server.
         */
        addServer: function() {
            const serverName = $('#ai_chat_bedrock_mcp_server_name').val().trim();
            const serverUrl = $('#ai_chat_bedrock_mcp_server_url').val().trim();
            
            if (!serverName || !serverUrl) {
                AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.missing_fields, 'error');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_register_mcp_server',
                    nonce: ai_chat_bedrock_admin.nonce,
                    server_name: serverName,
                    server_url: serverUrl
                },
                beforeSend: function() {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', true).text(ai_chat_bedrock_admin.i18n.adding);
                },
                success: function(response) {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', false).text(ai_chat_bedrock_admin.i18n.add_server);
                    
                    if (response.success) {
                        $('#ai_chat_bedrock_mcp_server_name').val('');
                        $('#ai_chat_bedrock_mcp_server_url').val('');
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', false).text(ai_chat_bedrock_admin.i18n.add_server);
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
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_unregister_mcp_server',
                    nonce: ai_chat_bedrock_admin.nonce,
                    server_name: serverName
                },
                beforeSend: function() {
                    $(this).prop('disabled', true).text(ai_chat_bedrock_admin.i18n.removing);
                },
                success: function(response) {
                    if (response.success) {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                },
                complete: function() {
                    $(this).prop('disabled', false).text(ai_chat_bedrock_admin.i18n.remove);
                }
            });
        },

        /**
         * View MCP server tools.
         */
        viewTools: function() {
            const serverName = $(this).data('server');
            
            // Show modal
            $('#ai-chat-bedrock-mcp-tools-modal').show();
            $('#ai-chat-bedrock-mcp-tools-list').html('<p>' + ai_chat_bedrock_admin.i18n.loading_tools + '</p>');
            
            // Load tools
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_chat_bedrock_get_mcp_servers',
                    nonce: ai_chat_bedrock_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.servers && response.data.servers[serverName]) {
                        const server = response.data.servers[serverName];
                        AIChatBedrockMCP.renderTools(server.tools || []);
                    } else {
                        $('#ai-chat-bedrock-mcp-tools-list').html('<p>' + ai_chat_bedrock_admin.i18n.no_tools + '</p>');
                    }
                },
                error: function() {
                    $('#ai-chat-bedrock-mcp-tools-list').html('<p>' + ai_chat_bedrock_admin.i18n.ajax_error + '</p>');
                }
            });
        },

        /**
         * Refresh MCP server tools.
         */
        refreshTools: function() {
            const serverName = $(this).data('server');
            const $button = $(this);
            
            $button.prop('disabled', true).text(ai_chat_bedrock_admin.i18n.refreshing);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_discover_mcp_tools',
                    nonce: ai_chat_bedrock_admin.nonce,
                    server_name: serverName
                },
                success: function(response) {
                    $button.prop('disabled', false).text(ai_chat_bedrock_admin.i18n.refresh);
                    
                    if (response.success) {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(ai_chat_bedrock_admin.i18n.refresh);
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                }
            });
        },

        /**
         * Render MCP tools in modal.
         *
         * @param {Array} tools The tools data.
         */
        renderTools: function(tools) {
            const $container = $('#ai-chat-bedrock-mcp-tools-list');
            $container.empty();
            
            if (tools.length === 0) {
                $container.html('<p>' + ai_chat_bedrock_admin.i18n.no_tools + '</p>');
                return;
            }
            
            const template = $('#ai-chat-bedrock-mcp-tool-item-template').html();
            
            tools.forEach(function(tool) {
                let parametersHtml = '';
                
                if (tool.parameters && tool.parameters.properties) {
                    const properties = tool.parameters.properties;
                    
                    for (const key in properties) {
                        if (properties.hasOwnProperty(key)) {
                            const param = properties[key];
                            parametersHtml += '<li><strong>' + key + '</strong>: ' + 
                                (param.description || '') + 
                                (param.required ? ' <span class="required">*</span>' : '') + 
                                '</li>';
                        }
                    }
                }
                
                if (!parametersHtml) {
                    parametersHtml = '<li>' + ai_chat_bedrock_admin.i18n.no_parameters + '</li>';
                }
                
                let html = template
                    .replace(/{{name}}/g, tool.name)
                    .replace(/{{description}}/g, tool.description || '')
                    .replace(/{{parameters}}/g, parametersHtml);
                
                $container.append(html);
            });
        },

        /**
         * Close the modal.
         */
        closeModal: function() {
            $('.ai-chat-bedrock-modal').hide();
        }
    };

    $(document).ready(function() {
        AIChatBedrockMCP.init();
    });

})(jQuery);
