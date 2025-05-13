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
    private $aws_auth;
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
            error_log("[{$timestamp}] AI Chat Bedrock WebSocket - {$title} {$data}");
        }
    }
    
    /**
     * 生成预签名的 WebSocket URL
     *
     * @since    1.0.0
     * @return   string    预签名的WebSocket URL
     */
    public function get_presigned_websocket_url() {
        // 构建请求头
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        
        // 添加协议版本查询参数
        $endpoint_with_query = $this->endpoint . '?protocol-version=1.0';
        
        // 生成预签名URL
        $presigned_url = $this->aws_auth->generate_presigned_websocket_url($endpoint_with_query, $headers);
        
        $this->log_debug('Generated presigned WebSocket URL:', $presigned_url);
        
        return $presigned_url;
    }
    
    /**
     * 准备 Nova Sonic 事件序列
     *
     * @since    1.0.0
     * @param    string    $audio_data      音频数据
     * @param    string    $system_prompt   系统提示
     * @param    array     $options         选项
     * @return   array                      事件数据
     */
    public function prepare_nova_sonic_events($audio_data, $system_prompt, $options = []) {
        // 记录音频数据大小
        $this->log_debug('Audio data size:', is_string($audio_data) ? strlen($audio_data) : 'Not a string');
        
        // 创建唯一标识符
        $prompt_name = uniqid('prompt_');
        
        // 准备事件序列
        $events = [];
        
        // 1. 会话开始事件
        $events[] = [
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
        
        // 2. 提示开始事件
        $voice_id = isset($options['voice_id']) ? $options['voice_id'] : 'matthew';
        $sample_rate = isset($options['speech_sample_rate']) ? (int)$options['speech_sample_rate'] : 24000;
        
        $events[] = [
            'event' => [
                'promptStart' => [
                    'promptName' => $prompt_name,
                    'textOutputConfiguration' => [
                        'mediaType' => 'text/plain'
                    ],
                    'audioOutputConfiguration' => [
                        'mediaType' => 'audio/lpcm',
                        'sampleRateHertz' => $sample_rate,
                        'sampleSizeBits' => 16,
                        'channelCount' => 1,
                        'voiceId' => $voice_id,
                        'encoding' => 'base64',
                        'audioType' => 'SPEECH'
                    ]
                ]
            ]
        ];
        
        // 3. 系统内容开始
        $system_content_name = uniqid('content_');
        $events[] = [
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
        
        // 4. 系统文本输入
        $events[] = [
            'event' => [
                'textInput' => [
                    'promptName' => $prompt_name,
                    'contentName' => $system_content_name,
                    'content' => $system_prompt
                ]
            ]
        ];
        
        // 5. 系统内容结束
        $events[] = [
            'event' => [
                'contentEnd' => [
                    'promptName' => $prompt_name,
                    'contentName' => $system_content_name
                ]
            ]
        ];
        
        // 6. 用户内容开始
        $user_content_name = uniqid('content_');
        $events[] = [
            'event' => [
                'contentStart' => [
                    'promptName' => $prompt_name,
                    'contentName' => $user_content_name,
                    'type' => 'AUDIO',
                    'role' => 'USER',
                    'interactive' => true,
                    'audioInputConfiguration' => [
                        'mediaType' => isset($options['audio_input_format']) ? $options['audio_input_format'] : 'audio/lpcm',
                        'sampleRateHertz' => isset($options['audio_input_sample_rate']) ? (int)$options['audio_input_sample_rate'] : 16000,
                        'sampleSizeBits' => 16,
                        'channelCount' => 1,
                        'audioType' => 'SPEECH',
                        'encoding' => 'base64'
                    ]
                ]
            ]
        ];
        
        // 7. 音频输入
        if ($audio_data) {
            // 如果音频数据不是Base64编码，先进行编码
            if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $audio_data)) {
                $audio_data = base64_encode($audio_data);
            }
            
            // 分块发送音频数据
            $chunk_size = 10000; // 每个块的大小（字节）
            $total_length = strlen($audio_data);
            
            for ($i = 0; $i < $total_length; $i += $chunk_size) {
                $chunk = substr($audio_data, $i, $chunk_size);
                $events[] = [
                    'event' => [
                        'audioInput' => [
                            'promptName' => $prompt_name,
                            'contentName' => $user_content_name,
                            'content' => $chunk
                        ]
                    ]
                ];
            }
        }
        
        // 8. 用户内容结束
        $events[] = [
            'event' => [
                'contentEnd' => [
                    'promptName' => $prompt_name,
                    'contentName' => $user_content_name
                ]
            ]
        ];
        
        // 9. 提示结束
        $events[] = [
            'event' => [
                'promptEnd' => [
                    'promptName' => $prompt_name
                ]
            ]
        ];
        
        // 10. 会话结束
        $events[] = [
            'event' => [
                'sessionEnd' => []
            ]
        ];
        
        $this->log_debug('Prepared events count:', count($events));
        
        return [
            'prompt_name' => $prompt_name,
            'events' => $events
        ];
    }
}
