<?php
/**
 * Nova Sonic API 集成
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * Nova Sonic API 集成
 * 将 Bedrock WebSocket 功能集成到 WordPress REST API
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Nova_Sonic_API {
    private $bedrock_ws;
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
        
        // 初始化 Bedrock WebSocket 客户端
        require_once plugin_dir_path(__FILE__) . 'class-aws-sigv4.php';
        require_once plugin_dir_path(__FILE__) . 'class-bedrock-websocket.php';
        
        $this->bedrock_ws = new AI_Chat_Bedrock_WebSocket($access_key, $secret_key, $region, 'amazon.nova-sonic-v1:0', $this->debug);
        
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
            error_log("[{$timestamp}] AI Chat Bedrock Nova Sonic API - {$title} {$data}");
        }
    }
    
    /**
     * 注册 REST API 路由
     *
     * @since    1.0.0
     */
    public function register_routes() {
        // 注意：我们不再注册这些路由，改为使用代理API
        // 这样可以避免前端直接连接到 Bedrock
        $this->log_debug('REST API routes registration skipped', 'Using proxy mode instead');
        
        // 以下代码被注释掉，不再注册直接连接的路由
        /*
        $this->log_debug('Registering REST API routes', 'websocket-url and prepare-events');
        
        register_rest_route('ai-chat-bedrock/v1', '/websocket-url', [
            'methods' => 'POST',
            'callback' => [$this, 'get_websocket_url'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        
        register_rest_route('ai-chat-bedrock/v1', '/prepare-events', [
            'methods' => 'POST',
            'callback' => [$this, 'prepare_events'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
        */
    }
    
    /**
     * 检查用户权限
     *
     * @since    1.0.0
     * @return   bool    是否有权限
     */
    public function check_permission() {
        // 在测试阶段，允许所有请求
        return true;
        
        // 生产环境中应该检查nonce或用户权限
        // return is_user_logged_in() || wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', 'wp_rest');
    }
    
    /**
     * 获取预签名WebSocket URL
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response|WP_Error      响应对象
     */
    public function get_websocket_url($request) {
        try {
            $this->log_debug('Generating WebSocket URL', 'Starting');
            
            $presigned_url = $this->bedrock_ws->get_presigned_websocket_url();
            
            $this->log_debug('Generated WebSocket URL:', $presigned_url);
            
            return new WP_REST_Response([
                'status' => 'success',
                'websocket_url' => $presigned_url
            ], 200);
        } catch (Exception $e) {
            $this->log_debug('Error generating WebSocket URL:', $e->getMessage());
            return new WP_Error('websocket_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * 准备事件序列
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response|WP_Error      响应对象
     */
    public function prepare_events($request) {
        $audio_data = $request->get_param('audio_data');
        $system_prompt = $request->get_param('system_prompt');
        $options = $request->get_param('options') ?: [];
        
        if (empty($system_prompt)) {
            $system_prompt = "You are a helpful assistant. The user and you will engage in a spoken dialog exchanging the transcripts of a natural real-time conversation. Keep your responses short, generally two or three sentences for chatty scenarios.";
        }
        
        try {
            $events_data = $this->bedrock_ws->prepare_nova_sonic_events($audio_data, $system_prompt, $options);
            
            $this->log_debug('Prepared events count:', count($events_data['events']));
            
            return new WP_REST_Response([
                'status' => 'success',
                'prompt_name' => $events_data['prompt_name'],
                'events' => $events_data['events'],
                'events_count' => count($events_data['events'])
            ], 200);
        } catch (Exception $e) {
            $this->log_debug('Error preparing events:', $e->getMessage());
            return new WP_Error('events_error', $e->getMessage(), ['status' => 500]);
        }
    }
}
