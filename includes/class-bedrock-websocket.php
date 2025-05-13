<?php
/**
 * Bedrock WebSocket 实现
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * Bedrock WebSocket 实现
 * 处理与 Bedrock 的双向流式通信
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_WebSocket {
    private $aws;
    private $region;
    private $model_id;
    private $endpoint;
    private $debug;
    
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
            if (is_array($data) || is_object($data)) {
                $data = print_r($data, true);
            }
            
            // 添加时间戳以便更好地跟踪事件顺序
            $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
            error_log("[{$timestamp}] AI Chat Bedrock WebSocket - {$title} {$data}");
        }
    }
    
    /**
     * 获取预签名的 WebSocket URL
     *
     * @since    1.0.0
     * @return   string    预签名的 WebSocket URL
     */
    public function get_websocket_url() {
        $this->log_debug('Getting WebSocket URL', 'Endpoint: ' . $this->endpoint);
        
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
        $payload_hash = hash('sha256', '');
        $canonical_request = "GET\n{$canonical_uri}\n{$canonical_querystring}\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        
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
        
        // 将 HTTP 端点转换为 WebSocket 端点
        $ws_endpoint = str_replace('https://', 'wss://', $this->endpoint);
        
        // 构建查询参数
        $query_params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $access_key . '/' . $credential_scope,
            'X-Amz-Date' => $amz_date,
            'X-Amz-SignedHeaders' => $signed_headers,
            'X-Amz-Signature' => $signature,
        ];
        
        // 添加查询参数到URL
        $separator = (strpos($ws_endpoint, '?') !== false) ? '&' : '?';
        $presigned_url = $ws_endpoint . $separator . http_build_query($query_params);
        
        $this->log_debug('Generated presigned WebSocket URL:', $presigned_url);
        
        return $presigned_url;
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
}
