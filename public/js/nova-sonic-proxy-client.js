/**
 * Nova Sonic 代理客户端
 * 通过 REST API 与服务器端代理通信，而不是直接连接到 Bedrock
 */
class NovaSonicProxyClient {
    /**
     * 构造函数
     * @param {Object} options 选项
     */
    constructor(options) {
        this.options = Object.assign({
            nonce: '',
            audioSampleRate: 16000,
            systemPrompt: 'You are a helpful assistant.',
            voiceId: 'matthew',
            restApiUrl: '/wp-json/ai-chat-bedrock/v1',
            pollingInterval: 500
        }, options);
        
        this.clientId = null;
        this.lastEventId = -1;
        this.isRecording = false;
        this.audioContext = null;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.callbacks = {
            onMessage: [],
            onRemoveMessage: [],
            onAudio: [],
            onStatus: [],
            onError: []
        };
        this.pollingTimer = null;
        this.processingMessageShown = false;
        this.processedEventIds = new Set();
    }
    
    /**
     * 注册回调
     * @param {string} event 事件名称
     * @param {Function} callback 回调函数
     * @returns {NovaSonicProxyClient} 客户端实例
     */
    on(event, callback) {
        if (this.callbacks[event]) {
            this.callbacks[event].push(callback);
        }
        return this;
    }
    
    /**
     * 触发回调
     * @param {string} event 事件名称
     * @param {*} data 数据
     */
    triggerCallback(event, data) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in ${event} callback:`, error);
                }
            });
        }
    }
    
    /**
     * 初始化客户端
     */
    async init() {
        try {
            console.log("Initializing Nova Sonic Proxy Client");
            
            // 初始化处理中消息标记
            this.processingMessageShown = false;
            
            // 初始化已处理事件ID集合
            this.processedEventIds = new Set();
            
            // 初始化音频
            await this.initAudio();
            console.log("Audio initialized successfully");
            
            // 创建会话
            console.log("Creating session at:", this.options.restApiUrl + "/nova-sonic/session");
            await this.createSession();
            console.log("Session created successfully with client ID:", this.clientId);
            
            // 开始轮询事件
            this.startPolling();
            console.log("Event polling started");
            
            this.triggerCallback('onStatus', {
                status: 'ready',
                message: 'Nova Sonic client initialized'
            });
            
            return true;
        } catch (error) {
            console.error('Initialization error:', error);
            this.triggerCallback('onError', error);
            return false;
        }
    }
    
    /**
     * 初始化音频
     */
    async initAudio() {
        try {
            // 检查浏览器支持
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Your browser does not support audio recording');
            }
            
            // 创建音频上下文
            window.AudioContext = window.AudioContext || window.webkitAudioContext;
            this.audioContext = new AudioContext({
                sampleRate: this.options.audioSampleRate
            });
            
            // 获取麦克风权限
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    sampleRate: this.options.audioSampleRate,
                    channelCount: 1,
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });
            
            // 创建媒体录制器
            this.mediaRecorder = new MediaRecorder(stream);
            
            // 设置数据可用事件
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                    this.processAudioData(event.data);
                }
            };
            
            return true;
        } catch (error) {
            console.error('Error initializing audio:', error);
            throw error;
        }
    }
    
    /**
     * 创建会话
     */
    async createSession() {
        try {
            const response = await fetch(`${this.options.restApiUrl}/nova-sonic/session`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce || ''
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to create session: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.clientId = data.client_id;
                return data;
            } else {
                throw new Error(`Error creating session: ${data.message}`);
            }
        } catch (error) {
            console.error('Error creating session:', error);
            throw new Error(`Failed to create session: ${error.message}`);
        }
    }
    
    /**
     * 开始轮询事件
     */
    startPolling() {
        this.stopPolling();
        this.pollingTimer = setInterval(() => this.pollEvents(), this.options.pollingInterval);
    }
    
    /**
     * 停止轮询事件
     */
    stopPolling() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
            this.pollingTimer = null;
        }
    }
    
    /**
     * 轮询事件
     */
    async pollEvents() {
        if (!this.clientId) {
            return;
        }
        
        try {
            // 不再每次轮询都打印日志
            
            const response = await fetch(`${this.options.restApiUrl}/nova-sonic/events?client_id=${this.clientId}&last_id=${this.lastEventId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce || ''
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to poll events: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.events && data.events.length > 0) {
                // 只在有新事件时打印日志
                console.log(`Received ${data.events.length} events, last ID: ${data.last_id}`);
                this.lastEventId = data.last_id;
                this.processEvents(data.events);
            }
            // 不再打印"No new events"日志
        } catch (error) {
            // 只在出错时打印日志
            console.error('Error polling events:', error);
        }
    }
    
    /**
     * 处理事件
     */
    processEvents(events) {
        if (!events || events.length === 0) return;
        
        // 按ID排序事件，确保按顺序处理
        events.sort((a, b) => (a.id || 0) - (b.id || 0));
        
        // 添加去重逻辑
        const processedMessages = new Set();
        const recentMessages = new Map(); // 存储最近处理的消息及其时间戳
        const messageTimeout = 5000; // 5秒内的相同消息视为重复
        const currentTime = Date.now();
        
        for (const event of events) {
            // 跳过已处理的事件
            if (event.id && this.processedEventIds.has(event.id)) {
                console.log('Skipping already processed event:', event.id);
                continue;
            }
            
            // 记录已处理的事件ID
            if (event.id) {
                this.processedEventIds.add(event.id);
            }
            
            // 如果是文本消息，检查是否已经处理过相同内容
            if (event.type === 'text' && event.content) {
                // 创建唯一标识符（内容+角色）
                const messageKey = `${event.content}_${event.role || 'assistant'}`;
                
                // 检查是否最近处理过相同内容的消息
                if (recentMessages.has(messageKey)) {
                    const lastTime = recentMessages.get(messageKey);
                    if (currentTime - lastTime < messageTimeout) {
                        console.log('Skipping recently processed message:', event.content);
                        continue;
                    }
                }
                
                // 更新最近处理的消息
                recentMessages.set(messageKey, currentTime);
                
                // 如果是处理中的消息，只显示一次
                if (event.content === '我听到了您的声音，正在处理...') {
                    if (this.processingMessageShown) {
                        continue;
                    }
                    this.processingMessageShown = true;
                } else {
                    // 如果收到了实际响应，重置处理中的标记
                    this.processingMessageShown = false;
                }
            }
            
            this.processEvent(event);
        }
    }
    
    /**
     * 处理单个事件
     */
    processEvent(event) {
        console.log('Processing event:', event);
        
        // 处理不同类型的事件
        if (event.type === 'text') {
            this.triggerCallback('onMessage', {
                type: 'text',
                content: event.content,
                role: event.role || 'assistant',
                isProcessing: event.content === '我听到了您的声音，正在处理...'
            });
        } else if (event.type === 'audio') {
            this.triggerCallback('onAudio', {
                type: 'audio',
                data: event.data
            });
            this.playAudio(event.data);
        } else if (event.type === 'status') {
            this.triggerCallback('onStatus', {
                status: event.status,
                message: event.message
            });
        }
    }
    
    /**
     * 处理音频数据
     * @param {Blob} audioBlob 音频数据
     */
    async processAudioData(audioBlob) {
        try {
            // 将Blob转换为Base64
            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            
            reader.onloadend = async () => {
                try {
                    // 去掉Base64前缀
                    const base64Data = reader.result.split(',')[1];
                    
                    // 发送音频数据
                    await this.sendAudioData(base64Data);
                } catch (error) {
                    console.error('Error processing audio data:', error);
                    this.triggerCallback('onError', error);
                }
            };
        } catch (error) {
            console.error('Error reading audio data:', error);
            this.triggerCallback('onError', error);
        }
    }
    
    /**
     * 发送音频数据
     * @param {string} audioData Base64编码的音频数据
     */
    async sendAudioData(audioData) {
        try {
            const response = await fetch(`${this.options.restApiUrl}/nova-sonic/audio`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce || ''
                },
                body: JSON.stringify({
                    client_id: this.clientId,
                    audio_data: audioData
                }),
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to send audio data: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.status !== 'success') {
                throw new Error(`Error sending audio data: ${data.message}`);
            }
            
            return data;
        } catch (error) {
            console.error('Error sending audio data:', error);
            throw error;
        }
    }
    
    /**
     * 播放音频
     * @param {string} base64Audio Base64编码的音频数据
     */
    async playAudio(base64Audio) {
        try {
            // 将Base64转换为ArrayBuffer
            const binaryString = atob(base64Audio);
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            
            // 解码音频
            const audioBuffer = await this.audioContext.decodeAudioData(bytes.buffer);
            
            // 创建音频源
            const source = this.audioContext.createBufferSource();
            source.buffer = audioBuffer;
            
            // 连接到输出
            source.connect(this.audioContext.destination);
            
            // 播放
            source.start();
            
            return new Promise((resolve) => {
                source.onended = resolve;
            });
        } catch (error) {
            console.error('Error playing audio:', error);
        }
    }
    
    /**
     * 开始录音
     */
    startRecording() {
        if (this.isRecording) {
            return false;
        }
        
        try {
            // 恢复音频上下文
            if (this.audioContext.state === 'suspended') {
                this.audioContext.resume();
            }
            
            // 清空音频块
            this.audioChunks = [];
            
            // 开始录音
            this.mediaRecorder.start(500);
            this.isRecording = true;
            
            // 触发状态回调
            this.triggerCallback('onStatus', {
                status: 'recording',
                message: 'Recording started'
            });
            
            return true;
        } catch (error) {
            console.error('Error starting recording:', error);
            this.triggerCallback('onError', error);
            return false;
        }
    }
    
    /**
     * 停止录音
     */
    stopRecording() {
        if (!this.isRecording) {
            return false;
        }
        
        try {
            // 停止录音
            this.mediaRecorder.stop();
            this.isRecording = false;
            
            // 触发状态回调
            this.triggerCallback('onStatus', {
                status: 'processing',
                message: 'Processing audio'
            });
            
            return true;
        } catch (error) {
            console.error('Error stopping recording:', error);
            this.triggerCallback('onError', error);
            return false;
        }
    }
    
    /**
     * 更改语音
     * @param {string} voiceId 语音ID
     */
    async changeVoice(voiceId) {
        try {
            const response = await fetch(`${this.options.restApiUrl}/nova-sonic/voice`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce || ''
                },
                body: JSON.stringify({
                    client_id: this.clientId,
                    voice_id: voiceId
                }),
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to change voice: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.status === 'success') {
                this.options.voiceId = voiceId;
                this.triggerCallback('onStatus', {
                    status: 'voice_changed',
                    message: `Voice changed to ${voiceId}`
                });
                return true;
            } else {
                throw new Error(`Error changing voice: ${data.message}`);
            }
        } catch (error) {
            console.error('Error changing voice:', error);
            this.triggerCallback('onError', error);
            return false;
        }
    }
    
    /**
     * 关闭客户端
     */
    async close() {
        try {
            // 停止录音
            if (this.isRecording) {
                this.stopRecording();
            }
            
            // 停止轮询
            this.stopPolling();
            
            // 关闭会话
            if (this.clientId) {
                await fetch(`${this.options.restApiUrl}/nova-sonic/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.options.nonce || ''
                    },
                    body: JSON.stringify({
                        client_id: this.clientId
                    }),
                    credentials: 'same-origin'
                });
            }
            
            // 关闭音频上下文
            if (this.audioContext && this.audioContext.state !== 'closed') {
                await this.audioContext.close();
            }
            
            // 触发状态回调
            this.triggerCallback('onStatus', {
                status: 'disconnected',
                message: 'Client closed'
            });
            
            return true;
        } catch (error) {
            console.error('Error closing client:', error);
            return false;
        }
    }
}
