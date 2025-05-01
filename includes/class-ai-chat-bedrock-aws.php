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
			// Claude format
			$formatted_messages = array();
			foreach ( $messages as $message ) {
				$formatted_messages[] = array(
					'role' => $message['role'],
					'content' => $message['content'],
				);
			}
			
			$payload['anthropic_version'] = 'bedrock-2023-05-31';
			$payload['messages'] = $formatted_messages;
			
		} elseif ( strpos( $model_id, 'amazon.titan' ) !== false ) {
			// Titan format
			$prompt = '';
			foreach ( $messages as $message ) {
				if ( $message['role'] === 'user' ) {
					$prompt .= "Human: " . $message['content'] . "\n";
				} elseif ( $message['role'] === 'assistant' ) {
					$prompt .= "Assistant: " . $message['content'] . "\n";
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
	private function invoke_model( $payload, $model_id, $is_streaming = false, $stream_callback = null ) {
		$endpoint = "https://bedrock-runtime.{$this->region}.amazonaws.com";
		$service = 'bedrock';
		$host = "bedrock-runtime.{$this->region}.amazonaws.com";
		$content_type = 'application/json';
		
		$request_path = $is_streaming ? '/model/invoke-with-response-stream' : '/model/invoke';
		$request_parameters = '';
		
		$amz_target = $is_streaming ? 'BedrockRuntime.InvokeModelWithResponseStream' : 'BedrockRuntime.InvokeModel';
		
		$request_body = json_encode( $payload );
		
		// Add model ID to the request body
		$request_body_with_model = json_encode( array(
			'modelId' => $model_id,
			'contentType' => $content_type,
			'accept' => $content_type,
			'body' => $request_body,
		) );
		
		$datetime = new DateTime( 'UTC' );
		$amz_date = $datetime->format( 'Ymd\THis\Z' );
		$date_stamp = $datetime->format( 'Ymd' );
		
		// Create canonical request
		$canonical_uri = $request_path;
		$canonical_querystring = $request_parameters;
		$canonical_headers = "content-type:{$content_type}\n" . "host:{$host}\n" . "x-amz-date:{$amz_date}\n" . "x-amz-target:{$amz_target}\n";
		$signed_headers = 'content-type;host;x-amz-date;x-amz-target';
		$payload_hash = hash( 'sha256', $request_body_with_model );
		$canonical_request = "POST\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
		
		// Create string to sign
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date_stamp}/{$this->region}/{$service}/aws4_request";
		$string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash( 'sha256', $canonical_request );
		
		// Calculate signature
		$signing_key = $this->get_signature_key( $this->secret_key, $date_stamp, $this->region, $service );
		$signature = hash_hmac( 'sha256', $string_to_sign, $signing_key );
		
		// Create authorization header
		$authorization_header = "{$algorithm} " . "Credential={$this->access_key}/{$credential_scope}, " . "SignedHeaders={$signed_headers}, " . "Signature={$signature}";
		
		// Create request headers
		$request_headers = array(
			'Content-Type' => $content_type,
			'X-Amz-Date' => $amz_date,
			'X-Amz-Target' => $amz_target,
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
			'body' => $request_body_with_model,
			'cookies' => array(),
			'stream' => $is_streaming,
		);
		
		if ( $this->debug ) {
			$this->log_debug( 'Request URL:', $request_url );
			$this->log_debug( 'Request Headers:', $request_headers );
			$this->log_debug( 'Request Body:', $request_body_with_model );
		}
		
		// Make the request
		$response = wp_remote_post( $request_url, $args );
		
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log_debug( 'Request Error:', $error_message );
			return array(
				'success' => false,
				'message' => $error_message,
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
					'message' => "Error: HTTP {$response_code} - {$response_body}",
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
				'message' => 'Invalid response from server',
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
			'content' => $complete_response,
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
		
		if ( strpos( $model_id, 'anthropic.claude' ) !== false ) {
			// Claude format
			if ( isset( $response_data['content'] ) && is_array( $response_data['content'] ) ) {
				foreach ( $response_data['content'] as $content_block ) {
					if ( isset( $content_block['text'] ) ) {
						$content .= $content_block['text'];
					}
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
		
		return array(
			'success' => true,
			'content' => $content,
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
