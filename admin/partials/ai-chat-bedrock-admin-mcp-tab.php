<?php
/**
 * Provide a admin area view for the MCP settings tab
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

<div class="ai-chat-bedrock-mcp-settings">
    <h2><?php esc_html_e('Model Context Protocol (MCP) Settings', 'ai-chat-for-amazon-bedrock'); ?></h2>
    
    <p class="description">
        <?php esc_html_e('MCP is an open protocol that standardizes how applications provide context to LLMs. Enable MCP to extend your AI chat capabilities with external tools and resources.', 'ai-chat-for-amazon-bedrock'); ?>
    </p>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="ai_chat_bedrock_enable_mcp"><?php esc_html_e('Enable MCP', 'ai-chat-for-amazon-bedrock'); ?></label>
            </th>
            <td>
                <input type="checkbox" id="ai_chat_bedrock_enable_mcp" name="ai_chat_bedrock_enable_mcp" value="1" <?php checked(get_option('ai_chat_bedrock_enable_mcp', false)); ?> />
                <p class="description"><?php esc_html_e('Enable Model Context Protocol integration to extend AI capabilities with external tools.', 'ai-chat-for-amazon-bedrock'); ?></p>
            </td>
        </tr>
    </table>

    <div id="ai-chat-bedrock-mcp-servers-section" class="<?php echo get_option('ai_chat_bedrock_enable_mcp', false) ? '' : 'hidden'; ?>">
        <h3><?php esc_html_e('MCP Servers', 'ai-chat-for-amazon-bedrock'); ?></h3>
        
        <div class="ai-chat-bedrock-mcp-add-server">
            <h4><?php esc_html_e('Add MCP Server', 'ai-chat-for-amazon-bedrock'); ?></h4>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="ai_chat_bedrock_mcp_server_name"><?php esc_html_e('Server Name', 'ai-chat-for-amazon-bedrock'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="ai_chat_bedrock_mcp_server_name" class="regular-text" placeholder="<?php esc_attr_e('e.g., my-mcp-server', 'ai-chat-for-amazon-bedrock'); ?>" />
                        <p class="description"><?php esc_html_e('A unique name to identify this MCP server.', 'ai-chat-for-amazon-bedrock'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ai_chat_bedrock_mcp_server_url"><?php esc_html_e('Server URL', 'ai-chat-for-amazon-bedrock'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="ai_chat_bedrock_mcp_server_url" class="regular-text" placeholder="<?php esc_attr_e('http://localhost:8080', 'ai-chat-for-amazon-bedrock'); ?>" />
                        <p class="description"><?php esc_html_e('The URL of the MCP server.', 'ai-chat-for-amazon-bedrock'); ?></p>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" id="ai_chat_bedrock_add_mcp_server" class="button button-primary">
                    <?php esc_html_e('Add Server', 'ai-chat-for-amazon-bedrock'); ?>
                </button>
            </p>
        </div>

        <div class="ai-chat-bedrock-mcp-servers-list">
            <h4><?php esc_html_e('Registered MCP Servers', 'ai-chat-for-amazon-bedrock'); ?></h4>
            <div id="ai-chat-bedrock-mcp-servers-table-container">
                <table class="widefat" id="ai-chat-bedrock-mcp-servers-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Server Name', 'ai-chat-for-amazon-bedrock'); ?></th>
                            <th><?php esc_html_e('URL', 'ai-chat-for-amazon-bedrock'); ?></th>
                            <th><?php esc_html_e('Status', 'ai-chat-for-amazon-bedrock'); ?></th>
                            <th><?php esc_html_e('Tools', 'ai-chat-for-amazon-bedrock'); ?></th>
                            <th><?php esc_html_e('Actions', 'ai-chat-for-amazon-bedrock'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="no-items">
                            <td colspan="5"><?php esc_html_e('No MCP servers registered yet.', 'ai-chat-for-amazon-bedrock'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="ai-chat-bedrock-mcp-tools-modal" class="ai-chat-bedrock-modal">
        <div class="ai-chat-bedrock-modal-content">
            <span class="ai-chat-bedrock-modal-close">&times;</span>
            <h3><?php esc_html_e('MCP Server Tools', 'ai-chat-for-amazon-bedrock'); ?></h3>
            <div id="ai-chat-bedrock-mcp-tools-list">
                <p><?php esc_html_e('Loading tools...', 'ai-chat-for-amazon-bedrock'); ?></p>
            </div>
        </div>
    </div>
</div>

<script type="text/template" id="ai-chat-bedrock-mcp-server-row-template">
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
                <?php esc_html_e('View Tools', 'ai-chat-for-amazon-bedrock'); ?>
            </button>
            <button type="button" class="button ai-chat-bedrock-refresh-tools" data-server="{{server_name}}">
                <?php esc_html_e('Refresh', 'ai-chat-for-amazon-bedrock'); ?>
            </button>
        </td>
        <td>
            <button type="button" class="button ai-chat-bedrock-remove-server" data-server="{{server_name}}">
                <?php esc_html_e('Remove', 'ai-chat-for-amazon-bedrock'); ?>
            </button>
        </td>
    </tr>
</script>

<script type="text/template" id="ai-chat-bedrock-mcp-tool-item-template">
    <div class="ai-chat-bedrock-mcp-tool-item">
        <h4>{{name}}</h4>
        <p class="description">{{description}}</p>
        <div class="ai-chat-bedrock-mcp-tool-parameters">
            <h5><?php esc_html_e('Parameters', 'ai-chat-for-amazon-bedrock'); ?></h5>
            <ul>
                {{parameters}}
            </ul>
        </div>
    </div>
</script>
