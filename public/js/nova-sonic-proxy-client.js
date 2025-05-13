/**
 * Nova Sonic 代理客户端
 * 处理与 Nova Sonic 代理服务器的通信
 */
class NovaSonicProxyClient {
    constructor(options) {
        // 记录从PHP传递的REST URL
        console.log("REST URL from PHP:", window.aiChatBedrockVoiceParams?.rest_url);
        
        // 获取REST API基础URL
        const restBaseUrl = window.aiChatBedrockVoiceParams && 
                           window.aiChatBedrockVoiceParams.rest_url ? 
                           window.aiChatBedrockVoiceParams.rest_url : 
                           '/wp-json/ai-chat-bedrock/v1';
        
        // 创建默认选项
        const defaultOptions = {
            restApiUrl: restBaseUrl,
            pollingInterval: 1000, // 轮询间隔（毫秒）
            audioSampleRate: 16000,
            audioChunkSize: 4096,
            systemPrompt: "You are a helpful assistant. Keep your responses short and engaging.",
            voiceId: "matthew"
        };
        
        // 合并选项
        this.options = {...defaultOptions, ...options};
        
        console.log("Final REST URL:", this.options.restApiUrl);
        
        this.clientId = null;
        this.lastEventId = -1;
        this.pollingTimer = null;
        this.audioContext = null;
        this.mediaRecorder = null;
        this.isRecording = false;
        this.audioQueue = [];
        this.audioPlayer = new Audio();
        this.currentVoice = this.options.voiceId;
        
        this.callbacks = {
            onMessage: () => {},
            onAudio: () => {},
            onStatus: () => {},
            onError: () => {},
        };
    }
    
    /**
     * 设置回调函数
     */
    on(event, callback) {
        if (typeof this.callbacks[event] !== 'undefined') {
            this.callbacks[event] = callback;
        }
        return this;
    }
    
    /**
     * 触发回调函数
     */
    triggerCallback(event, data) {
        if (typeof this.callbacks[event] === 'function') {
            this.callbacks[event](data);
        }
    }
    
    /**
     * 初始化客户端
     */
    async init() {
        try {
            console.log("Initializing Nova Sonic Proxy Client");
            
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
     * 创建会话
     */
    async createSession() {
        try {
            console.log("Creating session at:", this.options.restApiUrl + "/nova-sonic/session");
            
            const response = await fetch(this.options.restApiUrl + "/nova-sonic/session", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce || ''
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                console.error("Session creation failed:", response.status, response.statusText);
                throw new Error(`Failed to create session: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log("Session created:", data);
            
            if (data.status === 'success' && data.client_id) {
                this.clientId = data.client_id;
                this.triggerCallback('onStatus', {
                    status: 'connected',
                    message: 'Session created successfully'
                });
                return data;
            } else {
                throw new Error('Invalid response from server');
            }
        } catch (error) {
            console.error('Error creating session:', error);
            throw error;
        }
    }
    
    /**
     * 开始轮询事件
     */
    startPolling() {
        if (this.pollingTimer) {
            clearInterval(this.pollingTimer);
        }
        
        this.pollingTimer = setInterval(() => {
            this.pollEvents();
        }, this.options.pollingInterval);
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
            console.log(`Polling events for client ${this.clientId}, last ID: ${this.lastEventId}`);
            
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
                console.log(`Received ${data.events.length} events, last ID: ${data.last_id}`);
                this.lastEventId = data.last_id;
                this.processEvents(data.events);
            } else {
                console.log("No new events");
            }
        } catch (error) {
            console.error('Error polling events:', error);
            // 不要触发错误回调，以免影响用户体验
        }
    }
    
    /**
     * 处理事件
     */
    processEvents(events) {
        for (const event of events) {
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
                role: event.role || 'assistant'
            });
        } else if (event.type === 'audio') {
            this.triggerCallback('onAudio', {
                type: 'audio',
                content: event.data
            });
            this.playAudio(event.data);
        } else if (event.type === 'binary') {
            // 处理二进制数据
            console.log('Received binary data');
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
            
            // 处理录制数据
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
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
     * 开始录音
     */
    startRecording() {
        if (!this.mediaRecorder || this.isRecording) {
            return false;
        }
        
        this.isRecording = true;
        this.triggerCallback('onStatus', {
            status: 'recording',
            message: 'Recording started'
        });
        
        // 每 100ms 获取一次数据
        this.mediaRecorder.start(100);
        return true;
    }
    
    /**
     * 停止录音
     */
    stopRecording() {
        if (!this.mediaRecorder || !this.isRecording) {
            return false;
        }
        
        this.isRecording = false;
        this.triggerCallback('onStatus', {
            status: 'processing',
            message: 'Processing audio'
        });
        
        this.mediaRecorder.stop();
        return true;
    }
    
    /**
     * 处理音频数据
     */
    processAudioData(audioBlob) {
        const reader = new FileReader();
        reader.readAsDataURL(audioBlob);
        reader.onloadend = async () => {
            const base64data = reader.result.split(',')[1];
            
            try {
                await this.sendAudioData(base64data);
            } catch (error) {
                console.error('Error processing audio data:', error);
                this.triggerCallback('onError', error);
            }
        };
    }
    
    /**
     * 发送音频数据
     */
    async sendAudioData(audioData) {
        if (!this.clientId) {
            throw new Error('No active session');
        }
        
        try {
            const response = await fetch(this.options.restApiUrl + "/nova-sonic/audio", {
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
            
            return await response.json();
        } catch (error) {
            console.error('Error sending audio data:', error);
            throw error;
        }
    }
    
    /**
     * 更改语音
     */
    async changeVoice(voice) {
        if (voice === this.currentVoice) {
            return;
        }
        
        if (!this.clientId) {
            throw new Error('No active session');
        }
        
        try {
            const response = await fetch(this.options.restApiUrl + "/nova-sonic/voice", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.options.nonce || ''
                },
                body: JSON.stringify({
                    client_id: this.clientId,
                    voice: voice
                }),
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to change voice: ${response.statusText}`);
            }
            
            const data = await response.json();
            this.currentVoice = voice;
            
            this.triggerCallback('onStatus', {
                status: 'voice_changed',
                message: `Voice changed to ${voice}`
            });
            
            return data;
        } catch (error) {
            console.error('Error changing voice:', error);
            throw error;
        }
    }
    
    /**
     * 播放音频
     */
    playAudio(audioData) {
        try {
            // 解码Base64音频数据
            const binaryString = atob(audioData);
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }
            
            // 创建音频上下文
            if (!this.audioContext) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)({
                    sampleRate: 24000
                });
            }
            
            // 解码音频数据
            this.audioContext.decodeAudioData(bytes.buffer, (buffer) => {
                // 创建音频源
                const source = this.audioContext.createBufferSource();
                source.buffer = buffer;
                source.connect(this.audioContext.destination);
                source.start(0);
            }, (error) => {
                console.error('Error decoding audio data:', error);
            });
        } catch (error) {
            console.error('Error playing audio:', error);
        }
    }
    
    /**
     * 关闭连接
     */
    async close() {
        this.stopPolling();
        
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
        }
        
        if (this.clientId) {
            try {
                await fetch(this.options.restApiUrl + "/nova-sonic/close", {
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
                
                this.clientId = null;
            } catch (error) {
                console.error('Error closing session:', error);
            }
        }
    }
}
