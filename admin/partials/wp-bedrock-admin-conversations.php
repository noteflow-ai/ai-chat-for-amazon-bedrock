<?php
/**
 * Admin conversations page for AI Chat for Amazon Bedrock
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if tables exist
global $wpdb;
$table_conversations = $wpdb->prefix . 'aichat_bedrock_conversations';
$table_messages = $wpdb->prefix . 'aichat_bedrock_messages';

$tables_exist = 
    $wpdb->get_var("SHOW TABLES LIKE '$table_conversations'") === $table_conversations &&
    $wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages;

if (!$tables_exist) {
    require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
    AICHAT_AMAZON_BEDROCK\WP_Bedrock_Activator::activate();
    
    // Check again
    $tables_exist = 
        $wpdb->get_var("SHOW TABLES LIKE '$table_conversations'") === $table_conversations &&
        $wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages;
}

// Get conversations
$conversations = [];
if ($tables_exist) {
    $conversations = $wpdb->get_results(
        "SELECT c.*, COUNT(m.id) as message_count 
         FROM $table_conversations c
         LEFT JOIN $table_messages m ON c.id = m.conversation_id
         GROUP BY c.id
         ORDER BY c.updated_at DESC"
    );
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!$tables_exist): ?>
        <div class="notice notice-error">
            <p>Error: Database tables could not be created. Please check your database permissions.</p>
        </div>
    <?php elseif (empty($conversations)): ?>
        <div class="notice notice-info">
            <p>No conversations found. Start chatting with the AI to create conversations.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>User</th>
                    <th>Model</th>
                    <th>Messages</th>
                    <th>Created</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conversations as $conversation): ?>
                    <?php 
                    $user_info = get_userdata($conversation->user_id);
                    $username = $user_info ? $user_info->user_login : 'Unknown';
                    ?>
                    <tr>
                        <td><?php echo esc_html($conversation->title); ?></td>
                        <td><?php echo esc_html($username); ?></td>
                        <td><?php echo esc_html($conversation->model); ?></td>
                        <td><?php echo esc_html($conversation->message_count); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($conversation->created_at))); ?></td>
                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($conversation->updated_at))); ?></td>
                        <td>
                            <a href="#" class="button view-conversation" data-id="<?php echo esc_attr($conversation->id); ?>">View</a>
                            <a href="#" class="button delete-conversation" data-id="<?php echo esc_attr($conversation->id); ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- View Conversation Modal -->
<div id="view-conversation-modal" style="display:none;">
    <div class="conversation-messages"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // View conversation
    $('.view-conversation').on('click', function(e) {
        e.preventDefault();
        var conversationId = $(this).data('id');
        
        // AJAX call to get conversation messages
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aichat_bedrock_get_conversation',
                conversation_id: conversationId,
                nonce: '<?php echo wp_create_nonce('aichat-bedrock-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var messages = response.data;
                    var html = '<div class="conversation-container">';
                    
                    messages.forEach(function(message) {
                        var roleClass = message.role === 'user' ? 'user-message' : 'assistant-message';
                        html += '<div class="message ' + roleClass + '">';
                        html += '<div class="message-role">' + message.role + '</div>';
                        html += '<div class="message-content">' + message.content + '</div>';
                        html += '<div class="message-time">' + message.created_at + '</div>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                    
                    $('#view-conversation-modal .conversation-messages').html(html);
                    $('#view-conversation-modal').dialog({
                        title: 'Conversation',
                        width: 800,
                        height: 600,
                        modal: true
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while fetching the conversation.');
            }
        });
    });
    
    // Delete conversation
    $('.delete-conversation').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to delete this conversation?')) {
            return;
        }
        
        var conversationId = $(this).data('id');
        var row = $(this).closest('tr');
        
        // AJAX call to delete conversation
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'aichat_bedrock_delete_conversation',
                conversation_id: conversationId,
                nonce: '<?php echo wp_create_nonce('aichat-bedrock-nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while deleting the conversation.');
            }
        });
    });
});
</script>
