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

	const POST_TYPE = 'wp_invoice';

	/**
	 * Register the custom post type and taxonomy.
	 */
	public function register() {
		$this->register_post_type();
		$this->register_status_taxonomy();
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
	 * Register invoice_status taxonomy for filtering.
	 */
	private function register_status_taxonomy() {
		// We store status as post_meta, but register a hidden taxonomy for advanced queries if needed.
		// Intentionally left lightweight.
	}
}
