<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * AWS Bedrock API handler.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      AI_Chat_Bedrock_AWS    $aws    The AWS API handler.
	 */
	private $aws;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->aws = new AI_Chat_Bedrock_AWS();
	}

	/**
	 * Add MCP templates to admin head
	 */
	public function add_mcp_templates_to_admin_head() {
		$screen = get_current_screen();
		if ($screen && (strpos($screen->id, $this->plugin_name . '-mcp') !== false || strpos($screen->id, $this->plugin_name . '-test-wp-mcp') !== false)) {
			?>
			<!-- MCP Templates -->
			<script id="ai-chat-bedrock-mcp-server-row-template" type="text/template">
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
			<script id="ai-chat-bedrock-mcp-tool-item-template" type="text/template">
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
			<?php
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/ai-chat-bedrock-admin.css', array(), $this->version, 'all' );
		
		// On test page, also enqueue the public styles
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, $this->plugin_name . '-test' ) !== false ) {
			wp_enqueue_style( $this->plugin_name . '-public', plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/ai-chat-bedrock-public.css', array(), $this->version, 'all' );
		}
		
		// Enqueue MCP styles if MCP is enabled
		if ( get_option( 'ai_chat_bedrock_enable_mcp', false ) ) {
			wp_enqueue_style( $this->plugin_name . '-mcp', plugin_dir_url( __FILE__ ) . 'css/ai-chat-bedrock-mcp.css', array(), $this->version, 'all' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/ai-chat-bedrock-admin.js', array( 'jquery' ), $this->version, false );
		
		// On test page, also enqueue the public script
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, $this->plugin_name . '-test' ) !== false ) {
			wp_enqueue_script( $this->plugin_name . '-public', plugin_dir_url( dirname( __FILE__ ) ) . 'public/js/ai-chat-bedrock-public.js', array( 'jquery' ), $this->version, false );
			
			// Get settings for public script
			$options = get_option( 'ai_chat_bedrock_settings' );
			$welcome_message = isset( $options['welcome_message'] ) ? $options['welcome_message'] : __( 'Hello! How can I help you today?', 'ai-chat-bedrock' );
			$enable_streaming = isset( $options['enable_streaming'] ) && $options['enable_streaming'] === 'on';
			
			wp_localize_script( $this->plugin_name . '-public', 'ai_chat_bedrock_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ai_chat_bedrock_nonce' ),
				'welcome_message' => $welcome_message,
				'enable_streaming' => $enable_streaming,
			) );
		}
		
		wp_localize_script( $this->plugin_name, 'ai_chat_bedrock_admin', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ai_chat_bedrock_nonce' ),
			'mcp_nonce' => wp_create_nonce( 'ai_chat_bedrock_mcp_nonce' ),
			'i18n' => array(
				'settings_saved' => __( 'Settings saved successfully.', 'ai-chat-for-amazon-bedrock' ),
				'ajax_error' => __( 'An error occurred while processing your request.', 'ai-chat-for-amazon-bedrock' ),
				'no_servers' => __( 'No MCP servers registered yet.', 'ai-chat-for-amazon-bedrock' ),
				'missing_fields' => __( 'Server name and URL are required.', 'ai-chat-for-amazon-bedrock' ),
				'adding' => __( 'Adding...', 'ai-chat-for-amazon-bedrock' ),
				'add_server' => __( 'Add Server', 'ai-chat-for-amazon-bedrock' ),
				'removing' => __( 'Removing...', 'ai-chat-for-amazon-bedrock' ),
				'remove' => __( 'Remove', 'ai-chat-for-amazon-bedrock' ),
				'refreshing' => __( 'Refreshing...', 'ai-chat-for-amazon-bedrock' ),
				'refresh' => __( 'Refresh', 'ai-chat-for-amazon-bedrock' ),
				'loading_tools' => __( 'Loading tools...', 'ai-chat-for-amazon-bedrock' ),
				'no_tools' => __( 'No tools found for this server.', 'ai-chat-for-amazon-bedrock' ),
				'no_parameters' => __( 'No parameters required.', 'ai-chat-for-amazon-bedrock' ),
				'available' => __( 'Available', 'ai-chat-for-amazon-bedrock' ),
				'unavailable' => __( 'Unavailable', 'ai-chat-for-amazon-bedrock' ),
				'confirm_remove_server' => __( 'Are you sure you want to remove this MCP server?', 'ai-chat-for-amazon-bedrock' ),
			),
		) );
		
		// Enqueue MCP scripts if MCP is enabled
		if ( get_option( 'ai_chat_bedrock_enable_mcp', false ) ) {
			wp_enqueue_script( $this->plugin_name . '-mcp', plugin_dir_url( __FILE__ ) . 'js/ai-chat-bedrock-mcp.js', array( 'jquery', $this->plugin_name ), $this->version, false );
		}
	}

	/**
	 * Add menu item for the plugin.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'AI Chat for Amazon Bedrock', 'ai-chat-for-amazon-bedrock' ),
			__( 'AI Chat Bedrock', 'ai-chat-for-amazon-bedrock' ),
			'manage_options',
			$this->plugin_name,
			array( $this, 'display_plugin_admin_page' ),
			'dashicons-format-chat',
			100
		);
		
		add_submenu_page(
			$this->plugin_name,
			__( 'Settings', 'ai-chat-for-amazon-bedrock' ),
			__( 'Settings', 'ai-chat-for-amazon-bedrock' ),
			'manage_options',
			$this->plugin_name . '-settings',
			array( $this, 'display_plugin_admin_settings_page' )
		);
		
		add_submenu_page(
			$this->plugin_name,
			__( 'Test Chat', 'ai-chat-for-amazon-bedrock' ),
			__( 'Test Chat', 'ai-chat-for-amazon-bedrock' ),
			'manage_options',
			$this->plugin_name . '-test',
			array( $this, 'display_plugin_admin_test_page' )
		);
		
		add_submenu_page(
			$this->plugin_name,
			__( 'MCP Settings', 'ai-chat-for-amazon-bedrock' ),
			__( 'MCP Settings', 'ai-chat-for-amazon-bedrock' ),
			'manage_options',
			$this->plugin_name . '-mcp',
			array( $this, 'display_plugin_admin_mcp_page' )
		);
		
		add_submenu_page(
			$this->plugin_name,
			__( 'Test WordPress MCP', 'ai-chat-for-amazon-bedrock' ),
			__( 'Test WordPress MCP', 'ai-chat-for-amazon-bedrock' ),
			'manage_options',
			$this->plugin_name . '-test-wp-mcp',
			array( $this, 'display_plugin_admin_test_wp_mcp_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting(
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_settings',
			array( $this, 'validate_settings' )
		);
		
		add_settings_section(
			'ai_chat_bedrock_aws_settings',
			__( 'AWS Settings', 'ai-chat-bedrock' ),
			array( $this, 'aws_settings_section_callback' ),
			'ai_chat_bedrock_settings'
		);
		
		add_settings_field(
			'aws_region',
			__( 'AWS Region', 'ai-chat-bedrock' ),
			array( $this, 'aws_region_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_aws_settings'
		);
		
		add_settings_field(
			'aws_access_key',
			__( 'AWS Access Key', 'ai-chat-bedrock' ),
			array( $this, 'aws_access_key_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_aws_settings'
		);
		
		add_settings_field(
			'aws_secret_key',
			__( 'AWS Secret Key', 'ai-chat-bedrock' ),
			array( $this, 'aws_secret_key_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_aws_settings'
		);
		
		add_settings_section(
			'ai_chat_bedrock_model_settings',
			__( 'Model Settings', 'ai-chat-bedrock' ),
			array( $this, 'model_settings_section_callback' ),
			'ai_chat_bedrock_settings'
		);
		
		add_settings_field(
			'model_id',
			__( 'Model ID', 'ai-chat-bedrock' ),
			array( $this, 'model_id_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_model_settings'
		);
		
		add_settings_field(
			'max_tokens',
			__( 'Max Tokens', 'ai-chat-bedrock' ),
			array( $this, 'max_tokens_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_model_settings'
		);
		
		add_settings_field(
			'temperature',
			__( 'Temperature', 'ai-chat-bedrock' ),
			array( $this, 'temperature_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_model_settings'
		);
		
		add_settings_field(
			'enable_streaming',
			__( 'Enable Streaming', 'ai-chat-bedrock' ),
			array( $this, 'enable_streaming_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_model_settings'
		);
		
		add_settings_section(
			'ai_chat_bedrock_chat_settings',
			__( 'Chat Settings', 'ai-chat-bedrock' ),
			array( $this, 'chat_settings_section_callback' ),
			'ai_chat_bedrock_settings'
		);
		
		add_settings_field(
			'system_prompt',
			__( 'System Prompt', 'ai-chat-bedrock' ),
			array( $this, 'system_prompt_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_chat_settings'
		);
		
		add_settings_field(
			'chat_title',
			__( 'Chat Title', 'ai-chat-bedrock' ),
			array( $this, 'chat_title_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_chat_settings'
		);
		
		add_settings_field(
			'welcome_message',
			__( 'Welcome Message', 'ai-chat-bedrock' ),
			array( $this, 'welcome_message_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_chat_settings'
		);
		
		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'ai-chat-bedrock' ),
			array( $this, 'debug_mode_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_chat_settings'
		);
		
		// Register MCP settings
		register_setting(
			'ai_chat_bedrock_mcp_settings',
			'ai_chat_bedrock_enable_mcp',
			array(
				'type' => 'boolean',
				'default' => false,
			)
		);
	}

	/**
	 * Display the main plugin admin page.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once 'partials/ai-chat-bedrock-admin-display.php';
	}

	/**
	 * Display the plugin settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_settings_page() {
		include_once 'partials/ai-chat-bedrock-admin-settings.php';
	}
	
	/**
	 * Display the plugin test chat page.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_test_page() {
		include_once 'partials/ai-chat-bedrock-admin-test.php';
	}
	
	/**
	 * Display the plugin MCP settings page.
	 *
	 * @since    1.0.7
	 */
	public function display_plugin_admin_mcp_page() {
		include_once 'partials/ai-chat-bedrock-admin-mcp-tab.php';
	}
	
	/**
	 * Display the plugin WordPress MCP test page.
	 *
	 * @since    1.0.7
	 */
	public function display_plugin_admin_test_wp_mcp_page() {
		include_once 'partials/ai-chat-bedrock-admin-test-mcp.php';
	}
	
	/**
	 * AJAX handler for saving a single option.
	 *
	 * @since    1.0.7
	 */
	public function save_option() {
		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
		}

		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
		}

		// Get option details
		$option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
		$option_value = isset($_POST['option_value']) ? sanitize_text_field($_POST['option_value']) : '';

		if (empty($option_name)) {
			wp_send_json_error(array('message' => __('Option name is required', 'ai-chat-for-amazon-bedrock')), 400);
		}

		// Save option
		update_option($option_name, $option_value);
		wp_send_json_success(array('message' => __('Option saved successfully', 'ai-chat-for-amazon-bedrock')));
	}

	/**
	 * Handle chat message AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function handle_chat_message() {
		// Check if this is a GET request (for EventSource) or POST request
		$is_eventsource = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['streaming']) && $_GET['streaming'] === '1';
		$is_streaming_post = isset($_POST['streaming']) && $_POST['streaming'] === '1';
		
		// Check nonce - handle both GET and POST requests
		if ($is_eventsource) {
			$nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
			if (!wp_verify_nonce($nonce, 'ai_chat_bedrock_nonce')) {
				if ($is_eventsource) {
					header('Content-Type: text/event-stream');
					echo "data: " . wp_json_encode(array('error' => 'Invalid security token.')) . "\n\n";
					echo "data: " . wp_json_encode(array('end' => true)) . "\n\n";
					flush();
					exit;
				} else {
					wp_send_json_error(array('message' => 'Invalid security token.'));
					wp_die();
				}
			}
		} else {
			if (!check_ajax_referer('ai_chat_bedrock_nonce', 'nonce', false)) {
				wp_send_json_error(array('message' => 'Invalid security token.'));
				wp_die();
			}
		}

		// Get message from GET or POST
		if ($is_eventsource) {
			$message = isset($_GET['message']) ? sanitize_textarea_field(wp_unslash($_GET['message'])) : '';
			$history = isset($_GET['history']) ? json_decode(wp_unslash($_GET['history']), true) : array();
		} else {
			$message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
			$history = isset($_POST['history']) ? json_decode(wp_unslash($_POST['history']), true) : array();
		}
		
		if (empty($message)) {
			if ($is_eventsource) {
				header('Content-Type: text/event-stream');
				echo "data: " . wp_json_encode(array('error' => 'Message cannot be empty.')) . "\n\n";
				echo "data: " . wp_json_encode(array('end' => true)) . "\n\n";
				flush();
				exit;
			} else {
				wp_send_json_error(array('message' => 'Message cannot be empty.'));
				wp_die();
			}
		}
		
		// Validate history format
		if (!is_array($history)) {
			$history = array();
		}
		
		// Get system prompt from settings
		$options = get_option('ai_chat_bedrock_settings');
		$system_prompt = isset($options['system_prompt']) ? $options['system_prompt'] : 'You are a helpful AI assistant powered by Amazon Bedrock.';
		
		// Prepare messages array
		$messages = array();
		
		// Add system message
		$messages[] = array(
			'role' => 'system',
			'content' => $system_prompt,
		);
		
		// Add history messages
		foreach ($history as $entry) {
			if (isset($entry['role']) && isset($entry['content'])) {
				$messages[] = array(
					'role' => sanitize_text_field($entry['role']),
					'content' => sanitize_textarea_field($entry['content']),
				);
			}
		}
		
		// Add current user message
		$messages[] = array(
			'role' => 'user',
			'content' => $message,
		);
		
		// Prepare payload for AWS Bedrock
		$payload = array(
			'messages' => $messages,
		);
		
		// Apply MCP tools filter if available
		$payload = apply_filters('ai_chat_bedrock_message_payload', $payload, $message);
		
		// Check if streaming is enabled in settings and requested
		$enable_streaming = isset($options['enable_streaming']) && $options['enable_streaming'] === 'on';
		$streaming_requested = $is_eventsource || $is_streaming_post;
		
		// For EventSource requests, we always need to stream
		if ($is_eventsource) {
			// Set headers for streaming response
			header('Content-Type: text/event-stream');
			header('Cache-Control: no-cache');
			header('Connection: keep-alive');
			header('X-Accel-Buffering: no'); // Disable nginx buffering
			
			// Flush headers and turn off output buffering
			if (ob_get_level()) ob_end_clean();
			ob_implicit_flush(true);
			
			// Define streaming callback
			$streaming_callback = function($data) {
				if (isset($data['content'])) {
					echo "data: " . wp_json_encode(array('content' => $data['content'])) . "\n\n";
					flush();
				} else if (isset($data['data']) && isset($data['data']['message'])) {
					echo "data: " . wp_json_encode(array('content' => $data['data']['message'])) . "\n\n";
					flush();
				}
			};
			
			// Add streaming callback to payload
			$payload['streaming_callback'] = $streaming_callback;
			
			// Send message to AWS Bedrock with streaming
			$response = $this->aws->handle_chat_message($payload);
			
			// Save the complete response to session for history
			if (isset($response['content'])) {
				$this->save_message_to_history($message, $response['content']);
			} else if (isset($response['data']) && isset($response['data']['message'])) {
				$this->save_message_to_history($message, $response['data']['message']);
			}
			
			// Send end of stream marker
			echo "data: " . wp_json_encode(array('end' => true)) . "\n\n";
			flush();
			exit;
		} 
		// For POST requests with streaming flag, just acknowledge receipt
		else if ($is_streaming_post) {
			// This is the initial POST request before EventSource connection
			// Just acknowledge receipt and let the EventSource handle the actual streaming
			wp_send_json_success(array('message' => 'Streaming request received'));
			wp_die();
		}
		// Regular AJAX request without streaming
		else {
			// Send message to AWS Bedrock without streaming
			$response = $this->aws->handle_chat_message($payload);
			
			// Process MCP tool calls if any
			$response = apply_filters('ai_chat_bedrock_process_response', $response, $message);
			
			if (isset($response['success']) && $response['success']) {
				// Save the message to history
				$ai_message = isset($response['data']['message']) ? $response['data']['message'] : '';
				$this->save_message_to_history($message, $ai_message);
				
				// Return the entire response for frontend processing
				wp_send_json($response);
			} else {
				$error_message = isset($response['data']['message']) ? $response['data']['message'] : 'An error occurred while processing your request.';
				wp_send_json_error(array(
					'message' => $error_message,
				));
			}
			
			wp_die();
		}
	}
	
	/**
	 * Save message to chat history.
	 *
	 * @since    1.0.0
	 * @param    string    $user_message    The user message.
	 * @param    string    $ai_response     The AI response.
	 */
	private function save_message_to_history($user_message, $ai_response) {
		// Initialize session if not already started
		if (!session_id() && !headers_sent()) {
			session_start();
		}
		
		// Initialize history array if it doesn't exist
		if (!isset($_SESSION['ai_chat_bedrock_history'])) {
			$_SESSION['ai_chat_bedrock_history'] = array();
		}
		
		// Add messages to history
		$_SESSION['ai_chat_bedrock_history'][] = array(
			'role' => 'user',
			'content' => $user_message,
		);
		
		$_SESSION['ai_chat_bedrock_history'][] = array(
			'role' => 'assistant',
			'content' => $ai_response,
		);
		
		// Limit history size to prevent session bloat
		if (count($_SESSION['ai_chat_bedrock_history']) > 20) {
			// Keep only the last 20 messages (10 exchanges)
			$_SESSION['ai_chat_bedrock_history'] = array_slice($_SESSION['ai_chat_bedrock_history'], -20);
		}
	}

	/**
	 * Clear chat history AJAX request.
	 *
	 * @since    1.0.0
	 */
	public function clear_chat_history() {
		// Check nonce
		if ( ! check_ajax_referer( 'ai_chat_bedrock_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
			wp_die();
		}
		
		// Clear session data if needed
		if ( isset( $_SESSION['ai_chat_bedrock_history'] ) ) {
			unset( $_SESSION['ai_chat_bedrock_history'] );
		}
		
		wp_send_json_success( array( 'message' => 'Chat history cleared.' ) );
		wp_die();
	}
}
