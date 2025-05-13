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
        // AWS SigV4 实现现在直接集成到 WebSocket 类中
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
        
        // 完全禁用日志记录
        // 以下代码被注释掉，不再注册直接连接的路由
        /*
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
     * 检查权限
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   bool                           是否有权限
     */
    public function check_permission($request) {
        // 检查用户是否已登录
        return is_user_logged_in();
    }
    
    /**
     * 获取 WebSocket URL
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function get_websocket_url($request) {
        $this->log_debug('Getting WebSocket URL', '');
        
        try {
            $url = $this->bedrock_ws->get_websocket_url();
            return new WP_REST_Response([
                'url' => $url
            ]);
        } catch (Exception $e) {
            $this->log_debug('Error getting WebSocket URL', $e->getMessage());
            return new WP_Error('websocket_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * 准备事件
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    请求对象
     * @return   WP_REST_Response               响应对象
     */
    public function prepare_events($request) {
        $this->log_debug('Preparing events', '');
        
        // 这个方法在实际实现中应该准备事件
        // 但由于我们使用代理API，这个方法不会被调用
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Events prepared'
        ]);
    }
}
