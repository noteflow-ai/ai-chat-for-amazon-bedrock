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
                error: function(xhr, status, error) {
                    console.error('AJAX error:', status, error);
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
                data: {
                    action: 'ai_chat_bedrock_get_mcp_servers',
                    nonce: ai_chat_bedrock_admin.mcp_nonce
                },
                success: function(response) {
                    console.log('MCP servers response:', response);
                    
                    if (response.success && response.data.servers) {
                        AIChatBedrockMCP.renderServers(response.data.servers);
                    } else {
                        console.error('Failed to load MCP servers:', response);
                        // Don't show error notice for empty servers
                        if (response.data && response.data.message) {
                            AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error loading servers:', status, error);
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
            
            const $tbody = $('#ai-chat-bedrock-mcp-servers-table tbody');
            $tbody.empty();
            
            if (!servers || Object.keys(servers).length === 0) {
                $tbody.html('<tr class="no-items"><td colspan="5">No MCP servers registered yet.</td></tr>');
                return;
            }
            
            // Create rows directly without using a template
            $.each(servers, function(name, server) {
                const statusClass = server.available ? 'status-available' : 'status-unavailable';
                const statusText = server.available ? 'Available' : 'Unavailable';
                
                const $row = $('<tr>').attr('data-server-name', name);
                $row.append($('<td>').text(name));
                $row.append($('<td>').text(server.url));
                $row.append($('<td>').append(
                    $('<span>').addClass('ai-chat-bedrock-server-status ' + statusClass).text(statusText)
                ));
                
                const $toolsCell = $('<td>');
                $toolsCell.append(
                    $('<button>').attr({
                        'type': 'button',
                        'class': 'button ai-chat-bedrock-view-tools',
                        'data-server': name
                    }).text('View Tools')
                );
                $toolsCell.append(' ');
                $toolsCell.append(
                    $('<button>').attr({
                        'type': 'button',
                        'class': 'button ai-chat-bedrock-refresh-tools',
                        'data-server': name
                    }).text('Refresh')
                );
                $row.append($toolsCell);
                
                $row.append($('<td>').append(
                    $('<button>').attr({
                        'type': 'button',
                        'class': 'button ai-chat-bedrock-remove-server',
                        'data-server': name
                    }).text('Remove')
                ));
                
                $tbody.append($row);
            });
        },

        /**
         * Add a new MCP server.
         */
        addServer: function() {
            const serverName = $('#ai_chat_bedrock_mcp_server_name').val().trim();
            const serverUrl = $('#ai_chat_bedrock_mcp_server_url').val().trim();
            
            console.log('Adding MCP server:', serverName, serverUrl);
            
            if (!serverName || !serverUrl) {
                AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.missing_fields, 'error');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_register_mcp_server',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName,
                    server_url: serverUrl
                },
                beforeSend: function() {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', true).text(ai_chat_bedrock_admin.i18n.adding);
                },
                success: function(response) {
                    $('#ai_chat_bedrock_add_mcp_server').prop('disabled', false).text(ai_chat_bedrock_admin.i18n.add_server);
                    
                    console.log('Add server response:', response);
                    
                    if (response.success) {
                        $('#ai_chat_bedrock_mcp_server_name').val('');
                        $('#ai_chat_bedrock_mcp_server_url').val('');
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error adding server:', status, error);
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
            const $button = $(this);
            
            console.log('Removing MCP server:', serverName);
            
            if (!confirm(ai_chat_bedrock_admin.i18n.confirm_remove_server)) {
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_unregister_mcp_server',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName
                },
                beforeSend: function() {
                    $button.prop('disabled', true).text(ai_chat_bedrock_admin.i18n.removing);
                },
                success: function(response) {
                    console.log('Remove server response:', response);
                    
                    if (response.success) {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error removing server:', status, error);
                    AIChatBedrockAdmin.showNotice(ai_chat_bedrock_admin.i18n.ajax_error, 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text(ai_chat_bedrock_admin.i18n.remove);
                }
            });
        },

        /**
         * View MCP server tools.
         */
        viewTools: function() {
            const serverName = $(this).data('server');
            
            console.log('Viewing tools for server:', serverName);
            
            // Show modal
            $('#ai-chat-bedrock-mcp-tools-modal').show();
            $('#ai-chat-bedrock-mcp-tools-list').html('<p>' + ai_chat_bedrock_admin.i18n.loading_tools + '</p>');
            
            // Load tools
            $.ajax({
                url: ajaxurl,
                type: 'GET',
                data: {
                    action: 'ai_chat_bedrock_get_mcp_servers',
                    nonce: ai_chat_bedrock_admin.mcp_nonce
                },
                success: function(response) {
                    console.log('Get servers for tools response:', response);
                    
                    if (response.success && response.data.servers && response.data.servers[serverName]) {
                        const server = response.data.servers[serverName];
                        AIChatBedrockMCP.renderTools(server.tools || []);
                    } else {
                        $('#ai-chat-bedrock-mcp-tools-list').html('<p>' + ai_chat_bedrock_admin.i18n.no_tools + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting tools:', status, error);
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
            
            console.log('Refreshing tools for server:', serverName);
            
            $button.prop('disabled', true).text(ai_chat_bedrock_admin.i18n.refreshing);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_chat_bedrock_discover_mcp_tools',
                    nonce: ai_chat_bedrock_admin.mcp_nonce,
                    server_name: serverName
                },
                success: function(response) {
                    console.log('Refresh tools response:', response);
                    
                    $button.prop('disabled', false).text(ai_chat_bedrock_admin.i18n.refresh);
                    
                    if (response.success) {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'success');
                        AIChatBedrockMCP.loadServers();
                    } else {
                        AIChatBedrockAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error refreshing tools:', status, error);
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
            console.log('Rendering tools:', tools);
            
            const $container = $('#ai-chat-bedrock-mcp-tools-list');
            $container.empty();
            
            if (!tools || tools.length === 0) {
                $container.html('<p>No tools found for this server.</p>');
                return;
            }
            
            tools.forEach(function(tool) {
                const $toolItem = $('<div>').addClass('ai-chat-bedrock-mcp-tool-item');
                $toolItem.append($('<h4>').text(tool.name || 'Unnamed Tool'));
                $toolItem.append($('<p>').addClass('description').text(tool.description || 'No description available'));
                
                const $parametersSection = $('<div>').addClass('ai-chat-bedrock-mcp-tool-parameters');
                $parametersSection.append($('<h5>').text('Parameters'));
                
                const $parametersList = $('<ul>');
                
                if (tool.parameters && tool.parameters.properties) {
                    const properties = tool.parameters.properties;
                    
                    for (const key in properties) {
                        if (properties.hasOwnProperty(key)) {
                            const param = properties[key];
                            const $paramItem = $('<li>');
                            const $paramName = $('<strong>').text(key);
                            $paramItem.append($paramName);
                            $paramItem.append(': ' + (param.description || ''));
                            
                            if (param.required) {
                                $paramItem.append(' ');
                                $paramItem.append($('<span>').addClass('required').text('*'));
                            }
                            
                            $parametersList.append($paramItem);
                        }
                    }
                }
                
                if ($parametersList.children().length === 0) {
                    $parametersList.append($('<li>').text('No parameters required'));
                }
                
                $parametersSection.append($parametersList);
                $toolItem.append($parametersSection);
                
                $container.append($toolItem);
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
        console.log('Document ready, initializing MCP');
        AIChatBedrockMCP.init();
    });

})(jQuery);
