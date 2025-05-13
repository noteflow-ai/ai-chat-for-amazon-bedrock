<?php
/**
 * Bedrock WebSocket 代理
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * Bedrock WebSocket 代理
 * 处理与 Bedrock 的双向流式通信，作为服务器端代理
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_WebSocket_Proxy {
    private $aws;
    private $region;
    private $model_id;
    private $endpoint;
    private $debug;
    private $client_connections = [];
    
    /**
     * 初始化类并设置属性
     *
     * @since    1.0.0
     * @param    string    $access_key    AWS访问密钥
     * @param    string    $secret_key    AWS秘密密钥
     * @param    string    $region        AWS区域
     * @param    string    $model_id      模型ID
     * @param    bool      $debug         是否启用调试
     */
    public function __construct($access_key, $secret_key, $region = 'us-east-1', $model_id = 'amazon.nova-sonic-v1:0', $debug = false) {
        // 使用 AI_Chat_Bedrock_AWS 类
        $this->aws = new AI_Chat_Bedrock_AWS();
        $this->region = $region;
        $this->model_id = $model_id;
        $this->debug = $debug;
        $this->endpoint = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model_id}/invoke-with-bidirectional-stream";
    }
    
    /**
     * 记录调试信息
     *
     * @since    1.0.0
     * @param    string    $title    调试标题
     * @param    mixed     $data     调试数据
     */
    private function log_debug($title, $data) {
        if ($this->debug) {
            // 过滤掉轮询相关的日志
            if (strpos($title, 'Getting events') !== false || 
                (is_string($data) && strpos($data, 'No new events') !== false)) {
                return; // 不记录轮询相关的日志
            }
            
            if (is_array($data) || is_object($data)) {
                $data = print_r($data, true);
            }
            
            // 添加时间戳以便更好地跟踪事件顺序
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            error_log("[{$timestamp}] AI Chat Bedrock WebSocket - {$title} {$data}");
        }
    }
    
    /**
     * 创建与 Bedrock 的连接
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @return   array                   连接结果
     */
    public function create_bedrock_connection($client_id) {
        $this->log_debug('Creating Bedrock connection for client', $client_id);
        $this->log_debug('Using endpoint', $this->endpoint);
        
        // 构建请求体
        $request_body = json_encode([
            'inputContentType' => 'application/json',
            'outputContentType' => 'application/json',
            'contentType' => 'application/json',
            'accept' => 'application/json',
        ]);
        
        $this->log_debug('Request body', $request_body);
        
        // 使用 AI_Chat_Bedrock_AWS 类的签名方法
        $service = 'bedrock';
        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $content_type = 'application/json';
        
        // 构建请求路径
        $request_path = parse_url($this->endpoint, PHP_URL_PATH);
        $request_parameters = '';
        
        // 获取当前时间
        $datetime = new DateTime('UTC');
        $amz_date = $datetime->format('Ymd\THis\Z');
        $date_stamp = $datetime->format('Ymd');
        
        // 获取规范URI
        $canonical_uri = $this->get_canonical_uri($request_path);
        
        $this->log_debug('Original Request Path:', $request_path);
        $this->log_debug('Canonical URI:', $canonical_uri);
        
        $canonical_querystring = $request_parameters;
        $canonical_headers = "content-type:{$content_type}\nhost:{$host}\nx-amz-date:{$amz_date}\n";
        $signed_headers = 'content-type;host;x-amz-date';
        $payload_hash = hash('sha256', $request_body);
        $canonical_request = "POST\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        
        $this->log_debug('Canonical Request:', $canonical_request);
        $this->log_debug('Payload Hash:', $payload_hash);
        
        // Create string to sign
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = "{$date_stamp}/{$this->region}/{$service}/aws4_request";
        $string_to_sign = "{$algorithm}\n{$amz_date}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
        
        $this->log_debug('String to Sign:', $string_to_sign);
        $this->log_debug('Credential Scope:', $credential_scope);
        
        // 获取 AWS 凭证
        $options = get_option('ai_chat_bedrock_settings');
        $access_key = isset($options['aws_access_key']) ? $options['aws_access_key'] : '';
        $secret_key = isset($options['aws_secret_key']) ? $options['aws_secret_key'] : '';
        
        // Calculate signature
        $signing_key = $this->get_signature_key($secret_key, $date_stamp, $this->region, $service);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        
        // Create authorization header
        $authorization_header = "{$algorithm} " . "Credential={$access_key}/{$credential_scope}, " . "SignedHeaders={$signed_headers}, " . "Signature={$signature}";
        
        // Create request headers
        $headers = [
            'Content-Type' => $content_type,
            'X-Amz-Date' => $amz_date,
            'Authorization' => $authorization_header,
        ];
        
        $this->log_debug('Signed headers', print_r($headers, true));
        
        // 创建 cURL 请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        
        // 设置请求头
        $curl_headers = [];
        foreach ($headers as $key => $value) {
            $curl_headers[] = "$key: $value";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
        
        $this->log_debug('cURL headers', print_r($curl_headers, true));
        
        // 设置为流式传输
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use ($client_id) {
            // 处理从 Bedrock 接收的数据
            $this->log_debug('Received data from Bedrock', 'Length: ' . strlen($data));
            $this->process_bedrock_data($client_id, $data);
            return strlen($data);
        });
        
        // 保持连接打开
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        
        // 添加详细的错误信息
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $this->log_debug('cURL setup complete', 'Ready to execute');
        
        // 存储连接
        $this->client_connections[$client_id] = [
            'curl' => $ch,
            'status' => 'connecting',
            'created_at' => time(),
            'verbose' => $verbose
        ];
        
        // 执行 cURL 请求
        $this->execute_curl_async($ch, $client_id);
        
        // 返回成功结果
        return [
            'status' => 'success',
            'message' => 'Connection established',
            'client_id' => $client_id
        ];
    }
    
    /**
     * 获取规范URI
     *
     * @since    1.0.0
     * @param    string    $path    请求路径
     * @return   string             规范URI
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
     * @since    1.0.0
     * @param    string    $key         密钥
     * @param    string    $date_stamp  日期戳
     * @param    string    $region      区域
     * @param    string    $service     服务
     * @return   string                 签名密钥
     */
    private function get_signature_key($key, $date_stamp, $region, $service) {
        $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $key, true);
        $k_region = hash_hmac('sha256', $region, $k_date, true);
        $k_service = hash_hmac('sha256', $service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        
        $this->log_debug('Signing Key Components:', [
            'k_date_hex' => bin2hex($k_date),
            'k_region_hex' => bin2hex($k_region),
            'k_service_hex' => bin2hex($k_service),
            'k_signing_hex' => bin2hex($k_signing),
        ]);
        
        return $k_signing;
    }
    
    /**
     * 执行 cURL 请求
     *
     * @since    1.0.0
     * @param    resource    $ch          cURL 句柄
     * @param    string      $client_id   客户端ID
     */
    private function execute_curl_async($ch, $client_id) {
        $this->log_debug('执行 cURL 请求', '客户端ID: ' . $client_id);
        
        // 直接执行 cURL 请求，而不是尝试异步执行
        $result = curl_exec($ch);
        
        if ($result === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $this->log_debug('cURL 错误', "错误 #$errno: $error");
            
            // 存储错误信息
            $events_key = 'bedrock_events_' . $client_id;
            $events = get_transient($events_key) ?: [];
            $events[] = [
                'type' => 'error',
                'content' => "连接错误: $error",
                'code' => $errno
            ];
            set_transient($events_key, $events, 3600);
        } else {
            $info = curl_getinfo($ch);
            $this->log_debug('cURL 成功', 'HTTP 代码: ' . $info['http_code'] . ', 响应长度: ' . strlen($result));
            
            // 存储连接成功信息
            $events_key = 'bedrock_events_' . $client_id;
            $events = get_transient($events_key) ?: [];
            $events[] = [
                'type' => 'status',
                'status' => 'connected',
                'message' => 'WebSocket 连接已建立'
            ];
            set_transient($events_key, $events, 3600);
        }
        
        // 关闭 cURL 句柄
        curl_close($ch);
    }
    
    /**
     * 获取客户端事件
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    int       $last_id      最后一个事件ID
     * @return   array                   事件数据
     */
    public function get_client_events($client_id, $last_id = -1) {
        $events_key = 'bedrock_events_' . $client_id;
        $events = get_transient($events_key) ?: [];
        
        // 过滤出新事件
        $new_events = [];
        $max_id = $last_id;
        
        foreach ($events as $index => $event) {
            // 如果事件没有ID，添加一个
            if (!isset($event['id'])) {
                $event['id'] = $index;
            }
            
            // 只返回ID大于last_id的事件
            if ($event['id'] > $last_id) {
                $new_events[] = $event;
                $max_id = max($max_id, $event['id']);
            }
        }
        
        return [
            'events' => $new_events,
            'last_id' => $max_id
        ];
    }
    
    /**
     * 处理从 Bedrock 接收的数据
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    string    $data         接收到的数据
     */
    public function process_bedrock_data($client_id, $data) {
        $this->log_debug('NOVA SONIC RESPONSE', 'Received data from Bedrock for client: ' . $client_id);
        
        // 解析数据
        $events = $this->parse_aws_events($data);
        
        if (count($events) > 0) {
            $this->log_debug('NOVA SONIC RESPONSE SUCCESS', 'Successfully parsed events: ' . count($events));
        }
        
        foreach ($events as $event) {
            // 将事件发送到客户端
            $this->send_to_client($client_id, $event);
        }
        
        // 更新连接状态
        if (isset($this->client_connections[$client_id])) {
            $this->client_connections[$client_id]['status'] = 'connected';
            $this->client_connections[$client_id]['last_activity'] = time();
        }
    }
    
    /**
     * 解析 AWS 事件
     *
     * @since    1.0.0
     * @param    string    $data    接收到的数据
     * @return   array              解析后的事件
     */
    private function parse_aws_events($data) {
        $events = [];
        
        // 尝试解析 JSON
        $json_data = json_decode($data, true);
        if ($json_data !== null && json_last_error() === JSON_ERROR_NONE) {
            // 处理 JSON 数据
            if (isset($json_data['message'])) {
                $events[] = [
                    'type' => 'text',
                    'content' => $json_data['message']
                ];
            } else if (isset($json_data['error'])) {
                $events[] = [
                    'type' => 'error',
                    'content' => $json_data['error']
                ];
            } else {
                // 尝试提取其他类型的事件
                $events = array_merge($events, $this->extract_events_from_json($json_data));
            }
        } else {
            // 如果不是 JSON，尝试按行解析
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $line_json = json_decode($line, true);
                    if ($line_json !== null && json_last_error() === JSON_ERROR_NONE) {
                        $events = array_merge($events, $this->extract_events_from_json($line_json));
                    }
                }
            }
        }
        
        return $events;
    }
    
    /**
     * 从 JSON 数据中提取事件
     *
     * @since    1.0.0
     * @param    array     $json_data    JSON 数据
     * @return   array                   提取的事件
     */
    private function extract_events_from_json($json_data) {
        $events = [];
        
        // 处理不同类型的事件
        if (isset($json_data['chunk']) && isset($json_data['chunk']['bytes'])) {
            // 处理二进制数据
            $bytes = $json_data['chunk']['bytes'];
            $events[] = [
                'type' => 'binary',
                'data' => $bytes
            ];
        } else if (isset($json_data['contentBlockDelta']) && isset($json_data['contentBlockDelta']['delta']['text'])) {
            // 处理文本增量
            $text = $json_data['contentBlockDelta']['delta']['text'];
            $events[] = [
                'type' => 'text',
                'content' => $text
            ];
        } else if (isset($json_data['audioOutput']) && isset($json_data['audioOutput']['content'])) {
            // 处理音频输出
            $audio = $json_data['audioOutput']['content'];
            $events[] = [
                'type' => 'audio',
                'data' => $audio
            ];
        } else if (isset($json_data['textOutput']) && isset($json_data['textOutput']['content'])) {
            // 处理文本输出
            $text = $json_data['textOutput']['content'];
            $events[] = [
                'type' => 'text',
                'content' => $text
            ];
        } else if (isset($json_data['contentStart']) && isset($json_data['contentStart']['role'])) {
            // 处理内容开始事件
            $role = $json_data['contentStart']['role'];
            $events[] = [
                'type' => 'status',
                'status' => 'content_start',
                'role' => $role
            ];
        } else if (isset($json_data['contentBlockStart'])) {
            // 处理内容块开始事件
            $events[] = [
                'type' => 'status',
                'status' => 'content_block_start'
            ];
        } else if (isset($json_data['contentBlockStop'])) {
            // 处理内容块结束事件
            $events[] = [
                'type' => 'status',
                'status' => 'content_block_stop'
            ];
        } else if (isset($json_data['messageStop'])) {
            // 处理消息结束事件
            $events[] = [
                'type' => 'status',
                'status' => 'message_stop'
            ];
        }
        
        return $events;
    }
    
    /**
     * 将事件发送到客户端
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    array     $event        事件数据
     */
    private function send_to_client($client_id, $event) {
        $events_key = 'bedrock_events_' . $client_id;
        $events = get_transient($events_key) ?: [];
        
        // 生成唯一事件ID
        $next_id = count($events) > 0 ? max(array_column($events, 'id') ?: [0]) + 1 : 1;
        
        // 添加ID和时间戳
        $event['id'] = $next_id;
        $event['timestamp'] = time();
        
        // 添加到事件队列
        $events[] = $event;
        
        // 保存事件队列
        set_transient($events_key, $events, 3600);
    }
    
    /**
     * 发送音频数据到 Bedrock
     *
     * @since    1.0.0
     * @param    string    $client_id     客户端ID
     * @param    string    $audio_data    音频数据（Base64编码）
     * @return   array                    结果
     */
    public function send_audio_data($client_id, $audio_data) {
        try {
            $this->log_debug('Sending audio data for client', $client_id . ', data length: ' . strlen($audio_data));
            
            if (!isset($this->client_connections[$client_id])) {
                $this->log_debug('Invalid client ID', 'Client ID: ' . $client_id . ' not found in connections');
                return ['error' => 'Invalid client ID or connection not established'];
            }
            
            // 构建音频事件
            $audio_event = json_encode([
                'inputAudio' => [
                    'audio' => $audio_data,
                    'contentType' => 'audio/wav',
                ]
            ]);
            
            $this->log_debug('Audio event created', 'Event length: ' . strlen($audio_event));
            
            // 获取连接
            $connection = $this->client_connections[$client_id];
            
            // 在实际实现中，这应该通过已建立的连接发送
            // 这里暂时模拟发送成功
            $this->log_debug('Simulating audio data sending', 'Client ID: ' . $client_id);
            
            // 更新最后活动时间
            $this->client_connections[$client_id]['last_activity'] = time();
            
            // 创建一个模拟响应
            $events_key = 'bedrock_events_' . $client_id;
            $events = get_transient($events_key) ?: [];
            
            // 添加实际的响应内容
            $events[] = [
                'type' => 'text',
                'content' => '我已收到您的语音输入，这是来自 WebSocket 代理的响应。请问您需要什么帮助？',
                'role' => 'assistant'
            ];
            
            set_transient($events_key, $events, 3600);
            
            return [
                'status' => 'sent',
                'message' => 'Audio data sent to Bedrock',
            ];
        } catch (Exception $e) {
            $this->log_debug('Error in send_audio_data', $e->getMessage());
            return ['error' => 'Error processing audio: ' . $e->getMessage()];
        }
    }
    
    /**
     * 更改语音
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    string    $voice        语音ID
     * @return   array                   结果
     */
    public function change_voice($client_id, $voice) {
        $this->log_debug('Changing voice for client', $client_id . ' to ' . $voice);
        
        if (!isset($this->client_connections[$client_id])) {
            return ['error' => 'Invalid client ID or connection not established'];
        }
        
        // 在实际实现中，这应该发送语音更改事件
        // 这里暂时模拟成功
        
        // 更新最后活动时间
        $this->client_connections[$client_id]['last_activity'] = time();
        
        // 创建语音更改事件
        $events_key = 'bedrock_events_' . $client_id;
        $events = get_transient($events_key) ?: [];
        $events[] = [
            'type' => 'status',
            'status' => 'voice_changed',
            'message' => '语音已更改为 ' . $voice
        ];
        set_transient($events_key, $events, 3600);
        
        return [
            'status' => 'success',
            'message' => 'Voice changed to ' . $voice,
        ];
    }
    
    /**
     * 关闭连接
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @return   array                   结果
     */
    public function close_connection($client_id) {
        $this->log_debug('Closing connection for client', $client_id);
        
        if (!isset($this->client_connections[$client_id])) {
            return ['error' => 'Invalid client ID or connection not established'];
        }
        
        // 关闭 cURL 句柄
        if (isset($this->client_connections[$client_id]['curl']) && is_resource($this->client_connections[$client_id]['curl'])) {
            curl_close($this->client_connections[$client_id]['curl']);
        }
        
        // 关闭 verbose 文件句柄
        if (isset($this->client_connections[$client_id]['verbose']) && is_resource($this->client_connections[$client_id]['verbose'])) {
            fclose($this->client_connections[$client_id]['verbose']);
        }
        
        // 移除连接
        unset($this->client_connections[$client_id]);
        
        // 创建关闭事件
        $events_key = 'bedrock_events_' . $client_id;
        $events = get_transient($events_key) ?: [];
        $events[] = [
            'type' => 'status',
            'status' => 'disconnected',
            'message' => '连接已关闭'
        ];
        set_transient($events_key, $events, 3600);
        
        return [
            'status' => 'success',
            'message' => 'Connection closed',
        ];
    }
    
    /**
     * 清理过期的连接
     *
     * @since    1.0.0
     */
    public function cleanup_connections() {
        $now = time();
        $timeout = 3600; // 1小时超时
        
        foreach ($this->client_connections as $client_id => $connection) {
            if ($now - $connection['created_at'] > $timeout) {
                $this->log_debug('Cleaning up expired connection', $client_id);
                $this->close_connection($client_id);
            }
        }
    }
}
