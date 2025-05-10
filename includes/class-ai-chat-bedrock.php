<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/includes
 * @author     Glay <https://github.com/noteflow-ai>
 */
class AI_Chat_Bedrock {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      AI_Chat_Bedrock_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'AI_CHAT_BEDROCK_VERSION' ) ) {
			$this->version = AI_CHAT_BEDROCK_VERSION;
		} else {
			$this->version = '1.0.7';
		}
		$this->plugin_name = 'ai-chat-for-amazon-bedrock';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_mcp_hooks();
		$this->init_wp_mcp_server();
		
		// Register AJAX handlers
		add_action('wp_ajax_ai_chat_bedrock_save_option', array($this, 'ajax_save_option'));
	}
	
	/**
	 * AJAX handler for saving a single option.
	 *
	 * @since    1.0.7
	 */
	public function ajax_save_option() {
		// Check permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied', 'ai-chat-for-amazon-bedrock')), 403);
		}

		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ai_chat_bedrock_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'ai-chat-for-amazon-bedrock')), 403);
		}

		// Get option details
		$option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
		$option_value = isset($_POST['option_value']) ? sanitize_text_field($_POST['option_value']) : '';

		if (empty($option_name)) {
			wp_send_json_error(array('message' => __('Option name is required', 'ai-chat-for-amazon-bedrock')), 400);
		}

		// Save option
		update_option($option_name, $option_value);
		wp_send_json_success(array('message' => __('Option saved successfully', 'ai-chat-for-amazon-bedrock')));
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - AI_Chat_Bedrock_Loader. Orchestrates the hooks of the plugin.
	 * - AI_Chat_Bedrock_i18n. Defines internationalization functionality.
	 * - AI_Chat_Bedrock_Admin. Defines all hooks for the admin area.
	 * - AI_Chat_Bedrock_Public. Defines all hooks for the public side of the site.
	 * - AI_Chat_Bedrock_AWS. Handles AWS Bedrock API integration.
	 * - AI_Chat_Bedrock_MCP_Client. Handles MCP client functionality.
	 * - AI_Chat_Bedrock_MCP_Integration. Integrates MCP client with the plugin.
	 * - AI_Chat_Bedrock_WP_MCP_Server. WordPress MCP server implementation.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-ai-chat-bedrock-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-ai-chat-bedrock-public.php';

		/**
		 * The class responsible for AWS Bedrock API integration.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-aws.php';

		/**
		 * The class responsible for MCP client functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-mcp-client.php';

		/**
		 * The class responsible for MCP integration with the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-mcp-integration.php';

		/**
		 * The class responsible for WordPress MCP server implementation.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-ai-chat-bedrock-wp-mcp-server.php';

		$this->loader = new AI_Chat_Bedrock_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the AI_Chat_Bedrock_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new AI_Chat_Bedrock_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$plugin_admin = new AI_Chat_Bedrock_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_plugin_admin_menu' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		
		// Add this line to inject templates into admin head
		$this->loader->add_action( 'admin_head', $plugin_admin, 'add_mcp_templates_to_admin_head' );
		
		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_ai_chat_bedrock_message', $plugin_admin, 'handle_chat_message' );
		$this->loader->add_action( 'wp_ajax_nopriv_ai_chat_bedrock_message', $plugin_admin, 'handle_chat_message' );
		$this->loader->add_action( 'wp_ajax_ai_chat_bedrock_clear_history', $plugin_admin, 'clear_chat_history' );
		$this->loader->add_action( 'wp_ajax_ai_chat_bedrock_save_option', $plugin_admin, 'save_option' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new AI_Chat_Bedrock_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		
		// Register shortcode
		$this->loader->add_shortcode( 'ai_chat_bedrock', $plugin_public, 'display_chat_interface' );
		
		// Register AJAX handlers for public-facing functionality
		$this->loader->add_action( 'wp_ajax_ai_chat_bedrock_message', $plugin_public, 'handle_chat_message' );
		$this->loader->add_action( 'wp_ajax_nopriv_ai_chat_bedrock_message', $plugin_public, 'handle_chat_message' );
		$this->loader->add_action( 'wp_ajax_ai_chat_bedrock_clear_history', $plugin_public, 'clear_chat_history' );
		$this->loader->add_action( 'wp_ajax_nopriv_ai_chat_bedrock_clear_history', $plugin_public, 'clear_chat_history' );
		$this->loader->add_action( 'wp_ajax_ai_chat_bedrock_tool_results', $plugin_public, 'handle_tool_results' );
		$this->loader->add_action( 'wp_ajax_nopriv_ai_chat_bedrock_tool_results', $plugin_public, 'handle_tool_results' );
	}

	/**
	 * Register all of the hooks related to the MCP functionality
	 * of the plugin.
	 *
	 * @since    1.0.7
	 * @access   private
	 */
	private function define_mcp_hooks() {
		$plugin_mcp = new AI_Chat_Bedrock_MCP_Integration();
		$plugin_mcp->register_hooks($this->loader);
	}

	/**
	 * Initialize the WordPress MCP server.
	 *
	 * @since    1.0.7
	 * @access   private
	 */
	private function init_wp_mcp_server() {
		// Only initialize if MCP is enabled
		if (get_option('ai_chat_bedrock_enable_mcp', false)) {
			new AI_Chat_Bedrock_WP_MCP_Server();
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    AI_Chat_Bedrock_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
