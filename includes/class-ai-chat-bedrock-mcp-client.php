<?php
/**
 * The MCP Client functionality of the plugin.
 *
 * @link       https://github.com/noteflow-ai
 * @since      1.0.7
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * The MCP Client class.
 *
 * This is used to implement the Model Context Protocol (MCP) client functionality
 * to extend the capability to call MCP Servers.
 *
 * @since      1.0.7
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Glay <https://github.com/noteflow-ai>
 */
class AI_Chat_Bedrock_MCP_Client {

    /**
     * The base URL for MCP server communication.
     *
     * @since    1.0.7
     * @access   private
     * @var      string    $base_url    The base URL for MCP server.
     */
    private $base_url;

    /**
     * The timeout for MCP server requests in seconds.
     *
     * @since    1.0.7
     * @access   private
     * @var      int    $timeout    The timeout for requests.
     */
    private $timeout;

    /**
     * Available MCP servers.
     *
     * @since    1.0.7
     * @access   private
     * @var      array    $servers    The registered MCP servers.
     */
    private $servers = array();

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.7
     * @param    string    $base_url    Optional. The base URL for MCP server.
     * @param    int       $timeout     Optional. The timeout for requests.
     */
    public function __construct($base_url = 'http://localhost:8080', $timeout = 10) {
        $this->base_url = $base_url;
        $this->timeout = $timeout;
        
        // Load registered servers from options
        $this->load_servers();
    }

    /**
     * Load registered MCP servers from WordPress options.
     *
     * @since    1.0.7
     * @access   private
     */
    private function load_servers() {
        $saved_servers = get_option('ai_chat_bedrock_mcp_servers', array());
        if (!empty($saved_servers) && is_array($saved_servers)) {
            $this->servers = $saved_servers;
        }
    }

    /**
     * Save registered MCP servers to WordPress options.
     *
     * @since    1.0.7
     * @access   private
     */
    private function save_servers() {
        update_option('ai_chat_bedrock_mcp_servers', $this->servers);
    }

    /**
     * Register a new MCP server.
     *
     * @since    1.0.7
     * @param    string    $server_name    The name of the MCP server.
     * @param    string    $server_url     The URL of the MCP server.
     * @return   bool                      True if registered successfully, false otherwise.
     */
    public function register_server($server_name, $server_url) {
        if (empty($server_name) || empty($server_url)) {
            return false;
        }

        // Validate server URL
        if (!filter_var($server_url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if server is already registered
        if (isset($this->servers[$server_name])) {
            return false;
        }

        // Add server to the list
        $this->servers[$server_name] = array(
            'url' => $server_url,
            'tools' => array(),
        );

        // Save servers to options
        $this->save_servers();

        // Discover server tools
        $this->discover_server_tools($server_name);

        return true;
    }

    /**
     * Unregister an MCP server.
     *
     * @since    1.0.7
     * @param    string    $server_name    The name of the MCP server.
     * @return   bool                      True if unregistered successfully, false otherwise.
     */
    public function unregister_server($server_name) {
        if (empty($server_name) || !isset($this->servers[$server_name])) {
            return false;
        }

        // Remove server from the list
        unset($this->servers[$server_name]);

        // Save servers to options
        $this->save_servers();

        return true;
    }

    /**
     * Discover available tools from an MCP server.
     *
     * @since    1.0.7
     * @param    string    $server_name    The name of the MCP server.
     * @return   array                     Array of discovered tools or empty array on failure.
     */
    public function discover_server_tools($server_name) {
        if (!isset($this->servers[$server_name])) {
            return array();
        }

        $server_url = $this->servers[$server_name]['url'];
        $discovery_url = trailingslashit($server_url) . 'mcp/discover';

        $response = wp_remote_get($discovery_url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            error_log('MCP discovery error: ' . $response->get_error_message());
            return array();
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('MCP discovery failed with status code: ' . $status_code);
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || !isset($data['tools']) || !is_array($data['tools'])) {
            error_log('Invalid MCP discovery response');
            return array();
        }

        // Update server tools
        $this->servers[$server_name]['tools'] = $data['tools'];
        $this->save_servers();

        return $data['tools'];
    }

    /**
     * Get all registered MCP servers.
     *
     * @since    1.0.7
     * @return   array    Array of registered MCP servers.
     */
    public function get_servers() {
        return $this->servers;
    }

    /**
     * Get a specific MCP server by name.
     *
     * @since    1.0.7
     * @param    string    $server_name    The name of the MCP server.
     * @return   array|null                Server data or null if not found.
     */
    public function get_server($server_name) {
        return isset($this->servers[$server_name]) ? $this->servers[$server_name] : null;
    }

    /**
     * Call an MCP server tool.
     *
     * @since    1.0.7
     * @param    string    $server_name    The name of the MCP server.
     * @param    string    $tool_name      The name of the tool to call.
     * @param    array     $parameters     The parameters to pass to the tool.
     * @return   array|WP_Error            Response data or WP_Error on failure.
     */
    public function call_tool($server_name, $tool_name, $parameters = array()) {
        if (!isset($this->servers[$server_name])) {
            return new WP_Error('invalid_server', __('Invalid MCP server name', 'ai-chat-for-amazon-bedrock'));
        }

        $server = $this->servers[$server_name];
        $tool_found = false;

        // Check if the tool exists
        foreach ($server['tools'] as $tool) {
            if ($tool['name'] === $tool_name) {
                $tool_found = true;
                break;
            }
        }

        if (!$tool_found) {
            return new WP_Error('invalid_tool', __('Invalid MCP tool name', 'ai-chat-for-amazon-bedrock'));
        }

        $server_url = $server['url'];
        $tool_url = trailingslashit($server_url) . 'mcp/tools/' . $tool_name;

        $response = wp_remote_post($tool_url, array(
            'timeout' => $this->timeout,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => json_encode($parameters),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error(
                'mcp_error',
                sprintf(__('MCP server returned error code: %d', 'ai-chat-for-amazon-bedrock'), $status_code),
                array('status' => $status_code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return new WP_Error('invalid_response', __('Invalid response from MCP server', 'ai-chat-for-amazon-bedrock'));
        }

        return $data;
    }

    /**
     * Get all available tools from all registered MCP servers.
     *
     * @since    1.0.7
     * @return   array    Array of available tools with server name prefix.
     */
    public function get_all_tools() {
        $all_tools = array();

        foreach ($this->servers as $server_name => $server) {
            if (!empty($server['tools']) && is_array($server['tools'])) {
                foreach ($server['tools'] as $tool) {
                    // Add server name prefix to tool name
                    $prefixed_tool = $tool;
                    $prefixed_tool['name'] = $server_name . '___' . $tool['name'];
                    
                    // Make sure parameters are properly formatted
                    if (!isset($prefixed_tool['parameters']) || !is_array($prefixed_tool['parameters'])) {
                        $prefixed_tool['parameters'] = array(
                            'type' => 'object',
                            'properties' => new stdClass(), // Use stdClass for empty object
                            'required' => array()
                        );
                    }
                    
                    // Ensure properties exists and is an object, not an array
                    if (!isset($prefixed_tool['parameters']['properties'])) {
                        $prefixed_tool['parameters']['properties'] = new stdClass(); // Use stdClass for empty object
                    } else if (is_array($prefixed_tool['parameters']['properties']) && empty($prefixed_tool['parameters']['properties'])) {
                        // Convert empty array to stdClass for proper JSON encoding as {}
                        $prefixed_tool['parameters']['properties'] = new stdClass();
                    }
                    
                    // Ensure required exists
                    if (!isset($prefixed_tool['parameters']['required'])) {
                        $prefixed_tool['parameters']['required'] = array();
                    }
                    
                    $all_tools[] = $prefixed_tool;
                }
            }
        }
        
        error_log('AI Chat Bedrock Debug - All MCP tools: ' . print_r($all_tools, true));

        return $all_tools;
    }

    /**
     * Parse a prefixed tool name to get server name and tool name.
     *
     * @since    1.0.7
     * @param    string    $prefixed_tool_name    The prefixed tool name (server___tool).
     * @return   array                           Array with server_name and tool_name.
     */
    public function parse_tool_name($prefixed_tool_name) {
        $parts = explode('___', $prefixed_tool_name, 2);
        
        if (count($parts) !== 2) {
            return array(
                'server_name' => '',
                'tool_name' => '',
            );
        }

        return array(
            'server_name' => $parts[0],
            'tool_name' => $parts[1],
        );
    }

    /**
     * Check if an MCP server is available.
     *
     * @since    1.0.7
     * @param    string    $server_name    The name of the MCP server.
     * @return   bool                      True if server is available, false otherwise.
     */
    public function is_server_available($server_name) {
        if (!isset($this->servers[$server_name])) {
            return false;
        }

        $server_url = $this->servers[$server_name]['url'];
        $health_url = trailingslashit($server_url) . 'mcp/health';

        $response = wp_remote_get($health_url, array(
            'timeout' => 5, // Short timeout for health check
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        return $status_code === 200;
    }
}
