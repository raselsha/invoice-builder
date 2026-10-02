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

		// Public share link (no login required) — pretty URL (front end) and
		// the legacy admin-post.php query-string URL (already-sent links).
		add_action( 'template_redirect', array( $this, 'maybe_render_shared_invoice' ) );
		add_action( 'admin_post_wp_im_view_shared_invoice',        array( $this, 'handle_view_shared_invoice' ) );
		add_action( 'admin_post_nopriv_wp_im_view_shared_invoice', array( $this, 'handle_view_shared_invoice' ) );
		add_action( 'admin_post_wp_im_regenerate_share_link',      array( $this, 'handle_regenerate_share_link' ) );

		// AJAX status update
		add_action( 'wp_ajax_wp_im_update_status', array( $this, 'ajax_update_status' ) );

		// Customers
		add_action( 'admin_post_wp_im_save_customer',   array( $this, 'handle_save_customer' ) );
		add_action( 'admin_post_wp_im_delete_customer', array( $this, 'handle_delete_customer' ) );
		add_action( 'wp_ajax_wp_im_search_customers',   array( $this, 'ajax_search_customers' ) );

		// Payments
		add_action( 'admin_post_wp_im_record_payment', array( $this, 'handle_record_payment' ) );
		add_action( 'admin_post_wp_im_delete_payment', array( $this, 'handle_delete_payment' ) );

		// Settings
		add_action( 'admin_post_wp_im_save_settings', array( $this, 'handle_save_settings' ) );

		// Duplicate / bulk actions / export
		add_action( 'admin_post_wp_im_duplicate_invoice', array( $this, 'handle_duplicate_invoice' ) );
		add_action( 'admin_post_wp_im_bulk_action',       array( $this, 'handle_bulk_action' ) );
		add_action( 'admin_post_wp_im_export_csv',        array( $this, 'handle_export_csv' ) );

		// Pay Now — scaffold only, no live gateway calls (see handle_pay_now()).
		add_action( 'admin_post_wp_im_pay_now',        array( $this, 'handle_pay_now' ) );
		add_action( 'admin_post_nopriv_wp_im_pay_now', array( $this, 'handle_pay_now' ) );

		// Recurring invoices + daily maintenance (overdue detection, reminders)
		add_action( 'wp_im_process_recurring_invoices', array( $this, 'process_recurring_invoices' ) );
		add_action( 'wp_im_process_recurring_invoices', array( $this, 'process_daily_maintenance' ) );

		// Self-healing: make sure the daily cron event stays scheduled even
		// if the plugin was updated without a deactivate/reactivate cycle.
		if ( ! wp_next_scheduled( 'wp_im_process_recurring_invoices' ) ) {
			wp_schedule_event( time(), 'daily', 'wp_im_process_recurring_invoices' );
		}
	}

	/**
	 * Cron callback: generate the next invoice for every due recurring template.
	 */
	public function process_recurring_invoices() {
		foreach ( WP_IM_Invoice::get_due_recurring_templates() as $template_id ) {
			WP_IM_Invoice::generate_from_recurring( $template_id );
		}
	}

	/**
	 * Cron callback: flip sent/partial invoices past their due date to
	 * "overdue", then email a reminder for any overdue invoice that hasn't
	 * had one in the last 7 days.
	 */
	public function process_daily_maintenance() {
		foreach ( WP_IM_Invoice::get_newly_overdue_ids() as $post_id ) {
			update_post_meta( $post_id, WP_IM_Invoice::META_STATUS, 'overdue' );
		}

		foreach ( WP_IM_Invoice::get_overdue_for_reminder() as $post_id ) {
			$invoice = WP_IM_Invoice::get( $post_id );
			if ( ! $invoice || empty( $invoice['client_email'] ) ) {
				continue;
			}

			$subject = sprintf(
				/* translators: %s: Invoice number */
				__( 'Reminder: Invoice %s is overdue', 'wp-invoice-manager' ),
				$invoice['number']
			);
			$message = sprintf(
				/* translators: %1$s: client name, %2$s: invoice number, %3$s: balance due, %4$s: due date */
				__(
					"Dear %1\$s,\n\nThis is a friendly reminder that invoice %2\$s for %3\$s was due on %4\$s and is still unpaid.\n\nPlease arrange payment at your earliest convenience.\n\nThank you.",
					'wp-invoice-manager'
				),
				$invoice['client_name'],
				$invoice['number'],
				WP_IM_Invoice::currency_symbol( $invoice['currency'] ) . number_format( $invoice['totals']['balance'], 2 ),
				$invoice['due_date']
			);

			wp_mail( $invoice['client_email'], $subject, $message );
			update_post_meta( $post_id, WP_IM_Invoice::META_LAST_REMINDER_SENT, current_time( 'Y-m-d' ) );
		}
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
			__( 'Customers', 'wp-invoice-manager' ),
			__( 'Customers', 'wp-invoice-manager' ),
			'manage_options',
			'wp-im-customers',
			array( $this, 'page_customers' )
		);

		add_submenu_page(
			'wp-invoice-manager',
			__( 'Reports', 'wp-invoice-manager' ),
			__( 'Reports', 'wp-invoice-manager' ),
			'manage_options',
			'wp-im-reports',
			array( $this, 'page_reports' )
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
			'invoices_page_wp-im-customers',
			'invoices_page_wp-im-reports',
		);

		if ( ! in_array( $hook, $our_pages, true ) && ! isset( $_GET['page'] ) ) {
			return;
		}

		$allowed_get_pages = array( 'wp-invoice-manager', 'wp-im-new-invoice', 'wp-im-settings', 'wp-im-customers', 'wp-im-reports' );
		if ( ! in_array( $_GET['page'] ?? '', $allowed_get_pages, true ) && ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		if ( 'wp-im-settings' === ( $_GET['page'] ?? '' ) ) {
			wp_enqueue_media();
		}

		$css_path = WP_IM_PLUGIN_DIR . 'admin/css/admin.css';
		$js_path  = WP_IM_PLUGIN_DIR . 'admin/js/admin.js';

		wp_enqueue_style(
			'wp-im-admin',
			WP_IM_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			file_exists( $css_path ) ? filemtime( $css_path ) : WP_IM_VERSION
		);

		wp_enqueue_script(
			'wp-im-admin',
			WP_IM_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			file_exists( $js_path ) ? filemtime( $js_path ) : WP_IM_VERSION,
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
		$invoice          = null;
		$post_id          = absint( $_GET['invoice_id'] ?? 0 );
		$share_url        = '';
		$prefill_customer = null;

		if ( $post_id ) {
			$invoice = WP_IM_Invoice::get( $post_id );
			if ( $invoice ) {
				$share_url = WP_IM_Invoice::get_share_url( $post_id );
			}
		} elseif ( ! empty( $_GET['customer_id'] ) ) {
			// Arrived via a customer's "New Invoice" quick-link — prefill client details.
			$prefill_customer = WP_IM_Customer::get( absint( $_GET['customer_id'] ) );
		}

		$statuses   = WP_IM_Invoice::get_statuses();
		$currencies = WP_IM_Invoice::get_currencies();
		$all_terms  = get_option( 'wp_im_terms_conditions', WP_IM_Invoice::get_default_terms() );
		include WP_IM_PLUGIN_DIR . 'admin/views/form.php';
	}

	public function page_settings() {
		include WP_IM_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function page_reports() {
		$currency_stats  = WP_IM_Invoice::get_stats_by_currency();
		$monthly_revenue = WP_IM_Invoice::get_monthly_revenue( 6 );
		$top_clients     = WP_IM_Invoice::get_top_clients( 5 );
		$aging           = WP_IM_Invoice::get_overdue_aging();
		include WP_IM_PLUGIN_DIR . 'admin/views/reports.php';
	}

	public function page_customers() {
		$customers = WP_IM_Customer::get_all();
		include WP_IM_PLUGIN_DIR . 'admin/views/customers-list.php';
	}

	// ── Form handlers ─────────────────────────────────────────────────────────

	public function handle_create_invoice() {
		$this->verify_nonce( 'wp_im_invoice_nonce', 'wp_im_invoice_action' );
		$this->check_capability();

		$post_id = WP_IM_Invoice::create( $_POST );

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}

		WP_IM_Customer::upsert_from_invoice_data( $_POST );

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

		WP_IM_Customer::upsert_from_invoice_data( $_POST );

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

		$share_url = WP_IM_Invoice::get_share_url( $post_id );

		$subject = sprintf(
			/* translators: %s: Invoice number */
			__( 'Invoice %s', 'wp-invoice-manager' ),
			$invoice['number']
		);

		// No PDF library (e.g. Dompdf) is bundled with this plugin, so instead
		// of a real PDF attachment, this sends a branded HTML email with the
		// invoice summary and a link to view/print/pay it online.
		$message = $this->build_send_invoice_email_html( $invoice, $share_url );

		add_filter( 'wp_mail_content_type', array( $this, 'set_html_mail_content_type' ) );
		wp_mail( $invoice['client_email'], $subject, $message );
		remove_filter( 'wp_mail_content_type', array( $this, 'set_html_mail_content_type' ) );

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $post_id,
			'message'    => 'sent',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Force text/html just for the "Send to Client" email above — never left
	 * globally attached, so it can't affect any other plugin's plain-text mail.
	 */
	public function set_html_mail_content_type() {
		return 'text/html';
	}

	/**
	 * Branded HTML email body for "Send to Client" — summary + a button
	 * linking to the same share view used by the "Share" link, so the client
	 * can view, print, and (once configured) pay online. No attachment.
	 *
	 * @param array  $invoice
	 * @param string $share_url
	 * @return string
	 */
	private function build_send_invoice_email_html( array $invoice, $share_url ) {
		$symbol      = WP_IM_Invoice::currency_symbol( $invoice['currency'] );
		$accent      = get_option( 'wp_im_primary_color', '#e94560' );
		$header_bg   = get_option( 'wp_im_header_color', '#1a1a2e' );
		$header_text = get_option( 'wp_im_header_text_color', WP_IM_Invoice::readable_text_color( $header_bg ) );
		$logo_url    = get_option( 'wp_im_company_logo_url', '' );
		$logo_mode   = get_option( 'wp_im_logo_display_mode', 'image' );
		$show_image  = $logo_url && 'text' !== $logo_mode;
		$show_text   = ! $logo_url || 'image' !== $logo_mode;

		ob_start();
		?>
		<div style="font-family:'Segoe UI',Arial,sans-serif;background:#f8f9fc;padding:48px 20px">
			<div style="max-width:620px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.1)">
				<div style="background:<?php echo esc_attr( $header_bg ); ?>;padding:40px 44px;color:<?php echo esc_attr( $header_text ); ?>">
					<?php if ( $show_image ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $invoice['biller_name'] ); ?>" style="display:inline-block;vertical-align:middle;max-height:56px;width:auto<?php echo $show_text ? ';margin-right:16px' : ''; ?>">
					<?php endif; ?>
					<?php if ( $show_text ) : ?>
						<span style="display:inline-block;vertical-align:middle;font-size:26px;font-weight:800;letter-spacing:-.02em"><?php echo esc_html( $invoice['biller_name'] ); ?></span>
					<?php endif; ?>
				</div>
				<div style="padding:44px">
					<p style="margin:0 0 20px;color:#1e293b;font-size:17px;font-weight:600">
						<?php echo esc_html( sprintf( __( 'Dear %s,', 'wp-invoice-manager' ), $invoice['client_name'] ) ); ?>
					</p>
					<p style="margin:0 0 32px;color:#475569;font-size:15px;line-height:1.75">
						<?php esc_html_e( 'Please find your invoice summary below. You can view, print, or pay it online using the button below.', 'wp-invoice-manager' ); ?>
					</p>
					<table style="width:100%;border-collapse:collapse;margin-bottom:32px">
						<tr>
							<td style="padding:14px 0;color:#64748b;font-size:14.5px"><?php esc_html_e( 'Invoice #', 'wp-invoice-manager' ); ?></td>
							<td style="padding:14px 0;color:#1e293b;font-size:15px;text-align:right;font-weight:700"><?php echo esc_html( $invoice['number'] ); ?></td>
						</tr>
						<tr>
							<td style="padding:14px 0;color:#64748b;font-size:14.5px;border-top:1px solid #f1f5f9"><?php esc_html_e( 'Total', 'wp-invoice-manager' ); ?></td>
							<td style="padding:14px 0;color:#1e293b;font-size:18px;text-align:right;font-weight:800;border-top:1px solid #f1f5f9"><?php echo esc_html( $symbol . number_format( $invoice['totals']['total'], 2 ) ); ?></td>
						</tr>
						<tr>
							<td style="padding:14px 0;color:#64748b;font-size:14.5px;border-top:1px solid #f1f5f9"><?php esc_html_e( 'Due Date', 'wp-invoice-manager' ); ?></td>
							<td style="padding:14px 0;color:#1e293b;font-size:15px;text-align:right;border-top:1px solid #f1f5f9"><?php echo esc_html( $invoice['due_date'] ? $invoice['due_date'] : '—' ); ?></td>
						</tr>
					</table>
					<div style="text-align:center;margin-bottom:12px">
						<a href="<?php echo esc_url( $share_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:#fff;padding:17px 44px;border-radius:8px;font-size:16px;font-weight:700;text-decoration:none">
							<?php esc_html_e( 'View, Print & Pay Invoice', 'wp-invoice-manager' ); ?>
						</a>
					</div>
					<p style="text-align:center;margin:24px 0 0;color:#94a3b8;font-size:13px">
						<?php esc_html_e( 'Thank you for your business.', 'wp-invoice-manager' ); ?>
					</p>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
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

	/**
	 * Public, tokenised invoice view — no login required. Used for the
	 * "Share" link on the invoice edit screen.
	 */
	public function handle_view_shared_invoice() {
		$post_id = absint( $_GET['invoice_id'] ?? 0 );
		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );

		$invoice = WP_IM_Invoice::get( $post_id );

		if ( ! $invoice || empty( $invoice['share_token'] ) || ! hash_equals( $invoice['share_token'], $token ) ) {
			$this->die_invalid_share_link();
		}

		$this->render_shared_invoice( $invoice );
	}

	/**
	 * Pretty-URL ("/invoice/{token}/" and "/invoice/{token}/pay/") front-end
	 * route — resolves the same share/pay views as the admin-post.php
	 * handlers above (kept working for already-sent links using the old
	 * query-string format), but via the rewrite rule in WP_IM_Post_Type.
	 */
	public function maybe_render_shared_invoice() {
		$token = get_query_var( 'wim_token' );
		if ( '' === $token || false === $token ) {
			return;
		}

		$invoice = WP_IM_Invoice::get_by_share_token( $token );
		if ( ! $invoice || ! hash_equals( $invoice['share_token'], $token ) ) {
			$this->die_invalid_share_link();
		}

		if ( get_query_var( 'wim_pay' ) ) {
			$this->render_pay_now_page( $invoice );
		}

		$this->render_shared_invoice( $invoice );
	}

	/**
	 * Render the public share view and exit. Shared by both the pretty-URL
	 * route and the legacy admin-post.php query-string route.
	 *
	 * @param array $invoice
	 */
	private function render_shared_invoice( array $invoice ) {
		WP_IM_Invoice::record_share_view( $invoice['post_id'] );
		$generator = new WP_IM_PDF_Generator( $invoice, true );
		$generator->render_html(); // exits
	}

	/**
	 * Render the "Pay Now — not set up yet" page and exit. Shared by both
	 * the pretty-URL route and the legacy admin-post.php query-string route.
	 *
	 * @param array $invoice
	 */
	private function render_pay_now_page( array $invoice ) {
		include WP_IM_PLUGIN_DIR . 'templates/pay-now-not-configured.php';
		exit;
	}

	/**
	 * wp_die() for an invalid/mismatched share token — used by every public
	 * share/pay route.
	 */
	private function die_invalid_share_link() {
		wp_die(
			esc_html__( 'This invoice link is invalid or no longer active.', 'wp-invoice-manager' ),
			esc_html__( 'Link not found', 'wp-invoice-manager' ),
			array( 'response' => 404 )
		);
	}

	public function handle_regenerate_share_link() {
		$post_id = absint( $_GET['invoice_id'] ?? 0 );
		check_admin_referer( 'wp_im_share_' . $post_id, 'wp_im_share_nonce' );
		$this->check_capability();

		WP_IM_Invoice::regenerate_share_token( $post_id );

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $post_id,
			'message'    => 'share_regenerated',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_save_settings() {
		$this->verify_nonce( 'wp_im_settings_nonce', 'wp_im_settings_action' );
		$this->check_capability();

		update_option( 'wp_im_invoice_prefix', sanitize_text_field( $_POST['invoice_prefix'] ?? 'INV-' ) );
		update_option( 'wp_im_reset_number_yearly', ! empty( $_POST['reset_number_yearly'] ) ? 1 : 0 );
		update_option( 'wp_im_company_name',   sanitize_text_field( $_POST['company_name'] ?? '' ) );
		update_option( 'wp_im_company_email',  sanitize_email( $_POST['company_email'] ?? '' ) );
		update_option( 'wp_im_company_phone',  sanitize_text_field( $_POST['company_phone'] ?? '' ) );
		update_option( 'wp_im_company_address',sanitize_textarea_field( $_POST['company_address'] ?? '' ) );
		update_option( 'wp_im_company_logo_url', esc_url_raw( $_POST['company_logo_url'] ?? '' ) );
		update_option( 'wp_im_logo_display_mode', in_array( $_POST['logo_display_mode'] ?? 'image', array( 'image', 'text', 'both' ), true ) ? $_POST['logo_display_mode'] : 'image' );
		update_option( 'wp_im_default_currency', sanitize_text_field( $_POST['default_currency'] ?? 'USD' ) );
		update_option( 'wp_im_default_tax',    floatval( $_POST['default_tax'] ?? 0 ) );
		update_option( 'wp_im_footer_text',    sanitize_text_field( $_POST['footer_text'] ?? WP_IM_Invoice::get_default_footer_text() ) );

		$terms = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['terms'] ?? array() ) );
		$terms = array_values( array_filter( $terms, function( $term ) {
			return '' !== trim( $term );
		} ) );
		update_option( 'wp_im_terms_conditions', $terms );

		update_option( 'wp_im_primary_color', $this->sanitize_hex_color( $_POST['primary_color'] ?? '', '#e94560' ) );
		update_option( 'wp_im_header_color',  $this->sanitize_hex_color( $_POST['header_color'] ?? '', '#1a1a2e' ) );
		update_option( 'wp_im_date_color',    $this->sanitize_hex_color( $_POST['date_color'] ?? '', '#0f3460' ) );
		update_option( 'wp_im_header_text_color', $this->sanitize_hex_color( $_POST['header_text_color'] ?? '', '#ffffff' ) );
		update_option( 'wp_im_date_text_color',   $this->sanitize_hex_color( $_POST['date_text_color'] ?? '', '#ffffff' ) );

		// Payment gateway credentials — scaffold only, stored for a future
		// real bKash / SSLCommerz integration. No live API calls are made
		// with these values yet (see handle_pay_now()).
		update_option( 'wp_im_bkash_enabled',          ! empty( $_POST['bkash_enabled'] ) ? 1 : 0 );
		update_option( 'wp_im_bkash_api_key',          sanitize_text_field( $_POST['bkash_api_key'] ?? '' ) );
		update_option( 'wp_im_bkash_api_secret',       sanitize_text_field( $_POST['bkash_api_secret'] ?? '' ) );
		update_option( 'wp_im_sslcommerz_enabled',         ! empty( $_POST['sslcommerz_enabled'] ) ? 1 : 0 );
		update_option( 'wp_im_sslcommerz_store_id',        sanitize_text_field( $_POST['sslcommerz_store_id'] ?? '' ) );
		update_option( 'wp_im_sslcommerz_store_password',  sanitize_text_field( $_POST['sslcommerz_store_password'] ?? '' ) );

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-im-settings',
			'message' => 'saved',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Customers ────────────────────────────────────────────────────────────

	public function handle_save_customer() {
		$this->verify_nonce( 'wp_im_customer_action', 'wp_im_customer_nonce' );
		$this->check_capability();

		$data = array(
			'name'    => $_POST['name'] ?? '',
			'email'   => $_POST['email'] ?? '',
			'phone'   => $_POST['phone'] ?? '',
			'address' => $_POST['address'] ?? '',
		);

		$post_id = absint( $_POST['post_id'] ?? 0 );

		if ( $post_id ) {
			WP_IM_Customer::update( $post_id, $data );
		} else {
			$post_id = WP_IM_Customer::create( $data );
			if ( is_wp_error( $post_id ) ) {
				wp_die( esc_html( $post_id->get_error_message() ) );
			}
		}

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-im-customers',
			'message' => 'saved',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_customer() {
		$this->verify_nonce( 'wp_im_delete_customer_' . absint( $_GET['customer_id'] ?? 0 ), 'wp_im_delete_customer_nonce' );
		$this->check_capability();

		WP_IM_Customer::delete( absint( $_GET['customer_id'] ?? 0 ) );

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-im-customers',
			'message' => 'deleted',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Payments ─────────────────────────────────────────────────────────────

	public function handle_record_payment() {
		$this->verify_nonce( 'wp_im_payment_action', 'wp_im_payment_nonce' );
		$this->check_capability();

		$post_id = absint( $_POST['post_id'] ?? 0 );

		WP_IM_Invoice::add_payment( $post_id, array(
			'amount' => $_POST['amount'] ?? 0,
			'date'   => $_POST['date'] ?? '',
			'method' => $_POST['method'] ?? '',
			'note'   => $_POST['note'] ?? '',
		) );

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $post_id,
			'message'    => 'payment_recorded',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_payment() {
		$post_id    = absint( $_GET['invoice_id'] ?? 0 );
		$payment_id = sanitize_text_field( $_GET['payment_id'] ?? '' );

		$this->verify_nonce( 'wp_im_delete_payment_' . $payment_id, 'wp_im_delete_payment_nonce' );
		$this->check_capability();

		WP_IM_Invoice::delete_payment( $post_id, $payment_id );

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $post_id,
			'message'    => 'payment_deleted',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Duplicate / bulk actions / export / pay now ─────────────────────────

	public function handle_duplicate_invoice() {
		$post_id = absint( $_GET['invoice_id'] ?? 0 );
		$this->verify_nonce( 'wp_im_duplicate_' . $post_id, 'wp_im_duplicate_nonce' );
		$this->check_capability();

		$new_id = WP_IM_Invoice::duplicate( $post_id );

		if ( ! $new_id ) {
			wp_die( esc_html__( 'Could not duplicate this invoice.', 'wp-invoice-manager' ) );
		}

		wp_redirect( add_query_arg( array(
			'page'       => 'wp-im-new-invoice',
			'invoice_id' => $new_id,
			'message'    => 'duplicated',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_bulk_action() {
		$this->verify_nonce( 'wp_im_bulk_action', 'wp_im_bulk_nonce' );
		$this->check_capability();

		$bulk_action = sanitize_key( $_POST['bulk_action'] ?? '' );
		$ids         = array_filter( array_map( 'absint', (array) ( $_POST['invoice_ids'] ?? array() ) ) );
		$statuses    = array_keys( WP_IM_Invoice::get_statuses() );

		if ( $ids && $bulk_action ) {
			if ( 'delete' === $bulk_action ) {
				foreach ( $ids as $id ) {
					WP_IM_Invoice::delete( $id );
				}
			} elseif ( in_array( $bulk_action, $statuses, true ) ) {
				foreach ( $ids as $id ) {
					update_post_meta( $id, WP_IM_Invoice::META_STATUS, $bulk_action );
				}
			}
		}

		wp_redirect( add_query_arg( array(
			'page'    => 'wp-invoice-manager',
			'message' => 'bulk_done',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_export_csv() {
		check_admin_referer( 'wp_im_export_csv', 'wp_im_export_nonce' );
		$this->check_capability();

		$invoices = WP_IM_Invoice::get_all();
		$statuses = WP_IM_Invoice::get_statuses();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="invoices-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Invoice #', 'Client', 'Email', 'Invoice Date', 'Due Date', 'Status', 'Currency', 'Subtotal', 'Tax', 'Discount', 'Total', 'Paid', 'Balance' ) );

		foreach ( $invoices as $post ) {
			$inv = WP_IM_Invoice::get( $post->ID );
			fputcsv( $out, array(
				$inv['number'],
				$inv['client_name'],
				$inv['client_email'],
				$inv['invoice_date'],
				$inv['due_date'],
				$statuses[ $inv['status'] ] ?? $inv['status'],
				$inv['currency'],
				number_format( $inv['totals']['subtotal'], 2, '.', '' ),
				number_format( $inv['totals']['tax_total'], 2, '.', '' ),
				number_format( $inv['totals']['discount'], 2, '.', '' ),
				number_format( $inv['totals']['total'], 2, '.', '' ),
				number_format( $inv['totals']['paid'], 2, '.', '' ),
				number_format( $inv['totals']['balance'], 2, '.', '' ),
			) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Public "Pay Now" link on a shared invoice. This is a scaffold only:
	 * Settings has fields ready for real bKash / SSLCommerz credentials, but
	 * no live gateway API integration has been written yet, so every click
	 * lands on an honest "online payment isn't set up yet" page rather than
	 * pretending to process anything.
	 */
	public function handle_pay_now() {
		$post_id = absint( $_GET['invoice_id'] ?? 0 );
		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		$invoice = WP_IM_Invoice::get( $post_id );

		if ( ! $invoice || empty( $invoice['share_token'] ) || ! hash_equals( $invoice['share_token'], $token ) ) {
			$this->die_invalid_share_link();
		}

		$this->render_pay_now_page( $invoice );
	}

	// ── AJAX ─────────────────────────────────────────────────────────────────

	public function ajax_search_customers() {
		check_ajax_referer( 'wp_im_nonce', 'nonce' );
		$this->check_capability();

		$term    = sanitize_text_field( wp_unslash( $_GET['term'] ?? $_POST['term'] ?? '' ) );
		$results = WP_IM_Customer::search( $term );

		wp_send_json_success( array( 'customers' => $results ) );
	}

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

	/**
	 * Sanitize a #rrggbb hex color, falling back to a default on invalid input.
	 *
	 * @param string $value
	 * @param string $default
	 * @return string
	 */
	private function sanitize_hex_color( $value, $default ) {
		$value = sanitize_text_field( $value );
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : $default;
	}
}
