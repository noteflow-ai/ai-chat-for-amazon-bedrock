<?php
/**
 * 语音交互支持
 *
 * @package AI_Chat_Bedrock
 */

class AI_Chat_Bedrock_Voice_Interaction {
    /**
     * 初始化语音交互功能
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_voice_scripts'));
        add_action('wp_ajax_ai_chat_bedrock_bidirectional_voice_chat', array($this, 'handle_bidirectional_voice_chat'));
        add_action('wp_ajax_nopriv_ai_chat_bedrock_bidirectional_voice_chat', array($this, 'handle_bidirectional_voice_chat'));
        
    }
    
    /**
     * 加载语音交互所需的脚本和样式
     */
    public function enqueue_voice_scripts() {     
        wp_enqueue_style(
            'ai-chat-bedrock-voice-css',
            AI_CHAT_BEDROCK_PLUGIN_URL . 'public/css/ai-chat-bedrock-voice.css',
            array(),
            AI_CHAT_BEDROCK_VERSION
        );
        
        // 传递设置到前端
        $options = get_option('ai_chat_bedrock_settings');
        $voice_enabled = isset($options['enable_voice']) ? (bool)$options['enable_voice'] : false;
        $voice_id = isset($options['voice_id']) ? $options['voice_id'] : 'tiffany';
        
        wp_localize_script(
            'ai-chat-bedrock-voice',
            'aiChatBedrockVoiceParams',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_chat_bedrock_voice_nonce'),
                'voice_enabled' => $voice_enabled,
                'voice_id' => $voice_id,
                'i18n' => array(
                    'start_recording' => __('开始录音', 'ai-chat-for-amazon-bedrock'),
                    'stop_recording' => __('停止录音', 'ai-chat-for-amazon-bedrock'),
                    'listening' => __('正在听...', 'ai-chat-for-amazon-bedrock'),
                    'processing' => __('处理中...', 'ai-chat-for-amazon-bedrock'),
                    'error_microphone' => __('无法访问麦克风', 'ai-chat-for-amazon-bedrock'),
                    'error_speech' => __('语音识别失败', 'ai-chat-for-amazon-bedrock')
                )
            )
        );
    }
    
    
    
   
    /**
     * 处理双向语音对话请求
     */
    public function handle_bidirectional_voice_chat() {
        // 检查是否是 GET 请求（用于 EventSource）
        $is_eventsource = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'GET';
        
        if ($is_eventsource) {
            // 验证 nonce
            $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
            if (!wp_verify_nonce($nonce, 'ai_chat_bedrock_voice_nonce')) {
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                echo "data: " . json_encode(array('error' => '安全验证失败')) . "\n\n";
                exit;
            }
            
            // 获取系统提示
            $system_prompt = isset($_GET['system_prompt']) ? sanitize_textarea_field($_GET['system_prompt']) : '';
            
            // 设置 SSE 头
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // 禁用 nginx 缓冲
            
            // 刷新缓冲区
            if (ob_get_level()) ob_end_clean();
            ob_implicit_flush(true);
            
            // 发送初始消息
            echo "data: " . json_encode(array('type' => 'info', 'content' => '连接已建立')) . "\n\n";
            flush();
            
            // 发送一个初始心跳
            echo ": heartbeat\n\n";
            flush();
            
            // 设置超时时间（30秒）
            $timeout = 30;
            $start_time = time();
            $heartbeat_interval = 5; // 每5秒发送一次心跳
            $last_heartbeat = $start_time;
            
            // 保持连接打开，但设置超时
            while ((time() - $start_time) < $timeout) {
                // 检查连接是否已关闭
                if (connection_aborted()) {
                    break;
                }
                
                // 发送心跳以保持连接
                $current_time = time();
                if (($current_time - $last_heartbeat) >= $heartbeat_interval) {
                    echo ": heartbeat\n\n";
                    flush();
                    $last_heartbeat = $current_time;
                }
                
                // 短暂休眠，减少 CPU 使用
                usleep(100000); // 100ms
            }
            
            exit;
        } else {
            // POST 请求处理
            
            // 验证 nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_voice_nonce')) {
                wp_send_json_error(array('message' => '安全验证失败'));
            }
            
            // 获取音频数据和系统提示
            $system_prompt = isset($_POST['system_prompt']) ? sanitize_textarea_field($_POST['system_prompt']) : '';
            
            // 检查是否上传了音频文件
            if (!isset($_FILES['audio_data']) || $_FILES['audio_data']['error'] !== UPLOAD_ERR_OK) {
                error_log('AI Chat Bedrock - 音频文件上传失败: ' . print_r($_FILES, true));
                wp_send_json_error(array('message' => '音频文件上传失败'));
                return;
            }
            
            // 获取上传的临时文件
            $tmp_file = $_FILES['audio_data']['tmp_name'];
            $audio_data = file_get_contents($tmp_file);
            
            if (empty($audio_data)) {
                error_log('AI Chat Bedrock - 音频数据为空');
                wp_send_json_error(array('message' => '音频数据为空'));
                return;
            }
            
            // 获取音频类型
            $audio_type = isset($_POST['audio_type']) ? sanitize_text_field($_POST['audio_type']) : 'audio/webm';
            
            // 记录音频数据大小和类型
            $audio_size = strlen($audio_data);
            $mime_type = $_FILES['audio_data']['type'];
            error_log('AI Chat Bedrock - 接收到音频数据，大小: ' . $audio_size . ' 字节，类型: ' . $mime_type . '，声明类型: ' . $audio_type);
            
            // 检查音频格式
            if ($audio_type === 'audio/pcm') {
                error_log('AI Chat Bedrock - 检测到PCM格式，直接使用');
                // PCM格式已经是我们需要的格式，不需要转换
            } else if ($mime_type === 'audio/webm' || $audio_type === 'audio/webm') {
                error_log('AI Chat Bedrock - 检测到WebM格式，需要转换为PCM');
                
                // 注意：这里我们不进行实际转换，因为需要额外的库
                // 在实际应用中，你可能需要使用FFmpeg或其他库进行转换
                // 目前我们只是记录日志，并继续使用原始数据
                
                // 如果有可能，可以考虑在前端直接录制PCM格式的音频
                // 或者在服务器上安装FFmpeg并使用它进行转换
            }
            
            try {
                // 使用 Nova Sonic 类进行双向语音对话
                require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ai-chat-bedrock-nova-sonic.php';
                $nova_sonic = new AI_Chat_Bedrock_Nova_Sonic();
                
                // 获取语音设置
                $options = get_option('ai_chat_bedrock_settings');
                $voice_id = isset($options['voice_id']) ? $options['voice_id'] : 'tiffany';
                $output_sample_rate = isset($options['speech_sample_rate']) ? (int)$options['speech_sample_rate'] : 24000;
                $audio_input_format = isset($options['audio_input_format']) ? $options['audio_input_format'] : 'audio/lpcm';
                $input_sample_rate = isset($options['audio_input_sample_rate']) ? (int)$options['audio_input_sample_rate'] : 16000;
                $debug_mode = isset($options['debug_mode']) && $options['debug_mode'] === 'on';
                
                // 准备选项
                $chat_options = array(
                    'enable_voice' => true,
                    'voice_id' => $voice_id,
                    'speech_sample_rate' => $output_sample_rate,
                    'audio_input_format' => $audio_input_format,
                    'audio_input_sample_rate' => $input_sample_rate,
                    'enable_mcp' => isset($options['enable_mcp']) ? (bool)$options['enable_mcp'] : false,
                    'debug' => $debug_mode
                );
                
                // 记录配置信息
                error_log('AI Chat Bedrock - 语音配置: ' . json_encode($chat_options));
                
                // 设置脚本执行时间限制
                set_time_limit(180); // 增加到180秒
                
                // 增加内存限制
                ini_set('memory_limit', '256M');
                
                // 创建回调函数来处理流式响应
                $response_callback = function($processed_response) {
                    // 检查响应是否有效
                    if (!is_array($processed_response)) {
                        return;
                    }
                    
                    // 准备要发送的数据
                    $send_data = array(
                        'type' => isset($processed_response['type']) ? $processed_response['type'] : 'unknown',
                        'timestamp' => time()
                    );
                    
                    // 添加内容（如果有）
                    if (isset($processed_response['content'])) {
                        $send_data['content'] = $processed_response['content'];
                    }
                    
                    // 添加角色（如果有）
                    if (isset($processed_response['role'])) {
                        $send_data['role'] = $processed_response['role'];
                    }
                    
                    // 添加工具使用信息（如果有）
                    if (isset($processed_response['tool_use'])) {
                        $send_data['tool_use'] = $processed_response['tool_use'];
                    }
                    
                    // 特殊处理eventstream格式的响应
                    if (isset($processed_response['type']) && $processed_response['type'] === 'eventstream') {
                        // 检查是否包含音频数据
                        if (isset($processed_response['content']) && 
                            is_array($processed_response['content']) && 
                            isset($processed_response['content']['audioInput']) && 
                            isset($processed_response['content']['audioInput']['content'])) {
                            
                            // 将音频数据单独发送
                            $audio_data = array(
                                'type' => 'audio',
                                'content' => $processed_response['content']['audioInput']['content'],
                                'timestamp' => time()
                            );
                            
                            // 发送音频数据
                            echo "data: " . json_encode($audio_data) . "\n\n";
                            flush();
                            
                            // 记录日志
                            error_log('AI Chat Bedrock - 发送音频数据，大小: ' . strlen($processed_response['content']['audioInput']['content']) . ' 字节');
                        }
                        
                        // 检查是否包含文本响应
                        if (isset($processed_response['content']) && 
                            is_array($processed_response['content']) && 
                            isset($processed_response['content']['textResponse']) && 
                            isset($processed_response['content']['textResponse']['text'])) {
                            
                            // 将文本数据单独发送
                            $text_data = array(
                                'type' => 'text',
                                'content' => $processed_response['content']['textResponse']['text'],
                                'timestamp' => time()
                            );
                            
                            // 发送文本数据
                            echo "data: " . json_encode($text_data) . "\n\n";
                            flush();
                            
                            // 记录日志
                            error_log('AI Chat Bedrock - 发送文本数据: ' . $processed_response['content']['textResponse']['text']);
                        }
                    }
                    
                    // 特殊处理音频输出事件
                    if (isset($processed_response['type']) && $processed_response['type'] === 'audio') {
                        // 直接发送音频数据，不包含在send_data中
                        $audio_data = array(
                            'type' => 'audio',
                            'content' => $processed_response['content'],
                            'timestamp' => time()
                        );
                        echo "data: " . json_encode($audio_data) . "\n\n";
                        flush();
                        
                        // 记录日志
                        error_log('AI Chat Bedrock - 发送音频数据，大小: ' . strlen($processed_response['content']) . ' 字节');
                        
                        // 不再将音频数据包含在常规消息中
                        if (isset($send_data['content']) && $send_data['content'] === $processed_response['content']) {
                            unset($send_data['content']);
                        }
                    }
                    
                    // 发送 SSE 消息（如果有内容）
                    if (!empty($send_data) && (isset($send_data['type']) || isset($send_data['content']))) {
                        echo "data: " . json_encode($send_data) . "\n\n";
                        flush();
                    }
                };
                
                // 调用 Nova Sonic 进行双向语音对话
                // 注意：chat方法现在期望第一个参数是音频数据，而不是文本提示
                $response = $nova_sonic->chat($audio_data, $system_prompt, $chat_options, $response_callback);
                
                // 提取用户消息和助手消息
                $user_message = "这是一条语音消息";  // 默认消息
                $assistant_message = "";
                
                // 处理响应以提取消息
                if (isset($response['responses']) && is_array($response['responses'])) {
                    foreach ($response['responses'] as $resp) {
                        if (isset($resp['type']) && $resp['type'] === 'text' && isset($resp['role'])) {
                            if ($resp['role'] === 'USER' && isset($resp['content'])) {
                                $user_message = $resp['content'];
                            } elseif ($resp['role'] === 'ASSISTANT' && isset($resp['content'])) {
                                $assistant_message .= $resp['content'];
                            }
                        }
                    }
                }
                
                // 返回响应结果
                wp_send_json_success(array(
                    'success' => true,
                    'user_message' => $user_message,
                    'assistant_message' => $assistant_message,
                    'responses' => $response['responses']
                ));
            } catch (Exception $e) {
                error_log('AI Chat Bedrock - 处理语音请求时出错: ' . $e->getMessage());
                wp_send_json_error(array('message' => $e->getMessage()));
            }
        }
    }
}
