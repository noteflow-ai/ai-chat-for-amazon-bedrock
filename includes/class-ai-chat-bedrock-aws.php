<?php
/**
 * AWS Bedrock API Integration
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * AWS Bedrock API Integration Class.
 *
 * This class handles all interactions with the AWS Bedrock API.
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_AWS {

	/**
	 * AWS Region
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $region    The AWS region.
	 */
	private $region;

	/**
	 * AWS Access Key
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $access_key    The AWS access key.
	 */
	private $access_key;

	/**
	 * AWS Secret Key
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $secret_key    The AWS secret key.
	 */
	private $secret_key;

	/**
	 * Debug mode
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      bool    $debug    Whether debug mode is enabled.
	 */
	private $debug;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$options = get_option( 'ai_chat_bedrock_settings' );
		
		$this->region = isset( $options['aws_region'] ) ? $options['aws_region'] : 'us-east-1';
		$this->access_key = isset( $options['aws_access_key'] ) ? $options['aws_access_key'] : '';
		$this->secret_key = isset( $options['aws_secret_key'] ) ? $options['aws_secret_key'] : '';
		$this->debug = isset( $options['debug_mode'] ) && $options['debug_mode'] === 'on';
	}

	/**
	 * Handle a chat message by sending it to the AWS Bedrock API.
	 *
	 * @since    1.0.0
	 * @param    array    $message_data    The message data.
	 * @return   array                     The response data.
	 */
	public function handle_chat_message( $message_data ) {
		$options = get_option( 'ai_chat_bedrock_settings' );
		$model_id = isset( $options['model_id'] ) ? $options['model_id'] : 'anthropic.claude-3-sonnet-20240229-v1:0';
		$max_tokens = isset( $options['max_tokens'] ) ? intval( $options['max_tokens'] ) : 1000;
		$temperature = isset( $options['temperature'] ) ? floatval( $options['temperature'] ) : 0.7;
		$enable_streaming = isset( $options['enable_streaming'] ) && $options['enable_streaming'] === 'on';

		// Format the message based on the model
		$payload = $this->format_payload_for_model( $model_id, $message_data, $max_tokens, $temperature );
		
		// Apply filters to modify payload (this is where MCP tools should be added)
		$payload = apply_filters('ai_chat_bedrock_message_payload', $payload, $message_data);
		
		// Debug log the full payload
		if ($this->debug) {
			$this->log_debug('Full payload before sending:', $payload);
		}
		
		// 根据后台设置决定是否使用流式处理
		if ( $enable_streaming && isset( $message_data['streaming_callback'] ) ) {
			// 使用流式处理
			$this->log_debug( 'Using streaming mode', '' );
			
			// 获取流式处理的端点
			$endpoint = $this->get_bedrock_endpoint( $this->region, $model_id, true );
			
			// 调用流式处理API
			return $this->invoke_streaming_model( $payload, $model_id, $endpoint, $message_data['streaming_callback'] );
		} else {
			// 使用非流式处理
			$this->log_debug( 'Using non-streaming mode', '' );
			
			// 获取非流式处理的端点
			$endpoint = $this->get_bedrock_endpoint( $this->region, $model_id, false );
			
			// 调用非流式处理API
			return $this->invoke_model( $payload, $model_id, $endpoint );
		}
	}

	
	/**
	 * Get the Bedrock endpoint URL.
	 *
	 * @since    1.0.0
	 * @param    string    $region         The AWS region.
	 * @param    string    $model_id       The model ID.
	 * @param    bool      $should_stream  Whether to use streaming.
	 * @return   string                    The endpoint URL.
	 */
	private function get_bedrock_endpoint( $region, $model_id, $should_stream ) {
		if ( empty( $region ) || empty( $model_id ) ) {
			throw new Exception( 'Region and model ID are required for Bedrock endpoint' );
		}
		
		$base_endpoint = "https://bedrock-runtime.{$region}.amazonaws.com";
		$endpoint = $should_stream ? 
			"{$base_endpoint}/model/{$model_id}/invoke-with-response-stream" : 
			"{$base_endpoint}/model/{$model_id}/invoke";
		
		return $endpoint;
	}

	/**
	 * Format the payload for the specific model.
	 *
	 * @since    1.0.0
	 * @param    string    $model_id       The model ID.
	 * @param    array     $message_data   The message data.
	 * @param    int       $max_tokens     The maximum number of tokens.
	 * @param    float     $temperature    The temperature.
	 * @return   array                     The formatted payload.
	 */
	private function format_payload_for_model( $model_id, $message_data, $max_tokens, $temperature ) {
		$messages = isset( $message_data['messages'] ) ? $message_data['messages'] : array();
		$user_message = isset( $message_data['message'] ) ? $message_data['message'] : '';
		
		// Debug log the input data
		if ($this->debug) {
			$this->log_debug('Message data received:', $message_data);
			$this->log_debug('Messages array:', $messages);
			$this->log_debug('User message:', $user_message);
		}
		
		// Default payload structure
		$payload = array(
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
		);
		
		// Store original payload for tools and other extensions
		$original_payload = $message_data;
		
		// 确保至少有一条消息
		if (empty($messages)) {
			// 如果有用户消息但没有历史消息，创建一个用户消息
			if (!empty($user_message)) {
				$messages[] = array(
					'role' => 'user',
					'content' => $user_message
				);
				
				if ($this->debug) {
					$this->log_debug('Added user message to empty messages array:', $user_message);
				}
			} else {
				// 如果既没有历史消息也没有用户消息，添加一个默认消息
				$messages[] = array(
					'role' => 'user',
					'content' => 'What are the recent posts on this site?'
				);
				
				if ($this->debug) {
					$this->log_debug('Added default message to empty messages array', '');
				}
			}
		}
		
		// Format based on model provider
		if ( strpos( $model_id, 'anthropic.claude' ) !== false ) {
			// Claude format - 根据错误消息，需要调整消息格式
			
			// 查找系统消息
			$system_content = '';
			foreach ( $messages as $key => $message ) {
				if ( $message['role'] === 'system' ) {
					$system_content = $message['content'];
					// 从消息数组中移除系统消息
					unset( $messages[$key] );
					break;
				}
			}
			
			// 重新索引数组
			$messages = array_values( $messages );
			
			// 如果有系统消息，将其添加为顶级参数
			if ( !empty( $system_content ) ) {
				$payload['system'] = $system_content;
			}
			
			// 格式化消息，确保角色交替
			$formatted_messages = array();
			$last_role = null;
			
			foreach ( $messages as $message ) {
				// 检查消息格式，确保content是正确的格式
				if (isset($message['content']) && !is_array($message['content']) && 
					($message['role'] === 'user' || $message['role'] === 'assistant')) {
					// 将字符串content转换为Claude 3.7需要的数组格式
					$message['content'] = array(
						array(
							'type' => 'text',
							'text' => $message['content']
						)
					);
				}
				
				// 只保留用户和助手角色
				if ( $message['role'] === 'user' || $message['role'] === 'assistant' || $message['role'] === 'tool' ) {
					// 如果当前消息与上一条消息角色相同，需要插入一个空的对方角色消息
					if ( $last_role === $message['role'] && $message['role'] !== 'tool' ) {
						$empty_role = $message['role'] === 'user' ? 'assistant' : 'user';
						$formatted_messages[] = array(
							'role' => $empty_role,
							'content' => array(
								array(
									'type' => 'text',
									'text' => $empty_role === 'assistant' ? 'I understand.' : 'Please continue.'
								)
							)
						);
					}
					
					$formatted_messages[] = $message;
					
					$last_role = $message['role'];
				}
			}
			
			// 确保消息以用户角色开始
			if ( !empty( $formatted_messages ) && $formatted_messages[0]['role'] !== 'user' ) {
				array_unshift( $formatted_messages, array(
					'role' => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'Hello'
						)
					)
				) );
			}
			
			// 根据Claude版本选择不同的API版本
			if ( strpos( $model_id, 'claude-3.5' ) !== false || strpos( $model_id, 'claude-3.7' ) !== false ) {
				$payload['anthropic_version'] = 'bedrock-2023-05-31';
			} else {
				$payload['anthropic_version'] = 'bedrock-2023-05-31';
			}
			
			$payload['messages'] = $formatted_messages;
			
			// Add tools to payload if available
			if (isset($original_payload['tools']) && !empty($original_payload['tools'])) {
				$payload['tools'] = $original_payload['tools'];
				$this->log_debug('Adding tools to Claude payload:', $payload['tools']);
				
				// Add tool_choice to indicate the model should use tools when appropriate
				// Format as object with type field, not just a string
				$payload['tool_choice'] = array('type' => 'auto');
			}
			
			if ( $this->debug ) {
				$this->log_debug( 'Formatted Claude Messages:', $formatted_messages );
			}
			
		} elseif ( strpos( $model_id, 'amazon.titan' ) !== false || strpos( $model_id, 'amazon.nova' ) !== false ) {
			// Titan format
			$prompt = '';
			foreach ( $messages as $message ) {
				if ( $message['role'] === 'user' ) {
					$prompt .= "Human: " . $message['content'] . "\n";
				} elseif ( $message['role'] === 'assistant' ) {
					$prompt .= "Assistant: " . $message['content'] . "\n";
				} elseif ( $message['role'] === 'system' ) {
					$prompt = $message['content'] . "\n\n" . $prompt;
				}
			}
			$prompt .= "Assistant: ";
			
			$payload['inputText'] = $prompt;
			
		} elseif ( strpos( $model_id, 'meta.llama' ) !== false ) {
			// Llama format
			$prompt = '';
			foreach ( $messages as $message ) {
				if ( $message['role'] === 'user' ) {
					$prompt .= "User: " . $message['content'] . "\n";
				} elseif ( $message['role'] === 'assistant' ) {
					$prompt .= "Assistant: " . $message['content'] . "\n";
				} elseif ( $message['role'] === 'system' ) {
					$prompt = $message['content'] . "\n\n" . $prompt;
				}
			}
			$prompt .= "Assistant: ";
			
			$payload['prompt'] = $prompt;
		} elseif ( strpos( $model_id, 'deepseek' ) !== false ) {
			// DeepSeek R1 format
			$formatted_messages = array();
			
			// 查找系统消息
			$system_content = '';
			foreach ( $messages as $key => $message ) {
				if ( $message['role'] === 'system' ) {
					$system_content = $message['content'];
					break;
				}
			}
			
			// 如果有系统消息，添加到格式化消息中
			if ( !empty( $system_content ) ) {
				$formatted_messages[] = array(
					'role' => 'system',
					'content' => $system_content,
				);
			}
			
			// 添加其他消息
			foreach ( $messages as $message ) {
				if ( $message['role'] !== 'system' ) {
					$formatted_messages[] = array(
						'role' => $message['role'],
						'content' => $message['content'],
					);
				}
			}
			
			$payload['messages'] = $formatted_messages;
		}
		
		return $payload;
	}

	/**
	 * Invoke the AWS Bedrock model.
	 *
	 * @since    1.0.0
	 * @param    array     $payload           The payload to send.
	 * @param    string    $model_id          The model ID.
	 * @param    bool      $is_streaming      Whether to use streaming.
	 * @param    callable  $stream_callback   The streaming callback function.
	 * @return   array                        The response data.
	 */
	/**
	 * Get the canonical URI for AWS request signing.
	 *
	 * @since    1.0.0
	 * @param    string    $path    The request path.
	 * @return   string             The canonical URI.
	 */
	private function get_canonical_uri($path) {
		// 如果路径为空或只有根路径，直接返回"/"
		if (empty($path) || $path === '/') return '/';
		
		// 分割路径
		$segments = explode('/', trim($path, '/'));
		$canonical_segments = array_map(function($segment) {
			// 空段保持为空
			if (empty($segment)) return '';
			
			// 对于特殊操作名称，不进行编码
			if ($segment === 'invoke' || $segment === 'invoke-with-response-stream') {
				return $segment;
			}
			
			// 对于包含模型ID的段，需要特殊处理
			if (strpos($segment, 'model/') !== false) {
				// 分割"model/"和模型ID
				$parts = explode('model/', $segment, 2);
				return 'model/' . rawurlencode($parts[1]);
			}
			
			// 对其他段进行URL编码
			return rawurlencode($segment);
		}, $segments);
		
		// 重新组合路径
		return '/' . implode('/', $canonical_segments);
	}

	/**
	 * Invoke the AWS Bedrock model (non-streaming).
	 *
	 * @since    1.0.0
	 * @param    array     $payload           The payload to send.
	 * @param    string    $model_id          The model ID.
	 * @param    string    $endpoint          The endpoint URL.
	 * @return   array                        The response data.
	 */
	/**
	 * Invoke the AWS Bedrock model (non-streaming).
	 *
	 * @since    1.0.0
	 * @param    array     $payload           The payload to send.
	 * @param    string    $model_id          The model ID.
	 * @param    string    $endpoint          The endpoint URL.
	 * @return   array                        The response data.
	 */
	private function invoke_model( $payload, $model_id, $endpoint ) {
		$service = 'bedrock';
		$host = parse_url( $endpoint, PHP_URL_HOST );
		$content_type = 'application/json';
		
		// 构建请求路径
		$request_path = parse_url( $endpoint, PHP_URL_PATH );
		$request_parameters = '';
		
		// 直接使用payload作为请求体，不再包装
		$request_body = json_encode($payload);
		
		$datetime = new DateTime( 'UTC' );
		$amz_date = $datetime->format( 'Ymd\THis\Z' );
		$date_stamp = $datetime->format( 'Ymd' );
		
		// 获取规范URI
		$canonical_uri = $this->get_canonical_uri($request_path);
		
		if ( $this->debug ) {
			$this->log_debug( 'Original Request Path:', $request_path );
			$this->log_debug( 'Canonical URI:', $canonical_uri );
		}
		$canonical_querystring = $request_parameters;
		$canonical_headers = "content-type:{$content_type}\nhost:{$host}\nx-amz-date:{$amz_date}\n";
		$signed_headers = 'content-type;host;x-amz-date';
		$payload_hash = hash( 'sha256', $request_body );
		$canonical_request = "POST\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
		
		if ( $this->debug ) {
			$this->log_debug( 'Canonical Request:', $canonical_request );
			$this->log_debug( 'Payload Hash:', $payload_hash );
		}
		
		// Create string to sign
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date_stamp}/{$this->region}/{$service}/aws4_request";
		$string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );
		
		if ( $this->debug ) {
			$this->log_debug( 'String to Sign:', $string_to_sign );
			$this->log_debug( 'Credential Scope:', $credential_scope );
		}
		
		// Calculate signature
		$signing_key = $this->get_signature_key( $this->secret_key, $date_stamp, $this->region, $service );
		$signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );
		
		// Create authorization header
		$authorization_header = "{$algorithm} " . "Credential={$this->access_key}/{$credential_scope}, " . "SignedHeaders={$signed_headers}, " . "Signature={$signature}";
		
		// Create request headers
		$request_headers = array(
			'Content-Type' => $content_type,
			'X-Amz-Date' => $amz_date,
			'Authorization' => $authorization_header,
		);
		
		// Prepare the request
		$args = array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking' => true,
			'headers' => $request_headers,
			'body' => $request_body,
			'cookies' => array(),
		);
		
		if ( $this->debug ) {
			$this->log_debug( 'Request URL:', $endpoint );
			$this->log_debug( 'Request Headers:', $request_headers );
			$this->log_debug( 'Request Body:', $request_body );
			$this->log_debug( 'Payload:', $payload );
		}
		
		// Make the request
		$response = wp_remote_post( $endpoint, $args );
		
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log_debug( 'Request Error:', $error_message );
			return array(
				'success' => false,
				'data' => array(
					'message' => $error_message,
				),
			);
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $this->debug ) {
			$this->log_debug( 'Response Code:', $response_code );
			$this->log_debug( 'Response Body:', $response_body );
		}
		
		if ( $response_code >= 200 && $response_code < 300 ) {
			$response_data = json_decode( $response_body, true );
			return $this->parse_model_response( $response_data, $model_id );
		} else {
			return array(
				'success' => false,
				'data' => array(
					'message' => "Error: HTTP {$response_code} - {$response_body}",
				),
			);
		}
	}
	
	/**
	 * Invoke the AWS Bedrock model with streaming.
	 *
	 * @since    1.0.0
	 * @param    array     $payload           The payload to send.
	 * @param    string    $model_id          The model ID.
	 * @param    string    $endpoint          The endpoint URL.
	 * @param    callable  $stream_callback   The streaming callback function.
	 * @return   array                        The response data.
	 */
	private function invoke_streaming_model( $payload, $model_id, $endpoint, $stream_callback ) {
		$service = 'bedrock';
		$host = parse_url( $endpoint, PHP_URL_HOST );
		$content_type = 'application/json';
		
		// 构建请求路径
		$request_path = parse_url( $endpoint, PHP_URL_PATH );
		$request_parameters = '';
		
		// 直接使用payload作为请求体，不再包装
		$request_body = json_encode($payload);
		
		$datetime = new DateTime( 'UTC' );
		$amz_date = $datetime->format( 'Ymd\THis\Z' );
		$date_stamp = $datetime->format( 'Ymd' );
		
		// 获取规范URI
		$canonical_uri = $this->get_canonical_uri($request_path);
		
		if ( $this->debug ) {
			$this->log_debug( 'Original Request Path:', $request_path );
			$this->log_debug( 'Canonical URI:', $canonical_uri );
		}
		$canonical_querystring = $request_parameters;
		$canonical_headers = "content-type:{$content_type}\nhost:{$host}\nx-amz-date:{$amz_date}\n";
		$signed_headers = 'content-type;host;x-amz-date';
		$payload_hash = hash( 'sha256', $request_body );
		$canonical_request = "POST\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
		
		if ( $this->debug ) {
			$this->log_debug( 'Canonical Request:', $canonical_request );
			$this->log_debug( 'Payload Hash:', $payload_hash );
		}
		
		// Create string to sign
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date_stamp}/{$this->region}/{$service}/aws4_request";
		$string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );
		
		if ( $this->debug ) {
			$this->log_debug( 'String to Sign:', $string_to_sign );
			$this->log_debug( 'Credential Scope:', $credential_scope );
		}
		
		// Calculate signature
		$signing_key = $this->get_signature_key( $this->secret_key, $date_stamp, $this->region, $service );
		$signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );
		
		// Create authorization header
		$authorization_header = "{$algorithm} " . "Credential={$this->access_key}/{$credential_scope}, " . "SignedHeaders={$signed_headers}, " . "Signature={$signature}";
		
		// Create request headers
		$request_headers = array(
			'Content-Type' => $content_type,
			'X-Amz-Date' => $amz_date,
			'Authorization' => $authorization_header,
		);
		
		// Prepare the request
		$args = array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking' => true,
			'headers' => $request_headers,
			'body' => $request_body,
			'cookies' => array(),
		);
		
		if ( $this->debug ) {
			$this->log_debug( 'Streaming Request URL:', $endpoint );
			$this->log_debug( 'Streaming Request Headers:', $request_headers );
			$this->log_debug( 'Streaming Request Body:', $request_body );
			$this->log_debug( 'Streaming Payload:', $payload );
		}
		
		// Make the request
		$response = wp_remote_post( $endpoint, $args );
		
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log_debug( 'Streaming Request Error:', $error_message );
			return array(
				'success' => false,
				'data' => array(
					'message' => $error_message,
				),
			);
		}
		
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		
		if ( $this->debug ) {
			$this->log_debug( 'Streaming Response Code:', $response_code );
			$this->log_debug( 'Streaming Response Body (first 200 chars):', substr($response_body, 0, 200) . '...' );
		}
		
		if ( $response_code >= 200 && $response_code < 300 ) {
			// 尝试解析JSON响应
			$json_data = json_decode( $response_body, true );
			if ( $json_data !== null && json_last_error() === JSON_ERROR_NONE ) {
				$this->log_debug( 'Successfully parsed streaming JSON response', '' );
				
				// 提取内容
				$content = $this->extract_content_from_json( $json_data );
				if ( !empty( $content ) ) {
					// 发送内容给回调函数
					$stream_callback( array( 'content' => $content ) );
					
					return array(
						'success' => true,
						'data' => array(
							'message' => $content,
						),
					);
				}
			}
			
			// 检查响应体是否是二进制数据
			if ( $this->is_binary_data( $response_body ) ) {
				$this->log_debug( 'Detected binary response', '' );
				
				// 尝试解析二进制响应
				$complete_response = $this->parse_binary_response( $response_body, $stream_callback );
				
				if ( !empty( $complete_response ) ) {
					return array(
						'success' => true,
						'data' => array(
							'message' => $complete_response,
						),
					);
				}
			} else {
				// 如果不是二进制数据，尝试按行解析
				$lines = explode( "\n", $response_body );
				
				$complete_response = '';
				foreach ( $lines as $line ) {
					if ( !empty( $line ) ) {
						try {
							$event_data = $this->parse_event_data( $line );
							if ( !empty( $event_data ) ) {
								$stream_callback( $event_data );
								$complete_response .= isset( $event_data['content'] ) ? $event_data['content'] : '';
							}
						} catch ( Exception $e ) {
							if ( $this->debug ) {
								$this->log_debug( 'Stream parsing error:', $e->getMessage() );
							}
						}
					}
				}
			}
			
			if ( !empty( $complete_response ) ) {
				return array(
					'success' => true,
					'data' => array(
						'message' => $complete_response,
					),
				);
			} else {
				$this->log_debug( 'Failed to extract content from streaming response body', '' );
				return array(
					'success' => false,
					'data' => array(
						'message' => 'Failed to extract content from streaming response',
					),
				);
			}
		} else {
			return array(
				'success' => false,
				'data' => array(
					'message' => "Error: HTTP {$response_code} - {$response_body}",
				),
			);
		}
	}




	/**
	 * Parse event data from a streaming response.
	 * 
	 * This function is based on the TypeScript processMessage function.
	 *
	 * @since    1.0.0
	 * @param    string    $chunk    The chunk of data.
	 * @return   array               The parsed event data.
	 */
	private function parse_event_data( $chunk ) {
		if ( empty( $chunk ) ) {
			return array();
		}
		
		// Remove "data: " prefix if present (standard SSE format)
		if (strpos($chunk, 'data: ') === 0) {
			$chunk = substr($chunk, 6);
		}
		
		$text = trim($chunk);
		$results = array();
		
		try {
			// First try to parse as regular JSON
			$parsed = json_decode( $text, true );
			if ( $parsed === null && json_last_error() !== JSON_ERROR_NONE ) {
				if ( $this->debug ) {
					$this->log_debug( 'JSON parse error:', json_last_error_msg() . ' - Raw text: ' . substr($text, 0, 100) );
				}
				
				// Try to extract valid JSON if the chunk contains multiple JSON objects
				if (preg_match('/(\{.*\})/', $text, $matches)) {
					$parsed = json_decode($matches[1], true);
					if ($parsed === null) {
						throw new Exception('Could not extract valid JSON: ' . json_last_error_msg());
					}
				} else {
					throw new Exception('Invalid JSON: ' . json_last_error_msg());
				}
			}
			
			// 添加调试日志
			if ( $this->debug ) {
				$this->log_debug( 'Parsed event data:', $parsed );
			}
			
			// 严格参考TypeScript代码中的processMessage函数
			$content = '';
			
			// Handle Nova's tool calls with exact schema match
			if ( isset( $parsed['contentBlockStart']['start']['toolUse'] ) ) {
				// 工具调用，在PHP中我们可能不需要处理这个
				$this->log_debug( 'Tool use detected:', $parsed['contentBlockStart']['start']['toolUse'] );
				return $results;
			}
			
			// Handle Nova's tool input in contentBlockDelta
			if ( isset( $parsed['contentBlockDelta']['delta']['toolUse']['input'] ) ) {
				// 工具输入，在PHP中我们可能不需要处理这个
				$this->log_debug( 'Tool input detected:', $parsed['contentBlockDelta']['delta']['toolUse']['input'] );
				return $results;
			}
			
			// Handle Nova's text content
			if ( isset( $parsed['output']['message']['content'][0]['text'] ) ) {
				$content = $parsed['output']['message']['content'][0]['text'];
				$results['content'] = $content;
				return $results;
			}
			
			// Handle Nova's messageStart event
			if ( isset( $parsed['messageStart'] ) ) {
				// 消息开始事件，不需要处理内容
				return $results;
			}
			
			// Handle Nova's text delta
			if ( isset( $parsed['contentBlockDelta']['delta']['text'] ) ) {
				$content = $parsed['contentBlockDelta']['delta']['text'];
				$results['content'] = $content;
				return $results;
			}
			
			// Handle Nova's contentBlockStop event
			if ( isset( $parsed['contentBlockStop'] ) ) {
				// 内容块结束事件，不需要处理内容
				return $results;
			}
			
			// Handle Nova's messageStop event
			if ( isset( $parsed['messageStop'] ) ) {
				// 消息结束事件，不需要处理内容
				return $results;
			}
			
			// Handle message_start event (for other models)
			if ( isset( $parsed['type'] ) && $parsed['type'] === 'message_start' ) {
				// 消息开始事件，不需要处理内容
				return $results;
			}
			
			// Handle content_block_start event (for other models)
			if ( isset( $parsed['type'] ) && $parsed['type'] === 'content_block_start' ) {
				// 内容块开始事件，不需要处理内容
				return $results;
			}
			
			// Handle content_block_delta event (for other models)
			if ( isset( $parsed['type'] ) && $parsed['type'] === 'content_block_delta' ) {
				if ( isset( $parsed['delta']['type'] ) && $parsed['delta']['type'] === 'input_json_delta' ) {
					// JSON输入增量，在PHP中我们可能不需要处理这个
					return $results;
				} else if ( isset( $parsed['delta']['type'] ) && $parsed['delta']['type'] === 'text_delta' ) {
					$content = $parsed['delta']['text'] ?? '';
					if ( !empty( $content ) ) {
						$results['content'] = $content;
					}
					return $results;
				}
			}
			
			// Handle tool calls for other models
			if ( isset( $parsed['choices'][0]['message']['tool_calls'] ) ) {
				// 工具调用，在PHP中我们可能不需要处理这个
				$this->log_debug( 'Tool calls detected:', $parsed['choices'][0]['message']['tool_calls'] );
				return $results;
			}
			
			// Handle Claude streaming format (特殊处理)
			if ( isset( $parsed['chunk'] ) && isset( $parsed['chunk']['bytes'] ) ) {
				$inner_chunk = json_decode( $parsed['chunk']['bytes'], true );
				if ( isset( $inner_chunk['type'] ) && $inner_chunk['type'] === 'content_block_delta' ) {
					$content = $inner_chunk['delta']['text'] ?? '';
					if ( !empty( $content ) ) {
						$results['content'] = $content;
					}
					return $results;
				}
			}
			
			// Handle various response formats
			if ( isset( $parsed['delta']['text'] ) ) {
				$content = $parsed['delta']['text'];
			} else if ( isset( $parsed['choices'][0]['message']['content'] ) ) {
				$content = $parsed['choices'][0]['message']['content'];
			} else if ( isset( $parsed['content'][0]['text'] ) ) {
				$content = $parsed['content'][0]['text'];
			} else if ( isset( $parsed['generation'] ) ) {
				$content = $parsed['generation'];
			} else if ( isset( $parsed['outputText'] ) ) {
				$content = $parsed['outputText'];
			} else if ( isset( $parsed['response'] ) ) {
				$content = $parsed['response'];
			} else if ( isset( $parsed['output'] ) && is_string( $parsed['output'] ) ) {
				$content = $parsed['output'];
			} else if ( isset( $parsed['content'] ) && is_string( $parsed['content'] ) ) {
				$content = $parsed['content'];
			} else if ( isset( $parsed['data'] ) && isset( $parsed['data']['message'] ) ) {
				$results['data'] = array(
					'message' => $parsed['data']['message']
				);
				return $results;
			}
			
			// 如果提取到了内容，添加到结果中
			if ( !empty( $content ) ) {
				$results['content'] = $content;
			}
			
			// 添加调试日志
			if ( $this->debug ) {
				$this->log_debug( 'Event results:', $results );
			}
			
			return $results;
		} catch ( Exception $e ) {
			if ( $this->debug ) {
				$this->log_debug( 'Event parsing error:', $e->getMessage() );
			}
			return array();
		}
	}

	/**
	 * Parse the model response.
	 *
	 * @since    1.0.0
	 * @param    array     $response_data    The response data.
	 * @param    string    $model_id         The model ID.
	 * @return   array                       The parsed response.
	 */
	private function parse_model_response( $response_data, $model_id ) {
		$content = '';
		$tool_calls = array();
		
		if ( $this->debug ) {
			$this->log_debug( 'Parsing response for model:', $model_id );
			$this->log_debug( 'Response data structure:', print_r( $response_data, true ) );
		}
		
		// 参考TypeScript代码中的extractMessage函数
		if ( strpos( $model_id, 'nova' ) !== false ) {
			// Handle Nova model response format
			if ( isset( $response_data['output']['message']['content'][0]['text'] ) ) {
				$content = $response_data['output']['message']['content'][0]['text'];
			} else if ( isset( $response_data['output'] ) && is_string( $response_data['output'] ) ) {
				$content = $response_data['output'];
			}
		} else if ( strpos( $model_id, 'mistral' ) !== false ) {
			// Handle Mistral model response format
			if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
				$content = $response_data['choices'][0]['message']['content'];
			} else if ( isset( $response_data['output'] ) && is_string( $response_data['output'] ) ) {
				$content = $response_data['output'];
			}
		} else if ( strpos( $model_id, 'llama' ) !== false || strpos( $model_id, 'meta' ) !== false ) {
			// Handle Llama model response format
			$content = isset( $response_data['generation'] ) ? $response_data['generation'] : '';
		} else if ( strpos( $model_id, 'titan' ) !== false ) {
			// Handle Titan model response format
			$content = isset( $response_data['outputText'] ) ? $response_data['outputText'] : '';
			// 兼容旧版API
			if ( empty( $content ) && isset( $response_data['results'] ) && isset( $response_data['results'][0]['outputText'] ) ) {
				$content = $response_data['results'][0]['outputText'];
			}
		} else if ( strpos( $model_id, 'claude' ) !== false ) {
			// Handle Claude model response format (包括Claude 3.5和Claude 3.7)
			if ( isset( $response_data['content'] ) && is_array( $response_data['content'] ) ) {
				foreach ( $response_data['content'] as $content_block ) {
					if ( isset( $content_block['type'] ) && $content_block['type'] === 'text' && isset( $content_block['text'] ) ) {
						$content .= $content_block['text'];
					} else if ( isset( $content_block['type'] ) && $content_block['type'] === 'tool_use' ) {
						// Extract tool calls from Claude response
						$tool_calls[] = array(
							'id' => isset($content_block['id']) ? $content_block['id'] : uniqid('tool_call_'),
							'name' => $content_block['name'],
							'parameters' => isset($content_block['input']) ? $content_block['input'] : array(),
						);
						
						if ( $this->debug ) {
							$this->log_debug( 'Found tool call in Claude response:', $content_block );
						}
					}
				}
				
				if ( $this->debug ) {
					$this->log_debug( 'Extracted content from Claude response:', $content );
					if (!empty($tool_calls)) {
						$this->log_debug( 'Extracted tool calls from Claude response:', $tool_calls );
					}
				}
			}
		} else if ( strpos( $model_id, 'deepseek' ) !== false ) {
			// Handle DeepSeek R1 model response format
			if ( isset( $response_data['choices'][0]['message']['content'] ) ) {
				$content = $response_data['choices'][0]['message']['content'];
			} else if ( isset( $response_data['output'] ) && is_string( $response_data['output'] ) ) {
				$content = $response_data['output'];
			} else if ( isset( $response_data['response'] ) ) {
				$content = $response_data['response'];
			}
		} else {
			// Handle other response formats
			if ( isset( $response_data['content'][0]['text'] ) ) {
				$content = $response_data['content'][0]['text'];
			} else if ( isset( $response_data['output'] ) && is_string( $response_data['output'] ) ) {
				$content = $response_data['output'];
			} else if ( isset( $response_data['response'] ) ) {
				$content = $response_data['response'];
			} else if ( isset( $response_data['message'] ) ) {
				$content = $response_data['message'];
			}
		}
		
		if ( empty( $content ) && $this->debug ) {
			$this->log_debug( 'Warning: Failed to extract content from response', '' );
		}
		
		$result = array(
			'success' => true,
			'data' => array(
				'message' => $content,  // 修改结构为前端期望的格式
			),
		);
		
		// Add tool calls to response if any
		if (!empty($tool_calls)) {
			$result['tool_calls'] = $tool_calls;
		}
		
		return $result;
	}

	/**
	 * Get the signature key for AWS request signing.
	 *
	 * @since    1.0.0
	 * @param    string    $key         The key to use.
	 * @param    string    $date_stamp  The date stamp.
	 * @param    string    $region      The AWS region.
	 * @param    string    $service     The AWS service.
	 * @return   string                 The signature key.
	 */
	private function get_signature_key( $key, $date_stamp, $region, $service ) {
		$k_date = hash_hmac( 'sha256', $date_stamp, 'AWS4' . $key, true );
		$k_region = hash_hmac( 'sha256', $region, $k_date, true );
		$k_service = hash_hmac( 'sha256', $service, $k_region, true );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
		
		if ( $this->debug ) {
			$this->log_debug( 'Signing Key Components:', array(
				'k_date_hex' => bin2hex($k_date),
				'k_region_hex' => bin2hex($k_region),
				'k_service_hex' => bin2hex($k_service),
				'k_signing_hex' => bin2hex($k_signing),
			));
		}
		
		return $k_signing;
	}

	/**
	 * Log debug information.
	 *
	 * @since    1.0.0
	 * @param    string    $title    The log title.
	 * @param    mixed     $data     The data to log.
	 */
	private function log_debug( $title, $data ) {
		if ( $this->debug ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				$data = print_r( $data, true );
			}
			
			error_log( "AI Chat Bedrock Debug - {$title} {$data}" );
		}
	}

	/**
	 * Extract content from JSON response.
	 *
	 * @since    1.0.0
	 * @param    array     $json_data    The JSON data.
	 * @return   string                  The extracted content.
	 */
	private function extract_content_from_json( $json_data ) {
		$content = '';
		
		// 参考TypeScript代码中的extractMessage函数
		if ( isset( $json_data['output']['message']['content'][0]['text'] ) ) {
			$content = $json_data['output']['message']['content'][0]['text'];
		} else if ( isset( $json_data['contentBlockDelta']['delta']['text'] ) ) {
			$content = $json_data['contentBlockDelta']['delta']['text'];
		} else if ( isset( $json_data['choices'][0]['message']['content'] ) ) {
			$content = $json_data['choices'][0]['message']['content'];
		} else if ( isset( $json_data['content'][0]['text'] ) ) {
			$content = $json_data['content'][0]['text'];
		} else if ( isset( $json_data['outputText'] ) ) {
			$content = $json_data['outputText'];
		} else if ( isset( $json_data['generation'] ) ) {
			$content = $json_data['generation'];
		} else if ( isset( $json_data['response'] ) ) {
			$content = $json_data['response'];
		} else if ( isset( $json_data['output'] ) && is_string( $json_data['output'] ) ) {
			$content = $json_data['output'];
		} else if ( isset( $json_data['message'] ) ) {
			$content = $json_data['message'];
		} else if ( isset( $json_data['id'] ) && isset( $json_data['content'] ) && is_array( $json_data['content'] ) ) {
			// Claude format
			foreach ( $json_data['content'] as $content_block ) {
				if ( isset( $content_block['type'] ) && $content_block['type'] === 'text' && isset( $content_block['text'] ) ) {
					$content .= $content_block['text'];
				}
			}
		}
		
		return $content;
	}

	/**
	 * Check if data is binary.
	 *
	 * @since    1.0.0
	 * @param    string    $data    The data to check.
	 * @return   bool               Whether the data is binary.
	 */
	private function is_binary_data( $data ) {
		// 检查数据是否包含非打印字符
		return preg_match( '/[^\x20-\x7E\t\r\n]/', $data ) === 1;
	}
	
	/**
	 * Parse binary response.
	 *
	 * @since    1.0.0
	 * @param    string    $binary_data      The binary data.
	 * @param    callable  $stream_callback  The streaming callback function.
	 * @return   string                      The extracted content.
	 */
	private function parse_binary_response( $binary_data, $stream_callback ) {
		$complete_response = '';
		
		// 尝试解析二进制响应
		$this->log_debug( 'Binary response length:', strlen( $binary_data ) );
		
		// 检查是否包含Base64编码的数据
		if ( preg_match_all( '/"bytes"\s*:\s*"([^"]+)"/', $binary_data, $matches ) ) {
			$this->log_debug( 'Found Base64 encoded data:', count( $matches[1] ) . ' matches' );
			
			foreach ( $matches[1] as $base64_data ) {
				// 解码Base64数据
				$decoded_data = base64_decode( $base64_data );
				if ( $decoded_data === false ) {
					$this->log_debug( 'Failed to decode Base64 data', '' );
					continue;
				}
				
				// 尝试解析JSON
				$json_data = json_decode( $decoded_data, true );
				if ( $json_data === null && json_last_error() !== JSON_ERROR_NONE ) {
					$this->log_debug( 'Failed to parse JSON from decoded data:', json_last_error_msg() );
					continue;
				}
				
				$this->log_debug( 'Successfully decoded and parsed JSON from Base64 data', '' );
				$this->log_debug( 'Decoded JSON structure:', print_r( $json_data, true ) );
				
				// 处理解码后的JSON数据
				if ( isset( $json_data['type'] ) && $json_data['type'] === 'content_block_delta' ) {
					if ( isset( $json_data['delta']['text'] ) ) {
						$content = $json_data['delta']['text'];
						$complete_response .= $content;
						
						// 发送内容给回调函数
						$stream_callback( array( 'content' => $content ) );
					}
				} else if ( isset( $json_data['type'] ) && $json_data['type'] === 'message_start' ) {
					// 消息开始事件，不需要处理内容
					$this->log_debug( 'Message start event', '' );
				} else if ( isset( $json_data['type'] ) && $json_data['type'] === 'content_block_start' ) {
					// 内容块开始事件，不需要处理内容
					$this->log_debug( 'Content block start event', '' );
				} else if ( isset( $json_data['type'] ) && $json_data['type'] === 'message_delta' ) {
					// 消息增量事件，不需要处理内容
					$this->log_debug( 'Message delta event', '' );
				} else if ( isset( $json_data['type'] ) && $json_data['type'] === 'message_stop' ) {
					// 消息结束事件，不需要处理内容
					$this->log_debug( 'Message stop event', '' );
				} else {
					// 尝试提取内容
					$content = $this->extract_content_from_json( $json_data );
					if ( !empty( $content ) ) {
						$complete_response .= $content;
						
						// 发送内容给回调函数
						$stream_callback( array( 'content' => $content ) );
					}
				}
			}
		}
		
		return $complete_response;
	}

	/**
	 * Sanitize data for logging.
	 *
	 * @since    1.0.0
	 * @param    mixed     $data    The data to sanitize.
	 * @return   mixed              The sanitized data.
	 */
	private function sanitize_log_data( $data ) {
		// Sanitize sensitive data before logging
		if ( is_string( $data ) ) {
			// Mask AWS keys
			$data = preg_replace( '/("AWS[^"]*Key":\s*")[^"]+(")/i', '$1*****$2', $data );
			$data = preg_replace( '/("Secret[^"]*":\s*")[^"]+(")/i', '$1*****$2', $data );
			$data = preg_replace( '/("Password[^"]*":\s*")[^"]+(")/i', '$1*****$2', $data );
		}
		
		return $data;
	}
}
