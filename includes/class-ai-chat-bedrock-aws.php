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
		
		if ( $enable_streaming && isset( $message_data['streaming_callback'] ) ) {
			return $this->invoke_model( $payload, $model_id, true, $message_data['streaming_callback'] );
		} else {
			return $this->invoke_model( $payload, $model_id, false );
		}
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
		
		// Default payload structure
		$payload = array(
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
		);
		
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
			
			// 如果有系统消息，将其添加到第一个用户消息的前面
			if ( !empty( $system_content ) && !empty( $messages ) ) {
				foreach ( $messages as $key => $message ) {
					if ( $message['role'] === 'user' ) {
						$messages[$key]['content'] = $system_content . "\n\n" . $message['content'];
						break;
					}
				}
			}
			
			// 格式化消息，确保角色交替
			$formatted_messages = array();
			$last_role = null;
			
			foreach ( $messages as $message ) {
				// 只保留用户和助手角色
				if ( $message['role'] === 'user' || $message['role'] === 'assistant' ) {
					// 如果当前消息与上一条消息角色相同，需要插入一个空的对方角色消息
					if ( $last_role === $message['role'] ) {
						$empty_role = $message['role'] === 'user' ? 'assistant' : 'user';
						$formatted_messages[] = array(
							'role' => $empty_role,
							'content' => $empty_role === 'assistant' ? 'I understand.' : 'Please continue.',
						);
					}
					
					$formatted_messages[] = array(
						'role' => $message['role'],
						'content' => $message['content'],
					);
					
					$last_role = $message['role'];
				}
			}
			
			// 确保消息以用户角色开始
			if ( !empty( $formatted_messages ) && $formatted_messages[0]['role'] !== 'user' ) {
				array_unshift( $formatted_messages, array(
					'role' => 'user',
					'content' => 'Hello',
				) );
			}
			
			$payload['anthropic_version'] = 'bedrock-2023-05-31';
			$payload['messages'] = $formatted_messages;
			
			if ( $this->debug ) {
				$this->log_debug( 'Formatted Claude Messages:', $formatted_messages );
			}
			
		} elseif ( strpos( $model_id, 'amazon.titan' ) !== false ) {
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

	private function invoke_model( $payload, $model_id, $is_streaming = false, $stream_callback = null ) {
		$endpoint = "https://bedrock-runtime.{$this->region}.amazonaws.com";
		$service = 'bedrock';
		$host = "bedrock-runtime.{$this->region}.amazonaws.com";
		$content_type = 'application/json';
		
		// 构建请求路径
		$request_path = $is_streaming ? 
			"/model/{$model_id}/invoke-with-response-stream" : 
			"/model/{$model_id}/invoke";
		$request_parameters = '';
		
		// 不再使用X-Amz-Target头
		
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
		$request_url = "{$endpoint}{$request_path}";
		
		$args = array(
			'method' => 'POST',
			'timeout' => 60,
			'redirection' => 5,
			'httpversion' => '1.1',
			'blocking' => !$is_streaming,
			'headers' => $request_headers,
			'body' => $request_body,
			'cookies' => array(),
			'stream' => $is_streaming,
		);
		
		if ( $this->debug ) {
			$this->log_debug( 'Request URL:', $request_url );
			$this->log_debug( 'Request Headers:', $request_headers );
			$this->log_debug( 'Request Body:', $request_body );
			$this->log_debug( 'Payload (before wrapping):', $payload );
		}
		
		// Make the request
		$response = wp_remote_post( $request_url, $args );
		
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
		
		if ( $is_streaming && $stream_callback ) {
			return $this->process_stream( $response, $stream_callback );
		} else {
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
	}

	/**
	 * Process a streaming response.
	 *
	 * @since    1.0.0
	 * @param    mixed     $response          The response object.
	 * @param    callable  $callback          The callback function.
	 * @return   array                        The response data.
	 */
	private function process_stream( $response, $callback ) {
		if ( !is_resource( $response ) ) {
			if ( $this->debug ) {
				$this->log_debug( 'Stream error:', 'Invalid response object provided to process_stream' );
			}
			return array(
				'success' => false,
				'data' => array(
					'message' => 'Invalid response from server',
				),
			);
		}
		
		$buffer = '';
		$complete_response = '';
		$error_count = 0;
		$max_errors = 5;
		
		while ( !feof( $response ) ) {
			$chunk = fread( $response, 8192 );
			if ( $chunk === false ) {
				$error_count++;
				if ( $error_count > $max_errors ) {
					if ( $this->debug ) {
						$this->log_debug( 'Stream error:', 'Too many read errors, breaking stream' );
					}
					break;
				}
				continue;
			}
			
			$buffer .= $chunk;
			
			// Process complete messages from buffer
			while ( ( $pos = strpos( $buffer, "\n" ) ) !== false ) {
				$message = substr( $buffer, 0, $pos );
				$buffer = substr( $buffer, $pos + 1 );
				
				if ( !empty( $message ) ) {
					if ( $this->debug ) {
						$this->log_debug( 'Streaming response:', $message );
					}
					
					try {
						$event_data = $this->parse_event_data( $message );
						if ( !empty( $event_data ) ) {
							$callback( $event_data );
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
		
		// Process any remaining data in buffer
		if ( !empty( $buffer ) ) {
			try {
				$event_data = $this->parse_event_data( $buffer );
				if ( !empty( $event_data ) ) {
					$callback( $event_data );
					$complete_response .= isset( $event_data['content'] ) ? $event_data['content'] : '';
				}
			} catch ( Exception $e ) {
				if ( $this->debug ) {
					$this->log_debug( 'Final buffer parsing error:', $e->getMessage() );
				}
			}
		}
		
		fclose( $response );
		
		if ( $this->debug ) {
			$this->log_debug( 'Stream complete, total response length:', strlen( $complete_response ) );
		}
		
		return array(
			'success' => true,
			'data' => array(
				'message' => $complete_response,  // 修改结构为前端期望的格式
			),
		);
	}

	/**
	 * Parse event data from a streaming response.
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
			
			// Handle different model response formats
			if ( isset( $parsed['chunk'] ) && isset( $parsed['chunk']['bytes'] ) ) {
				// Claude streaming format
				$inner_chunk = json_decode( $parsed['chunk']['bytes'], true );
				if ( isset( $inner_chunk['type'] ) && $inner_chunk['type'] === 'content_block_delta' ) {
					$results['content'] = $inner_chunk['delta']['text'];
				}
			} elseif ( isset( $parsed['outputText'] ) ) {
				// Titan format
				$results['content'] = $parsed['outputText'];
			} elseif ( isset( $parsed['generation'] ) ) {
				// Llama format
				$results['content'] = $parsed['generation'];
			} elseif ( isset( $parsed['content'] ) ) {
				// Direct content format (our custom format)
				$results['content'] = $parsed['content'];
			} elseif ( isset( $parsed['data'] ) && isset( $parsed['data']['message'] ) ) {
				// 新的响应格式
				$results['data'] = array(
					'message' => $parsed['data']['message']
				);
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
		
		if ( $this->debug ) {
			$this->log_debug( 'Parsing response for model:', $model_id );
			$this->log_debug( 'Response data structure:', print_r( $response_data, true ) );
		}
		
		if ( strpos( $model_id, 'anthropic.claude' ) !== false ) {
			// Claude format - 新版API返回格式
			if ( isset( $response_data['content'] ) && is_array( $response_data['content'] ) ) {
				foreach ( $response_data['content'] as $content_block ) {
					if ( isset( $content_block['type'] ) && $content_block['type'] === 'text' && isset( $content_block['text'] ) ) {
						$content .= $content_block['text'];
					}
				}
				
				if ( $this->debug ) {
					$this->log_debug( 'Extracted content from Claude response:', $content );
				}
			}
		} elseif ( strpos( $model_id, 'amazon.titan' ) !== false ) {
			// Titan format
			if ( isset( $response_data['results'] ) && isset( $response_data['results'][0]['outputText'] ) ) {
				$content = $response_data['results'][0]['outputText'];
			}
		} elseif ( strpos( $model_id, 'meta.llama' ) !== false ) {
			// Llama format
			if ( isset( $response_data['generation'] ) ) {
				$content = $response_data['generation'];
			}
		}
		
		if ( empty( $content ) && $this->debug ) {
			$this->log_debug( 'Warning: Failed to extract content from response', '' );
		}
		
		return array(
			'success' => true,
			'data' => array(
				'message' => $content,  // 修改结构为前端期望的格式
			),
		);
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
