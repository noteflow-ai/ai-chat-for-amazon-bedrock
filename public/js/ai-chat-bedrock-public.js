(function( $ ) {
	'use strict';

	/**
	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.
	 */
	
	$(document).ready(function() {
		// Chat history storage
		let chatHistory = [];
		
		// DOM elements
		const $chatContainer = $('.ai-chat-bedrock-container');
		const $messagesContainer = $('.ai-chat-bedrock-messages');
		const $form = $('.ai-chat-bedrock-form');
		const $textarea = $('.ai-chat-bedrock-textarea');
		const $submitButton = $('.ai-chat-bedrock-submit');
		const $clearButton = $('.ai-chat-bedrock-clear');
		
		// Check if elements exist
		if (!$chatContainer.length) return;
		
		// 初始化时，将欢迎消息添加到聊天历史中
		if ($messagesContainer.find('.ai-chat-bedrock-welcome-message').length) {
			const welcomeMessage = $messagesContainer.find('.ai-chat-bedrock-welcome-message .ai-chat-bedrock-message-content').text().trim();
			if (welcomeMessage && chatHistory.length === 0) {
				console.log('Adding welcome message to history:', welcomeMessage);
				chatHistory.push({ role: 'assistant', content: welcomeMessage });
			}
		}
		
		// Function to add a message to the chat
		function addMessage(content, isUser = false) {
			const messageClass = isUser ? 'user-message' : 'ai-message';
			const avatarContent = isUser ? 
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M4 22a8 8 0 1 1 16 0H4zm8-9c-3.315 0-6-2.685-6-6s2.685-6 6-6 6 2.685 6 6-2.685 6-6 6z" fill="currentColor"/></svg>' : 
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3a3 3 0 0 1 3 3v1h1a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h1v-1a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v1h2v-1a1 1 0 0 0-1-1z" fill="currentColor"/></svg>';
			
			// Format the content to handle markdown-like syntax
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
			
			// 不再移除欢迎消息，而是将其转换为普通消息
			if ($messagesContainer.find('.ai-chat-bedrock-welcome-message').length) {
				// 将欢迎消息转换为普通消息
				const $welcomeMessage = $messagesContainer.find('.ai-chat-bedrock-welcome-message');
				$welcomeMessage.removeClass('ai-chat-bedrock-welcome-message');
			}
			
			$messagesContainer.append($message);
			scrollToBottom();
			
			// Add to chat history
			if (isUser) {
				chatHistory.push({ role: 'user', content: content });
			} else {
				chatHistory.push({ role: 'assistant', content: content });
			}
		}
		
		// Function to format message with markdown-like syntax
		function formatMessage(message) {
			// Handle code blocks
			message = message.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
			
			// Handle inline code
			message = message.replace(/`([^`]+)`/g, '<code>$1</code>');
			
			// Handle bold text
			message = message.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
			
			// Handle italic text
			message = message.replace(/\*(.*?)\*/g, '<em>$1</em>');
			
			// Handle line breaks
			message = message.replace(/\n/g, '<br>');
			
			return message;
		}
		
		// Function to show typing indicator
		function showTypingIndicator() {
			const $typing = $(`
				<div class="ai-chat-bedrock-message ai-message ai-chat-bedrock-typing">
					<div class="ai-chat-bedrock-avatar">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3a3 3 0 0 1 3 3v1h1a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h1v-1a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v1h2v-1a1 1 0 0 0-1-1z" fill="currentColor"/></svg>
					</div>
					<div class="ai-chat-bedrock-typing-indicator">
						<div class="ai-chat-bedrock-typing-dot"></div>
						<div class="ai-chat-bedrock-typing-dot"></div>
						<div class="ai-chat-bedrock-typing-dot"></div>
					</div>
				</div>
			`);
			
			$messagesContainer.append($typing);
			scrollToBottom();
			
			return $typing;
		}
		
		// Function to remove typing indicator
		function removeTypingIndicator($typing) {
			if ($typing) {
				$typing.remove();
			}
		}
		
		// Function to scroll to bottom of messages
		function scrollToBottom() {
			$messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
		}
		
		// Function to show error message
		function showError(message) {
			const $error = $(`
				<div class="ai-chat-bedrock-error">
					${message}
				</div>
			`);
			
			$messagesContainer.append($error);
			scrollToBottom();
			
			// Remove error after 5 seconds
			setTimeout(() => {
				$error.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		}
		
		// Function to handle form submission
		function handleSubmit(e) {
			e.preventDefault();
			
			const message = $textarea.val().trim();
			if (!message) return;
			
			// Disable form while processing
			$textarea.prop('disabled', true);
			$submitButton.prop('disabled', true);
			
			// Add user message to chat
			addMessage(message, true);
			
			// Clear textarea
			$textarea.val('');
			
			// Show typing indicator
			const $typing = showTypingIndicator();
			
			// Get response from server
			const options = {
				url: ai_chat_bedrock_params.ajax_url,
				method: 'POST',
				data: {
					action: 'ai_chat_bedrock_message',
					nonce: ai_chat_bedrock_params.nonce,
					message: message,
					history: JSON.stringify(chatHistory)
				}
			};
			
			// 添加调试日志
			console.log('Form submitted, message:', message);
			
			// 直接使用常规AJAX请求，不使用流式处理
			handleRegularResponse(options, $typing);
		}
		
		// Function to handle streaming response
		function handleStreamingResponse(options, $typing) {
			// Create URL with parameters for POST request
			const formData = new FormData();
			for (const key in options.data) {
				formData.append(key, options.data[key]);
			}
			
			// Add streaming flag
			formData.append('streaming', '1');
			
			// First make a POST request to initiate the streaming
			$.ajax({
				url: options.url,
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (!response.success) {
						// Remove typing indicator
						removeTypingIndicator($typing);
						
						// Show error message
						showError(response.data?.message || 'Error initiating streaming connection');
						
						// Re-enable form
						$textarea.prop('disabled', false);
						$submitButton.prop('disabled', false);
						return;
					}
					
					// Now create EventSource for streaming
					const params = new URLSearchParams();
					for (const key in options.data) {
						params.append(key, options.data[key]);
					}
					params.append('streaming', '1');
					
					// Create EventSource
					const eventSourceUrl = `${options.url}?${params.toString()}`;
					console.log('Creating EventSource with URL:', eventSourceUrl);
					const eventSource = new EventSource(eventSourceUrl);
					
					let responseContent = '';
					let hasStarted = false;
					
					// Handle incoming messages
					eventSource.onmessage = function(event) {
						try {
							console.log('Received event data:', event.data);
							const data = JSON.parse(event.data);
							console.log('Parsed event data:', data);
							
							// Check if this is the end marker
							if (data.end) {
								console.log('Received end marker, closing connection');
								eventSource.close();
								
								// Remove typing indicator if still present
								removeTypingIndicator($typing);
						
								// Add complete message if we haven't started yet
								if (!hasStarted && responseContent) {
									console.log('Adding complete message at end:', responseContent);
									addMessage(responseContent);
								}
								
								// Re-enable form
								$textarea.prop('disabled', false);
								$submitButton.prop('disabled', false);
								$textarea.focus();
								return;
							}
							
							// Check for error
							if (data.error) {
								console.error('Error from server:', data.error);
								eventSource.close();
								
								// Remove typing indicator
								removeTypingIndicator($typing);
								
								// Show error message
								showError(data.error);
								
								// Re-enable form
								$textarea.prop('disabled', false);
								$submitButton.prop('disabled', false);
								$textarea.focus();
								return;
							}
							
							// Append content
							if (data.content) {
								console.log('Received content chunk:', data.content);
								
								// If this is the first chunk, remove typing indicator and add message
								if (!hasStarted) {
									console.log('First chunk received, adding message');
									removeTypingIndicator($typing);
									addMessage(data.content);
									hasStarted = true;
									responseContent = data.content;
								} else {
									// Append to existing message
									responseContent += data.content;
									console.log('Appending to existing message, new length:', responseContent.length);
									
									// Update the last message
									const $lastMessage = $messagesContainer.find('.ai-chat-bedrock-message:last-child .ai-chat-bedrock-message-content');
									$lastMessage.html(formatMessage(responseContent));
									scrollToBottom();
									
									// Update chat history
									if (chatHistory.length > 0) {
										chatHistory[chatHistory.length - 1].content = responseContent;
									}
								}
							}
						} catch (error) {
							console.error('Error parsing streaming response:', error, event.data);
						}
					};
					
					// Handle errors
					eventSource.onerror = function(error) {
						console.error('EventSource error:', error);
						eventSource.close();
						
						// Remove typing indicator
						removeTypingIndicator($typing);
						
						// Show error message
						showError('Error connecting to the server. Falling back to regular request.');
						
						// Fall back to regular AJAX
						handleRegularResponse(options, null);
						
						// Re-enable form
						$textarea.prop('disabled', false);
						$submitButton.prop('disabled', false);
					};
				},
				error: function(xhr, status, error) {
					// Remove typing indicator
					removeTypingIndicator($typing);
					
					// Show error message
					showError('Error: ' + (error || 'Could not connect to the server.'));
					
					// Re-enable form
					$textarea.prop('disabled', false);
					$submitButton.prop('disabled', false);
				}
			});
		}
		
		// Function to handle regular AJAX response
		function handleRegularResponse(options, $typing) {
			console.log('Making regular AJAX request with options:', options);
			
			$.ajax(options)
				.done(function(response) {
					// Remove typing indicator
					removeTypingIndicator($typing);
					
					console.log('Received AJAX response:', response);
					
					if (response.success) {
						console.log('Response success, message:', response.data?.message);
						// Add AI response to chat
						addMessage(response.data.message);
					} else {
						console.error('Response error:', response.data?.message);
						// Show error message
						showError(response.data?.message || 'An error occurred while processing your request.');
					}
				})
				.fail(function(xhr, status, error) {
					// Remove typing indicator
					removeTypingIndicator($typing);
					
					// Show error message
					showError('Error: ' + (error || 'Could not connect to the server.'));
				})
				.always(function() {
					// Re-enable form
					$textarea.prop('disabled', false);
					$submitButton.prop('disabled', false);
					$textarea.focus();
				});
		}
		
		// Function to clear chat history
		function clearChat() {
			// Confirm before clearing
			if (!confirm('Are you sure you want to clear the chat history?')) {
				return;
			}
			
			// Clear chat history array
			chatHistory = [];
			
			// Clear messages from DOM
			$messagesContainer.empty();
			
			// 从参数中获取欢迎消息
			const welcomeMessage = ai_chat_bedrock_params.welcome_message || 'Hello! How can I help you today?';
			$messagesContainer.html(`
				<div class="ai-chat-bedrock-welcome-message">
					<div class="ai-chat-bedrock-message ai-message">
						<div class="ai-chat-bedrock-avatar">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16zm0 3a3 3 0 0 1 3 3v1h1a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1h1v-1a3 3 0 0 1 3-3zm0 2a1 1 0 0 0-1 1v1h2v-1a1 1 0 0 0-1-1z" fill="currentColor"/></svg>
						</div>
						<div class="ai-chat-bedrock-message-content">
							${welcomeMessage}
						</div>
					</div>
				</div>
			`);
			
			// 将欢迎消息添加到聊天历史中
			chatHistory.push({ role: 'assistant', content: welcomeMessage });
			console.log('Added welcome message to history after clearing:', welcomeMessage);
			
			// Send clear history request to server
			$.ajax({
				url: ai_chat_bedrock_params.ajax_url,
				method: 'POST',
				data: {
					action: 'ai_chat_bedrock_clear_history',
					nonce: ai_chat_bedrock_params.nonce
				}
			});
		}
		
		// Handle textarea enter key (submit on Enter, new line on Shift+Enter)
		$textarea.on('keydown', function(e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				$form.submit();
			}
		});
		
		// Handle form submission
		$form.on('submit', handleSubmit);
		
		// Handle clear button click
		$clearButton.on('click', clearChat);
	});

})( jQuery );
