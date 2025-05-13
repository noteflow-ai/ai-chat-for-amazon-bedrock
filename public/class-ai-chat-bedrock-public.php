<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/public
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Public {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of the plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/ai-chat-bedrock-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/ai-chat-bedrock-public.js', array( 'jquery' ), $this->version, false );
		
		// Get settings
		$options = get_option( 'ai_chat_bedrock_settings' );
		$welcome_message = isset( $options['welcome_message'] ) ? $options['welcome_message'] : __( 'Hello! How can I help you today?', 'ai-chat-bedrock' );
		$enable_streaming = isset( $options['enable_streaming'] ) && $options['enable_streaming'] === 'on';
		$enable_voice = isset( $options['enable_voice'] ) && $options['enable_voice'] === 'on';
		
		wp_localize_script( $this->plugin_name, 'ai_chat_bedrock_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ai_chat_bedrock_nonce' ),
			'welcome_message' => $welcome_message,
			'enable_streaming' => $enable_streaming,
			'enable_voice' => $enable_voice,
		) );
		
		// 加载语音交互脚本和样式
		if ($enable_voice) {
			// 加载语音样式
			wp_enqueue_style( 'ai-chat-bedrock-voice', plugin_dir_url( __FILE__ ) . 'css/ai-chat-bedrock-voice.css', array(), $this->version, 'all' );
			
			// 注册Nova Sonic代理客户端脚本（服务器代理方式）
			wp_enqueue_script( $this->plugin_name . '-nova-sonic-proxy', plugin_dir_url( __FILE__ ) . 'js/nova-sonic-proxy-client.js', array( 'jquery' ), $this->version, true );
			
			// 注册语音交互脚本 - 只使用代理模式
			wp_enqueue_script( 'ai-chat-bedrock-voice-proxy', plugin_dir_url( __FILE__ ) . 'js/ai-chat-bedrock-voice-proxy.js', array( 'jquery', $this->plugin_name, $this->plugin_name . '-nova-sonic-proxy' ), $this->version, true );
			
			// 传递语音设置到前端
			$voice_id = isset( $options['voice_id'] ) ? $options['voice_id'] : 'alloy';
			$sample_rate = isset( $options['speech_sample_rate'] ) ? intval( $options['speech_sample_rate'] ) : 24000;
			$system_prompt = isset( $options['system_prompt'] ) ? $options['system_prompt'] : 'You are a helpful assistant.';
			
			// 获取完整的REST API URL，包含WordPress路径和index.php
			$site_url = site_url();
			$rest_url = $site_url . '/index.php/wp-json/ai-chat-bedrock/v1';
			
			// 传递参数到代理语音脚本
			wp_localize_script( 'ai-chat-bedrock-voice-proxy', 'aiChatBedrockVoiceParams', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'rest_url' => $rest_url, // 使用完整的REST API URL
				'nonce' => wp_create_nonce( 'ai_chat_bedrock_voice_nonce' ),
				'voice_enabled' => $enable_voice,
				'voice_id' => $voice_id,
				'sample_rate' => $sample_rate,
				'system_prompt' => $system_prompt, // 添加系统提示
				'i18n' => array(
					'start_recording' => __( '开始录音', 'ai-chat-for-amazon-bedrock' ),
					'stop_recording' => __( '停止录音', 'ai-chat-for-amazon-bedrock' ),
					'listening' => __( '正在听...', 'ai-chat-for-amazon-bedrock' ),
					'processing' => __( '处理中...', 'ai-chat-for-amazon-bedrock' ),
					'error_microphone' => __( '无法访问麦克风', 'ai-chat-for-amazon-bedrock' ),
					'error_speech' => __( '语音识别失败', 'ai-chat-for-amazon-bedrock' ),
					'proxy_mode' => __( '使用服务器代理模式', 'ai-chat-for-amazon-bedrock' )
				)
			) );
		}
	}

	/**
	 * Display the chat interface.
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes.
	 * @return   string            The chat interface HTML.
	 */
	public function display_chat_interface( $atts ) {
		// Enqueue styles and scripts
		$this->enqueue_styles();
		$this->enqueue_scripts();
		
		// Get settings
		$options = get_option( 'ai_chat_bedrock_settings' );
		$chat_title = isset( $options['chat_title'] ) ? $options['chat_title'] : 'Chat with AI';
		
		// Parse shortcode attributes
		$atts = shortcode_atts(
			array(
				'title' => $chat_title,
				'placeholder' => 'Type your message here...',
				'button_text' => 'Send',
				'clear_text' => 'Clear Chat',
				'width' => '100%',
				'height' => '500px',
			),
			$atts,
			'ai_chat_bedrock'
		);
		
		// Start output buffering
		ob_start();
		
		// Include the template
		include plugin_dir_path( __FILE__ ) . 'partials/ai-chat-bedrock-public-display.php';
		
		// Return the buffered content
		return ob_get_clean();
	}
	
	/**
	 * Handle AJAX request for chat messages.
	 *
	 * @since    1.0.0
	 */
	public function handle_chat_message() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ai_chat_bedrock_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
		}
		
		// Get message and history
		$message = isset( $_POST['message'] ) ? sanitize_text_field( $_POST['message'] ) : '';
		$history = isset( $_POST['history'] ) ? json_decode( stripslashes( $_POST['history'] ), true ) : array();
		
		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}
		
		// Get AWS instance
		$aws = new AI_Chat_Bedrock_AWS();
		
		// Send to AWS Bedrock
		$response = $aws->handle_chat_message( array( 'messages' => $history, 'message' => $message ) );
		
		// Return the response
		wp_send_json( $response );
	}
	
	/**
	 * Handle AJAX request to clear chat history.
	 *
	 * @since    1.0.0
	 */
	public function clear_chat_history() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ai_chat_bedrock_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
		}
		
		// Return success
		wp_send_json_success( array( 'message' => 'Chat history cleared.' ) );
	}
	
	/**
	 * Handle AJAX request for tool results.
	 *
	 * @since    1.0.7
	 */
	public function handle_tool_results() {
		// Check nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ai_chat_bedrock_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
		}
		
		// Get tool calls and original message
		$tool_calls = isset( $_POST['tool_calls'] ) ? json_decode( stripslashes( $_POST['tool_calls'] ), true ) : array();
		$original_message = isset( $_POST['original_message'] ) ? sanitize_text_field( $_POST['original_message'] ) : '';
		$history = isset( $_POST['history'] ) ? json_decode( stripslashes( $_POST['history'] ), true ) : array();
		
		if ( empty( $tool_calls ) ) {
			wp_send_json_error( array( 'message' => 'No tool calls provided.' ) );
		}
		
		// Log the tool calls
		error_log( 'AI Chat Bedrock - Tool calls received: ' . print_r( $tool_calls, true ) );
		
		// Create a new message array with the tool results
		$messages = array();
		
		// Add history messages
		if ( ! empty( $history ) ) {
			foreach ( $history as $msg ) {
				$messages[] = $msg;
			}
		}
		
		// Add the original user message if not in history
		if ( ! empty( $original_message ) ) {
			$messages[] = array( 'role' => 'user', 'content' => $original_message );
		}
		
		// Add the assistant's response with tool calls
		$messages[] = array( 
			'role' => 'assistant', 
			'content' => 'I need to search for information about your query.',
			'tool_calls' => $tool_calls
		);
		
		// Add a user message with tool results
		$tool_results_message = "I've found the following information:\n\n";
		foreach ($tool_calls as $tool_call) {
			if (isset($tool_call['result'])) {
				$tool_name = explode('___', $tool_call['name'])[1] ?? $tool_call['name'];
				$tool_results_message .= "Results from " . $tool_name . ":\n";
				$tool_results_message .= json_encode($tool_call['result'], JSON_PRETTY_PRINT);
				$tool_results_message .= "\n\n";
			}
		}
		$tool_results_message .= "Please provide a complete and helpful response based on these results.";
		
		$messages[] = array(
			'role' => 'user',
			'content' => $tool_results_message
		);
		
		// Get AWS instance
		$aws = new AI_Chat_Bedrock_AWS();
		
		// Send to AWS Bedrock
		$response = $aws->handle_chat_message( array( 'messages' => $messages ) );
		
		// Return the response
		wp_send_json( $response );
	}
}
