<?php
/**
 * Registers the 'wp_invoice' custom post type.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Post_Type {

	const POST_TYPE          = 'wp_invoice';
	const CUSTOMER_POST_TYPE = 'wim_customer';

	/**
	 * Register the custom post type and taxonomy.
	 */
	public function register() {
		$this->register_post_type();
		$this->register_customer_post_type();
		$this->register_status_taxonomy();
		$this->register_share_rewrite_rules();
	}

	/**
	 * Pretty public URLs for the share link ("/invoice/{token}/") and the
	 * Pay Now page ("/invoice/{token}/pay/") — resolved in
	 * WP_IM_Admin::maybe_render_shared_invoice() on template_redirect.
	 *
	 * Self-heals on a version bump: this plugin is often deployed by syncing
	 * files directly (no activation hook fires), so a stale/missing rewrite
	 * rule is checked and flushed on every 'init' instead of relying solely
	 * on activation — same pattern as the recurring-invoice cron self-heal.
	 */
	private function register_share_rewrite_rules() {
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'wim_token';
			$vars[] = 'wim_pay';
			return $vars;
		} );

		add_rewrite_tag( '%wim_token%', '([^&/]+)' );
		add_rewrite_rule( '^invoice/([^/]+)/pay/?$', 'index.php?wim_token=$matches[1]&wim_pay=1', 'top' );
		add_rewrite_rule( '^invoice/([^/]+)/?$', 'index.php?wim_token=$matches[1]', 'top' );

		if ( get_option( 'wim_rewrite_version' ) !== WP_IM_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'wim_rewrite_version', WP_IM_VERSION );
		}
	}

	/**
	 * Register the wp_invoice post type.
	 */
	private function register_post_type() {
		$labels = array(
			'name'               => _x( 'Invoices', 'post type general name', 'wp-invoice-manager' ),
			'singular_name'      => _x( 'Invoice', 'post type singular name', 'wp-invoice-manager' ),
			'menu_name'          => __( 'Invoices', 'wp-invoice-manager' ),
			'add_new'            => __( 'Add New', 'wp-invoice-manager' ),
			'add_new_item'       => __( 'Add New Invoice', 'wp-invoice-manager' ),
			'edit_item'          => __( 'Edit Invoice', 'wp-invoice-manager' ),
			'new_item'           => __( 'New Invoice', 'wp-invoice-manager' ),
			'view_item'          => __( 'View Invoice', 'wp-invoice-manager' ),
			'search_items'       => __( 'Search Invoices', 'wp-invoice-manager' ),
			'not_found'          => __( 'No invoices found', 'wp-invoice-manager' ),
			'not_found_in_trash' => __( 'No invoices found in trash', 'wp-invoice-manager' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // We add our own menu
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => array( 'title' ),
			'show_in_rest'        => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the wim_customer post type – a lightweight, reusable
	 * client/customer record (auto-filed from invoices, or added manually).
	 */
	private function register_customer_post_type() {
		$labels = array(
			'name'               => _x( 'Customers', 'post type general name', 'wp-invoice-manager' ),
			'singular_name'      => _x( 'Customer', 'post type singular name', 'wp-invoice-manager' ),
			'menu_name'          => __( 'Customers', 'wp-invoice-manager' ),
			'add_new'            => __( 'Add New', 'wp-invoice-manager' ),
			'add_new_item'       => __( 'Add New Customer', 'wp-invoice-manager' ),
			'edit_item'          => __( 'Edit Customer', 'wp-invoice-manager' ),
			'new_item'           => __( 'New Customer', 'wp-invoice-manager' ),
			'view_item'          => __( 'View Customer', 'wp-invoice-manager' ),
			'search_items'       => __( 'Search Customers', 'wp-invoice-manager' ),
			'not_found'          => __( 'No customers found', 'wp-invoice-manager' ),
			'not_found_in_trash' => __( 'No customers found in trash', 'wp-invoice-manager' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => false, // We add our own menu
			'query_var'       => false,
			'rewrite'         => false,
			'capability_type' => 'post',
			'has_archive'     => false,
			'hierarchical'    => false,
			'menu_position'   => null,
			'supports'        => array( 'title' ),
			'show_in_rest'    => false,
		);

		register_post_type( self::CUSTOMER_POST_TYPE, $args );
	}

	/**
	 * Register invoice_status taxonomy for filtering.
	 */
	private function register_status_taxonomy() {
		// We store status as post_meta, but register a hidden taxonomy for advanced queries if needed.
		// Intentionally left lightweight.
	}
}
