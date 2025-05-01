/**
 * WordPress Bedrock Chat Plugin - Response Handler
 * 
 * Handles streaming responses from Amazon Bedrock API
 */

class WpBedrockResponseHandler {
    constructor(options) {
        this.ajaxUrl = options.ajaxUrl || '';
        this.nonce = options.nonce || '';
        this.onMessage = options.onMessage || function() {};
        this.onError = options.onError || function() {};
        this.onComplete = options.onComplete || function() {};
        this.debug = options.debug || false;
    }

    /**
     * Log debug messages if debug mode is enabled
     */
    log(...args) {
        if (this.debug) {
            console.log('[WP Bedrock]', ...args);
        }
    }

    /**
     * Start streaming response from the server
     * 
     * @param {Object} data - The data to send to the server
     */
    startStreaming(data) {
        this.log('Starting streaming request with data:', data);
        
        // Extract nonce from URL if not provided
        if (!this.nonce && window.location.href.indexOf('nonce=') > -1) {
            const urlParams = new URLSearchParams(window.location.search);
            this.nonce = urlParams.get('nonce');
        }

        // Ensure we have required data
        if (!data.message) {
            this.onError('No message provided');
            return;
        }

        // Create FormData for POST request
        const formData = new FormData();
        formData.append('action', 'ai_chat_bedrock_message');
        formData.append('nonce', this.nonce);
        formData.append('message', data.message);
        
        if (data.history) {
            formData.append('history', JSON.stringify(data.history));
        }
        
        // Add streaming flag
        formData.append('streaming', '1');

        // Create URL for EventSource
        const params = new URLSearchParams();
        params.append('action', 'ai_chat_bedrock_message');
        params.append('nonce', this.nonce);
        params.append('message', data.message);
        params.append('streaming', '1');
        
        if (data.history) {
            params.append('history', JSON.stringify(data.history));
        }

        // First make a POST request to initiate the streaming session
        fetch(this.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            this.log('POST request successful, establishing EventSource connection');
            
            // Now establish the EventSource connection
            const eventSourceUrl = `${this.ajaxUrl}?${params.toString()}`;
            const eventSource = new EventSource(eventSourceUrl);
            
            let responseContent = '';
            
            eventSource.onmessage = (event) => {
                try {
                    this.log('Received event data:', event.data);
                    const data = JSON.parse(event.data);
                    
                    // Check if this is the end marker
                    if (data.end) {
                        this.log('Received end marker, closing connection');
                        eventSource.close();
                        this.onComplete(responseContent);
                        return;
                    }
                    
                    // Process content
                    if (data.content) {
                        responseContent += data.content;
                        this.onMessage(data.content, responseContent);
                    }
                } catch (error) {
                    this.log('Error parsing event data:', error, event.data);
                    this.onError(`Error parsing response: ${error.message}`);
                }
            };
            
            eventSource.onerror = (error) => {
                this.log('EventSource error:', error);
                eventSource.close();
                this.onError('Connection error. Please try again.');
            };
        })
        .catch(error => {
            this.log('Fetch error:', error);
            this.onError(`Request failed: ${error.message}`);
        });
    }

    /**
     * Make a regular AJAX request (non-streaming)
     * 
     * @param {Object} data - The data to send to the server
     */
    makeRequest(data) {
        this.log('Making regular AJAX request with data:', data);
        
        // Create request data
        const requestData = {
            action: 'ai_chat_bedrock_message',
            nonce: this.nonce,
            message: data.message
        };
        
        if (data.history) {
            requestData.history = JSON.stringify(data.history);
        }
        
        // Make AJAX request
        jQuery.ajax({
            url: this.ajaxUrl,
            method: 'POST',
            data: requestData,
            success: (response) => {
                if (response.success) {
                    this.onMessage(response.data.message, response.data.message);
                    this.onComplete(response.data.message);
                } else {
                    this.onError(response.data.message || 'An error occurred');
                }
            },
            error: (xhr, status, error) => {
                this.onError(`Request failed: ${error}`);
            }
        });
    }
}
