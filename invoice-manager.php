<?php
/**
 * Plugin Name:       WP Invoice Manager
 * Plugin URI:        https://github.com/your-repo/wp-invoice-manager
 * Description:       A professional OOP-based invoice management system for WordPress.
 * Version:           1.0.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp-invoice-manager
 * Domain Path:       /languages
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WP_IM_VERSION', '1.0.0' );
define( 'WP_IM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_IM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_IM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class (Singleton)
 */
final class WP_Invoice_Manager {

	/**
	 * Singleton instance
	 *
	 * @var WP_Invoice_Manager
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return WP_Invoice_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – load dependencies and hooks
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->define_hooks();
	}

	/**
	 * Load required class files
	 */
	private function load_dependencies() {
		require_once WP_IM_PLUGIN_DIR . 'includes/class-wp-im-activator.php';
		require_once WP_IM_PLUGIN_DIR . 'includes/class-wp-im-deactivator.php';
		require_once WP_IM_PLUGIN_DIR . 'includes/class-wp-im-post-type.php';
		require_once WP_IM_PLUGIN_DIR . 'includes/class-wp-im-invoice.php';
		require_once WP_IM_PLUGIN_DIR . 'includes/class-wp-im-pdf-generator.php';
		require_once WP_IM_PLUGIN_DIR . 'admin/class-wp-im-admin.php';
	}

	/**
	 * Register activation/deactivation hooks and init
	 */
	private function define_hooks() {
		register_activation_hook( __FILE__, array( 'WP_IM_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'WP_IM_Deactivator', 'deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Initialize plugin components
	 */
	public function init() {
		// Register CPT
		$post_type = new WP_IM_Post_Type();
		$post_type->register();

		// Boot admin
		if ( is_admin() ) {
			$admin = new WP_IM_Admin();
			$admin->init();
		}
	}

	/**
	 * Load translation files
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'wp-invoice-manager',
			false,
			dirname( WP_IM_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}

// Bootstrap
WP_Invoice_Manager::get_instance();
