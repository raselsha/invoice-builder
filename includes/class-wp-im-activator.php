<?php
/**
 * Fired during plugin activation.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Activator {

	/**
	 * Run on activation: create custom DB table for invoice items.
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'invoice_items';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			invoice_id   BIGINT(20) UNSIGNED NOT NULL,
			description  TEXT NOT NULL,
			quantity     DECIMAL(10,2) NOT NULL DEFAULT 1,
			unit_price   DECIMAL(10,2) NOT NULL DEFAULT 0,
			tax_rate     DECIMAL(5,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY invoice_id (invoice_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'wp_im_db_version', WP_IM_VERSION );
		add_option( 'wp_im_invoice_prefix', 'INV-' );
		add_option( 'wp_im_next_invoice_number', 1 );
	}
}
