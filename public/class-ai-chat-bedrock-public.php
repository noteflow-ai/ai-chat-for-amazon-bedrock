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
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'ai_chat_bedrock_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token.' ) );
		}
		
		// 检查是否是流式处理的第二阶段（EventSource连接）
		$streaming = isset( $_REQUEST['streaming'] ) && $_REQUEST['streaming'] === '1';
		$stream_id = isset( $_REQUEST['stream_id'] ) ? sanitize_text_field( $_REQUEST['stream_id'] ) : '';
		
		// 如果是流式处理的第二阶段，从会话中获取消息和历史
		if ( $streaming && !empty( $stream_id ) && !isset( $_REQUEST['message'] ) ) {
			// 设置流式响应头
			header('Content-Type: text/event-stream');
			header('Cache-Control: no-cache');
			header('Connection: keep-alive');
			header('X-Accel-Buffering: no'); // 禁用 Nginx 缓冲
			
			// 清除并关闭之前的输出缓冲
			if (ob_get_level()) ob_end_clean();
			
			
			// 从transients中获取消息和历史
			$message = get_transient('ai_chat_bedrock_message_' . $stream_id);
			$history = get_transient('ai_chat_bedrock_history_' . $stream_id);
			
			// 记录调试信息
			error_log('从transient获取消息，流ID: ' . $stream_id . ', 消息: ' . ($message ? $message : '未找到'));
			
			if ( empty( $message ) ) {
				echo "data: " . json_encode(array(
					'error' => 'No message found in session'
				)) . "\n\n";
				flush();
				echo "data: " . json_encode(array('end' => true)) . "\n\n";
				flush();
				exit;
			}
			
			// 检查是否有工具调用缓存
			$tool_calls = get_transient('ai_chat_bedrock_tool_calls_' . $stream_id);
			if (!empty($tool_calls)) {
				error_log('从transient获取工具调用，流ID: ' . $stream_id . ', 工具调用: ' . print_r($tool_calls, true));
				
				// 发送工具调用给前端
				echo "data: " . json_encode(array(
					'tool_calls' => $tool_calls
				)) . "\n\n";
				flush();
				
				// 发送结束标记
				echo "data: " . json_encode(array('end' => true)) . "\n\n";
				flush();
				exit;
			}
			
			// 获取AWS实例
			$aws = new AI_Chat_Bedrock_AWS();
			
			// 获取设置
			$options = get_option( 'ai_chat_bedrock_settings' );
			$enable_mcp = isset( $options['enable_mcp'] ) && $options['enable_mcp'] === 'on';
			
			// 准备发送到AWS Bedrock的数据
			$aws_data = array(
				'messages' => $history,
				'message' => $message,
				'streaming_callback' => function($data) {
					echo "data: " . json_encode($data) . "\n\n";
					flush();
				}
			);
			
			// 如果启用了MCP，添加MCP工具到payload
			if ($enable_mcp) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-mcp-integration.php';
				$mcp_integration = new AI_Chat_Bedrock_MCP_Integration();
				
				// 使用MCP集成添加工具到payload
				$aws_data = $mcp_integration->add_mcp_tools_to_payload($aws_data, array('message' => $message));
				error_log('流式处理第二阶段：添加MCP工具到payload: ' . print_r($aws_data, true));
			}
			
			// 发送到 AWS Bedrock
			$response = $aws->handle_chat_message($aws_data);
			
			// 发送结束标记
			echo "data: " . json_encode(array('end' => true)) . "\n\n";
			flush();
			exit;
		}
		
		// 常规处理或流式处理的第一阶段
		$message = isset( $_REQUEST['message'] ) ? sanitize_text_field( $_REQUEST['message'] ) : '';
		$history = isset( $_REQUEST['history'] ) ? json_decode( stripslashes( $_REQUEST['history'] ), true ) : array();
		
		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}
		
		// 如果是流式处理的第一阶段，先检查是否有工具调用
		if ( $streaming && !isset( $_REQUEST['stream_id'] ) ) {
			// 获取AWS实例
			$aws = new AI_Chat_Bedrock_AWS();
			
			// 获取设置
			$options = get_option( 'ai_chat_bedrock_settings' );
			$enable_mcp = isset( $options['enable_mcp'] ) && $options['enable_mcp'] === 'on';
			
			// 如果启用了MCP，获取MCP集成
			$mcp_integration = null;
			if ($enable_mcp) {
				require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-mcp-integration.php';
				$mcp_integration = new AI_Chat_Bedrock_MCP_Integration();
			}
			
			// 准备发送到AWS Bedrock的数据
			$aws_data = array(
				'messages' => $history,
				'message' => $message
			);
			
			// 如果启用了MCP，添加MCP工具到payload
			if ($enable_mcp && $mcp_integration) {
				// 使用MCP集成添加工具到payload
				$aws_data = $mcp_integration->add_mcp_tools_to_payload($aws_data, array('message' => $message));
				error_log('添加MCP工具到payload: ' . print_r($aws_data, true));
			}
			
			// 发送到 AWS Bedrock
			$response = $aws->handle_chat_message($aws_data);
			
			// 检查是否有工具调用
			if (isset($response['tool_calls']) && !empty($response['tool_calls'])) {
				error_log('流式处理第一阶段检测到工具调用: ' . print_r($response['tool_calls'], true));
				
				// 直接返回工具调用，不进行流式处理
				wp_send_json($response);
				return;
			}
			
			// 生成唯一的流ID
			$stream_id = uniqid('stream_');
			
			// 保存消息和历史到transients中，使用流ID作为键
			set_transient('ai_chat_bedrock_message_' . $stream_id, $message, 3600); // 1小时过期
			set_transient('ai_chat_bedrock_history_' . $stream_id, $history, 3600); // 1小时过期
			
			// 记录调试信息
			error_log('保存消息到transient，流ID: ' . $stream_id . ', 消息: ' . $message);
			
			// 返回成功响应，前端将使用这个响应创建EventSource
			wp_send_json_success( array( 
				'message' => 'Streaming initialized',
				'stream_id' => $stream_id
			) );
			return;
		}
		
		// 常规（非流式）处理或流式处理的第一阶段（检查工具调用）
		$aws = new AI_Chat_Bedrock_AWS();
		
		// 获取设置
		$options = get_option( 'ai_chat_bedrock_settings' );
		$enable_mcp = isset( $options['enable_mcp'] ) && $options['enable_mcp'] === 'on';
		
		// 如果启用了MCP，获取MCP集成
		$mcp_integration = null;
		if ($enable_mcp) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-mcp-integration.php';
			$mcp_integration = new AI_Chat_Bedrock_MCP_Integration();
		}
		
		// 准备发送到AWS Bedrock的数据
		$aws_data = array(
			'messages' => $history,
			'message' => $message
		);
		
		// 如果启用了MCP，添加MCP工具到payload
		if ($enable_mcp && $mcp_integration) {
			// 使用MCP集成添加工具到payload
			$aws_data = $mcp_integration->add_mcp_tools_to_payload($aws_data, array('message' => $message));
			error_log('添加MCP工具到payload: ' . print_r($aws_data, true));
		}
		
		// 发送到 AWS Bedrock
		$response = $aws->handle_chat_message($aws_data);
		
		// 检查是否有工具调用
		if (isset($response['tool_calls']) && !empty($response['tool_calls'])) {
			error_log('检测到工具调用: ' . print_r($response['tool_calls'], true));
			
			// 如果是流式处理的第一阶段，保存工具调用到transients中
			if ($streaming) {
				// 生成唯一的流ID
				$stream_id = uniqid('stream_');
				
				// 保存消息、历史和工具调用到transients中
				set_transient('ai_chat_bedrock_message_' . $stream_id, $message, 3600); // 1小时过期
				set_transient('ai_chat_bedrock_history_' . $stream_id, $history, 3600); // 1小时过期
				set_transient('ai_chat_bedrock_tool_calls_' . $stream_id, $response['tool_calls'], 3600); // 1小时过期
				
				// 记录调试信息
				error_log('流式处理中检测到工具调用，保存到transient，流ID: ' . $stream_id);
				
				// 返回成功响应，前端将使用这个响应创建EventSource
				wp_send_json_success(array(
					'message' => 'Streaming initialized with tool calls',
					'stream_id' => $stream_id
				));
				return;
			}
		}
		
		// 如果是流式处理的第一阶段，保存消息和历史到transients中
		if ($streaming) {
			// 生成唯一的流ID
			$stream_id = uniqid('stream_');
			
			// 保存消息和历史到transients中，使用流ID作为键
			set_transient('ai_chat_bedrock_message_' . $stream_id, $message, 3600); // 1小时过期
			set_transient('ai_chat_bedrock_history_' . $stream_id, $history, 3600); // 1小时过期
			
			// 记录调试信息
			error_log('保存消息到transient，流ID: ' . $stream_id . ', 消息: ' . $message);
			
			// 返回成功响应，前端将使用这个响应创建EventSource
			wp_send_json_success( array( 
				'message' => 'Streaming initialized',
				'stream_id' => $stream_id
			) );
			return;
		}
		
		// 返回常规响应
		wp_send_json($response);
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
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_nonce')) {
			wp_send_json_error(array('message' => 'Invalid security token.'));
		}
		
		// Get tool calls and original message
		$tool_calls = isset($_POST['tool_calls']) ? json_decode(stripslashes($_POST['tool_calls']), true) : array();
		$original_message = isset($_POST['original_message']) ? sanitize_text_field($_POST['original_message']) : '';
		$history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
		
		if (empty($tool_calls)) {
			wp_send_json_error(array('message' => 'No tool calls provided.'));
		}
		
		error_log('AI Chat Bedrock Debug - Tool calls to process: ' . print_r($tool_calls, true));
		
		// Process the first tool call (Claude typically makes one call at a time)
		$tool_call = $tool_calls[0];
		$tool_call_id = $tool_call['id'];
		$tool_name = $tool_call['name'];
		$parameters = isset($tool_call['parameters']) ? $tool_call['parameters'] : array();
		
		// Execute the tool call to get the result
		error_log('AI Chat Bedrock Debug - Executing tool call: ' . $tool_name);
		
		// Get MCP client
		require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ai-chat-bedrock-mcp-client.php';
		$mcp_client = new AI_Chat_Bedrock_MCP_Client();
		
		// Parse tool name to get server name and tool name
		$parts = explode('___', $tool_name);
		if (count($parts) !== 2) {
			wp_send_json_error(array('message' => 'Invalid tool name format.'));
			return;
		}
		
		$server_name = $parts[0];
		$tool_name_only = $parts[1];
		
		// Call the tool
		$result = $mcp_client->call_tool($server_name, $tool_name_only, $parameters);
		
		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => 'Tool call failed: ' . $result->get_error_message()));
			return;
		}
		
		// Store the result in the tool call
		$tool_call['result'] = $result;
		
		// Format the result as a string
		$formatted_result = $this->format_tool_result($tool_call);
		
		// Create a new conversation with Claude that includes the tool result
		$messages = array();
		
		// Add the original user query
		$messages[] = array(
			'role' => 'user',
			'content' => array(
				array(
					'type' => 'text',
					'text' => $original_message
				)
			)
		);
		
		// Add Claude's response with the tool call
		$messages[] = array(
			'role' => 'assistant',
			'content' => array(
				array(
					'type' => 'text',
					'text' => '我需要查找相关信息。'
				),
				array(
					'type' => 'tool_use',
					'id' => $tool_call_id,
					'name' => $tool_name,
					'input' => (object)$parameters  // 将参数转换为对象，确保即使是空数组也会变成空对象
				)
			)
		);
		
		// Add user message with tool result
		$messages[] = array(
			'role' => 'user',
			'content' => array(
				array(
					'type' => 'tool_result',
					'tool_use_id' => $tool_call_id,
					'content' => $formatted_result
				),
				array(
					'type' => 'text',
					'text' => '请根据上面的工具调用结果，用中文总结这些信息并提供完整的回答。'
				)
			)
		);
		
		error_log('AI Chat Bedrock Debug - Final messages for Claude: ' . print_r($messages, true));
		
		// Get AWS instance
		$aws = new AI_Chat_Bedrock_AWS();
		
		// Send to AWS Bedrock
		$response = $aws->handle_chat_message(array('messages' => $messages));
		
		// Return the response
		wp_send_json($response);
	}

	/**
	 * Handle AJAX request to get tool call details.
	 *
	 * @since    1.0.7
	 */
	public function handle_get_tool_call() {
		// Check nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_nonce')) {
			wp_send_json_error(array('message' => 'Invalid security token.'));
		}
		
		// Get message
		$message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
		
		if (empty($message)) {
			wp_send_json_error(array('message' => 'Message is required.'));
		}
		
		// 获取设置
		$options = get_option('ai_chat_bedrock_settings');
		$enable_mcp = isset($options['enable_mcp']) && $options['enable_mcp'] === 'on';
		
		// 如果启用了MCP，获取MCP集成
		$mcp_integration = null;
		if ($enable_mcp) {
			require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ai-chat-bedrock-mcp-integration.php';
			$mcp_integration = new AI_Chat_Bedrock_MCP_Integration();
		}
		
		// 准备发送到AWS Bedrock的数据
		$aws_data = array(
			'messages' => array(),
			'message' => $message
		);
		
		// 如果启用了MCP，添加MCP工具到payload
		if ($enable_mcp && $mcp_integration) {
			// 使用MCP集成添加工具到payload
			$aws_data = $mcp_integration->add_mcp_tools_to_payload($aws_data, array('message' => $message));
			error_log('添加MCP工具到payload: ' . print_r($aws_data, true));
		}
		
		// 获取AWS实例
		$aws = new AI_Chat_Bedrock_AWS();
		
		// 发送到AWS Bedrock
		$response = $aws->handle_chat_message($aws_data);
		
		// 检查是否有工具调用
		if (isset($response['tool_calls']) && !empty($response['tool_calls'])) {
			error_log('检测到工具调用: ' . print_r($response['tool_calls'], true));
			wp_send_json($response);
		} else {
			wp_send_json_error(array('message' => 'No tool calls found.'));
		}
	}

	/**
	 * Format tool result based on tool type
	 */
	private function format_tool_result($tool_call) {
		$tool_name = explode('___', $tool_call['name'])[1] ?? $tool_call['name'];
		$result = $tool_call['result'];
		
		switch ($tool_name) {
			case 'search_posts':
				if (!is_array($result)) return json_encode($result);
				
				$output = "找到 " . count($result) . " 篇文章：\n\n";
				foreach ($result as $post) {
					$output .= "标题：" . ($post['title'] ?? '无标题') . "\n";
					$output .= "发布日期：" . ($post['date'] ?? '未知日期') . "\n";
					$output .= "链接：" . ($post['url'] ?? '无链接') . "\n";
					$output .= "摘要：" . ($post['excerpt'] ?? '无摘要') . "\n\n";
				}
				return $output;
				
			case 'get_post':
				if (!isset($result['title'])) return json_encode($result);
				
				return "文章详情：\n" .
					   "标题：" . ($result['title'] ?? '无标题') . "\n" .
					   "发布日期：" . ($result['date'] ?? '未知日期') . "\n" .
					   "内容：" . ($result['content'] ?? '无内容') . "\n";
				
			case 'get_categories':
				if (!is_array($result)) return json_encode($result);
				
				$output = "分类列表：\n\n";
				foreach ($result as $category) {
					$output .= "- " . ($category['name'] ?? '未命名') . 
							  " (" . ($category['count'] ?? 0) . " 篇文章)\n";
				}
				return $output;
				
			case 'get_tags':
				if (!is_array($result)) return json_encode($result);
				
				$output = "标签列表：\n\n";
				foreach ($result as $tag) {
					$output .= "- " . ($tag['name'] ?? '未命名') . 
							  " (" . ($tag['count'] ?? 0) . " 篇文章)\n";
				}
				return $output;
				
			case 'get_site_info':
				return "网站信息：\n" .
					   "标题：" . ($result['title'] ?? '未知') . "\n" .
					   "描述：" . ($result['description'] ?? '无描述') . "\n" .
					   "网址：" . ($result['url'] ?? '无网址') . "\n";
				
			default:
				return json_encode($result);
		}
	}
}
