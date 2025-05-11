/**
 * AI Chat for Amazon Bedrock - 语音交互功能
 */
(function($) {
    'use strict';
    
    // 全局变量
    let mediaRecorder;
    let audioChunks = [];
    let isRecording = false;
    let stream;
    let recordingAudioContext;
    let audioProcessor;
    let novaSonicStream;
    let eventSource;
    let playbackAudioContext = null;
    let audioPlaybackQueue = [];
    let isPlaybackActive = false;
    
    // DOM 元素
    let $chatContainer;
    let $messagesContainer;
    let $voiceButton;
    let $voiceIndicator;
    
    // 初始化函数
    function initVoiceChat() {
        console.log('初始化语音交互功能');
        
        // 查找聊天容器
        $chatContainer = $('.ai-chat-bedrock-container');
        if (!$chatContainer.length) {
            console.log('未找到聊天容器');
            return;
        }
        
        $messagesContainer = $('.ai-chat-bedrock-messages');
        
        // 创建语音按钮
        createVoiceButton();
        
        // 检查浏览器支持
        checkBrowserSupport();
    }
    
    // 在文档加载完成后初始化
    $(document).ready(function() {
        // 检查是否启用了语音功能
        console.log('语音功能状态:', aiChatBedrockVoiceParams.voice_enabled);
        initVoiceChat();
    });
    
    // 创建语音按钮
    function createVoiceButton() {
        // 创建语音按钮
        $voiceButton = $('<button>', {
            'class': 'ai-chat-bedrock-voice-button',
            'title': aiChatBedrockVoiceParams.i18n.start_recording,
            'type': 'button'
        }).append(
            $('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 1a5 5 0 015 5v6a5 5 0 01-10 0V6a5 5 0 015-5zm0 2a3 3 0 00-3 3v6a3 3 0 006 0V6a3 3 0 00-3-3zm-1 18h2v2h-2v-2z" fill="currentColor"/></svg>')
        );
        
        // 创建语音指示器
        $voiceIndicator = $('<div>', {
            'class': 'ai-chat-bedrock-voice-indicator'
        }).append(
            $('<span>', {
                'class': 'ai-chat-bedrock-voice-indicator-text'
            })
        );
        
        // 将按钮添加到聊天表单的发送按钮旁边
        const $form = $('.ai-chat-bedrock-form');
        const $submitButton = $form.find('.ai-chat-bedrock-submit');
        
        // 在发送按钮前插入语音按钮
        $submitButton.before($voiceButton);
        
        // 将指示器添加到表单
        $form.append($voiceIndicator);
        
        // 绑定点击事件
        $voiceButton.on('click', function(e) {
            e.preventDefault(); // 防止表单提交
            toggleRecording();
        });
    }
    
    // 检查浏览器支持
    function checkBrowserSupport() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.error('浏览器不支持 getUserMedia API');
            $voiceButton.prop('disabled', true);
            $voiceButton.attr('title', '您的浏览器不支持语音功能');
            return false;
        }
        
        if (!window.AudioContext && !window.webkitAudioContext) {
            console.error('浏览器不支持 AudioContext API');
            $voiceButton.prop('disabled', true);
            $voiceButton.attr('title', '您的浏览器不支持语音功能');
            return false;
        }
        
        return true;
    }
    
    // 切换录音状态
    function toggleRecording() {
        if (isRecording) {
            stopRecording();
        } else {
            startRecording();
        }
    }
    
    // 开始录音
    function startRecording() {
        if (!checkBrowserSupport()) return;
        
        // 请求麦克风权限
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function(microphone) {
                stream = microphone;
                
                // 创建 AudioContext
                recordingAudioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // 创建媒体流源
                const source = recordingAudioContext.createMediaStreamSource(stream);
                
                // 创建处理器节点
                const bufferSize = 4096;
                audioProcessor = recordingAudioContext.createScriptProcessor(bufferSize, 1, 1);
                
                // 连接节点
                source.connect(audioProcessor);
                audioProcessor.connect(recordingAudioContext.destination);
                
                // 存储PCM数据
                let pcmChunks = [];
                
                // 处理音频数据
                audioProcessor.onaudioprocess = function(e) {
                    if (!isRecording) return;
                    
                    // 获取音频数据
                    const inputData = e.inputBuffer.getChannelData(0);
                    
                    // 将音频数据转换为 16 位整数
                    const pcmData = convertFloat32ToInt16(inputData);
                    
                    // 存储PCM数据
                    pcmChunks.push(new Int16Array(pcmData));
                    
                    // 发送音频数据到 Nova Sonic
                    if (novaSonicStream) {
                        novaSonicStream.send(pcmData);
                    }
                };
                
                // 创建 MediaRecorder 作为备份方案
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                
                // 收集音频数据
                mediaRecorder.ondataavailable = function(e) {
                    audioChunks.push(e.data);
                };
                
                // 录音结束后处理
                mediaRecorder.onstop = function() {
                    // 如果有PCM数据，优先使用PCM数据
                    if (pcmChunks.length > 0) {
                        console.log('使用PCM格式音频数据');
                        
                        // 计算总长度
                        let totalLength = 0;
                        for (const chunk of pcmChunks) {
                            totalLength += chunk.length;
                        }
                        
                        // 创建合并后的数组
                        const mergedPcm = new Int16Array(totalLength);
                        let offset = 0;
                        
                        // 合并所有PCM块
                        for (const chunk of pcmChunks) {
                            mergedPcm.set(chunk, offset);
                            offset += chunk.length;
                        }
                        
                        // 创建PCM音频Blob
                        const pcmBlob = new Blob([mergedPcm], { type: 'audio/pcm' });
                        
                        // 发送到服务器进行处理
                        sendAudioToServer(pcmBlob, 'audio/pcm');
                    } else {
                        console.log('使用WebM格式音频数据（备份方案）');
                        
                        // 创建音频 Blob
                        const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                        
                        // 发送到服务器进行处理
                        sendAudioToServer(audioBlob, 'audio/webm');
                    }
                    
                    // 清空PCM数据
                    pcmChunks = [];
                };
                
                // 开始录音
                mediaRecorder.start();
                isRecording = true;
                
                // 更新 UI
                $voiceButton.addClass('recording');
                $voiceButton.attr('title', aiChatBedrockVoiceParams.i18n.stop_recording);
                $voiceIndicator.addClass('active');
                $voiceIndicator.find('.ai-chat-bedrock-voice-indicator-text').text(aiChatBedrockVoiceParams.i18n.listening);
                
                // 开始 Nova Sonic 双向流
                startNovaSonicStream();
                
            })
            .catch(function(error) {
                console.error('获取麦克风权限失败:', error);
                showError(aiChatBedrockVoiceParams.i18n.error_microphone);
            });
    }
    
    // 停止录音
    function stopRecording() {
        if (!isRecording) return;
        
        // 停止录音
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        
        // 停止音频处理
        if (audioProcessor) {
            audioProcessor.disconnect();
            audioProcessor = null;
        }
        
        // 停止媒体流
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        
        // 关闭 AudioContext
        if (recordingAudioContext) {
            recordingAudioContext.close().then(() => {
                recordingAudioContext = null;
            });
        }
        
        // 停止 Nova Sonic 双向流
        stopNovaSonicStream();
        
        // 更新状态
        isRecording = false;
        
        // 更新 UI
        $voiceButton.removeClass('recording');
        $voiceButton.attr('title', aiChatBedrockVoiceParams.i18n.start_recording);
        $voiceIndicator.removeClass('active');
        $voiceIndicator.find('.ai-chat-bedrock-voice-indicator-text').text('');
    }
    
    // 开始 Nova Sonic 双向流
    function startNovaSonicStream() {
        // 准备系统提示
        const systemPrompt = '你是一个有用的AI助手。请用中文回答问题。';
        
        // 设置 SSE
        const url = new URL(aiChatBedrockVoiceParams.ajax_url);
        url.searchParams.append('action', 'ai_chat_bedrock_bidirectional_voice_chat');
        url.searchParams.append('nonce', aiChatBedrockVoiceParams.nonce);
        url.searchParams.append('system_prompt', systemPrompt);
        
        try {
            // 关闭可能存在的旧连接
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            // 创建 EventSource
            eventSource = new EventSource(url.toString());
            
            // 处理连接打开
            eventSource.onopen = function() {
                console.log('SSE 连接已建立');
            };
            
            // 处理消息
            eventSource.onmessage = function(event) {
                try {
                    // 检查是否为空数据
                    if (!event.data || event.data.trim() === '') {
                        console.log('收到空 SSE 消息，忽略');
                        return;
                    }
                    
                    const data = JSON.parse(event.data);
                    console.log('收到 Nova Sonic 响应:', data);
                    
                    // 处理文本响应
                    if (data.type === 'text' && data.content) {
                        // 添加助手消息
                        addMessage(data.content, false);
                    } else if (data.type === 'info') {
                        console.log('SSE 信息:', data.content);
                    } else if (data.type === 'error') {
                        console.error('SSE 错误:', data.content);
                        showError(data.content || '处理语音时出错');
                    }
                    
                    // 处理音频响应
                    if (data.type === 'audio' && data.content) {
                        // 播放音频
                        playAudio(data.content);
                    }
                    
                    // 处理工具使用
                    if (data.type === 'tool_use' && data.tool_use) {
                        console.log('工具使用:', data.tool_use);
                    }
                } catch (error) {
                    console.error('解析 SSE 消息失败:', error, '原始数据:', event.data);
                }
            };
            
            // 处理错误
            eventSource.onerror = function(error) {
                console.error('SSE 错误:', error);
                // 不要立即关闭连接，尝试重新连接
                if (eventSource.readyState === EventSource.CLOSED) {
                    console.log('SSE 连接已关闭，尝试重新连接');
                    setTimeout(() => {
                        if (isRecording) {
                            startNovaSonicStream();
                        }
                    }, 2000); // 2秒后尝试重新连接
                }
            };
        } catch (error) {
            console.error('创建 EventSource 失败:', error);
            showError('无法建立语音连接');
        }
    }
    
    // 停止 Nova Sonic 双向流
    function stopNovaSonicStream() {
        if (eventSource) {
            eventSource.close();
            eventSource = null;
        }
        
        if (novaSonicStream) {
            novaSonicStream.close();
            novaSonicStream = null;
        }
    }
    
    // 发送音频到服务器
    function sendAudioToServer(audioBlob, audioType = 'audio/pcm') {
        // 检查音频 Blob 是否有效
        if (!audioBlob || audioBlob.size === 0) {
            console.error('无效的音频数据');
            showError('录音失败，请重试');
            return;
        }
        
        console.log('准备发送音频数据，大小:', audioBlob.size, '字节，类型:', audioType);
        
        // 创建 FormData
        const formData = new FormData();
        formData.append('action', 'ai_chat_bedrock_bidirectional_voice_chat');
        formData.append('nonce', aiChatBedrockVoiceParams.nonce);
        
        // 根据音频类型设置文件名
        const fileName = audioType === 'audio/pcm' ? 'recording.pcm' : 'recording.webm';
        formData.append('audio_data', audioBlob, fileName);
        
        // 添加音频类型信息
        formData.append('audio_type', audioType);
        
        // 添加系统提示
        const systemPrompt = '你是一个有用的AI助手。请用中文回答问题。';
        formData.append('system_prompt', systemPrompt);
        
        // 更新 UI
        $voiceIndicator.find('.ai-chat-bedrock-voice-indicator-text').text(aiChatBedrockVoiceParams.i18n.processing);
        
        // 添加默认用户消息
        addMessage('语音消息处理中...', true);
        
        // 发送请求
        $.ajax({
            url: aiChatBedrockVoiceParams.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'text', // 使用text而不是json，以便我们可以手动处理响应
            timeout: 60000, // 增加到60秒超时
            success: function(responseText) {
                console.log('收到响应:', responseText.substring(0, 100) + '...');
                
                // 检查是否为SSE格式的响应或包含eventstream数据
                if (responseText && (responseText.startsWith('data: ') || responseText.includes('application/vnd.amazon.eventstream'))) {
                    // 处理SSE格式的响应
                    const lines = responseText.split('\n\n');
                    let hasProcessedContent = false;
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const jsonStr = line.substring(6); // 移除 'data: ' 前缀
                            try {
                                const data = JSON.parse(jsonStr);
                                console.log('解析SSE数据:', data);
                                
                                // 处理文本响应
                                if (data.type === 'text' && data.content) {
                                    // 添加助手消息
                                    addMessage(data.content, false);
                                    hasProcessedContent = true;
                                }
                                
                // 处理eventstream类型的响应
                else if (data.type === 'eventstream') {
                    console.log('处理eventstream响应:', data);
                    
                    // 详细记录content内容，帮助调试
                    if (data.content && typeof data.content === 'object') {
                        console.log('eventstream content详情:', JSON.stringify(data.content).substring(0, 200));
                    }
                    
                    // 检查是否包含event字段
                    if (data.content && typeof data.content === 'object' && data.content.event) {
                        console.log('检测到event字段:', data.content.event);
                        
                        // 处理audioOutput事件
                        if (data.content.event.audioOutput) {
                            console.log('检测到audioOutput事件');
                            if (data.content.event.audioOutput.content) {
                                console.log('检测到音频内容，长度:', data.content.event.audioOutput.content.length);
                                // 播放音频
                                playAudio(data.content.event.audioOutput.content);
                                hasProcessedContent = true;
                            }
                        }
                        
                        // 处理textOutput事件
                        if (data.content.event.textOutput) {
                            console.log('检测到textOutput事件');
                            if (data.content.event.textOutput.content) {
                                console.log('检测到文本内容:', data.content.event.textOutput.content);
                                addMessage(data.content.event.textOutput.content, false);
                                hasProcessedContent = true;
                            }
                        }
                    }
                    
                    // 如果content是对象并包含message字段，显示为消息
                    if (data.content && typeof data.content === 'object' && data.content.message) {
                        addMessage(data.content.message, false);
                        hasProcessedContent = true;
                    }
                    
                    // 如果content是对象并包含text_content字段，显示为消息
                    else if (data.content && typeof data.content === 'object' && data.content.text_content) {
                        addMessage(data.content.text_content, false);
                        hasProcessedContent = true;
                    }
                    
                    // 处理Nova的文本增量事件
                    else if (data.event_type === 'contentBlockDelta' && data.content && data.content.contentBlockDelta && 
                             data.content.contentBlockDelta.delta && data.content.contentBlockDelta.delta.text) {
                        addMessage(data.content.contentBlockDelta.delta.text, false);
                        hasProcessedContent = true;
                    }
                    
                    // 处理Nova Sonic的音频响应 - 旧格式
                    else if (data.content && typeof data.content === 'object' && data.content.audioInput) {
                        console.log('检测到Nova Sonic audioInput响应');
                        if (data.content.audioInput.content) {
                            console.log('检测到audioInput音频内容，长度:', data.content.audioInput.content.length);
                            // 播放音频
                            playAudio(data.content.audioInput.content);
                            hasProcessedContent = true;
                        }
                    }
                    
                    // 处理Nova Sonic的文本响应 - 旧格式
                    else if (data.content && typeof data.content === 'object' && data.content.textResponse) {
                        console.log('检测到Nova Sonic textResponse响应');
                        if (data.content.textResponse.text) {
                            addMessage(data.content.textResponse.text, false);
                            hasProcessedContent = true;
                        }
                    }
                    
                    // 处理直接包含在content中的文本（可能是Base64编码的）
                    else if (data.content && typeof data.content === 'string') {
                        try {
                            // 尝试解码Base64
                            const decodedContent = atob(data.content);
                            if (decodedContent && decodedContent.trim()) {
                                addMessage(decodedContent, false);
                                hasProcessedContent = true;
                            }
                        } catch (e) {
                            // 如果不是Base64，直接使用原始内容
                            if (data.content.trim()) {
                                addMessage(data.content, false);
                                hasProcessedContent = true;
                            }
                        }
                    }
                    
                    // 检查是否有错误消息
                    if (data.content && typeof data.content === 'object' && data.content.message && 
                        (data.content.message.includes('error') || data.content.message.includes('unexpected'))) {
                        showError(data.content.message);
                        hasProcessedContent = true;
                    }
                }
                                
                                // 处理完整文本响应
                                else if (data.type === 'complete_text' && data.content) {
                                    addMessage(data.content, false);
                                    hasProcessedContent = true;
                                }
                                
                                // 处理错误响应
                                else if (data.type === 'error' || data.type === 'parse_error') {
                                    const errorMsg = data.content || data.message || '处理语音时出错';
                                    
                                    // 检查是否为Amazon Bedrock常见错误
                                    if (errorMsg.includes('unexpected error') || 
                                        errorMsg.includes('Invalid input request') ||
                                        errorMsg.includes('Try your request again')) {
                                        
                                        // 显示友好的错误消息
                                        showError('语音识别暂时不可用，请稍后再试或使用文本输入');
                                        
                                        // 添加一个默认的助手回复，提供更好的用户体验
                                        addMessage('抱歉，我无法处理您的语音消息。请尝试使用文本输入或稍后再试。', false);
                                    } else {
                                        showError(errorMsg);
                                    }
                                    
                                    hasProcessedContent = true;
                                }
                            } catch (jsonError) {
                                console.error('解析SSE JSON数据失败:', jsonError, '原始数据:', jsonStr);
                            }
                        }
                    }
                    
                    // 如果没有处理任何内容，显示默认消息
                    if (!hasProcessedContent) {
                        // 检查是否包含错误消息
                        if (responseText.includes('error') || responseText.includes('Error')) {
                            // 尝试提取错误消息
                            let errorMsg = '处理语音时出错';
                            if (responseText.includes('message')) {
                                try {
                                    const match = responseText.match(/"message"\s*:\s*"([^"]+)"/);
                                    if (match && match[1]) {
                                        errorMsg = match[1];
                                    }
                                } catch (e) {
                                    console.error('提取错误消息失败:', e);
                                }
                            }
                            showError(errorMsg);
                        } else {
                            addMessage('抱歉，我无法处理您的语音消息。请再试一次。', false);
                        }
                    }
                } else if (isBinaryData(responseText)) {
                    // 处理二进制数据
                    console.log('检测到二进制响应数据');
                    
                    // 尝试提取可能的文本内容
                    let textContent = '';
                    try {
                        // 尝试从二进制数据中提取文本
                        const textMatch = responseText.match(/text[^\{]*\{[^\}]*"content"\s*:\s*"([^"]+)"/);
                        if (textMatch && textMatch[1]) {
                            textContent = textMatch[1];
                        }
                    } catch (e) {
                        console.error('从二进制数据提取文本失败:', e);
                    }
                    
                    if (textContent) {
                        // 如果成功提取到文本，显示为消息
                        addMessage(textContent, false);
                    } else {
                        // 否则显示默认消息
                        addMessage('我已收到您的语音消息，但无法提取完整回复。请再试一次。', false);
                    }
                } else {
                    // 尝试解析为标准JSON
                    try {
                        const response = JSON.parse(responseText);
                        console.log('音频处理成功:', response);
                        
                        if (response.success && response.data) {
                            // 添加用户消息
                            if (response.data.user_message) {
                                // 替换之前的临时消息
                                replaceLastUserMessage(response.data.user_message);
                            }
                            
                            // 添加助手消息
                            if (response.data.assistant_message) {
                                addMessage(response.data.assistant_message, false);
                            }
                        } else {
                            // 检查是否有错误消息
                            const errorMsg = response.data?.message || '处理语音时出错';
                            showError(errorMsg);
                        }
                    } catch (error) {
                        console.error('解析JSON响应失败:', error);
                        
                        // 检查是否包含错误消息
                        if (responseText.includes('error') || responseText.includes('Error')) {
                            // 尝试提取错误消息
                            let errorMsg = '处理语音时出错';
                            if (responseText.includes('message')) {
                                try {
                                    const match = responseText.match(/"message"\s*:\s*"([^"]+)"/);
                                    if (match && match[1]) {
                                        errorMsg = match[1];
                                    }
                                } catch (e) {
                                    console.error('提取错误消息失败:', e);
                                }
                            }
                            showError(errorMsg);
                        } else {
                            showError(aiChatBedrockVoiceParams.i18n.error_speech);
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('音频处理失败:', error, '状态:', status);
                
                // 检查是否为超时
                if (status === 'timeout') {
                    showError('请求超时，请稍后再试');
                } else {
                    // 尝试从响应中提取错误信息
                    let errorMsg = aiChatBedrockVoiceParams.i18n.error_speech;
                    
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMsg = response.data.message;
                            }
                        } catch (e) {
                            // 尝试使用正则表达式提取错误消息
                            const match = xhr.responseText.match(/"message"\s*:\s*"([^"]+)"/);
                            if (match && match[1]) {
                                errorMsg = match[1];
                            }
                        }
                    }
                    
                    showError(errorMsg);
                }
            },
            complete: function() {
                // 重置 UI
                $voiceIndicator.find('.ai-chat-bedrock-voice-indicator-text').text('');
            }
        });
    }
    
    // 替换最后一条用户消息
    function replaceLastUserMessage(newContent) {
        const $userMessages = $messagesContainer.find('.ai-chat-bedrock-message.user-message');
        if ($userMessages.length > 0) {
            const $lastUserMessage = $userMessages.last();
            $lastUserMessage.find('.ai-chat-bedrock-message-content').html(formatMessage(newContent));
            
            // 更新聊天历史
            if (typeof chatHistory !== 'undefined') {
                for (let i = chatHistory.length - 1; i >= 0; i--) {
                    if (chatHistory[i].role === 'user') {
                        chatHistory[i].content = newContent;
                        break;
                    }
                }
            }
        } else {
            // 如果没有找到用户消息，添加一个新的
            addMessage(newContent, true);
        }
    }
    
    // 添加消息到聊天界面
    function addMessage(content, isUser) {
        // 检查是否已经有相同的消息
        const lastMessage = $messagesContainer.find('.ai-chat-bedrock-message:last');
        if (lastMessage.length && lastMessage.find('.ai-chat-bedrock-message-content').text().trim() === content.trim()) {
            return;
        }
        
        // 创建消息元素
        const messageClass = isUser ? 'user-message' : 'ai-message';
        const avatarContent = isUser ? 
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M4 22a8 8 0 1 1 16 0H4zm8-9c-3.315 0-6-2.685-6-6s2.685-6 6-6 6 2.685 6 6-2.685 6-6 6z" fill="currentColor"/></svg>' : 
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3a3 3 0 0 1 3 3v1h1a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h1v-1a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v1h2v-1a1 1 0 0 0-1-1z" fill="currentColor"/></svg>';
        
        // 格式化内容
        const formattedContent = formatMessage(content);
        
        const $message = $(`
            <div class="ai-chat-bedrock-message ${messageClass}">
                <div class="ai-chat-bedrock-avatar">
                    ${avatarContent}
                </div>
                <div class="ai-chat-bedrock-message-content">
                    ${formattedContent}
                </div>
            </div>
        `);
        
        // 添加到消息容器
        $messagesContainer.append($message);
        
        // 滚动到底部
        scrollToBottom();
        
        // 更新聊天历史
        if (typeof chatHistory !== 'undefined') {
            chatHistory.push({ role: isUser ? 'user' : 'assistant', content: content });
        }
    }
    
    // 格式化消息
    function formatMessage(message) {
        // 处理代码块
        message = message.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        
        // 处理内联代码
        message = message.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // 处理粗体文本
        message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // 处理斜体文本
        message = message.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // 处理换行
        message = message.replace(/\n/g, '<br>');
        
        return message;
    }
    
    // 滚动到底部
    function scrollToBottom() {
        $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
    }
    
    // 显示错误消息
    function showError(message) {
        const $error = $(`
            <div class="ai-chat-bedrock-error">
                ${message}
            </div>
        `);
        
        $messagesContainer.append($error);
        scrollToBottom();
        
        // 5秒后移除错误消息
        setTimeout(() => {
            $error.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // 音频上下文和队列
    let audioContext = null;
    let audioQueue = [];
    let isAudioPlaying = false;
    const SAMPLE_RATE = 24000; // Nova Sonic默认采样率
    
    // Base64转换为Float32Array
    function base64ToFloat32Array(base64String) {
        const binaryString = atob(base64String);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        
        // 假设音频是16位PCM格式
        const int16Array = new Int16Array(bytes.buffer);
        const float32Array = new Float32Array(int16Array.length);
        
        // 转换为-1.0到1.0范围的浮点数
        for (let i = 0; i < int16Array.length; i++) {
            float32Array[i] = int16Array[i] / (int16Array[i] < 0 ? 0x8000 : 0x7FFF);
        }
        
        return float32Array;
    }
    
    // 播放音频
    function playAudio(base64Audio) {
        // 如果没有音频上下文，创建一个
        if (!audioContext) {
            try {
                audioContext = new (window.AudioContext || window.webkitAudioContext)({
                    sampleRate: SAMPLE_RATE
                });
            } catch (error) {
                console.error('创建音频上下文失败:', error);
                fallbackPlayAudio(base64Audio);
                return;
            }
        }
        
        try {
            // 将Base64转换为Float32Array
            const audioData = base64ToFloat32Array(base64Audio);
            
            // 将音频数据添加到队列
            audioQueue.push(audioData);
            
            // 如果当前没有播放音频，开始播放
            if (!isAudioPlaying) {
                processAudioQueue();
            }
        } catch (error) {
            console.error('处理音频数据失败:', error);
            fallbackPlayAudio(base64Audio);
        }
    }
    
    // 处理音频队列
    async function processAudioQueue() {
        if (audioQueue.length === 0) {
            isAudioPlaying = false;
            return;
        }
        
        isAudioPlaying = true;
        
        try {
            // 获取队列中的下一个音频数据
            const audioData = audioQueue.shift();
            
            // 播放音频数据
            await playAudioData(audioData);
            
            // 使用requestAnimationFrame来处理下一个音频，提供更平滑的播放体验
            requestAnimationFrame(() => {
                processAudioQueue();
            });
        } catch (error) {
            console.error('处理音频队列时出错:', error);
            isAudioPlaying = false;
        }
    }
    
    // 播放音频数据
    async function playAudioData(audioData) {
        return new Promise((resolve, reject) => {
            try {
                // 创建音频缓冲区
                const buffer = audioContext.createBuffer(1, audioData.length, SAMPLE_RATE);
                buffer.getChannelData(0).set(audioData);
                
                // 创建音频源
                const source = audioContext.createBufferSource();
                source.buffer = buffer;
                
                // 创建增益节点用于控制音量
                const gainNode = audioContext.createGain();
                gainNode.gain.value = 1.0; // 音量设置为1.0
                
                // 连接节点
                source.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // 播放结束后解决Promise
                source.onended = () => {
                    source.disconnect();
                    gainNode.disconnect();
                    resolve();
                };
                
                // 开始播放
                source.start(0);
                
                // 如果5秒后还没有结束，强制解决Promise
                setTimeout(() => {
                    if (source.onended) {
                        source.onended = null;
                        resolve();
                    }
                }, 5000);
            } catch (error) {
                console.error('播放音频数据失败:', error);
                reject(error);
            }
        });
    }
    
    // 备用音频播放方法
    function fallbackPlayAudio(audioData) {
        let audioBlob;
        
        // 检查输入类型
        if (typeof audioData === 'string') {
            // 如果是Base64字符串
            const binaryString = window.atob(audioData);
            const len = binaryString.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            audioBlob = new Blob([bytes], { type: 'audio/mp3' });
        } else {
            // 如果已经是Blob
            audioBlob = audioData;
        }
        
        // 创建 URL
        const audioUrl = URL.createObjectURL(audioBlob);
        
        // 创建音频元素
        const audio = new Audio(audioUrl);
        
        // 播放音频
        audio.play().catch(error => {
            console.error('备用方法播放音频失败:', error);
        });
        
        // 播放完成后释放资源
        audio.onended = function() {
            URL.revokeObjectURL(audioUrl);
        };
    }
    
    // 将 Float32 音频数据转换为 Int16
    function convertFloat32ToInt16(buffer) {
        const l = buffer.length;
        const buf = new Int16Array(l);
        
        for (let i = 0; i < l; i++) {
            buf[i] = Math.min(1, Math.max(-1, buffer[i])) * 0x7FFF;
        }
        
        return buf.buffer;
    }
    
    // 检测是否为二进制数据
    function isBinaryData(str) {
        // 检查前100个字符是否包含非打印字符
        const sample = str.substring(0, 100);
        for (let i = 0; i < sample.length; i++) {
            const code = sample.charCodeAt(i);
            // 排除常见的空白字符
            if (code < 32 && ![9, 10, 13].includes(code)) {
                return true;
            }
        }
        return false;
    }
    
    // 在文档加载完成后初始化
    $(document).ready(function() {
        // 检查是否启用了语音功能
        if (aiChatBedrockVoiceParams.voice_enabled) {
            initVoiceChat();
        }
    });
    
})(jQuery);
