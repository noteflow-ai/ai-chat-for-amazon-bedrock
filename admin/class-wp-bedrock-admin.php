<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    AI_Chat_For_Amazon_Bedrock
 * @subpackage AI_Chat_For_Amazon_Bedrock/admin
 */

namespace AICHAT_AMAZON_BEDROCK;

class WP_Bedrock_Admin {

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
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wp-bedrock-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wp-bedrock-admin.js', array('jquery'), $this->version, false);
        
        // Localize the script with new data
        wp_localize_script($this->plugin_name, 'aichat_bedrock_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aichat-bedrock-nonce'),
        ));
    }
    
    /**
     * Add menu items to the admin dashboard
     */
    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page(
            'AI Chat', 
            'AI Chat', 
            'manage_options', 
            'ai-chat-for-amazon-bedrock', 
            array($this, 'display_plugin_dashboard_page'),
            'dashicons-format-chat',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'ai-chat-for-amazon-bedrock',
            'Settings',
            'Settings',
            'manage_options',
            'ai-chat-for-amazon-bedrock-settings',
            array($this, 'display_plugin_settings_page')
        );
        
        // Conversations submenu
        add_submenu_page(
            'ai-chat-for-amazon-bedrock',
            'Conversations',
            'Conversations',
            'manage_options',
            'ai-chat-for-amazon-bedrock-conversations',
            array($this, 'display_plugin_conversations_page')
        );
    }
    
    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('admin.php?page=ai-chat-for-amazon-bedrock-settings') . '">' . __('Settings', 'ai-chat-for-amazon-bedrock') . '</a>',
        );
        return array_merge($settings_link, $links);
    }
    
    /**
     * Render the dashboard page
     */
    public function display_plugin_dashboard_page() {
        include_once('partials/wp-bedrock-admin-dashboard.php');
    }
    
    /**
     * Render the settings page
     */
    public function display_plugin_settings_page() {
        include_once('partials/wp-bedrock-admin-settings.php');
    }
    
    /**
     * Render the conversations page
     */
    public function display_plugin_conversations_page() {
        include_once('partials/wp-bedrock-admin-conversations.php');
    }
    
    /**
     * Process form submissions
     */
    public function process_settings_form() {
        if (isset($_POST['action']) && $_POST['action'] == 'aichat_bedrock_save_settings') {
            if (!isset($_POST['aichat_bedrock_settings_nonce']) || !wp_verify_nonce($_POST['aichat_bedrock_settings_nonce'], 'aichat_bedrock_settings_nonce')) {
                wp_die('Security check failed');
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'aichat_bedrock';
            
            // Create table if it doesn't exist
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                require_once AICHAT_BEDROCK_PLUGIN_DIR . 'includes/class-wp-bedrock-activator.php';
                AICHAT_AMAZON_BEDROCK\WP_Bedrock_Activator::activate();
                
                // Check again if table was created
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                if (!$table_exists) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error is-dismissible"><p>Error: Could not create database tables. Please check your database permissions.</p></div>';
                    });
                    return;
                }
            }
            
            // Save settings
            $current_time = current_time('mysql');
            $settings = [
                'aws_region' => sanitize_text_field($_POST['aws_region']),
                'aws_access_key' => sanitize_text_field($_POST['aws_access_key']),
                'aws_secret_key' => sanitize_text_field($_POST['aws_secret_key']),
                'default_model' => sanitize_text_field($_POST['default_model']),
                'max_tokens' => intval($_POST['max_tokens']),
                'temperature' => floatval($_POST['temperature']),
                'system_prompt' => sanitize_textarea_field($_POST['system_prompt']),
            ];
            
            $success = true;
            foreach ($settings as $option_name => $option_value) {
                // Check if option exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE option_name = %s",
                    $option_name
                ));
                
                if ($exists) {
                    // Update existing option
                    $result = $wpdb->update(
                        $table_name,
                        [
                            'option_value' => $option_value,
                            'updated_at' => $current_time
                        ],
                        ['option_name' => $option_name],
                        ['%s', '%s'],
                        ['%s']
                    );
                } else {
                    // Insert new option
                    $result = $wpdb->insert(
                        $table_name,
                        [
                            'option_name' => $option_name,
                            'option_value' => $option_value,
                            'created_at' => $current_time,
                            'updated_at' => $current_time
                        ],
                        ['%s', '%s', '%s', '%s']
                    );
                }
                
                if ($result === false) {
                    $success = false;
                    error_log('Database error saving setting: ' . $option_name . ' - ' . $wpdb->last_error);
                }
            }
            
            if ($success) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>Error saving settings. Please check the error log.</p></div>';
                });
            }
        }
    }
}
