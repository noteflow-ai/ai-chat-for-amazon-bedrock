/**
 * 使用服务器端代理模式的语音交互功能
 * 这个文件替代了直接使用WebSocket的ai-chat-bedrock-voice.js
 */
jQuery(document).ready(function($) {
    // 检查是否启用了语音功能
    if (!aiChatBedrockVoiceParams.voice_enabled) {
        return;
    }

    console.log("初始化语音代理模式 - 仅使用服务器端代理");

    // 初始化Nova Sonic代理客户端
    let novaSonicClient = null;
    
    // 创建语音按钮
    const $voiceButton = $('<button>', {
        'class': 'ai-chat-bedrock-voice-button',
        'html': '<i class="ai-chat-bedrock-mic-icon"></i>',
        'title': aiChatBedrockVoiceParams.i18n.proxy_mode
    });
    
    // 创建语音状态指示器
    const $voiceStatus = $('<div>', {
        'class': 'ai-chat-bedrock-voice-status',
        'text': ''
    });
    
    // 添加到聊天界面
    $('.ai-chat-bedrock-send-button').before($voiceButton);
    $('.ai-chat-bedrock-input-container').append($voiceStatus);
    
    // 初始化Nova Sonic代理客户端
    function initNovaSonicClient() {
        if (novaSonicClient) {
            novaSonicClient.close();
        }
        
        console.log("Creating Nova Sonic Proxy Client");
        $voiceStatus.text("初始化中...");
        
        // 使用aiChatBedrockVoiceParams中的系统提示
        const systemPrompt = aiChatBedrockVoiceParams.system_prompt || "You are a helpful assistant. Keep your responses short and engaging.";
        
        // 创建代理客户端
        novaSonicClient = new NovaSonicProxyClient({
            nonce: aiChatBedrockVoiceParams.nonce,
            audioSampleRate: 16000,
            systemPrompt: systemPrompt,
            voiceId: aiChatBedrockVoiceParams.voice_id || "matthew",
            restApiUrl: aiChatBedrockVoiceParams.rest_url
        });
        
        console.log("Nova Sonic Proxy Client created with REST URL:", aiChatBedrockVoiceParams.rest_url);
        
        // 设置回调
        novaSonicClient
            .on('message', handleMessage)
            .on('audio', handleAudio)
            .on('status', handleStatus)
            .on('error', handleError);
        
        // 初始化客户端
        novaSonicClient.init().then(() => {
            console.log("Nova Sonic Proxy Client initialized successfully");
            $voiceStatus.text("准备就绪");
            setTimeout(() => {
                $voiceStatus.text("");
            }, 2000);
        }).catch(error => {
            console.error('Failed to initialize Nova Sonic proxy client:', error);
            $voiceStatus.text(aiChatBedrockVoiceParams.i18n.error_microphone);
            $voiceStatus.addClass('error');
        });
    }
    
    // 处理文本消息
    function handleMessage(data) {
        console.log("Received message:", data);
        if (data.type === 'text' && data.content) {
            // 添加助手消息到聊天界面
            aiChatBedrock.addMessage({
                role: 'assistant',
                content: data.content
            });
        }
    }
    
    // 处理音频数据
    function handleAudio(data) {
        // 这里可以处理音频播放，但目前我们依赖Nova Sonic客户端内部的音频播放功能
        console.log('Audio received:', data.type);
    }
    
    // 处理状态更新
    function handleStatus(data) {
        console.log('Status update:', data.status, data.message);
        
        switch (data.status) {
            case 'recording':
                $voiceStatus.text(aiChatBedrockVoiceParams.i18n.listening);
                $voiceStatus.removeClass('error').addClass('active');
                break;
            case 'processing':
                $voiceStatus.text(aiChatBedrockVoiceParams.i18n.processing);
                $voiceStatus.removeClass('error active').addClass('processing');
                break;
            case 'ready':
                $voiceStatus.text("准备就绪");
                setTimeout(() => {
                    $voiceStatus.text("");
                }, 2000);
                $voiceStatus.removeClass('error active processing');
                break;
            case 'connected':
                $voiceStatus.text("已连接");
                setTimeout(() => {
                    $voiceStatus.text("");
                }, 2000);
                $voiceStatus.removeClass('error active processing');
                break;
            case 'disconnected':
                $voiceStatus.text("已断开");
                setTimeout(() => {
                    $voiceStatus.text("");
                }, 2000);
                $voiceStatus.removeClass('active processing');
                break;
            case 'voice_changed':
                $voiceStatus.text(data.message);
                setTimeout(() => {
                    $voiceStatus.text('');
                }, 2000);
                break;
        }
    }
    
    // 处理错误
    function handleError(error) {
        console.error('Nova Sonic proxy error:', error);
        
        if (error.message) {
            $voiceStatus.text(error.message);
        } else {
            $voiceStatus.text(aiChatBedrockVoiceParams.i18n.error_speech);
        }
        
        $voiceStatus.addClass('error');
        
        // 5秒后清除错误状态
        setTimeout(() => {
            $voiceStatus.text('');
            $voiceStatus.removeClass('error');
        }, 5000);
    }
    
    // 语音按钮点击事件
    $voiceButton.on('click', function() {
        if (!novaSonicClient) {
            console.log("Initializing Nova Sonic client on button click");
            initNovaSonicClient();
            return;
        }
        
        if (novaSonicClient.isRecording) {
            // 停止录音
            console.log("Stopping recording");
            novaSonicClient.stopRecording();
            $voiceButton.html('<i class="ai-chat-bedrock-mic-icon"></i>');
            $voiceButton.removeClass('recording');
        } else {
            // 开始录音
            console.log("Starting recording");
            if (novaSonicClient.startRecording()) {
                $voiceButton.html('<i class="ai-chat-bedrock-mic-icon"></i>');
                $voiceButton.addClass('recording');
            } else {
                // 如果无法开始录音，可能需要重新初始化
                console.log("Failed to start recording, reinitializing");
                initNovaSonicClient();
            }
        }
    });
    
    // 在页面卸载时关闭连接
    $(window).on('beforeunload', function() {
        if (novaSonicClient) {
            console.log("Closing Nova Sonic client on page unload");
            novaSonicClient.close();
        }
    });
    
    // 导出全局函数，以便其他脚本可以调用
    window.initNovaSonicProxyClient = initNovaSonicClient;
    
    // 自动初始化客户端
    console.log("Auto-initializing Nova Sonic Proxy Client");
    initNovaSonicClient();
});
