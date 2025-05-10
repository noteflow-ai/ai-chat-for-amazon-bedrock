/**
 * Admin functionality for the plugin.
 *
 * @link       https://github.com/noteflow-ai
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/admin/js
 */

(function($) {
    'use strict';

    /**
     * Admin functionality.
     */
    const AIChatBedrockAdmin = {
        /**
         * Initialize the admin functionality.
         */
        init: function() {
            this.bindEvents();
            
            // Initialize test chat if on test page
            if ($('#ai-chat-bedrock-test-interface').length) {
                this.initTestChat();
            }
        },

        /**
         * Bind events for admin functionality.
         */
        bindEvents: function() {
            // Add your event bindings here
        },
        
        /**
         * Initialize test chat functionality.
         */
        initTestChat: function() {
            console.log('Initializing test chat interface');
            
            // Ensure the public JS is loaded and executed
            if (typeof ai_chat_bedrock_params === 'undefined') {
                console.error('ai_chat_bedrock_params is not defined. Public JS may not be loaded.');
                
                // Create a fallback for testing
                window.ai_chat_bedrock_params = {
                    ajax_url: ajaxurl,
                    nonce: ai_chat_bedrock_admin.nonce,
                    welcome_message: 'Hello! How can I help you today?',
                    enable_streaming: false
                };
            }
        },

        /**
         * Show a notification message.
         *
         * @param {string} message The message to show.
         * @param {string} type    The type of message (success, error, warning, info).
         */
        showNotice: function(message, type) {
            const $notice = $('<div class="notice is-dismissible"></div>');
            
            switch (type) {
                case 'success':
                    $notice.addClass('notice-success');
                    break;
                case 'error':
                    $notice.addClass('notice-error');
                    break;
                case 'warning':
                    $notice.addClass('notice-warning');
                    break;
                case 'info':
                default:
                    $notice.addClass('notice-info');
                    break;
            }
            
            $notice.append('<p>' + message + '</p>');
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            $('.wrap h1').after($notice);
            
            // Make the notice dismissible
            $('.notice-dismiss').on('click', function() {
                $(this).parent().fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    $(document).ready(function() {
        AIChatBedrockAdmin.init();
        
        // Make AIChatBedrockAdmin globally available
        window.AIChatBedrockAdmin = AIChatBedrockAdmin;
    });

})(jQuery);
