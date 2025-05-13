<?php
/**
 * AWS SigV4 签名实现
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 */

/**
 * AWS SigV4 签名实现
 * 参考 Python SDK 中的 SigV4AuthScheme 实现
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_AWS_SigV4 {
    private $access_key;
    private $secret_key;
    private $region;
    private $service = 'bedrock-runtime';
    private $debug;
    
    /**
     * 初始化类并设置属性
     *
     * @since    1.0.0
     * @param    string    $access_key    AWS访问密钥
     * @param    string    $secret_key    AWS秘密密钥
     * @param    string    $region        AWS区域
     * @param    bool      $debug         是否启用调试
     */
    public function __construct($access_key, $secret_key, $region = 'us-east-1', $debug = false) {
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
        $this->region = $region;
        $this->debug = $debug;
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
            error_log("[{$timestamp}] AI Chat Bedrock AWS SigV4 - {$title} {$data}");
        }
    }
    
    /**
     * 生成 AWS SigV4 签名
     *
     * @since    1.0.0
     * @param    string    $method     HTTP方法
     * @param    string    $endpoint   端点URL
     * @param    array     $headers    请求头
     * @param    string    $body       请求体
     * @return   array                 签名数据
     */
    public function sign_request($method, $endpoint, $headers = [], $body = '') {
        $datetime = gmdate('Ymd\THis\Z');
        $date = substr($datetime, 0, 8);
        
        // 解析 URL
        $url_parts = parse_url($endpoint);
        $host = $url_parts['host'];
        $path = isset($url_parts['path']) ? $url_parts['path'] : '/';
        $query = isset($url_parts['query']) ? $url_parts['query'] : '';
        
        // 准备请求头
        if (!isset($headers['host'])) {
            $headers['host'] = $host;
        }
        $headers['x-amz-date'] = $datetime;
        
        // 计算请求体哈希
        $payload_hash = hash('sha256', $body);
        $headers['x-amz-content-sha256'] = $payload_hash;
        
        // 规范化请求头
        ksort($headers);
        $canonical_headers = '';
        $signed_headers = '';
        
        foreach ($headers as $key => $value) {
            $canonical_headers .= strtolower($key) . ':' . trim($value) . "\n";
            $signed_headers .= strtolower($key) . ';';
        }
        $signed_headers = rtrim($signed_headers, ';');
        
        // 规范化查询字符串
        $canonical_query_string = '';
        if (!empty($query)) {
            $query_params = [];
            parse_str($query, $query_params);
            ksort($query_params);
            $canonical_query_string = http_build_query($query_params);
            // 修复 http_build_query 的编码问题
            $canonical_query_string = str_replace('+', '%20', $canonical_query_string);
        }
        
        // 构建规范请求
        $canonical_request = $method . "\n" .
                            $path . "\n" .
                            $canonical_query_string . "\n" .
                            $canonical_headers . "\n" .
                            $signed_headers . "\n" .
                            $payload_hash;
        
        $this->log_debug('Canonical Request:', $canonical_request);
        
        // 构建签名字符串
        $algorithm = 'AWS4-HMAC-SHA256';
        $credential_scope = $date . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $string_to_sign = $algorithm . "\n" .
                         $datetime . "\n" .
                         $credential_scope . "\n" .
                         hash('sha256', $canonical_request);
        
        $this->log_debug('String to Sign:', $string_to_sign);
        
        // 计算签名
        $k_date = hash_hmac('sha256', $date, 'AWS4' . $this->secret_key, true);
        $k_region = hash_hmac('sha256', $this->region, $k_date, true);
        $k_service = hash_hmac('sha256', $this->service, $k_region, true);
        $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
        $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
        
        // 构建授权头
        $auth_header = $algorithm . ' ' .
                      'Credential=' . $this->access_key . '/' . $credential_scope . ', ' .
                      'SignedHeaders=' . $signed_headers . ', ' .
                      'Signature=' . $signature;
        
        $this->log_debug('Authorization Header:', $auth_header);
        
        // 添加授权头到请求头
        $headers['Authorization'] = $auth_header;
        
        return [
            'headers' => $headers,
            'signature' => $signature,
            'signed_headers' => $signed_headers,
            'credential' => $this->access_key . '/' . $credential_scope,
            'datetime' => $datetime,
            'payload_hash' => $payload_hash
        ];
    }
    
    /**
     * 生成预签名 WebSocket URL
     *
     * @since    1.0.0
     * @param    string    $endpoint   端点URL
     * @param    array     $headers    请求头
     * @param    string    $body       请求体
     * @return   string                预签名URL
     */
    public function generate_presigned_websocket_url($endpoint, $headers = [], $body = '') {
        $signed_data = $this->sign_request('GET', $endpoint, $headers, $body);
        
        // 将 HTTP 端点转换为 WebSocket 端点
        $ws_endpoint = str_replace('https://', 'wss://', $endpoint);
        
        // 构建查询参数
        $query_params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $signed_data['credential'],
            'X-Amz-Date' => $signed_data['datetime'],
            'X-Amz-SignedHeaders' => $signed_data['signed_headers'],
            'X-Amz-Signature' => $signed_data['signature'],
        ];
        
        // 添加查询参数到URL
        $separator = (strpos($ws_endpoint, '?') !== false) ? '&' : '?';
        $presigned_url = $ws_endpoint . $separator . http_build_query($query_params);
        
        $this->log_debug('Generated presigned WebSocket URL:', $presigned_url);
        
        return $presigned_url;
    }
}
