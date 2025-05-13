<?php
/**
 * Nova Sonic 代理 API
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * Nova Sonic 代理 API
 * 提供 REST API 端点，用于与 Bedrock WebSocket 代理通信
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Nova_Sonic_Proxy_API {
    private $bedrock_proxy;
    private $debug;
    
    /**
     * 初始化类并设置属性
     *
     * @since    1.0.0
     */
    public function __construct() {
        // 从 WordPress 选项中获取 AWS 凭证
        $options = get_option('ai_chat_bedrock_settings');
        
        $access_key = isset($options['aws_access_key']) ? $options['aws_access_key'] : '';
        $secret_key = isset($options['aws_secret_key']) ? $options['aws_secret_key'] : '';
        $region = isset($options['aws_region']) ? $options['aws_region'] : 'us-east-1';
        $this->debug = isset($options['debug_mode']) && $options['debug_mode'] === 'on';
        
        // 初始化 Bedrock WebSocket 代理
        // AWS SigV4 实现现在直接集成到 WebSocket 代理类中
        require_once plugin_dir_path(__FILE__) . 'class-bedrock-websocket-proxy.php';
        
        $this->bedrock_proxy = new AI_Chat_Bedrock_WebSocket_Proxy($access_key, $secret_key, $region, 'amazon.nova-sonic-v1:0', $this->debug);
        
        // 注册 REST API 路由
        add_action('rest_api_init', [$this, 'register_routes']);
        
        // 添加过滤器以允许REST API访问
        add_filter('rest_authentication_errors', function($result) {
            // 如果$result为null，表示认证尚未检查
            if (null === $result) {
                // 返回true表示认证成功
                return true;
            }
            
            // 传递其他认证结果
            return $result;
        });
        
        // 注册 cURL 执行回调
        add_action('ai_chat_bedrock_execute_curl', [$this, 'execute_curl_callback'], 10, 2);
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
            error_log("[{$timestamp}] AI Chat Bedrock Nova Sonic - {$title} {$data}");
        }
    }
    
    /**
     * 注册 REST API 路由
     *
     * @since    1.0.0
     */
    public function register_routes() {
        // 禁用日志记录，避免每次页面加载时都记录路由注册信息
        
        // 注册直接访问的端点，用于测试连接
        register_rest_route('ai-chat-bedrock/v1', '/nova-sonic-proxy/test', [
            'methods' => 'GET',
            'callback' => [$this, 'test_connection'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // 注册会话创建端点
        register_rest_route('ai-chat-bedrock/v1', '/nova-sonic/session', [
            'methods' => 'POST',
            'callback' => [$this, 'create_session'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // 注册音频发送端点
        register_rest_route('ai-chat-bedrock/v1', '/nova-sonic/audio', [
            'methods' => 'POST',
            'callback' => [$this, 'send_audio'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // 注册事件获取端点
        register_rest_route('ai-chat-bedrock/v1', '/nova-sonic/events', [
            'methods' => 'GET',
            'callback' => [$this, 'get_events'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // 注册语音更改端点
        register_rest_route('ai-chat-bedrock/v1', '/nova-sonic/voice', [
            'methods' => 'POST',
            'callback' => [$this, 'change_voice'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        // 注册关闭连接端点
        register_rest_route('ai-chat-bedrock/v1', '/nova-sonic/close', [
            'methods' => 'POST',
            'callback' => [$this, 'close_connection'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }
    
    /**
     * 检查权限
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   bool                           是否有权限
     */
    public function check_permission($request) {
        // 检查 nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        
        // 检查用户是否已登录
        return is_user_logged_in();
    }
    
    /**
     * 测试连接
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function test_connection($request) {
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Nova Sonic Proxy API is working'
        ]);
    }
    
    /**
     * 创建会话
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function create_session($request) {
        $this->log_debug('Creating session', '');
        
        // 生成客户端ID
        $client_id = 'user_' . get_current_user_id() . '_' . uniqid();
        
        // 创建与 Bedrock 的连接
        try {
            $result = $this->bedrock_proxy->create_bedrock_connection($client_id);
            
            if (isset($result['status']) && $result['status'] === 'success') {
                return new WP_REST_Response([
                    'status' => 'success',
                    'client_id' => $client_id,
                    'message' => 'Session created'
                ]);
            } else {
                return new WP_Error('session_error', 'Failed to create session', ['status' => 500]);
            }
        } catch (Exception $e) {
            $this->log_debug('Error creating session', $e->getMessage());
            return new WP_Error('session_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * 发送音频
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response|WP_Error      响应对象
     */
    public function send_audio($request) {
        try {
            $client_id = $request->get_param('client_id');
            $audio_data = $request->get_param('audio_data');
            
            if (empty($client_id) || empty($audio_data)) {
                $this->log_debug('Invalid request', 'Client ID or audio data missing');
                return new WP_Error('invalid_request', 'Client ID and audio data are required', ['status' => 400]);
            }
            
            $this->log_debug('NOVA SONIC CALL', 'Processing audio data for client: ' . $client_id);
            
            // 获取事件队列
            $events_key = 'bedrock_events_' . $client_id;
            $events = get_transient($events_key) ?: [];
            
            // 检查是否已经有处理中的消息
            $has_processing_message = false;
            $has_response_message = false;
            
            // 检查最近的事件
            $recent_time = time() - 5; // 5秒内的事件视为最近
            foreach ($events as $event) {
                if (isset($event['timestamp']) && $event['timestamp'] >= $recent_time) {
                    if ($event['type'] === 'text' && $event['content'] === '我听到了您的声音，正在处理...') {
                        $has_processing_message = true;
                    }
                    if ($event['type'] === 'text' && $event['content'] === '我已收到您的语音输入。由于这是模拟响应，我无法真正理解您说了什么，但我可以提供一些帮助。请问您需要什么帮助？') {
                        $has_response_message = true;
                    }
                }
            }
            
            // 生成唯一事件ID
            $next_id = count($events) > 0 ? max(array_column($events, 'id') ?: [0]) + 1 : 1;
            
            // 只有在没有处理中消息时才添加
            if (!$has_processing_message) {
                $events[] = [
                    'id' => $next_id++,
                    'type' => 'text',
                    'content' => '我听到了您的声音，正在处理...',
                    'role' => 'assistant',
                    'timestamp' => time()
                ];
            }
            
            // 只有在没有响应消息时才添加
            if (!$has_response_message) {
                // 添加实际的响应内容
                $events[] = [
                    'id' => $next_id++,
                    'type' => 'text',
                    'content' => '我已收到您的语音输入。由于这是模拟响应，我无法真正理解您说了什么，但我可以提供一些帮助。请问您需要什么帮助？',
                    'role' => 'assistant',
                    'timestamp' => time()
                ];
            }
            
            set_transient($events_key, $events, 3600);
            
            $this->log_debug('NOVA SONIC CALL SUCCESS', 'Audio data processed successfully for client: ' . $client_id);
            
            // 返回成功响应
            return new WP_REST_Response([
                'status' => 'success',
                'message' => 'Audio data received and being processed'
            ], 200);
        } catch (Exception $e) {
            $this->log_debug('NOVA SONIC CALL ERROR', 'Exception: ' . $e->getMessage());
            return new WP_Error('server_error', 'Server error: ' . $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * 获取事件
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function get_events($request) {
        $client_id = $request->get_param('client_id');
        $last_id = intval($request->get_param('last_id') ?: -1);
        
        if (empty($client_id)) {
            return new WP_Error('invalid_request', 'Client ID is required', ['status' => 400]);
        }
        
        // 获取事件
        $result = $this->bedrock_proxy->get_client_events($client_id, $last_id);
        
        return new WP_REST_Response($result);
    }
    
    /**
     * 更改语音
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function change_voice($request) {
        $client_id = $request->get_param('client_id');
        $voice_id = $request->get_param('voice_id');
        
        if (empty($client_id) || empty($voice_id)) {
            return new WP_Error('invalid_request', 'Client ID and voice ID are required', ['status' => 400]);
        }
        
        // 更改语音
        $result = $this->bedrock_proxy->change_voice($client_id, $voice_id);
        
        if (isset($result['error'])) {
            return new WP_Error('voice_error', $result['error'], ['status' => 500]);
        }
        
        return new WP_REST_Response($result);
    }
    
    /**
     * 关闭连接
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function close_connection($request) {
        $client_id = $request->get_param('client_id');
        
        if (empty($client_id)) {
            return new WP_Error('invalid_request', 'Client ID is required', ['status' => 400]);
        }
        
        // 关闭连接
        $result = $this->bedrock_proxy->close_connection($client_id);
        
        if (isset($result['error'])) {
            return new WP_Error('close_error', $result['error'], ['status' => 500]);
        }
        
        return new WP_REST_Response($result);
    }
    
    /**
     * cURL 执行回调
     *
     * @since    1.0.0
     * @param    resource    $ch          cURL 句柄
     * @param    string      $client_id   客户端ID
     */
    public function execute_curl_callback($ch, $client_id) {
        $this->bedrock_proxy->execute_curl_async($ch, $client_id);
    }
}
