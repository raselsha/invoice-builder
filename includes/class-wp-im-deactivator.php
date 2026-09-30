<?php
/**
 * Fired during plugin deactivation.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Deactivator {

	/**
	 * Nothing destructive on deactivation – data is preserved.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wp_im_process_recurring_invoices' );
		flush_rewrite_rules();
	}
}
