<?php
/**
 * Admin controller – menus, assets, AJAX, and page routing.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Admin {

	/**
	 * Wire up all admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Form submissions
		add_action( 'admin_post_wp_im_create_invoice', array( $this, 'handle_create_invoice' ) );
		add_action( 'admin_post_wp_im_update_invoice', array( $this, 'handle_update_invoice' ) );
		add_action( 'admin_post_wp_im_delete_invoice', array( $this, 'handle_delete_invoice' ) );
		add_action( 'admin_post_wp_im_send_invoice',   array( $this, 'handle_send_invoice' ) );

		// Print / PDF
		add_action( 'admin_post_wp_im_print_invoice',  array( $this, 'handle_print_invoice' ) );

		// AJAX status update
		add_action( 'wp_ajax_wp_im_update_status', array( $this, 'ajax_update_status' ) );

		// Settings
		add_action( 'admin_post_wp_im_save_settings', array( $this, 'handle_save_settings' ) );
	}

	// ── Menu ─────────────────────────────────────────────────────────────────

	public function register_menus() {
		add_menu_page(
			__( 'Invoice Manager', 'wp-invoice-manager' ),
			__( 'Invoices', 'wp-invoice-manager' ),
			'manage_options',
			'wp-invoice-manager',
			array( $this, 'page_list' ),
			'dashicons-media-spreadsheet',
			30
		);

		add_submenu_page(
			'wp-invoice-manager',
			__( 'All Invoices', 'wp-invoice-manager' ),
			__( 'All Invoices', 'wp-invoice-manager' ),
			'manage_options',
			'wp-invoice-manager',
			array( $this, 'page_list' )
		);

		add_submenu_page(
			'wp-invoice-manager',
			__( 'New Invoice', 'wp-invoice-manager' ),
			__( 'New Invoice', 'wp-invoice-manager' ),
			'manage_options',
			'wp-im-new-invoice',
			array( $this, 'page_new' )
		);

		add_submenu_page(
			'wp-invoice-manager',
			__( 'Settings', 'wp-invoice-manager' ),
			__( 'Settings', 'wp-invoice-manager' ),
			'manage_options',
			'wp-im-settings',
			array( $this, 'page_settings' )
		);
	}

	// ── Assets ───────────────────────────────────────────────────────────────

	public function enqueue_assets( $hook ) {
		$our_pages = array(
			'toplevel_page_wp-invoice-manager',
			'invoices_page_wp-im-new-invoice',
			'invoices_page_wp-im-settings',
		);

		if ( ! in_array( $hook, $our_pages, true ) && ! isset( $_GET['page'] ) ) {
			return;
		}

		$allowed_get_pages = array( 'wp-invoice-manager', 'wp-im-new-invoice', 'wp-im-settings' );
		if ( ! in_array( $_GET['page'] ?? '', $allowed_get_pages, true ) && ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'wp-im-admin',
			WP_IM_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WP_IM_VERSION
		);

		wp_enqueue_script(
			'wp-im-admin',
			WP_IM_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			WP_IM_VERSION,
			true
		);

		wp_localize_script( 'wp-im-admin', 'WP_IM', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wp_im_nonce' ),
			'i18n'     => array(
				'confirm_delete' => __( 'Are you sure you want to delete this invoice?', 'wp-invoice-manager' ),
				'add_item'       => __( 'Add Item', 'wp-invoice-manager' ),
			),
		) );
	}

	// ── Page renderers ────────────────────────────────────────────────────────

	public function page_list() {
		$invoices  = WP_IM_Invoice::get_all();
		$statuses  = WP_IM_Invoice::get_statuses();
		$filter    = sanitize_key( $_GET['status'] ?? '' );

		// Count per status
		$counts    = array( 'all' => count( $invoices ) );
		foreach ( $statuses as $key => $label ) {
			$counts[ $key ] = 0;
		}
		foreach ( $invoices as $post ) {
			$s = get_post_meta( $post->ID, WP_IM_Invoice::META_STATUS, true );
			if ( isset( $counts[ $s ] ) ) {
				$counts[ $s ]++;
			}
		}

		// Filter
		if ( $filter && 'all' !== $filter ) {
			$invoices = array_filter( $invoices, function( $p ) use ( $filter ) {
				return get_post_meta( $p->ID, WP_IM_Invoice::META_STATUS, true ) === $filter;
			} );
		}

		include WP_IM_PLUGIN_DIR . 'admin/views/list.php';
	}

	public function page_new() {
		$invoice  = null;
		$post_id  = absint( $_GET['invoice_id'] ?? 0 );

		if ( $post_id ) {
			$invoice = WP_IM_Invoice::get( $post_id );
		}

		$statuses   = WP_IM_Invoice::get_statuses();
		$currencies = WP_IM_Invoice::get_currencies();
		include WP_IM_PLUGIN_DIR . 'admin/views/form.php';
	}

	public function page_settings() {
		include WP_IM_PLUGIN_DIR . 'admin/views/settings.php';
	}

	// ── Form handlers ─────────────────────────────────────────────────────────

	public function handle_create_invoice() {
		$this->verify_nonce( 'wp_im_invoice_nonce', 'wp_im_invoice_action' );
		$this->check_capability();

		$post_id = WP_IM_Invoice::create( $_POST );

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-invoice-manager',
			'message' => 'created',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_update_invoice() {
		$this->verify_nonce( 'wp_im_invoice_nonce', 'wp_im_invoice_action' );
		$this->check_capability();

		$post_id = absint( $_POST['post_id'] ?? 0 );
		WP_IM_Invoice::update( $post_id, $_POST );

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $post_id,
			'message'    => 'updated',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_invoice() {
		$this->verify_nonce( 'wp_im_delete_' . absint( $_GET['invoice_id'] ?? 0 ), 'wp_im_delete_nonce' );
		$this->check_capability();

		WP_IM_Invoice::delete( absint( $_GET['invoice_id'] ?? 0 ) );

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-invoice-manager',
			'message' => 'deleted',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_send_invoice() {
		$this->verify_nonce( 'wp_im_invoice_nonce', 'wp_im_invoice_action' );
		$this->check_capability();

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$invoice = WP_IM_Invoice::get( $post_id );

		if ( ! $invoice || empty( $invoice['client_email'] ) ) {
			wp_die( esc_html__( 'Invalid invoice or missing client email.', 'wp-invoice-manager' ) );
		}

		// Mark as sent
		update_post_meta( $post_id, WP_IM_Invoice::META_STATUS, 'sent' );

		$subject = sprintf(
			/* translators: %s: Invoice number */
			__( 'Invoice %s', 'wp-invoice-manager' ),
			$invoice['number']
		);

		$message = sprintf(
			/* translators: %1$s: client name, %2$s: invoice number, %3$s: total, %4$s: due date */
			__(
				"Dear %1\$s,\n\nPlease find your invoice %2\$s for a total of %3\$s.\nDue date: %4\$s.\n\nThank you for your business.",
				'wp-invoice-manager'
			),
			$invoice['client_name'],
			$invoice['number'],
			WP_IM_Invoice::currency_symbol( $invoice['currency'] ) . number_format( $invoice['totals']['total'], 2 ),
			$invoice['due_date']
		);

		wp_mail( $invoice['client_email'], $subject, $message );

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $post_id,
			'message'    => 'sent',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_print_invoice() {
		check_admin_referer( 'wp_im_print_' . absint( $_GET['invoice_id'] ?? 0 ), 'wp_im_print_nonce' );
		$this->check_capability();

		$invoice = WP_IM_Invoice::get( absint( $_GET['invoice_id'] ?? 0 ) );

		if ( ! $invoice ) {
			wp_die( esc_html__( 'Invoice not found.', 'wp-invoice-manager' ) );
		}

		$generator = new WP_IM_PDF_Generator( $invoice );
		$generator->render_html();
	}

	public function handle_save_settings() {
		$this->verify_nonce( 'wp_im_settings_nonce', 'wp_im_settings_action' );
		$this->check_capability();

		update_option( 'wp_im_invoice_prefix', sanitize_text_field( $_POST['invoice_prefix'] ?? 'INV-' ) );
		update_option( 'wp_im_company_name',   sanitize_text_field( $_POST['company_name'] ?? '' ) );
		update_option( 'wp_im_company_email',  sanitize_email( $_POST['company_email'] ?? '' ) );
		update_option( 'wp_im_company_address',sanitize_textarea_field( $_POST['company_address'] ?? '' ) );
		update_option( 'wp_im_default_currency', sanitize_text_field( $_POST['default_currency'] ?? 'USD' ) );
		update_option( 'wp_im_default_tax',    floatval( $_POST['default_tax'] ?? 0 ) );

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-im-settings',
			'message' => 'saved',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── AJAX ─────────────────────────────────────────────────────────────────

	public function ajax_update_status() {
		check_ajax_referer( 'wp_im_nonce', 'nonce' );
		$this->check_capability();

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$status  = sanitize_key( $_POST['status'] ?? '' );
		$allowed = array_keys( WP_IM_Invoice::get_statuses() );

		if ( ! $post_id || ! in_array( $status, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid data' ) );
		}

		update_post_meta( $post_id, WP_IM_Invoice::META_STATUS, $status );
		wp_send_json_success( array( 'message' => 'Status updated', 'status' => $status ) );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function verify_nonce( $action, $nonce_field ) {
		if ( ! isset( $_REQUEST[ $nonce_field ] ) || ! wp_verify_nonce( $_REQUEST[ $nonce_field ], $action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-invoice-manager' ) );
		}
	}

	private function check_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-invoice-manager' ) );
		}
	}
}
