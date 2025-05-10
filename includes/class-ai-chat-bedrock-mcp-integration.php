<?php
/**
 * The MCP Integration functionality of the plugin.
 *
 * @link       https://github.com/noteflow-ai
 * @since      1.0.7
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * The MCP Integration class.
 *
 * This class integrates the MCP Client functionality with the AI Chat for Amazon Bedrock plugin.
 *
 * @since      1.0.7
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Glay <https://github.com/noteflow-ai>
 */
class AI_Chat_Bedrock_MCP_Integration {

    /**
     * The MCP Client instance.
     *
     * @since    1.0.7
     * @access   private
     * @var      AI_Chat_Bedrock_MCP_Client    $mcp_client    The MCP Client instance.
     */
    private $mcp_client;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.7
     */
    public function __construct() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-mcp-client.php';
        $this->mcp_client = new AI_Chat_Bedrock_MCP_Client();
    }

    /**
     * Register MCP-related hooks.
     *
     * @since    1.0.7
     * @param    AI_Chat_Bedrock_Loader    $loader    The loader that's responsible for maintaining and registering all hooks that power the plugin.
     */
    public function register_hooks($loader) {
        // Admin AJAX handlers for MCP server management
        $loader->add_action('wp_ajax_ai_chat_bedrock_register_mcp_server', $this, 'ajax_register_mcp_server');
        $loader->add_action('wp_ajax_ai_chat_bedrock_unregister_mcp_server', $this, 'ajax_unregister_mcp_server');
        $loader->add_action('wp_ajax_ai_chat_bedrock_get_mcp_servers', $this, 'ajax_get_mcp_servers');
        $loader->add_action('wp_ajax_ai_chat_bedrock_discover_mcp_tools', $this, 'ajax_discover_mcp_tools');
        
        // Filter to modify AI message processing to include MCP tools
        $loader->add_filter('ai_chat_bedrock_message_payload', $this, 'add_mcp_tools_to_payload', 10, 2);
        
        // Filter to handle MCP tool calls in AI responses
        $loader->add_filter('ai_chat_bedrock_process_response', $this, 'process_mcp_tool_calls', 10, 2);
    }

    /**
     * AJAX handler for registering an MCP server.
     *
     * @since    1.0.7
     */
    public function ajax_register_mcp_server() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_mcp_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Get server details
        $server_name = isset($_POST['server_name']) ? sanitize_text_field($_POST['server_name']) : '';
        $server_url = isset($_POST['server_url']) ? esc_url_raw($_POST['server_url']) : '';

        if (empty($server_name) || empty($server_url)) {
            wp_send_json_error(array('message' => __('Server name and URL are required', 'ai-chat-for-amazon-bedrock')), 400);
        }

        // Register server
        $result = $this->mcp_client->register_server($server_name, $server_url);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('MCP server registered successfully', 'ai-chat-for-amazon-bedrock'),
                'server' => $this->mcp_client->get_server($server_name),
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to register MCP server', 'ai-chat-for-amazon-bedrock')), 400);
        }
    }

    /**
     * AJAX handler for unregistering an MCP server.
     *
     * @since    1.0.7
     */
    public function ajax_unregister_mcp_server() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_mcp_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Get server name
        $server_name = isset($_POST['server_name']) ? sanitize_text_field($_POST['server_name']) : '';

        if (empty($server_name)) {
            wp_send_json_error(array('message' => __('Server name is required', 'ai-chat-for-amazon-bedrock')), 400);
        }

        // Unregister server
        $result = $this->mcp_client->unregister_server($server_name);

        if ($result) {
            wp_send_json_success(array('message' => __('MCP server unregistered successfully', 'ai-chat-for-amazon-bedrock')));
        } else {
            wp_send_json_error(array('message' => __('Failed to unregister MCP server', 'ai-chat-for-amazon-bedrock')), 400);
        }
    }

    /**
     * AJAX handler for getting all registered MCP servers.
     *
     * @since    1.0.7
     */
    public function ajax_get_mcp_servers() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ai_chat_bedrock_mcp_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Get servers
        $servers = $this->mcp_client->get_servers();

        // Check server availability
        if (is_array($servers)) {
            foreach ($servers as $name => &$server) {
                $server['available'] = $this->mcp_client->is_server_available($name);
            }
        } else {
            $servers = array();
        }

        wp_send_json_success(array('servers' => $servers));
    }

    /**
     * AJAX handler for discovering MCP server tools.
     *
     * @since    1.0.7
     */
    public function ajax_discover_mcp_tools() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_mcp_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
        }

        // Get server name
        $server_name = isset($_POST['server_name']) ? sanitize_text_field($_POST['server_name']) : '';

        if (empty($server_name)) {
            wp_send_json_error(array('message' => __('Server name is required', 'ai-chat-for-amazon-bedrock')), 400);
        }

        // Discover tools
        $tools = $this->mcp_client->discover_server_tools($server_name);

        if (!empty($tools)) {
            wp_send_json_success(array(
                'message' => __('Tools discovered successfully', 'ai-chat-for-amazon-bedrock'),
                'tools' => $tools,
            ));
        } else {
            wp_send_json_error(array('message' => __('No tools found or server not available', 'ai-chat-for-amazon-bedrock')), 400);
        }
    }

    /**
     * Add MCP tools to the AI message payload.
     *
     * @since    1.0.7
     * @param    array     $payload    The message payload.
     * @param    array     $message    The user message.
     * @return   array                 Modified payload with MCP tools.
     */
    public function add_mcp_tools_to_payload($payload, $message) {
        // Check if MCP tools are enabled in settings
        $enable_mcp = get_option('ai_chat_bedrock_enable_mcp', false);
        
        if (!$enable_mcp) {
            return $payload;
        }

        // Get all available tools from MCP servers
        $mcp_tools = $this->mcp_client->get_all_tools();
        
        if (empty($mcp_tools)) {
            return $payload;
        }

        // Add tools to payload
        if (!isset($payload['tools'])) {
            $payload['tools'] = array();
        }

        // Merge MCP tools with existing tools
        $payload['tools'] = array_merge($payload['tools'], $mcp_tools);

        return $payload;
    }

    /**
     * Process MCP tool calls in AI responses.
     *
     * @since    1.0.7
     * @param    array     $response    The AI response.
     * @param    array     $message     The user message.
     * @return   array                  Modified response after processing tool calls.
     */
    public function process_mcp_tool_calls($response, $message) {
        // Check if response contains tool calls
        if (!isset($response['tool_calls']) || empty($response['tool_calls'])) {
            return $response;
        }

        // Process each tool call
        foreach ($response['tool_calls'] as $key => $tool_call) {
            // Check if this is an MCP tool call (contains ___ separator)
            if (strpos($tool_call['name'], '___') === false) {
                continue;
            }

            // Parse tool name to get server name and tool name
            $parsed_tool = $this->mcp_client->parse_tool_name($tool_call['name']);
            $server_name = $parsed_tool['server_name'];
            $tool_name = $parsed_tool['tool_name'];

            // Skip if server or tool name is empty
            if (empty($server_name) || empty($tool_name)) {
                continue;
            }

            // Call the MCP tool
            $result = $this->mcp_client->call_tool(
                $server_name,
                $tool_name,
                isset($tool_call['parameters']) ? $tool_call['parameters'] : array()
            );

            // Update the tool call with the result
            if (!is_wp_error($result)) {
                $response['tool_calls'][$key]['result'] = $result;
            } else {
                $response['tool_calls'][$key]['error'] = array(
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                );
            }
        }

        return $response;
    }

    /**
     * Get the MCP Client instance.
     *
     * @since    1.0.7
     * @return   AI_Chat_Bedrock_MCP_Client    The MCP Client instance.
     */
    public function get_mcp_client() {
        return $this->mcp_client;
    }
}
