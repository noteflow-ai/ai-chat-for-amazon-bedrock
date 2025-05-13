<?php
/**
 * AWS Bedrock Nova Sonic API Integration
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * AWS Bedrock Nova Sonic API Integration Class.
 *
 * This class handles interactions with the AWS Bedrock Nova Sonic API.
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Nova_Sonic {

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
        $options = get_option('ai_chat_bedrock_settings');
        
        $this->region = isset($options['aws_region']) ? $options['aws_region'] : 'us-east-1';
        $this->access_key = isset($options['aws_access_key']) ? $options['aws_access_key'] : '';
        $this->secret_key = isset($options['aws_secret_key']) ? $options['aws_secret_key'] : '';
        $this->debug = isset($options['debug_mode']) && $options['debug_mode'] === 'on';
    }

    /**
     * 准备 Nova Sonic 流式请求
     *
     * @param string $audio_data 音频数据（Base64编码）
     * @param string $system_prompt 系统提示
     * @param array $options 附加选项
     * @return array 请求数据
     */
    public function prepare_nova_sonic_request($audio_data, $system_prompt, $options = []) {
        // 记录音频数据大小
        $this->log_debug('Audio data size:', is_string($audio_data) ? strlen($audio_data) : 'Not a string');
        
        // 创建唯一标识符
        $session_id = uniqid('session_');
        $prompt_name = uniqid('prompt_');
        
        // 准备请求数据 - 会话开始
        $session_start_event = [
            'event' => [
                'sessionStart' => [
                    'inferenceConfiguration' => [
                        'maxTokens' => isset($options['max_tokens']) ? (int)$options['max_tokens'] : 1024,
                        'topP' => isset($options['top_p']) ? (float)$options['top_p'] : 0.9,
                        'temperature' => isset($options['temperature']) ? (float)$options['temperature'] : 0.7
                    ]
                ]
            ]
        ];
        
        // 添加提示开始事件
        $prompt_start_event = [
            'event' => [
                'promptStart' => [
                    'promptName' => $prompt_name,
                    'textOutputConfiguration' => [
                        'mediaType' => 'text/plain'
                    ]
                ]
            ]
        ];
        
        // 添加音频输出配置
        $voice_id = isset($options['voice_id']) ? $options['voice_id'] : 'tiffany';
        $sample_rate = isset($options['speech_sample_rate']) ? (int)$options['speech_sample_rate'] : 24000;
        
        $prompt_start_event['event']['promptStart']['audioOutputConfiguration'] = [
            'mediaType' => 'audio/lpcm',
            'sampleRateHertz' => $sample_rate,
            'sampleSizeBits' => 16,
            'channelCount' => 1,
            'voiceId' => $voice_id,
            'encoding' => 'base64',
            'audioType' => 'SPEECH'
        ];
        
        // 添加 MCP 工具配置
        if (isset($options['enable_mcp']) && $options['enable_mcp'] && class_exists('AI_Chat_Bedrock_MCP_Integration')) {
            $mcp_integration = new AI_Chat_Bedrock_MCP_Integration();
            $mcp_client = $mcp_integration->get_mcp_client();
            $tools = method_exists($mcp_client, 'get_all_tools') ? $mcp_client->get_all_tools() : array();
            
            if (!empty($tools)) {
                $prompt_start_event['event']['promptStart']['toolUseOutputConfiguration'] = [
                    'mediaType' => 'application/json'
                ];
                
                $prompt_start_event['event']['promptStart']['toolConfiguration'] = [
                    'tools' => array_map(function($tool) {
                        return [
                            'toolSpec' => [
                                'name' => $tool['name'],
                                'description' => $tool['description'],
                                'inputSchema' => [
                                    'json' => json_encode(isset($tool['parameters']) ? $tool['parameters'] : array())
                                ]
                            ]
                        ];
                    }, $tools)
                ];
            }
        }
        
        // 添加系统提示
        $system_content_name = uniqid('content_');
        $system_content_start = [
            'event' => [
                'contentStart' => [
                    'promptName' => $prompt_name,
                    'contentName' => $system_content_name,
                    'type' => 'TEXT',
                    'role' => 'SYSTEM',
                    'interactive' => true,
                    'textInputConfiguration' => [
                        'mediaType' => 'text/plain'
                    ]
                ]
            ]
        ];
        
        $system_text_input = [
            'event' => [
                'textInput' => [
                    'promptName' => $prompt_name,
                    'contentName' => $system_content_name,
                    'content' => $system_prompt
                ]
            ]
        ];
        
        $system_content_end = [
            'event' => [
                'contentEnd' => [
                    'promptName' => $prompt_name,
                    'contentName' => $system_content_name
                ]
            ]
        ];
        
        // 添加用户音频输入
        $user_content_name = uniqid('content_');
        $user_content_start = [
            'event' => [
                'contentStart' => [
                    'promptName' => $prompt_name,
                    'contentName' => $user_content_name,
                    'type' => 'AUDIO',
                    'role' => 'USER',
                    'interactive' => true,
                    'audioInputConfiguration' => [
                        'mediaType' => isset($options['audio_input_format']) ? $options['audio_input_format'] : 'audio/lpcm',  // 使用设置中指定的格式
                        'sampleRateHertz' => isset($options['audio_input_sample_rate']) ? (int)$options['audio_input_sample_rate'] : 16000,   // 使用设置中指定的采样率
                        'sampleSizeBits' => 16,       // 标准位深度
                        'channelCount' => 1,          // 单声道
                        'audioType' => 'SPEECH',      // 语音类型
                        'encoding' => 'base64'        // Base64编码
                    ]
                ]
            ]
        ];
        
        // 分块发送音频数据
        $chunk_size = 10000; // 每个块的大小（字节）
        $audio_chunks = [];
        
        // 如果音频数据不是Base64编码，先进行编码
        if ($audio_data && !preg_match('/^[A-Za-z0-9+\/=]+$/', $audio_data)) {
            $audio_data = base64_encode($audio_data);
        }
        
        // 分块
        if ($audio_data) {
            $total_length = strlen($audio_data);
            for ($i = 0; $i < $total_length; $i += $chunk_size) {
                $chunk = substr($audio_data, $i, $chunk_size);
                $audio_chunks[] = [
                    'event' => [
                        'audioInput' => [
                            'promptName' => $prompt_name,
                            'contentName' => $user_content_name,
                            'content' => $chunk
                        ]
                    ]
                ];
            }
        } else {
            // 如果没有音频数据，添加一个空块
            $audio_chunks[] = [
                'event' => [
                    'audioInput' => [
                        'promptName' => $prompt_name,
                        'contentName' => $user_content_name,
                        'content' => ''
                    ]
                ]
            ];
        }
        
        $user_content_end = [
            'event' => [
                'contentEnd' => [
                    'promptName' => $prompt_name,
                    'contentName' => $user_content_name
                ]
            ]
        ];
        
        // 添加提示结束事件
        $prompt_end_event = [
            'event' => [
                'promptEnd' => [
                    'promptName' => $prompt_name
                ]
            ]
        ];
        
        // 添加会话结束事件
        $session_end_event = [
            'event' => [
                'sessionEnd' => []
            ]
        ];
        
        // 构建完整的事件序列 - 确保顺序与Python示例一致
        $events = [
            $session_start_event,        // 1. 会话开始
            $prompt_start_event,         // 2. 提示开始
            $system_content_start,       // 3. 系统内容开始
            $system_text_input,          // 4. 系统文本输入
            $system_content_end,         // 5. 系统内容结束
            $user_content_start          // 6. 用户内容开始
        ];
        
        // 添加所有音频块
        $events = array_merge($events, $audio_chunks);
        
        // 添加结束事件
        $events = array_merge($events, [
            $user_content_end,           // 7. 用户内容结束
            $prompt_end_event,           // 8. 提示结束
            $session_end_event           // 9. 会话结束
        ]);;
        
        // 返回所有事件
        return [
            'prompt_name' => $prompt_name,
            'events' => $events
        ];
    }

    /**
     * 处理 Nova Sonic 流式响应
     *
     * @param string $response_chunk 响应块
     * @return array 处理后的响应数据
     */
    public function process_nova_sonic_response($response_chunk) {
        // 记录原始响应
        $this->log_debug('Raw response:', substr($response_chunk, 0, 200) . (strlen($response_chunk) > 200 ? '...' : ''));
        
        // 如果响应为空，返回默认结果
        if (empty($response_chunk) || trim($response_chunk) === '') {
            return [
                'type' => 'empty',
                'content' => null,
                'role' => null,
                'tool_use' => null,
                'barge_in' => false,
                'display_text' => true,
                'raw_event' => null,
                'timestamp' => time()
            ];
        }
        
        // 检查是否为二进制数据
        $is_binary = false;
        for ($i = 0; $i < min(strlen($response_chunk), 100); $i++) {
            if (ord($response_chunk[$i]) < 32 && !in_array(ord($response_chunk[$i]), [9, 10, 13])) { // 非打印字符
                $is_binary = true;
                break;
            }
        }
        
        if ($is_binary) {
            $this->log_debug('Binary data detected in response', '');
            
            // 尝试提取音频数据
            $audio_data = $this->extract_audio_data($response_chunk);
            if ($audio_data) {
                $this->log_debug('Extracted audio data from binary response, size:', strlen($audio_data));
                return [
                    'type' => 'audio',
                    'content' => $audio_data,
                    'role' => 'ASSISTANT',
                    'tool_use' => null,
                    'barge_in' => false,
                    'display_text' => false,
                    'raw_event' => null,
                    'timestamp' => time()
                ];
            }
            
            return [
                'type' => 'binary',
                'content' => base64_encode($response_chunk), // 转换为Base64以便安全处理
                'role' => null,
                'tool_use' => null,
                'barge_in' => false,
                'display_text' => false,
                'raw_event' => null,
                'timestamp' => time()
            ];
        }
        
        // 检查是否为eventstream格式
        if ($this->is_eventstream_format($response_chunk)) {
            $this->log_debug('Eventstream format detected in direct response', '');
            
            // 处理eventstream格式
            $eventstream_responses = $this->process_eventstream($response_chunk);
            if (!empty($eventstream_responses)) {
                // 查找音频响应
                foreach ($eventstream_responses as $resp) {
                    if ($resp['type'] === 'audio' && isset($resp['content'])) {
                        $this->log_debug('Found audio response in eventstream', '');
                        return $resp;
                    }
                }
                
                // 如果没有找到音频响应，返回第一个响应
                return $eventstream_responses[0];
            }
            
            // 尝试直接提取音频数据
            $audio_data = $this->extract_audio_data_from_eventstream($response_chunk);
            if ($audio_data) {
                $this->log_debug('Extracted audio data from eventstream response, size:', strlen($audio_data));
                return [
                    'type' => 'audio',
                    'content' => $audio_data,
                    'role' => 'ASSISTANT',
                    'tool_use' => null,
                    'barge_in' => false,
                    'display_text' => false,
                    'raw_event' => null,
                    'timestamp' => time()
                ];
            }
        }
        
        // 尝试解析 JSON
        $response_data = json_decode($response_chunk, true);
        
        // 如果解析失败，记录错误并返回默认结果
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('JSON parse error:', json_last_error_msg());
            
            // 尝试清理响应数据
            $cleaned_chunk = preg_replace('/[\x00-\x1F\x7F]/', '', $response_chunk);
            $response_data = json_decode($cleaned_chunk, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'type' => 'parse_error',
                    'content' => $response_chunk,
                    'role' => null,
                    'tool_use' => null,
                    'barge_in' => false,
                    'display_text' => true,
                    'raw_event' => null,
                    'error' => json_last_error_msg(),
                    'timestamp' => time()
                ];
            } else {
                $this->log_debug('JSON parsed successfully after cleaning', '');
            }
        }
        
        $result = [
            'type' => null,
            'content' => null,
            'role' => null,
            'tool_use' => null,
            'barge_in' => false,
            'display_text' => true,
            'raw_event' => $response_data,
            'timestamp' => time()
        ];
        
        if (isset($response_data['event'])) {
            // 处理内容开始事件
            if (isset($response_data['event']['contentStart'])) {
                $content_start = $response_data['event']['contentStart'];
                $result['type'] = 'content_start';
                $result['role'] = isset($content_start['role']) ? $content_start['role'] : null;
                
                // 检查是否有推测性内容
                if (isset($content_start['additionalModelFields'])) {
                    try {
                        $additional_fields = json_decode($content_start['additionalModelFields'], true);
                        if (isset($additional_fields['generationStage']) && $additional_fields['generationStage'] === 'SPECULATIVE') {
                            $result['display_text'] = true;
                        } else {
                            $result['display_text'] = false;
                        }
                    } catch (Exception $e) {
                        $this->log_debug('Error parsing additionalModelFields:', $e->getMessage());
                    }
                }
            }
            
            // 处理文本输出
            if (isset($response_data['event']['textOutput'])) {
                $result['type'] = 'text';
                $result['content'] = $response_data['event']['textOutput']['content'];
                $result['role'] = $response_data['event']['textOutput']['role'];
                
                // 检查是否有打断
                if (strpos($result['content'], '{ "interrupted" : true }') !== false) {
                    $result['barge_in'] = true;
                }
            }
            
            // 处理音频输出
            if (isset($response_data['event']['audioOutput'])) {
                $result['type'] = 'audio';
                $result['content'] = $response_data['event']['audioOutput']['content'];
                $result['role'] = 'ASSISTANT';
                $this->log_debug('检测到音频输出，大小:', strlen($response_data['event']['audioOutput']['content']));
            }
            
            // 处理复合事件
            if (isset($response_data['event']['composite']) && isset($response_data['event']['composite']['events'])) {
                $this->log_debug('检测到复合事件，事件数量:', count($response_data['event']['composite']['events']));
                
                // 遍历所有事件，查找音频输出
                foreach ($response_data['event']['composite']['events'] as $event) {
                    if (isset($event['audioOutput'])) {
                        $result['type'] = 'audio';
                        $result['content'] = $event['audioOutput']['content'];
                        $result['role'] = 'ASSISTANT';
                        $this->log_debug('在复合事件中检测到音频输出，大小:', strlen($event['audioOutput']['content']));
                        break;
                    }
                }
                
                // 如果没有找到音频输出，查找文本输出
                if ($result['type'] !== 'audio') {
                    foreach ($response_data['event']['composite']['events'] as $event) {
                        if (isset($event['textOutput'])) {
                            $result['type'] = 'text';
                            $result['content'] = $event['textOutput']['content'];
                            $result['role'] = $event['textOutput']['role'];
                            $this->log_debug('在复合事件中检测到文本输出', '');
                            break;
                        }
                    }
                }
            }
            
            // 处理工具使用
            if (isset($response_data['event']['toolUse'])) {
                $result['type'] = 'tool_use';
                $result['tool_use'] = $response_data['event']['toolUse'];
                $result['tool_name'] = $response_data['event']['toolUse']['toolName'];
                $result['tool_use_id'] = $response_data['event']['toolUse']['toolUseId'];
            }
            
            // 处理内容结束事件
            if (isset($response_data['event']['contentEnd'])) {
                $result['type'] = 'content_end';
                if (isset($response_data['event']['contentEnd']['type']) && $response_data['event']['contentEnd']['type'] === 'TOOL') {
                    $result['content_end_type'] = 'tool';
                }
            }
            
            // 处理完成结束事件
            if (isset($response_data['event']['completionEnd'])) {
                $result['type'] = 'completion_end';
                $this->log_debug('End of response sequence', '');
            }
            
            // 处理会话开始事件
            if (isset($response_data['event']['sessionStart'])) {
                $result['type'] = 'session_start';
                $this->log_debug('Session started', '');
            }
            
            // 处理提示开始事件
            if (isset($response_data['event']['promptStart'])) {
                $result['type'] = 'prompt_start';
                $this->log_debug('Prompt started', '');
            }
            
            // 处理提示结束事件
            if (isset($response_data['event']['promptEnd'])) {
                $result['type'] = 'prompt_end';
                $this->log_debug('Prompt ended', '');
            }
        }
        
        return $result;
    }

    /**
     * 使用双向流式 API 与 Nova Sonic 模型交互
     *
     * @param array $events 事件数组
     * @param callable $callback 回调函数
     * @return array 响应数据
     */
    public function invoke_model_with_bidirectional_stream($events, $callback = null) {
        // 设置重试参数
        $max_retries = 3;
        $retry_count = 0;
        $retry_delay = 1; // 初始延迟1秒
        
        while ($retry_count <= $max_retries) {
            try {
                // 如果是重试，记录重试信息
                if ($retry_count > 0) {
                    $this->log_debug('Retry attempt:', $retry_count . ' of ' . $max_retries);
                }
                
                // 设置 AWS 凭证和请求参数
                $service = 'bedrock-runtime'; // 修改: 从'bedrock'改为'bedrock-runtime'
                $region = $this->region;
                $model_id = 'amazon.nova-sonic-v1:0';
                
                // 构建请求 URL (使用WebSocket)，添加protocol-version查询参数
                $endpoint = "wss://bedrock-runtime.{$region}.amazonaws.com/model/{$model_id}/invoke-with-bidirectional-stream?protocol-version=1.0";
                
                $this->log_debug('Creating WebSocket connection to:', $endpoint);
                
                // 解析URL，分离主机名和路径
                $parsed_url = parse_url($endpoint);
                $host = $parsed_url['host'];
                $path = $parsed_url['path'];
                if (isset($parsed_url['query'])) {
                    $path .= '?' . $parsed_url['query']; // 确保查询参数包含在路径中
                }
                $port = isset($parsed_url['port']) ? $parsed_url['port'] : 443; // 默认HTTPS端口
                
                // 设置 AWS 签名
                $datetime = new DateTime('UTC');
                $amz_date = $datetime->format('Ymd\THis\Z');
                $date_stamp = $datetime->format('Ymd');
                
                // 获取规范URI
                $canonical_uri = $this->get_canonical_uri($path);
                
                // 创建签名
                $algorithm = 'AWS4-HMAC-SHA256';
                $credential_scope = "{$date_stamp}/{$region}/{$service}/aws4_request";
                
                // 使用空字符串的哈希值
                $payload_hash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
                
                // 设置请求头和签名 (添加x-amz-content-sha256头部)
                $canonical_headers = "content-type:application/json\nhost:{$host}\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$amz_date}\n";
                $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';
        
                $canonical_request = "GET\n{$canonical_uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
                $string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
                
                // 计算签名密钥
                $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $this->secret_key, true);
                $k_region = hash_hmac('sha256', $region, $k_date, true);
                $k_service = hash_hmac('sha256', $service, $k_region, true);
                $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
                
                // 计算签名
                $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
                
                // 创建授权头
                $authorization_header = "{$algorithm} " . "Credential={$this->access_key}/{$credential_scope}, " . "SignedHeaders={$signed_headers}, " . "Signature={$signature}";
        
                // 记录调试信息
                if ($this->debug) {
                    $this->log_debug('Canonical Request:', $canonical_request);
                    $this->log_debug('String to Sign:', $string_to_sign);
                    $this->log_debug('Authorization Header:', $authorization_header);
                }
                
                $responses = [];
                $response_buffer = '';
                
                // 检查是否有WebSocket扩展
                if (!function_exists('stream_socket_client')) {
                    $this->log_debug('Error:', 'stream_socket_client function not available');
                    return [
                        'success' => false,
                        'error' => 'WebSocket support not available in this PHP installation',
                        'responses' => []
                    ];
                }
        
                // 优化：将事件分组处理
                $event_groups = $this->optimize_events($events);
                
                // 使用PHP的stream_socket_client函数创建WebSocket连接
                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                    ]
                ]);
            
                // 将wss转换为ssl
                $protocol = ($parsed_url['scheme'] === 'wss') ? 'ssl' : 'tcp';
                $server = "$protocol://$host:$port";
                
                // 创建连接
                $socket = stream_socket_client(
                    $server,
                    $errno,
                    $errstr,
                    30,
                    STREAM_CLIENT_CONNECT,
                    $context
                );
        
                if (!$socket) {
                    $this->log_debug('Socket Error:', "$errno: $errstr");
                    return [
                        'success' => false,
                        'error' => "Failed to connect to WebSocket: $errstr ($errno)",
                        'responses' => []
                    ];
                }
        
                // 设置非阻塞模式
                stream_set_blocking($socket, false);
                
                // 构建WebSocket握手请求
                $headers = [
                    "GET " . $path . " HTTP/1.1",
                    "Host: " . $host,
                    "Upgrade: websocket",
                    "Connection: Upgrade",
                    "Sec-WebSocket-Key: " . base64_encode(openssl_random_pseudo_bytes(16)),
                    "Sec-WebSocket-Version: 13",
                    "Sec-WebSocket-Protocol: aws.bedrock.runtime.v1", // 添加WebSocket子协议
                    "X-Amz-Date: " . $amz_date,
                    "X-Amz-Content-Sha256: " . $payload_hash,
                    "Authorization: " . $authorization_header,
                    "", ""
                ];
        
                // 发送握手请求
                fwrite($socket, implode("\r\n", $headers));
                
                // 等待握手响应
                $response = '';
                $start_time = time();
                while (time() - $start_time < 10) { // 10秒超时
                    $buffer = fread($socket, 8192);
                    if ($buffer !== false) {
                        $response .= $buffer;
                        if (strpos($response, "\r\n\r\n") !== false) {
                            break;
                        }
                    }
                    usleep(100000); // 等待0.1秒
                }
                
                // 检查握手是否成功
                if (strpos($response, "HTTP/1.1 101") === false) {
                    $this->log_debug('WebSocket Handshake Failed:', $response);
                    fclose($socket);
                    
                    // 检查是否需要重试
                    if ($retry_count < $max_retries) {
                        $retry_count++;
                        $sleep_time = $retry_delay * pow(2, $retry_count - 1); // 指数退避
                        $this->log_debug('Retrying in', $sleep_time . ' seconds');
                        sleep($sleep_time);
                        continue; // 继续下一次重试
                    }
                    
                    return [
                        'success' => false,
                        'error' => "WebSocket handshake failed: " . $response,
                        'responses' => []
                    ];
                }
                
                $this->log_debug('WebSocket Handshake Successful', '');
                
                // 发送事件
                foreach ($event_groups as $group_index => $event_group) {
                    $request_body = json_encode($event_group);
                    
                    // 记录请求体
                    $this->log_debug('Sending event group ' . $group_index . ':', substr($request_body, 0, 200) . '...');
                    
                    // 发送WebSocket帧
                    $this->send_websocket_frame($socket, $request_body);
                    
                    // 接收响应
                    $start_time = time();
                    $timeout = 30; // 30秒超时
                    
                    while (time() - $start_time < $timeout) {
                        $buffer = fread($socket, 8192);
                        if ($buffer !== false && strlen($buffer) > 0) {
                            // 处理WebSocket帧
                            $frame_data = $this->parse_websocket_frame($buffer);
                            if ($frame_data !== false) {
                                $response_buffer .= $frame_data;
                                
                                // 尝试处理响应
                                $this->process_response_buffer($response_buffer, $responses, $callback);
                                
                                // 如果是关闭帧，退出循环
                                if (ord($buffer[0]) & 0x08) {
                                    break;
                                }
                            }
                        }
                        usleep(100000); // 等待0.1秒
                    }
                    
                    // 处理可能剩余在缓冲区中的数据
                    if (!empty($response_buffer)) {
                        $this->process_response_buffer($response_buffer, $responses, $callback, true);
                    }
                    
                    // 清空缓冲区，准备下一个请求
                    $response_buffer = '';
                }
                
                // 关闭WebSocket连接
                $this->send_websocket_close($socket);
                fclose($socket);
            } catch (Exception $e) {
                $this->log_debug('WebSocket Error:', $e->getMessage());
                
                // 检查是否需要重试
                if ($retry_count < $max_retries) {
                    $retry_count++;
                    $sleep_time = $retry_delay * pow(2, $retry_count - 1); // 指数退避
                    $this->log_debug('Retrying after error in', $sleep_time . ' seconds');
                    sleep($sleep_time);
                    continue; // 继续下一次重试
                }
                
                return [
                    'success' => false,
                    'error' => "WebSocket error: " . $e->getMessage(),
                    'responses' => $responses
                ];
            }
        
        // 如果成功完成，跳出重试循环
        break;
        } // 结束重试循环
        
        return [
            'success' => true,
            'responses' => $responses
        ];
    }
    
    /**
     * 发送WebSocket帧
     *
     * @param resource $socket WebSocket连接
     * @param string $data 要发送的数据
     * @return bool 是否成功
     */
    private function send_websocket_frame($socket, $data) {
        $length = strlen($data);
        $frame = chr(0x81); // FIN + text frame
        
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        $frame .= $data;
        return fwrite($socket, $frame) !== false;
    }
    
    /**
     * 发送WebSocket关闭帧
     *
     * @param resource $socket WebSocket连接
     * @return bool 是否成功
     */
    private function send_websocket_close($socket) {
        $frame = chr(0x88) . chr(0x00); // FIN + close frame, no payload
        return fwrite($socket, $frame) !== false;
    }
    
    /**
     * 解析WebSocket帧
     *
     * @param string $data WebSocket帧数据
     * @return string|false 解析后的数据，失败返回false
     */
    private function parse_websocket_frame($data) {
        if (strlen($data) < 2) {
            return false;
        }
        
        $first_byte = ord($data[0]);
        $second_byte = ord($data[1]);
        
        $fin = ($first_byte & 0x80) != 0;
        $opcode = $first_byte & 0x0F;
        $masked = ($second_byte & 0x80) != 0;
        $payload_length = $second_byte & 0x7F;
        
        $offset = 2;
        
        if ($payload_length == 126) {
            if (strlen($data) < 4) {
                return false;
            }
            $payload_length = unpack('n', substr($data, 2, 2))[1];
            $offset += 2;
        } elseif ($payload_length == 127) {
            if (strlen($data) < 10) {
                return false;
            }
            $payload_length = unpack('J', substr($data, 2, 8))[1];
            $offset += 8;
        }
        
        $mask_key = '';
        if ($masked) {
            if (strlen($data) < $offset + 4) {
                return false;
            }
            $mask_key = substr($data, $offset, 4);
            $offset += 4;
        }
        
        if (strlen($data) < $offset + $payload_length) {
            return false;
        }
        
        $payload = substr($data, $offset, $payload_length);
        
        if ($masked) {
            $unmasked = '';
            for ($i = 0; $i < strlen($payload); $i++) {
                $unmasked .= chr(ord($payload[$i]) ^ ord($mask_key[$i % 4]));
            }
            $payload = $unmasked;
        }
        
        // 处理不同的操作码
        switch ($opcode) {
            case 0x01: // 文本帧
                return $payload;
            case 0x02: // 二进制帧
                return $payload;
            case 0x08: // 关闭帧
                $this->log_debug('Received WebSocket Close Frame', '');
                return false;
            case 0x09: // Ping帧
                // 注意：这里不能直接回复Pong帧，因为我们没有socket参数
                // 在调用此方法的地方处理Ping帧
                $this->log_debug('Received WebSocket Ping Frame', '');
                return 'PING';
            case 0x0A: // Pong帧
                return false;
            default:
                return false;
        }
    }
    
    /**
     * 发送WebSocket Pong帧
     *
     * @param resource $socket WebSocket连接
     * @return bool 是否成功
     */
    private function send_websocket_pong($socket) {
        $frame = chr(0x8A) . chr(0x00); // FIN + pong frame, no payload
        return fwrite($socket, $frame) !== false;
    }
    
    /**
     * 优化事件，按照官方API参考格式组织事件
     * 确保事件序列与Python示例中的顺序一致
     *
     * @param array $events 原始事件数组
     * @return array 优化后的事件数组
     */
    private function optimize_events($events) {
        $this->log_debug('Optimizing events, original count:', count($events));
        
        // 检查事件序列是否符合预期顺序
        $expected_sequence = [
            'sessionStart',
            'promptStart',
            'contentStart', // SYSTEM
            'textInput',    // 系统提示
            'contentEnd',   // 系统提示结束
            'contentStart', // USER/AUDIO
            // 音频输入事件
            'contentEnd',   // 用户输入结束
            'promptEnd',
            'sessionEnd'
        ];
        
        // 验证事件序列
        $event_types = [];
        foreach ($events as $event) {
            if (isset($event['event'])) {
                $event_type = array_keys($event['event'])[0];
                $event_types[] = $event_type;
            }
        }
        
        $this->log_debug('Event sequence:', implode(', ', $event_types));
        
        // 根据官方文档，不应该使用复合事件，而是按顺序发送单独的事件
        $this->log_debug('Returning original events without merging', '');
        return $events;
    }
    
    /**
     * 合并多个事件为一个事件组
     *
     * @param array $events 要合并的事件数组
     * @return array 合并后的事件
     */
    private function merge_events($events) {
        if (count($events) === 1) {
            return $events[0];
        }
        
        // 创建一个复合事件
        $merged_event = [
            'event' => [
                'composite' => [
                    'events' => []
                ]
            ]
        ];
        
        foreach ($events as $event) {
            if (isset($event['event'])) {
                $merged_event['event']['composite']['events'][] = $event['event'];
            }
        }
        
        return $merged_event;
    }
    
    /**
     * 处理响应缓冲区，尝试提取完整的JSON对象或处理eventstream格式
     *
     * @param string &$buffer 响应缓冲区
     * @param array &$responses 响应数组
     * @param callable $callback 回调函数
     * @param bool $force_process 是否强制处理不完整的JSON
     */
    private function process_response_buffer(&$buffer, &$responses, $callback = null, $force_process = false) {
        // 如果缓冲区为空，直接返回
        if (empty($buffer) || trim($buffer) === '') {
            // 记录空响应
            $this->log_debug('Empty response buffer received', '');
            
            // 创建一个空响应对象
            $empty_response = [
                'type' => 'empty',
                'content' => null,
                'role' => null,
                'tool_use' => null,
                'barge_in' => false,
                'display_text' => true,
                'raw_event' => null,
                'timestamp' => time()
            ];
            
            $responses[] = $empty_response;
            
            // 如果提供了回调函数，调用它
            if (is_callable($callback)) {
                call_user_func($callback, $empty_response);
            }
            
            return;
        }
        
        // 记录原始响应
        $this->log_debug('Raw response buffer:', substr($buffer, 0, 200) . (strlen($buffer) > 200 ? '...' : ''));
        
        // 检查是否为eventstream格式
        if ($this->is_eventstream_format($buffer)) {
            $this->log_debug('Detected eventstream format', '');
            
            // 处理eventstream格式
            $eventstream_responses = $this->process_eventstream($buffer);
            
            if (!empty($eventstream_responses)) {
                foreach ($eventstream_responses as $resp) {
                    $responses[] = $resp;
                    
                    // 记录音频响应
                    if ($resp['type'] === 'audio') {
                        $this->log_debug('Audio response detected, size:', isset($resp['content']) ? strlen($resp['content']) : 'unknown');
                    }
                    
                    // 如果提供了回调函数，调用它
                    if (is_callable($callback)) {
                        call_user_func($callback, $resp);
                    }
                }
            } else {
                // 如果无法解析eventstream，尝试直接提取音频数据
                $audio_data = $this->extract_audio_data_from_eventstream($buffer);
                if ($audio_data) {
                    $this->log_debug('Extracted audio data from eventstream, size:', strlen($audio_data));
                    
                    $audio_response = [
                        'type' => 'audio',
                        'content' => $audio_data,
                        'role' => 'ASSISTANT',
                        'tool_use' => null,
                        'barge_in' => false,
                        'display_text' => false,
                        'raw_event' => null,
                        'timestamp' => time()
                    ];
                    
                    $responses[] = $audio_response;
                    
                    // 如果提供了回调函数，调用它
                    if (is_callable($callback)) {
                        call_user_func($callback, $audio_response);
                    }
                } else {
                    // 如果无法提取音频数据，创建一个基本响应
                    $eventstream_response = [
                        'type' => 'eventstream',
                        'content' => base64_encode($buffer), // 转换为Base64以便安全处理
                        'role' => null,
                        'tool_use' => null,
                        'barge_in' => false,
                        'display_text' => false,
                        'raw_event' => null,
                        'timestamp' => time()
                    ];
                    
                    $responses[] = $eventstream_response;
                    
                    // 如果提供了回调函数，调用它
                    if (is_callable($callback)) {
                        call_user_func($callback, $eventstream_response);
                    }
                }
            }
            
            // 清空缓冲区
            $buffer = '';
            return;
        }
        
        // 尝试解析JSON
        $json_result = json_decode($buffer, true);
        $json_error = json_last_error();
        
        // 如果解析成功或强制处理
        if ($json_error === JSON_ERROR_NONE || $force_process) {
            // 处理响应
            $processed_response = $this->process_nova_sonic_response($buffer);
            $this->log_debug('Processed response:', json_encode($processed_response));
            
            // 添加时间戳
            $processed_response['timestamp'] = time();
            
            $responses[] = $processed_response;
            
            // 如果提供了回调函数，调用它
            if (is_callable($callback)) {
                call_user_func($callback, $processed_response);
            }
            
            // 清空缓冲区
            $buffer = '';
        } else {
            // 如果JSON解析失败，可能是不完整的JSON或二进制数据
            $this->log_debug('JSON parse error:', json_last_error_msg());
            
            // 检查是否为二进制数据
            $is_binary = false;
            for ($i = 0; $i < min(strlen($buffer), 100); $i++) {
                if (ord($buffer[$i]) < 32 && !in_array(ord($buffer[$i]), [9, 10, 13])) { // 非打印字符
                    $is_binary = true;
                    break;
                }
            }
            
            if ($is_binary) {
                $this->log_debug('Binary data detected in response', '');
                
                // 尝试提取音频数据
                $audio_data = $this->extract_audio_data($buffer);
                if ($audio_data) {
                    $this->log_debug('Extracted audio data, size:', strlen($audio_data));
                    
                    $audio_response = [
                        'type' => 'audio',
                        'content' => $audio_data,
                        'role' => 'ASSISTANT',
                        'tool_use' => null,
                        'barge_in' => false,
                        'display_text' => false,
                        'raw_event' => null,
                        'timestamp' => time()
                    ];
                    
                    $responses[] = $audio_response;
                    
                    // 如果提供了回调函数，调用它
                    if (is_callable($callback)) {
                        call_user_func($callback, $audio_response);
                    }
                } else {
                    // 如果无法提取音频数据，创建一个二进制数据响应
                    $binary_response = [
                        'type' => 'binary',
                        'content' => base64_encode($buffer), // 转换为Base64以便安全处理
                        'role' => null,
                        'tool_use' => null,
                        'barge_in' => false,
                        'display_text' => false,
                        'raw_event' => null,
                        'timestamp' => time()
                    ];
                    
                    $responses[] = $binary_response;
                    
                    // 如果提供了回调函数，调用它
                    if (is_callable($callback)) {
                        call_user_func($callback, $binary_response);
                    }
                }
                
                // 清空缓冲区
                $buffer = '';
            } else if ($force_process) {
                // 强制处理模式下，创建一个基本响应
                $fallback_response = [
                    'type' => 'parse_error',
                    'content' => $buffer,
                    'role' => null,
                    'tool_use' => null,
                    'barge_in' => false,
                    'display_text' => true,
                    'raw_event' => null,
                    'error' => json_last_error_msg(),
                    'timestamp' => time()
                ];
                
                $this->log_debug('Forced processing of invalid JSON:', json_encode($fallback_response));
                $responses[] = $fallback_response;
                
                // 如果提供了回调函数，调用它
                if (is_callable($callback)) {
                    call_user_func($callback, $fallback_response);
                }
                
                // 清空缓冲区
                $buffer = '';
            }
        }
    }
    
    /**
     * 从二进制数据中提取音频数据
     *
     * @param string $data 二进制数据
     * @return string|false 提取的音频数据，如果无法提取则返回false
     */
    private function extract_audio_data($data) {
        // 尝试查找音频数据的标记
        $audio_markers = [
            'audio/lpcm',
            'audioOutput',
            'audio/pcm',
            'audio/wav'
        ];
        
        foreach ($audio_markers as $marker) {
            $pos = strpos($data, $marker);
            if ($pos !== false) {
                $this->log_debug('Found audio marker:', $marker . ' at position ' . $pos);
                
                // 尝试从标记位置开始查找音频数据
                // 通常音频数据会在标记后的某个位置
                $start_pos = $pos + strlen($marker);
                
                // 查找可能的音频数据开始位置
                // 这里我们假设音频数据是Base64编码的
                $base64_start = strpos($data, 'content":"', $start_pos);
                if ($base64_start !== false) {
                    $base64_start += 10; // 跳过 'content":"'
                    $base64_end = strpos($data, '"', $base64_start);
                    if ($base64_end !== false) {
                        $base64_data = substr($data, $base64_start, $base64_end - $base64_start);
                        // 检查是否为有效的Base64
                        if (base64_decode($base64_data, true) !== false) {
                            return $base64_data;
                        }
                    }
                }
                
                // 如果没有找到Base64编码的数据，尝试直接提取二进制数据
                // 这里我们假设音频数据从标记后的某个位置开始
                // 由于无法确定确切的开始位置，我们尝试从标记后的一段距离开始
                $binary_start = $start_pos + 20; // 假设音频数据在标记后20个字节开始
                if ($binary_start < strlen($data)) {
                    // 提取剩余的所有数据作为音频数据
                    return base64_encode(substr($data, $binary_start));
                }
            }
        }
        
        return false;
    }
    
    /**
     * 从eventstream格式的数据中提取音频数据
     *
     * @param string $data eventstream数据
     * @return string|false 提取的音频数据，如果无法提取则返回false
     */
    private function extract_audio_data_from_eventstream($data) {
        // 尝试查找eventstream中的音频数据
        $offset = 0;
        $data_length = strlen($data);
        
        while ($offset < $data_length) {
            // 确保至少有足够的字节来读取消息前导
            if ($offset + 12 > $data_length) {
                break;
            }
            
            // 读取消息前导
            $prelude = substr($data, $offset, 8);
            $total_length = unpack('N', substr($prelude, 0, 4))[1];
            $headers_length = unpack('N', substr($prelude, 4, 4))[1] & 0x00FFFFFF; // 取低24位
            
            // 验证长度
            if ($total_length <= 0 || $offset + $total_length > $data_length) {
                break;
            }
            
            // 读取头部
            $headers_offset = $offset + 12; // 前导(8) + 前导CRC(4)
            $headers_end = $headers_offset + $headers_length;
            $headers = [];
            
            // 解析头部
            while ($headers_offset < $headers_end) {
                // 读取头部名称长度
                $name_length = ord(substr($data, $headers_offset, 1));
                $headers_offset += 1;
                
                // 读取头部名称
                $name = substr($data, $headers_offset, $name_length);
                $headers_offset += $name_length;
                
                // 读取头部值类型
                $value_type = ord(substr($data, $headers_offset, 1));
                $headers_offset += 1;
                
                // 根据类型读取头部值
                $value = null;
                switch ($value_type) {
                    case 7: // 字符串
                        $value_length = unpack('n', substr($data, $headers_offset, 2))[1];
                        $headers_offset += 2;
                        $value = substr($data, $headers_offset, $value_length);
                        $headers_offset += $value_length;
                        break;
                    default:
                        // 跳过其他类型
                        $headers_offset = $headers_end;
                        break;
                }
                
                if ($name && $value !== null) {
                    $headers[$name] = $value;
                }
            }
            
            // 读取负载
            $payload_offset = $offset + 12 + $headers_length;
            $payload_length = $total_length - $headers_length - 16; // 总长度 - 头部长度 - 前导(8) - 前导CRC(4) - 消息CRC(4)
            $payload = substr($data, $payload_offset, $payload_length);
            
            // 检查是否为音频数据
            if (isset($headers[':content-type']) && 
                (strpos($headers[':content-type'], 'audio') !== false || 
                 strpos($headers[':content-type'], 'application/octet-stream') !== false)) {
                $this->log_debug('Found audio content in eventstream:', $headers[':content-type']);
                
                // 尝试解析JSON负载
                $json_payload = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json_payload['audioOutput']['content'])) {
                    return $json_payload['audioOutput']['content'];
                }
                
                // 如果不是JSON或没有找到音频内容，返回整个负载
                return base64_encode($payload);
            }
            
            // 移动到下一个消息
            $offset += $total_length;
        }
        
        return false;
    }
    
    /**
     * 检查数据是否为eventstream格式
     *
     * @param string $data 要检查的数据
     * @return bool 是否为eventstream格式
     */
    private function is_eventstream_format($data) {
        // 检查内容类型头
        if (strpos($data, 'application/vnd.amazon.eventstream') !== false) {
            return true;
        }
        
        // 检查eventstream格式的特征
        // eventstream消息通常以4字节的长度开始，然后是头部长度，然后是头部和负载
        if (strlen($data) >= 12) { // 至少需要一个基本的eventstream消息头
            // 尝试解析前导字节
            $prelude = substr($data, 0, 8);
            $total_length = unpack('N', substr($prelude, 0, 4))[1];
            $headers_length = unpack('N', substr($prelude, 4, 4))[1] & 0x00FFFFFF; // 取低24位
            
            // 验证长度是否合理
            if ($total_length > 0 && $total_length <= strlen($data) && $headers_length < $total_length) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 处理eventstream格式的响应
     *
     * @param string $data eventstream数据
     * @return array 处理后的响应数组
     */
    private function process_eventstream($data) {
        $responses = [];
        $offset = 0;
        $data_length = strlen($data);
        $complete_text = '';
        $audio_data = null;
        
        $this->log_debug('Processing eventstream data, length:', $data_length);
        
        while ($offset < $data_length) {
            // 确保至少有足够的字节来读取消息前导
            if ($offset + 12 > $data_length) {
                $this->log_debug('Not enough bytes for message prelude at offset:', $offset);
                break;
            }
            
            // 读取消息前导
            $prelude = substr($data, $offset, 8);
            $total_length = unpack('N', substr($prelude, 0, 4))[1];
            $headers_length = unpack('N', substr($prelude, 4, 4))[1] & 0x00FFFFFF; // 取低24位
            
            // 验证长度
            if ($total_length <= 0 || $offset + $total_length > $data_length) {
                $this->log_debug('Invalid message length:', $total_length);
                break;
            }
            
            $this->log_debug('Message at offset ' . $offset . ', length:', $total_length);
            
            // 读取消息CRC
            $prelude_crc = unpack('N', substr($data, $offset + 8, 4))[1];
            
            // 读取头部
            $headers_offset = $offset + 12; // 前导(8) + 前导CRC(4)
            $headers_end = $headers_offset + $headers_length;
            $headers = [];
            
            while ($headers_offset < $headers_end) {
                // 读取头部名称长度
                $name_length = ord(substr($data, $headers_offset, 1));
                $headers_offset += 1;
                
                // 读取头部名称
                $name = substr($data, $headers_offset, $name_length);
                $headers_offset += $name_length;
                
                // 读取头部值类型
                $value_type = ord(substr($data, $headers_offset, 1));
                $headers_offset += 1;
                
                // 根据类型读取头部值
                $value = null;
                switch ($value_type) {
                    case 0: // 布尔值 false
                        $value = false;
                        break;
                    case 1: // 布尔值 true
                        $value = true;
                        break;
                    case 2: // 8位整数
                        $value = unpack('c', substr($data, $headers_offset, 1))[1];
                        $headers_offset += 1;
                        break;
                    case 3: // 16位整数
                        $value = unpack('s', substr($data, $headers_offset, 2))[1];
                        $headers_offset += 2;
                        break;
                    case 4: // 32位整数
                        $value = unpack('l', substr($data, $headers_offset, 4))[1];
                        $headers_offset += 4;
                        break;
                    case 5: // 64位整数
                        $value = unpack('q', substr($data, $headers_offset, 8))[1];
                        $headers_offset += 8;
                        break;
                    case 6: // 字节数组
                        $value_length = unpack('n', substr($data, $headers_offset, 2))[1];
                        $headers_offset += 2;
                        $value = substr($data, $headers_offset, $value_length);
                        $headers_offset += $value_length;
                        break;
                    case 7: // 字符串
                        $value_length = unpack('n', substr($data, $headers_offset, 2))[1];
                        $headers_offset += 2;
                        $value = substr($data, $headers_offset, $value_length);
                        $headers_offset += $value_length;
                        break;
                    case 8: // 时间戳
                        $value = unpack('q', substr($data, $headers_offset, 8))[1];
                        $headers_offset += 8;
                        break;
                    case 9: // UUID
                        $value = bin2hex(substr($data, $headers_offset, 16));
                        $headers_offset += 16;
                        break;
                    default:
                        // 未知类型，跳过
                        $headers_offset = $headers_end;
                        break;
                }
                
                $headers[$name] = $value;
            }
            
            // 读取负载
            $payload_offset = $offset + 12 + $headers_length;
            $payload_length = $total_length - $headers_length - 16; // 总长度 - 头部长度 - 前导(8) - 前导CRC(4) - 消息CRC(4)
            $payload = substr($data, $payload_offset, $payload_length);
            
            // 读取消息CRC
            $message_crc = unpack('N', substr($data, $offset + $total_length - 4, 4))[1];
            
            // 处理事件
            $event_type = isset($headers[':event-type']) ? $headers[':event-type'] : '';
            $content_type = isset($headers[':content-type']) ? $headers[':content-type'] : '';
            
            $this->log_debug('Event type:', $event_type);
            $this->log_debug('Content type:', $content_type);
            
            $response = [
                'type' => 'eventstream',
                'event_type' => $event_type,
                'content_type' => $content_type,
                'headers' => $headers,
                'timestamp' => time()
            ];
            
            // 检查是否为音频内容
            if (strpos($content_type, 'audio') !== false) {
                $this->log_debug('Found audio content, length:', strlen($payload));
                $response['type'] = 'audio';
                $response['content'] = base64_encode($payload);
                $response['role'] = 'ASSISTANT';
                $audio_data = $response['content'];
            }
            // 如果是JSON内容，尝试解析
            else if ($content_type === 'application/json') {
                try {
                    $json_payload = json_decode($payload, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $response['content'] = $json_payload;
                        
                        // 检查是否包含音频输出
                        if (isset($json_payload['audioOutput']) && isset($json_payload['audioOutput']['content'])) {
                            $this->log_debug('Found audio output in JSON payload, length:', strlen($json_payload['audioOutput']['content']));
                            $response['type'] = 'audio';
                            $response['content'] = $json_payload['audioOutput']['content'];
                            $response['role'] = 'ASSISTANT';
                            $audio_data = $response['content'];
                        }
                        // 检查是否包含复合事件中的音频输出
                        else if (isset($json_payload['composite']) && isset($json_payload['composite']['events'])) {
                            foreach ($json_payload['composite']['events'] as $event) {
                                if (isset($event['audioOutput']) && isset($event['audioOutput']['content'])) {
                                    $this->log_debug('Found audio output in composite event, length:', strlen($event['audioOutput']['content']));
                                    $response['type'] = 'audio';
                                    $response['content'] = $event['audioOutput']['content'];
                                    $response['role'] = 'ASSISTANT';
                                    $audio_data = $response['content'];
                                    break;
                                }
                            }
                        }
                        // 参考Nova文本模型的处理方式
                        else if (isset($json_payload['messageStart'])) {
                            $response['type'] = 'message_start';
                            $this->log_debug('Nova message start event detected', '');
                        }
                        else if (isset($json_payload['contentBlockStart'])) {
                            $response['type'] = 'content_block_start';
                            
                            // 检查是否有工具使用
                            if (isset($json_payload['contentBlockStart']['start']['toolUse'])) {
                                $response['type'] = 'tool_use_start';
                                $response['tool_use'] = $json_payload['contentBlockStart']['start']['toolUse'];
                                $this->log_debug('Tool use start detected', json_encode($response['tool_use']));
                            }
                        }
                        else if (isset($json_payload['contentBlockDelta'])) {
                            // 检查是否有文本增量
                            if (isset($json_payload['contentBlockDelta']['delta']['text'])) {
                                $response['type'] = 'text';
                                $response['content'] = $json_payload['contentBlockDelta']['delta']['text'];
                                $complete_text .= $response['content'];
                                $this->log_debug('Text delta detected', $response['content']);
                            }
                            
                            // 检查是否有工具使用输入
                            else if (isset($json_payload['contentBlockDelta']['delta']['toolUse']['input'])) {
                                $response['type'] = 'tool_use_input';
                                $response['tool_use_input'] = $json_payload['contentBlockDelta']['delta']['toolUse']['input'];
                                $this->log_debug('Tool use input detected', json_encode($response['tool_use_input']));
                            }
                        }
                        else if (isset($json_payload['contentBlockStop'])) {
                            $response['type'] = 'content_block_stop';
                            $this->log_debug('Content block stop event detected', '');
                        }
                        else if (isset($json_payload['messageStop'])) {
                            $response['type'] = 'message_stop';
                            $this->log_debug('Message stop event detected', '');
                        }
                        else if (isset($json_payload['output']['message']['content'][0]['text'])) {
                            $response['type'] = 'text';
                            $response['content'] = $json_payload['output']['message']['content'][0]['text'];
                            $complete_text .= $response['content'];
                            $this->log_debug('Output text detected', $response['content']);
                        }
                        else {
                            // 提取特定的字段
                            if (isset($json_payload['message'])) {
                                $response['message'] = $json_payload['message'];
                                $this->log_debug('Message field detected:', json_encode($json_payload['message']));
                            }
                            
                            if (isset($json_payload['role'])) {
                                $response['role'] = $json_payload['role'];
                            }
                            
                            if (isset($json_payload['content'])) {
                                $response['text_content'] = $json_payload['content'];
                                $complete_text .= $json_payload['content'];
                            }
                        }
                    } else {
                        $response['content'] = base64_encode($payload);
                        $response['parse_error'] = json_last_error_msg();
                        $this->log_debug('JSON parse error:', json_last_error_msg());
                    }
                } catch (Exception $e) {
                    $response['content'] = base64_encode($payload);
                    $response['parse_error'] = $e->getMessage();
                    $this->log_debug('Exception parsing JSON:', $e->getMessage());
                }
            } else {
                // 对于非JSON内容，使用Base64编码
                $response['content'] = base64_encode($payload);
            }
            
            $responses[] = $response;
            
            // 移动到下一个消息
            $offset += $total_length;
        }
        
        // 如果找到了音频数据，添加一个专门的音频响应
        if ($audio_data !== null) {
            $this->log_debug('Adding dedicated audio response', '');
            $responses[] = [
                'type' => 'audio',
                'content' => $audio_data,
                'role' => 'ASSISTANT',
                'timestamp' => time()
            ];
        }
        
        // 如果有完整的文本，添加一个汇总响应
        if (!empty($complete_text)) {
            $responses[] = [
                'type' => 'complete_text',
                'content' => $complete_text,
                'timestamp' => time()
            ];
        }
        
        return $responses;
    }

    /**
     * 获取规范 URI
     *
     * @param string $path 请求路径
     * @return string 规范 URI
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
            if ($segment === 'invoke' || $segment === 'invoke-with-response-stream' || $segment === 'invoke-with-bidirectional-stream') {
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
     * 获取签名密钥
     *
     * @param string $key 密钥
     * @param string $date_stamp 日期戳
     * @param string $region 区域
     * @param string $service 服务
     * @return string 签名密钥
     */
    private function get_signature_key($key, $date_stamp, $region, $service) {
        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', $service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        
        return $k_signing;
    }

    /**
     * 记录调试信息
     *
     * @param string $title 标题
     * @param mixed $data 数据
     */
    private function log_debug($title, $data) {
        if ($this->debug) {
            if (is_array($data) || is_object($data)) {
                $data = print_r($data, true);
            }
            
            // 添加时间戳以便更好地跟踪事件顺序
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            error_log("[{$timestamp}] AI Chat Bedrock Nova Sonic Debug - {$title} {$data}");
        }
    }

    /**
     * 使用 Nova Sonic 模型进行对话
     *
     * @param string $prompt 用户提示
     * @param string $system_prompt 系统提示
     * @param array $options 选项
     * @param callable $callback 回调函数
     * @return array 响应数据
     */
    public function chat($prompt, $system_prompt = '', $options = [], $callback = null) {
        // 准备请求数据
        $request_data = $this->prepare_nova_sonic_request($prompt, $system_prompt, $options);
        
        // 使用双向流式 API 调用模型
        return $this->invoke_model_with_bidirectional_stream($request_data['events'], $callback);
    }
}
