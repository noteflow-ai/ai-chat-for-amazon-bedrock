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
		
		// 添加 AJAX 处理函数
		add_action('wp_ajax_ai_chat_bedrock_save_option', array($this, 'save_option'));
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
			$enable_voice = isset( $options['enable_voice'] ) && $options['enable_voice'] === 'on';
			
			wp_localize_script( $this->plugin_name . '-public', 'ai_chat_bedrock_params', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ai_chat_bedrock_nonce' ),
				'welcome_message' => $welcome_message,
				'enable_streaming' => $enable_streaming,
				'enable_voice' => $enable_voice,
			) );
			
			// 加载语音交互脚本和样式
			if ($enable_voice) {
				
				wp_enqueue_style( 'ai-chat-bedrock-voice', plugin_dir_url( dirname( __FILE__ ) ) . 'public/css/ai-chat-bedrock-voice.css', array(), $this->version, 'all' );
				
				// 传递语音设置到前端
				$voice_id = isset( $options['voice_id'] ) ? $options['voice_id'] : 'tiffany';
				$sample_rate = isset( $options['speech_sample_rate'] ) ? (int)$options['speech_sample_rate'] : 24000;
				
				wp_localize_script( 'ai-chat-bedrock-voice', 'aiChatBedrockVoiceParams', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'ai_chat_bedrock_voice_nonce' ),
					'voice_enabled' => $enable_voice,
					'voice_id' => $voice_id,
					'sample_rate' => $sample_rate,
					'i18n' => array(
						'start_recording' => __( '开始录音', 'ai-chat-for-amazon-bedrock' ),
						'stop_recording' => __( '停止录音', 'ai-chat-for-amazon-bedrock' ),
						'listening' => __( '正在听...', 'ai-chat-for-amazon-bedrock' ),
						'processing' => __( '处理中...', 'ai-chat-for-amazon-bedrock' ),
						'error_microphone' => __( '无法访问麦克风', 'ai-chat-for-amazon-bedrock' ),
						'error_speech' => __( '语音识别失败', 'ai-chat-for-amazon-bedrock' )
					)
				) );
			}
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
	public function add_admin_menu() {
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
			'ai_chat_bedrock_voice_settings',
			__( '语音交互设置', 'ai-chat-bedrock' ),
			array( $this, 'voice_settings_section_callback' ),
			'ai_chat_bedrock_settings'
		);
		
		add_settings_field(
			'enable_voice',
			__( '启用语音功能', 'ai-chat-bedrock' ),
			array( $this, 'enable_voice_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_voice_settings'
		);
		
		add_settings_field(
			'voice_id',
			__( 'AI语音', 'ai-chat-bedrock' ),
			array( $this, 'voice_id_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_voice_settings'
		);
		
		add_settings_field(
			'speech_sample_rate',
			__( '语音采样率', 'ai-chat-bedrock' ),
			array( $this, 'speech_sample_rate_render' ),
			'ai_chat_bedrock_settings',
			'ai_chat_bedrock_voice_settings'
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
		// 添加调试日志
		error_log('save_option called with POST data: ' . print_r($_POST, true));
		
		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
			return;
		}

		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_nonce')) {
			error_log('Nonce verification failed. Received: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'none'));
			wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
			return;
		}

		// Get option details
		$option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
		$option_value = isset($_POST['option_value']) ? sanitize_text_field($_POST['option_value']) : '';

		if (empty($option_name)) {
			wp_send_json_error(array('message' => __('Option name is required', 'ai-chat-for-amazon-bedrock')), 400);
			return;
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
		
		// For POST requests with streaming flag, check for tool calls first
		if ($is_streaming_post) {
			// Send message to AWS Bedrock without streaming to check for tool calls
			$response = $this->aws->handle_chat_message($payload);
			
			// Process MCP tool calls if any
			$response = apply_filters('ai_chat_bedrock_process_response', $response, $message);
			
			// Check if response contains tool calls
			if (isset($response['tool_calls']) && !empty($response['tool_calls'])) {
				// Return the tool calls in the response
				wp_send_json($response);
				wp_die();
			}
			
			// No tool calls, just acknowledge receipt for streaming
			wp_send_json_success(array('message' => 'Streaming request received'));
			wp_die();
		}
		// For EventSource requests, we always need to stream
		else if ($is_eventsource) {
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
			
			// Process MCP tool calls if any
			$response = apply_filters('ai_chat_bedrock_process_response', $response, $message);
			
			// Check if response contains tool calls
			if (isset($response['tool_calls']) && !empty($response['tool_calls'])) {
				// Send tool calls in the response
				echo "data: " . wp_json_encode($response) . "\n\n";
				echo "data: " . wp_json_encode(array('end' => true)) . "\n\n";
				flush();
				exit;
			}
			
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

	/**
	 * AWS settings section callback.
	 *
	 * @since    1.0.0
	 */
	public function aws_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure your AWS credentials for Amazon Bedrock access.', 'ai-chat-for-amazon-bedrock' ) . '</p>';
	}

	/**
	 * Model settings section callback.
	 *
	 * @since    1.0.0
	 */
	public function model_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the AI model settings.', 'ai-chat-for-amazon-bedrock' ) . '</p>';
	}

	/**
	 * Chat settings section callback.
	 *
	 * @since    1.0.0
	 */
	public function chat_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the chat interface settings.', 'ai-chat-for-amazon-bedrock' ) . '</p>';
	}

	/**
	 * AWS region field render.
	 *
	 * @since    1.0.0
	 */
	public function aws_region_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['aws_region'] ) ? $options['aws_region'] : 'us-east-1';
		?>
		<select name="ai_chat_bedrock_settings[aws_region]">
			<option value="us-east-1" <?php selected( $value, 'us-east-1' ); ?>>US East (N. Virginia)</option>
			<option value="us-east-2" <?php selected( $value, 'us-east-2' ); ?>>US East (Ohio)</option>
			<option value="us-west-1" <?php selected( $value, 'us-west-1' ); ?>>US West (N. California)</option>
			<option value="us-west-2" <?php selected( $value, 'us-west-2' ); ?>>US West (Oregon)</option>
			<option value="eu-west-1" <?php selected( $value, 'eu-west-1' ); ?>>EU (Ireland)</option>
			<option value="eu-central-1" <?php selected( $value, 'eu-central-1' ); ?>>EU (Frankfurt)</option>
			<option value="ap-northeast-1" <?php selected( $value, 'ap-northeast-1' ); ?>>Asia Pacific (Tokyo)</option>
			<option value="ap-southeast-1" <?php selected( $value, 'ap-southeast-1' ); ?>>Asia Pacific (Singapore)</option>
		</select>
		<p class="description"><?php esc_html_e( 'Select the AWS region where Amazon Bedrock is available.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * AWS access key field render.
	 *
	 * @since    1.0.0
	 */
	public function aws_access_key_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['aws_access_key'] ) ? $options['aws_access_key'] : '';
		?>
		<input type="text" name="ai_chat_bedrock_settings[aws_access_key]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Enter your AWS Access Key with permissions to use Amazon Bedrock.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * AWS secret key field render.
	 *
	 * @since    1.0.0
	 */
	public function aws_secret_key_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['aws_secret_key'] ) ? $options['aws_secret_key'] : '';
		?>
		<input type="password" name="ai_chat_bedrock_settings[aws_secret_key]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Enter your AWS Secret Key. Leave empty to keep the existing value.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Model ID field render.
	 *
	 * @since    1.0.0
	 */
	public function model_id_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['model_id'] ) ? $options['model_id'] : 'anthropic.claude-3-sonnet-20240229-v1:0';
		?>
		<select name="ai_chat_bedrock_settings[model_id]">
			<!-- Claude Models -->
			<optgroup label="Claude Models">
				<option value="anthropic.claude-3-sonnet-20240229-v1:0" <?php selected( $value, 'anthropic.claude-3-sonnet-20240229-v1:0' ); ?>>Claude 3 Sonnet</option>
				<option value="anthropic.claude-3-haiku-20240307-v1:0" <?php selected( $value, 'anthropic.claude-3-haiku-20240307-v1:0' ); ?>>Claude 3 Haiku</option>
				<option value="anthropic.claude-3-opus-20240229-v1:0" <?php selected( $value, 'anthropic.claude-3-opus-20240229-v1:0' ); ?>>Claude 3 Opus</option>
				<option value="us.anthropic.claude-3-5-sonnet-20241022-v2:0" <?php selected( $value, 'us.anthropic.claude-3-5-sonnet-20241022-v2:0' ); ?>>Claude 3.5 Sonnet</option>
				<option value="us.anthropic.claude-3-7-sonnet-20250219-v1:0" <?php selected( $value, 'us.anthropic.claude-3-7-sonnet-20250219-v1:0' ); ?>>Claude 3.7 Sonnet</option>
				
			</optgroup>
			
			<!-- Amazon Models -->
			<optgroup label="Amazon Models">
				<option value="amazon.titan-text-express-v1" <?php selected( $value, 'amazon.titan-text-express-v1' ); ?>>Amazon Titan Text Express</option>
				<option value="amazon.titan-text-premier-v1:0" <?php selected( $value, 'amazon.titan-text-premier-v1:0' ); ?>>Amazon Titan Text Premier</option>
				<option value="amazon.nova-text-v1:0" <?php selected( $value, 'amazon.nova-text-v1:0' ); ?>>Amazon Nova Text</option>
			</optgroup>
			
			<!-- Meta Models -->
			<optgroup label="Meta Models">
				<option value="meta.llama2-13b-chat-v1" <?php selected( $value, 'meta.llama2-13b-chat-v1' ); ?>>Meta Llama 2 13B</option>
				<option value="meta.llama2-70b-chat-v1" <?php selected( $value, 'meta.llama2-70b-chat-v1' ); ?>>Meta Llama 2 70B</option>
				<option value="meta.llama3-8b-instruct-v1:0" <?php selected( $value, 'meta.llama3-8b-instruct-v1:0' ); ?>>Meta Llama 3 8B</option>
				<option value="meta.llama3-70b-instruct-v1:0" <?php selected( $value, 'meta.llama3-70b-instruct-v1:0' ); ?>>Meta Llama 3 70B</option>
			</optgroup>
			
			<!-- Mistral Models -->
			<optgroup label="Mistral Models">
				<option value="mistral.mistral-7b-instruct-v0:2" <?php selected( $value, 'mistral.mistral-7b-instruct-v0:2' ); ?>>Mistral 7B</option>
				<option value="mistral.mixtral-8x7b-instruct-v0:1" <?php selected( $value, 'mistral.mixtral-8x7b-instruct-v0:1' ); ?>>Mistral Mixtral 8x7B</option>
			</optgroup>
			
			<!-- DeepSeek Models -->
			<optgroup label="DeepSeek Models">
				<option value="us.deepseek.r1-v1:0" <?php selected( $value, 'us.deepseek.r1-v1:0' ); ?>>DeepSeek R1</option>
			</optgroup>
		</select>
		<p class="description"><?php esc_html_e( 'Select the Amazon Bedrock model to use for chat.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Max tokens field render.
	 *
	 * @since    1.0.0
	 */
	public function max_tokens_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['max_tokens'] ) ? $options['max_tokens'] : 1000;
		?>
		<input type="number" name="ai_chat_bedrock_settings[max_tokens]" value="<?php echo esc_attr( $value ); ?>" min="100" max="4000" step="100">
		<p class="description"><?php esc_html_e( 'Maximum number of tokens to generate in the response.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Temperature field render.
	 *
	 * @since    1.0.0
	 */
	public function temperature_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['temperature'] ) ? $options['temperature'] : 0.7;
		?>
		<input type="range" name="ai_chat_bedrock_settings[temperature]" value="<?php echo esc_attr( $value ); ?>" min="0" max="1" step="0.1" oninput="this.nextElementSibling.value = this.value">
		<output><?php echo esc_html( $value ); ?></output>
		<p class="description"><?php esc_html_e( 'Controls randomness. Lower values are more deterministic, higher values are more creative.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Enable streaming field render.
	 *
	 * @since    1.0.0
	 */
	public function enable_streaming_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$checked = isset( $options['enable_streaming'] ) && $options['enable_streaming'] === 'on';
		?>
		<input type="checkbox" name="ai_chat_bedrock_settings[enable_streaming]" <?php checked( $checked ); ?>>
		<p class="description"><?php esc_html_e( 'Enable streaming responses for a more interactive chat experience.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * System prompt field render.
	 *
	 * @since    1.0.0
	 */
	public function system_prompt_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['system_prompt'] ) ? $options['system_prompt'] : 'You are a helpful AI assistant powered by Amazon Bedrock.';
		?>
		<textarea name="ai_chat_bedrock_settings[system_prompt]" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'System prompt to guide the AI\'s behavior and responses.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Chat title field render.
	 *
	 * @since    1.0.0
	 */
	public function chat_title_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['chat_title'] ) ? $options['chat_title'] : 'Chat with AI';
		?>
		<input type="text" name="ai_chat_bedrock_settings[chat_title]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Title displayed above the chat interface.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}
	
	/**
	 * Welcome message field render.
	 *
	 * @since    1.0.0
	 */
	public function welcome_message_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['welcome_message'] ) ? $options['welcome_message'] : 'Hello! How can I help you today?';
		?>
		<input type="text" name="ai_chat_bedrock_settings[welcome_message]" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
		<p class="description"><?php esc_html_e( 'Welcome message displayed when the chat is first loaded.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Debug mode field render.
	 *
	 * @since    1.0.0
	 */
	public function debug_mode_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$checked = isset( $options['debug_mode'] ) && $options['debug_mode'] === 'on';
		?>
		<input type="checkbox" name="ai_chat_bedrock_settings[debug_mode]" <?php checked( $checked ); ?>>
		<p class="description"><?php esc_html_e( 'Enable debug mode to log API requests and responses.', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}
	
	/**
	 * Voice settings section callback.
	 *
	 * @since    1.0.0
	 */
	public function voice_settings_section_callback() {
		echo '<p>' . esc_html__( '配置语音交互功能的设置。', 'ai-chat-for-amazon-bedrock' ) . '</p>';
	}
	
	/**
	 * Enable voice field render.
	 *
	 * @since    1.0.0
	 */
	public function enable_voice_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$checked = isset( $options['enable_voice'] ) && $options['enable_voice'] === 'on';
		?>
		<input type="checkbox" name="ai_chat_bedrock_settings[enable_voice]" <?php checked( $checked ); ?>>
		<p class="description"><?php esc_html_e( '启用语音交互功能，允许用户通过语音与AI交互。', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}
	
	/**
	 * Voice ID field render.
	 *
	 * @since    1.0.0
	 */
	public function voice_id_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['voice_id'] ) ? $options['voice_id'] : 'tiffany';
		?>
		<select name="ai_chat_bedrock_settings[voice_id]">
			<option value="tiffany" <?php selected( $value, 'tiffany' ); ?>>Tiffany (女声)</option>
			<option value="matthew" <?php selected( $value, 'matthew' ); ?>>Matthew (男声)</option>
		</select>
		<p class="description"><?php esc_html_e( '选择AI回应使用的语音。Nova Sonic目前仅支持Tiffany和Matthew两种语音。', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}
	
	/**
	 * Speech sample rate field render.
	 *
	 * @since    1.0.0
	 */
	public function speech_sample_rate_render() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$value = isset( $options['speech_sample_rate'] ) ? $options['speech_sample_rate'] : '24000';
		?>
		<select name="ai_chat_bedrock_settings[speech_sample_rate]">
			<option value="8000" <?php selected( $value, '8000' ); ?>>8 kHz</option>
			<option value="16000" <?php selected( $value, '16000' ); ?>>16 kHz</option>
			<option value="24000" <?php selected( $value, '24000' ); ?>>24 kHz</option>
		</select>
		<p class="description"><?php esc_html_e( '语音输出的采样率。', 'ai-chat-for-amazon-bedrock' ); ?></p>
		<?php
	}

	/**
	 * Validate settings.
	 *
	 * @since    1.0.0
	 * @param    array    $input    The input array.
	 * @return   array              The validated input array.
	 */
	public function validate_settings( $input ) {
		$output = array();
		
		// AWS Settings
		$output['aws_region'] = sanitize_text_field( $input['aws_region'] );
		$output['aws_access_key'] = sanitize_text_field( $input['aws_access_key'] );
		
		// Only update secret key if it's not empty (to avoid clearing it when not changed)
		if ( ! empty( $input['aws_secret_key'] ) ) {
			$output['aws_secret_key'] = sanitize_text_field( $input['aws_secret_key'] );
		} else {
			$options = get_option( 'ai_chat_bedrock_settings' );
			$output['aws_secret_key'] = isset( $options['aws_secret_key'] ) ? $options['aws_secret_key'] : '';
		}
		
		// Model Settings
		$output['model_id'] = sanitize_text_field( $input['model_id'] );
		$output['max_tokens'] = absint( $input['max_tokens'] );
		$output['temperature'] = min( 1, max( 0, floatval( $input['temperature'] ) ) );
		$output['enable_streaming'] = isset( $input['enable_streaming'] ) ? 'on' : 'off';
		
		// Voice Settings
		$output['enable_voice'] = isset( $input['enable_voice'] ) ? 'on' : 'off';
		$output['voice_id'] = sanitize_text_field( $input['voice_id'] );
		$output['speech_sample_rate'] = sanitize_text_field( $input['speech_sample_rate'] );
		
		// Chat Settings
		$output['system_prompt'] = sanitize_textarea_field( $input['system_prompt'] );
		$output['chat_title'] = sanitize_text_field( $input['chat_title'] );
		$output['welcome_message'] = sanitize_text_field( $input['welcome_message'] );
		$output['debug_mode'] = isset( $input['debug_mode'] ) ? 'on' : 'off';
		
		return $output;
	}
}
