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
            error_log('AI Chat Bedrock Debug - MCP is disabled, not adding tools to payload');
            return $payload;
        }

        // Get all available tools from MCP servers
        $mcp_tools = $this->mcp_client->get_all_tools();
        error_log('AI Chat Bedrock Debug - MCP tools before formatting: ' . print_r($mcp_tools, true));
        
        if (empty($mcp_tools)) {
            error_log('AI Chat Bedrock Debug - No MCP tools found');
            return $payload;
        }

        // Format tools for Claude
        $claude_tools = [];
        foreach ($mcp_tools as $tool) {
            // Ensure properties is an object, not an array
            $properties = isset($tool['parameters']['properties']) ? $tool['parameters']['properties'] : new stdClass();
            if (is_array($properties) && empty($properties)) {
                $properties = new stdClass(); // Convert empty array to empty object
            }
            
            $claude_tools[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => isset($tool['parameters']['required']) ? $tool['parameters']['required'] : []
                ]
            ];
        }

        // Add tools to payload
        $payload['tools'] = $claude_tools;
        
        error_log('AI Chat Bedrock Debug - Tools added to payload: ' . print_r($claude_tools, true));
        error_log('AI Chat Bedrock Debug - Final payload with tools: ' . print_r($payload, true));

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
        // Initialize tool_calls array if not present
        if (!isset($response['tool_calls'])) {
            $response['tool_calls'] = array();
        }
        
        // Check for Claude 3.7 format tool calls in content array
        if (isset($response['content']) && is_array($response['content'])) {
            error_log('AI Chat Bedrock Debug - Checking for tool calls in content array');
            
            foreach ($response['content'] as $content_item) {
                if (isset($content_item['type']) && $content_item['type'] === 'tool_use') {
                    error_log('AI Chat Bedrock Debug - Found tool_use in content: ' . print_r($content_item, true));
                    
                    // Add to tool_calls array for consistent processing
                    $response['tool_calls'][] = array(
                        'id' => isset($content_item['id']) ? $content_item['id'] : uniqid('tool_call_'),
                        'name' => $content_item['name'],
                        'parameters' => isset($content_item['input']) ? $content_item['input'] : array(),
                    );
                }
            }
        }
        
        // If still no tool calls, return the original response
        if (empty($response['tool_calls'])) {
            error_log('AI Chat Bedrock Debug - No tool calls found in response');
            return $response;
        }

        error_log('AI Chat Bedrock Debug - Processing tool calls: ' . print_r($response['tool_calls'], true));

        // Process each tool call
        foreach ($response['tool_calls'] as $key => $tool_call) {
            // Check if this is an MCP tool call (contains ___ separator)
            if (strpos($tool_call['name'], '___') === false) {
                error_log('AI Chat Bedrock Debug - Not an MCP tool call: ' . $tool_call['name']);
                continue;
            }

            // Parse tool name to get server name and tool name
            $parsed_tool = $this->parse_tool_name($tool_call['name']);
            $server_name = $parsed_tool['server_name'];
            $tool_name = $parsed_tool['tool_name'];

            // Skip if server or tool name is empty
            if (empty($server_name) || empty($tool_name)) {
                error_log('AI Chat Bedrock Debug - Empty server or tool name');
                continue;
            }

            error_log('AI Chat Bedrock Debug - Calling MCP tool: ' . $server_name . '___' . $tool_name);
            error_log('AI Chat Bedrock Debug - Tool parameters: ' . print_r(isset($tool_call['parameters']) ? $tool_call['parameters'] : array(), true));

            // Call the MCP tool
            $result = $this->mcp_client->call_tool(
                $server_name,
                $tool_name,
                isset($tool_call['parameters']) ? $tool_call['parameters'] : array()
            );

            // Update the tool call with the result
            if (!is_wp_error($result)) {
                $response['tool_calls'][$key]['result'] = $result;
                error_log('AI Chat Bedrock Debug - Tool call result: ' . print_r($result, true));
            } else {
                $response['tool_calls'][$key]['error'] = array(
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                );
                error_log('AI Chat Bedrock Debug - Tool call error: ' . $result->get_error_message());
            }
        }

        return $response;
    }
    
    /**
     * Parse tool name to get server name and tool name.
     *
     * @since    1.0.7
     * @param    string    $tool_name    The tool name with server prefix.
     * @return   array                   Array with server_name and tool_name.
     */
    private function parse_tool_name($tool_name) {
        $parts = explode('___', $tool_name, 2);
        
        return array(
            'server_name' => isset($parts[0]) ? $parts[0] : '',
            'tool_name' => isset($parts[1]) ? $parts[1] : '',
        );
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
