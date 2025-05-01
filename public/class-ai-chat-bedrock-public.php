<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    AI_Chat_Bedrock
 * @subpackage AI_Chat_Bedrock/public
 * @author     Your Name <email@example.com>
 */
class AI_Chat_Bedrock_Public {

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
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/ai-chat-bedrock-public.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/ai-chat-bedrock-public.js', array( 'jquery' ), $this->version, false );
		
		wp_localize_script( $this->plugin_name, 'ai_chat_bedrock_params', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'ai_chat_bedrock_nonce' ),
		) );
	}

	/**
	 * Display the chat interface.
	 *
	 * @since    1.0.0
	 * @param    array    $atts    Shortcode attributes.
	 * @return   string            The chat interface HTML.
	 */
	public function display_chat_interface( $atts ) {
		// Enqueue styles and scripts
		$this->enqueue_styles();
		$this->enqueue_scripts();
		
		// Get settings
		$options = get_option( 'ai_chat_bedrock_settings' );
		$chat_title = isset( $options['chat_title'] ) ? $options['chat_title'] : 'Chat with AI';
		
		// Parse shortcode attributes
		$atts = shortcode_atts(
			array(
				'title' => $chat_title,
				'placeholder' => 'Type your message here...',
				'button_text' => 'Send',
				'clear_text' => 'Clear Chat',
				'width' => '100%',
				'height' => '500px',
			),
			$atts,
			'ai_chat_bedrock'
		);
		
		// Start output buffering
		ob_start();
		
		// Include the template
		include plugin_dir_path( __FILE__ ) . 'partials/ai-chat-bedrock-public-display.php';
		
		// Return the buffered content
		return ob_get_clean();
	}
}
