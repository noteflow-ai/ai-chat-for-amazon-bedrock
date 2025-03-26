<?php
/**
 * Public chat interface for AI Chat for Amazon Bedrock
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/public/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="aichat-bedrock-container">
    <div class="aichat-bedrock-messages" id="aichat-bedrock-messages">
        <div class="aichat-bedrock-message assistant">
            <div class="aichat-bedrock-message-content">
                <p>Hello! I'm an AI assistant powered by Amazon Bedrock. How can I help you today?</p>
            </div>
        </div>
    </div>
    
    <div class="aichat-bedrock-input">
        <textarea id="aichat-bedrock-input-text" placeholder="Type your message here..."></textarea>
        <button id="aichat-bedrock-send-btn">Send</button>
    </div>
    
    <div class="aichat-bedrock-status" id="aichat-bedrock-status"></div>
</div>

<script>
jQuery(document).ready(function($) {
    var conversationId = 0;
    
    $('#aichat-bedrock-send-btn').on('click', function() {
        sendMessage();
    });
    
    $('#aichat-bedrock-input-text').on('keydown', function(e) {
        if (e.keyCode === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    function sendMessage() {
        var message = $('#aichat-bedrock-input-text').val().trim();
        if (message === '') {
            return;
        }
        
        // Add user message to chat
        addMessage('user', message);
        
        // Clear input
        $('#aichat-bedrock-input-text').val('');
        
        // Show loading indicator
        $('#aichat-bedrock-status').text('Thinking...');
        
        // Send to server
        $.ajax({
            url: aichat_bedrock_params.ajax_url,
            type: 'POST',
            data: {
                action: 'aichat_bedrock_send_message',
                nonce: aichat_bedrock_params.nonce,
                message: message,
                conversation_id: conversationId
            },
            success: function(response) {
                // Clear status
                $('#aichat-bedrock-status').text('');
                
                if (response.success) {
                    // Add assistant response
                    addMessage('assistant', response.data.response);
                    
                    // Update conversation ID
                    conversationId = response.data.conversation_id;
                } else {
                    // Show error
                    addMessage('system', 'Error: ' + response.data);
                }
                
                // Scroll to bottom
                scrollToBottom();
            },
            error: function() {
                $('#aichat-bedrock-status').text('');
                addMessage('system', 'An error occurred while processing your request.');
                scrollToBottom();
            }
        });
    }
    
    function addMessage(role, content) {
        var messageHtml = '<div class="aichat-bedrock-message ' + role + '">';
        messageHtml += '<div class="aichat-bedrock-message-content">';
        
        // Format content with paragraphs
        var paragraphs = content.split('\n');
        for (var i = 0; i < paragraphs.length; i++) {
            if (paragraphs[i].trim() !== '') {
                messageHtml += '<p>' + paragraphs[i] + '</p>';
            }
        }
        
        messageHtml += '</div></div>';
        
        $('#aichat-bedrock-messages').append(messageHtml);
        scrollToBottom();
    }
    
    function scrollToBottom() {
        var messagesContainer = document.getElementById('aichat-bedrock-messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
});
</script>
