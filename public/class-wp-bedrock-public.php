<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/public
 */

namespace AICHAT_AMAZON_BEDROCK;

class WP_Bedrock_Public {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wp-bedrock-public.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wp-bedrock-public.js', array('jquery'), $this->version, false);
        
        // Localize the script with new data
        wp_localize_script($this->plugin_name, 'aichat_bedrock_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aichat-bedrock-nonce'),
        ));
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('aichat_bedrock', array($this, 'render_chat_interface'));
    }
    
    /**
     * Render the chat interface
     */
    public function render_chat_interface($atts) {
        // Check if tables exist
        global $wpdb;
        $table_name = $wpdb->prefix . 'aichat_bedrock';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
            WP_Bedrock_Activator::activate();
        }
        
        // Get settings
        $settings = WP_Bedrock_AWS::get_model_settings();
        
        // Start output buffering
        ob_start();
        
        // Include the template
        include_once(AICHAT_BEDROCK_PLUGIN_DIR . 'public/partials/wp-bedrock-public-chat.php');
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX request to send a message to the AI
     */
    public function handle_send_message() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aichat-bedrock-nonce')) {
            wp_send_json_error('Invalid security token.');
        }
        
        // Get message
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        if (empty($message)) {
            wp_send_json_error('Message cannot be empty.');
        }
        
        // Get conversation ID if provided
        $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
        
        // Get previous messages if conversation exists
        $messages = [];
        if ($conversation_id > 0) {
            global $wpdb;
            $table_messages = $wpdb->prefix . 'aichat_bedrock_messages';
            
            $previous_messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT role, content FROM $table_messages WHERE conversation_id = %d ORDER BY id ASC",
                    $conversation_id
                ),
                ARRAY_A
            );
            
            if (!empty($previous_messages)) {
                $messages = $previous_messages;
            }
        }
        
        // Add the new user message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        // Send to Bedrock
        $response = WP_Bedrock_AWS::send_message($messages);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        // Create or update conversation
        $user_id = get_current_user_id();
        $settings = WP_Bedrock_AWS::get_model_settings();
        $model = $settings['model'];
        
        global $wpdb;
        $table_conversations = $wpdb->prefix . 'aichat_bedrock_conversations';
        $table_messages = $wpdb->prefix . 'aichat_bedrock_messages';
        
        // Check if tables exist
        $tables_exist = 
            $wpdb->get_var("SHOW TABLES LIKE '$table_conversations'") === $table_conversations &&
            $wpdb->get_var("SHOW TABLES LIKE '$table_messages'") === $table_messages;
        
        if (!$tables_exist) {
            require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
            WP_Bedrock_Activator::activate();
        }
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            $current_time = current_time('mysql');
            
            if ($conversation_id <= 0) {
                // Create new conversation
                $title = substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
                
                $wpdb->insert(
                    $table_conversations,
                    [
                        'user_id' => $user_id,
                        'title' => $title,
                        'model' => $model,
                        'created_at' => $current_time,
                        'updated_at' => $current_time
                    ],
                    ['%d', '%s', '%s', '%s', '%s']
                );
                
                $conversation_id = $wpdb->insert_id;
            } else {
                // Update existing conversation
                $wpdb->update(
                    $table_conversations,
                    ['updated_at' => $current_time],
                    ['id' => $conversation_id],
                    ['%s'],
                    ['%d']
                );
            }
            
            // Save user message
            $wpdb->insert(
                $table_messages,
                [
                    'conversation_id' => $conversation_id,
                    'role' => 'user',
                    'content' => $message,
                    'created_at' => $current_time
                ],
                ['%d', '%s', '%s', '%s']
            );
            
            // Save assistant response
            $wpdb->insert(
                $table_messages,
                [
                    'conversation_id' => $conversation_id,
                    'role' => 'assistant',
                    'content' => $response,
                    'created_at' => $current_time
                ],
                ['%d', '%s', '%s', '%s']
            );
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            wp_send_json_success([
                'response' => $response,
                'conversation_id' => $conversation_id
            ]);
            
        } catch (\Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Database error: ' . $e->getMessage());
        }
    }
}
