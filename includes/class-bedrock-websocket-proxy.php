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
    private $aws_auth;
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
        $this->aws_auth = new AI_Chat_Bedrock_AWS_SigV4($access_key, $secret_key, $region, $debug);
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
            if (is_array($data) || is_object($data)) {
                $data = print_r($data, true);
            }
            
            // 添加时间戳以便更好地跟踪事件顺序
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            error_log("[{$timestamp}] AI Chat Bedrock WebSocket Proxy - {$title} {$data}");
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
        
        // 构建请求头
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Amz-Content-Sha256' => hash('sha256', $request_body),
        ];
        
        $this->log_debug('Initial headers', print_r($headers, true));
        
        // 签名请求
        $this->log_debug('Signing request', 'Starting AWS SigV4 process');
        $signed_data = $this->aws_auth->sign_request('POST', $this->endpoint, $headers, $request_body);
        $signed_headers = $signed_data['headers'];
        
        $this->log_debug('Signed headers', print_r($signed_headers, true));
        
        // 创建 cURL 请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        
        // 设置请求头
        $curl_headers = [];
        foreach ($signed_headers as $key => $value) {
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
        
        // 在后台执行 cURL 请求
        $this->execute_curl_async($ch, $client_id);
        
        $this->log_debug('Connection initiated', 'Client ID: ' . $client_id);
        
        return [
            'status' => 'connecting',
            'client_id' => $client_id,
            'message' => 'Connection to Bedrock initiated',
        ];
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
     * 处理从 Bedrock 接收的数据
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    string    $data         接收到的数据
     */
    public function process_bedrock_data($client_id, $data) {
        $this->log_debug('Received data from Bedrock for client', $client_id . ', data length: ' . strlen($data));
        
        // 记录原始数据的前100个字符（用于调试）
        if (strlen($data) > 0) {
            $preview = substr($data, 0, min(100, strlen($data)));
            $this->log_debug('Data preview', $preview . (strlen($data) > 100 ? '...' : ''));
        }
        
        // 解析数据
        $events = $this->parse_aws_events($data);
        
        $this->log_debug('Parsed events count', count($events));
        
        foreach ($events as $event) {
            // 记录事件类型
            if (isset($event['type'])) {
                $this->log_debug('Event type', $event['type']);
            } elseif (isset($event['message'])) {
                $this->log_debug('Message event', 'Content length: ' . strlen($event['message']['content'] ?? ''));
            } elseif (isset($event['chunk'])) {
                $this->log_debug('Chunk event', 'Bytes length: ' . strlen($event['chunk']['bytes'] ?? ''));
            } elseif (isset($event['event'])) {
                $this->log_debug('Complex event', json_encode(array_keys($event['event'])));
            }
            
            // 将事件发送到客户端
            $this->send_to_client($client_id, $event);
        }
        
        // 更新连接状态
        if (isset($this->client_connections[$client_id])) {
            $this->client_connections[$client_id]['status'] = 'connected';
            $this->client_connections[$client_id]['last_activity'] = time();
            $this->log_debug('Connection status updated', 'Client: ' . $client_id . ', Status: connected');
        }
    }
    
    /**
     * 解析 AWS 事件流
     *
     * @since    1.0.0
     * @param    string    $data    接收到的数据
     * @return   array              解析后的事件数组
     */
    private function parse_aws_events($data) {
        $this->log_debug('Parsing AWS events', 'Data length: ' . strlen($data));
        
        // 实现 AWS 事件流解析
        // 这需要根据 AWS 事件流格式进行特定实现
        $events = [];
        
        // 尝试解析 JSON 数据
        $json_data = json_decode($data, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
            $this->log_debug('Successfully parsed JSON data', json_encode($json_data));
            $events[] = $json_data;
        } else {
            // 如果不是 JSON，可能是二进制数据或其他格式
            $this->log_debug('Failed to parse JSON data', 'Error: ' . json_last_error_msg());
            
            // 尝试解析二进制数据
            // 这里需要根据 AWS 事件流格式进行特定实现
            // 暂时将原始数据作为一个事件返回
            $events[] = [
                'type' => 'binary',
                'data' => base64_encode($data),
            ];
        }
        
        return $events;
    }
    
    /**
     * 发送数据到客户端
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    array     $event        事件数据
     */
    private function send_to_client($client_id, $event) {
        $this->log_debug('Sending event to client', $client_id);
        
        // 在实际实现中，这应该通过 WebSocket 发送到客户端
        // 这里使用 WordPress 的 transient API 来存储事件，供客户端轮询
        
        // 获取现有事件队列
        $events_key = 'bedrock_events_' . $client_id;
        $events = get_transient($events_key) ?: [];
        
        // 添加新事件
        $events[] = $event;
        
        // 限制队列大小
        if (count($events) > 100) {
            $events = array_slice($events, -100);
        }
        
        // 保存事件队列
        set_transient($events_key, $events, 3600); // 1小时过期
        
        // 更新最后活动时间
        if (isset($this->client_connections[$client_id])) {
            $this->client_connections[$client_id]['last_activity'] = time();
        }
    }
    
    /**
     * 获取客户端事件
     *
     * @since    1.0.0
     * @param    string    $client_id    客户端ID
     * @param    int       $last_id      最后接收的事件ID
     * @return   array                   事件数组
     */
    public function get_client_events($client_id, $last_id = -1) {
        $this->log_debug('Getting events for client', $client_id . ', last_id: ' . $last_id);
        
        // 获取事件队列
        $events_key = 'bedrock_events_' . $client_id;
        $events = get_transient($events_key) ?: [];
        
        // 过滤事件
        if ($last_id >= 0 && $last_id < count($events)) {
            $events = array_slice($events, $last_id + 1);
        }
        
        // 添加事件ID
        foreach ($events as $i => &$event) {
            $event['id'] = $last_id + $i + 1;
        }
        
        return [
            'events' => $events,
            'last_id' => $last_id + count($events),
        ];
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
            $events[] = [
                'type' => 'text',
                'content' => '我已收到您的语音输入。由于这是模拟响应，我无法真正理解您说了什么，但我可以提供一些一般性的帮助。请问您需要什么帮助？',
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
        
        // 构建语音更改事件
        $voice_event = json_encode([
            'changeVoice' => [
                'voiceId' => $voice,
            ]
        ]);
        
        // 获取连接
        $connection = $this->client_connections[$client_id];
        
        // 在实际实现中，这应该通过已建立的连接发送
        // 这里暂时模拟发送成功
        
        // 更新最后活动时间
        $this->client_connections[$client_id]['last_activity'] = time();
        
        return [
            'status' => 'changed',
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
        
        // 获取连接
        $connection = $this->client_connections[$client_id];
        
        // 关闭 cURL 连接
        if (isset($connection['curl']) && is_resource($connection['curl'])) {
            curl_close($connection['curl']);
        }
        
        // 删除连接记录
        unset($this->client_connections[$client_id]);
        
        // 删除事件队列
        $events_key = 'bedrock_events_' . $client_id;
        delete_transient($events_key);
        
        return [
            'status' => 'closed',
            'message' => 'Connection closed',
        ];
    }
    
    /**
     * 清理过期连接
     *
     * @since    1.0.0
     */
    public function cleanup_connections() {
        $this->log_debug('Cleaning up expired connections', 'Current count: ' . count($this->client_connections));
        
        $now = time();
        $timeout = 3600; // 1小时超时
        
        foreach ($this->client_connections as $client_id => $connection) {
            // 检查最后活动时间
            if (isset($connection['last_activity']) && $now - $connection['last_activity'] > $timeout) {
                $this->log_debug('Connection expired', $client_id);
                $this->close_connection($client_id);
            }
        }
    }
}
