<?php
/**
 * Online payments through Stripe Checkout, for invoices and payment links.
 *
 * Invoice: /invoice/{token}/pay/ — the customer pays the balance (or part of
 *          it, if the invoice allows) and the payment is added to the invoice.
 * Link:    /pay/{token}/ — the customer pays what the link asks; a paid
 *          invoice is created for it so the money shows up in the books.
 *
 * Both pages post back to themselves, go to Stripe, and come back with
 * ?paid={session}. The session is verified with Stripe before anything is
 * recorded; the webhook records it too, for customers who close the tab.
 * Recording is idempotent (keyed on the Checkout Session ID).
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Online_Payments {

	const INVOICE_META_SESSION = '_invoice_stripe_session';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'route_link' ), 0 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_webhook_route' ) );
		add_action( 'admin_post_wp_im_stripe_webhook', array( __CLASS__, 'handle_create_webhook' ) );
		add_action( 'admin_post_wp_im_stripe_check', array( __CLASS__, 'handle_check_connection' ) );
	}

	// ── Invoice ─────────────────────────────────────────────────────────────

	/**
	 * Can the customer pay this invoice online right now?
	 */
	public static function invoice_payable( array $invoice ) {
		return ! in_array( $invoice['status'], array( 'paid', 'cancelled' ), true )
			&& $invoice['totals']['balance'] > 0;
	}

	public static function invoice_allows_partial( array $invoice ) {
		return ! empty( $invoice['allow_partial'] )
			&& $invoice['totals']['balance'] > WP_IM_Stripe::min_amount( $invoice['currency'] );
	}

	/**
	 * /invoice/{token}/pay/ — called by WP_IM_Admin once the token is valid.
	 */
	public static function handle_invoice_pay( array $invoice ) {
		self::no_cache();

		if ( ! WP_IM_Stripe::is_ready() ) {
			include WP_IM_PLUGIN_DIR . 'templates/pay-now-not-configured.php';
			exit;
		}

		$view = array( 'kind' => 'invoice', 'invoice' => $invoice );

		if ( isset( $_GET['paid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$session_id = sanitize_text_field( wp_unslash( $_GET['paid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::render( array_merge( $view, self::return_state( $session_id, 'invoice', $invoice['post_id'] ) ) );
		}

		if ( ! self::invoice_payable( $invoice ) ) {
			self::render( array_merge( $view, array( 'state' => 'closed', 'status' => 'paid' === $invoice['status'] || $invoice['totals']['balance'] <= 0 ? 'used' : 'disabled' ) ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			self::checkout_invoice( $invoice );
		}

		self::render( array_merge( $view, array( 'cancelled' => isset( $_GET['cancelled'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private static function checkout_invoice( array $invoice ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public page, see route_link().
		$input = array(
			'name'   => sanitize_text_field( wp_unslash( $_POST['wim_name'] ?? '' ) ),
			'email'  => sanitize_email( wp_unslash( $_POST['wim_email'] ?? '' ) ),
			'custom' => sanitize_text_field( wp_unslash( $_POST['wim_custom'] ?? '' ) ),
		);
		// phpcs:enable

		$c       = $invoice['currency'];
		$balance = $invoice['totals']['balance'];
		$errors  = array();
		$amount  = $balance;

		if ( self::invoice_allows_partial( $invoice ) ) {
			$amount = WP_IM_Stripe::parse_amount( $input['custom'] );
			$min    = WP_IM_Stripe::min_amount( $c );
			if ( null === $amount ) {
				$errors[] = __( 'Please enter a valid amount, like 150 or 99.50.', 'wp-invoice-manager' );
			} elseif ( $amount < $min ) {
				/* translators: %s: smallest amount */
				$errors[] = sprintf( __( 'The smallest amount you can pay is %s.', 'wp-invoice-manager' ), WP_IM_Stripe::format( $min, $c ) );
			} elseif ( WP_IM_Stripe::to_minor( $amount, $c ) > WP_IM_Stripe::to_minor( $balance, $c ) ) {
				/* translators: %s: balance due */
				$errors[] = sprintf( __( 'You can pay up to the balance due, %s.', 'wp-invoice-manager' ), WP_IM_Stripe::format( $balance, $c ) );
			}
		}
		if ( '' === $input['name'] ) {
			$errors[] = __( 'Please enter your name.', 'wp-invoice-manager' );
		}
		if ( ! is_email( $input['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address — your receipt goes there.', 'wp-invoice-manager' );
		}

		if ( $errors ) {
			self::render( array( 'kind' => 'invoice', 'invoice' => $invoice, 'errors' => $errors, 'input' => $input ) );
		}

		$pay_url = WP_IM_Invoice::get_pay_now_url( $invoice['post_id'] );
		/* translators: %s: invoice number */
		$name    = sprintf( __( 'Invoice %s', 'wp-invoice-manager' ), $invoice['number'] );

		self::redirect_to_checkout( array(
			'currency'    => $c,
			'amount'      => $amount,
			'name'        => $name,
			'description' => $invoice['biller_name'],
			'email'       => $input['email'],
			'reference'   => 'wim-invoice-' . $invoice['post_id'],
			'metadata'    => array(
				'wim_kind'    => 'invoice',
				'wim_invoice' => $invoice['post_id'],
				'wim_name'    => mb_substr( $input['name'], 0, 200 ),
			),
			'success_url' => add_query_arg( 'paid', '{CHECKOUT_SESSION_ID}', $pay_url ),
			'cancel_url'  => add_query_arg( 'cancelled', '1', $pay_url ),
		), array( 'kind' => 'invoice', 'invoice' => $invoice, 'input' => $input ) );
	}

	// ── Payment link ────────────────────────────────────────────────────────

	/**
	 * /pay/{token}/. No nonce on purpose: the page is public, may be served
	 * to logged-out visitors from any cache layer, and starting a checkout
	 * changes nothing on our side — nothing is recorded until Stripe says paid.
	 */
	public static function route_link() {
		$token = get_query_var( 'wim_link' );
		if ( ! $token ) {
			return;
		}
		self::no_cache();

		$link = WP_IM_Payment_Link::find_by_token( $token );
		if ( ! $link ) {
			status_header( 404 );
			self::render( array( 'kind' => 'link', 'state' => 'missing' ) );
		}

		$view = array( 'kind' => 'link', 'link' => $link );

		if ( isset( $_GET['paid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$session_id = sanitize_text_field( wp_unslash( $_GET['paid'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			self::render( array_merge( $view, self::return_state( $session_id, 'link', $link['id'] ) ) );
		}

		$status = WP_IM_Payment_Link::status( $link );
		if ( 'active' !== $status ) {
			self::render( array_merge( $view, array( 'state' => 'closed', 'status' => $status ) ) );
		}
		if ( ! WP_IM_Stripe::is_ready() ) {
			self::render( array_merge( $view, array( 'state' => 'unavailable' ) ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			self::checkout_link( $link );
		}

		self::render( array_merge( $view, array( 'cancelled' => isset( $_GET['cancelled'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	private static function checkout_link( array $link ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- see route_link().
		$input = array(
			'choice' => sanitize_text_field( wp_unslash( $_POST['wim_choice'] ?? '' ) ),
			'custom' => sanitize_text_field( wp_unslash( $_POST['wim_custom'] ?? '' ) ),
			'name'   => sanitize_text_field( wp_unslash( $_POST['wim_name'] ?? '' ) ),
			'email'  => sanitize_email( wp_unslash( $_POST['wim_email'] ?? '' ) ),
			'note'   => sanitize_textarea_field( wp_unslash( $_POST['wim_note'] ?? '' ) ),
		);
		// phpcs:enable

		$errors = array();
		$amount = WP_IM_Payment_Link::resolve_amount( $link, $input['choice'], $input['custom'] );
		if ( is_wp_error( $amount ) ) {
			$errors[] = $amount->get_error_message();
		}
		if ( '' === $input['name'] ) {
			$errors[] = __( 'Please enter your name.', 'wp-invoice-manager' );
		}
		if ( ! is_email( $input['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address — your receipt goes there.', 'wp-invoice-manager' );
		}
		if ( 'required' === $link['note'] && '' === trim( $input['note'] ) ) {
			/* translators: %s: label of the note field */
			$errors[] = sprintf( __( 'Please fill in “%s”.', 'wp-invoice-manager' ), $link['note_label'] ? $link['note_label'] : __( 'Note', 'wp-invoice-manager' ) );
		}
		if ( 'off' === $link['note'] ) {
			$input['note'] = '';
		}

		if ( $errors ) {
			self::render( array( 'kind' => 'link', 'link' => $link, 'errors' => $errors, 'input' => $input ) );
		}

		self::redirect_to_checkout( array(
			'currency'    => $link['currency'],
			'amount'      => $amount,
			'name'        => $link['title'],
			'description' => wp_html_excerpt( $link['description'], 400, '…' ),
			'email'       => $input['email'],
			'reference'   => 'wim-link-' . $link['id'],
			'metadata'    => array(
				'wim_kind' => 'link',
				'wim_link' => $link['id'],
				'wim_name' => mb_substr( $input['name'], 0, 200 ),
				'wim_note' => mb_substr( $input['note'], 0, 480 ),
			),
			'success_url' => add_query_arg( 'paid', '{CHECKOUT_SESSION_ID}', $link['url'] ),
			'cancel_url'  => add_query_arg( 'cancelled', '1', $link['url'] ),
		), array( 'kind' => 'link', 'link' => $link, 'input' => $input ) );
	}

	// ── Stripe Checkout ─────────────────────────────────────────────────────

	/**
	 * Create a Checkout Session and send the customer to it — or re-render
	 * the form with an error if Stripe refuses.
	 */
	private static function redirect_to_checkout( array $p, array $view_on_error ) {
		$product = array( 'name' => $p['name'] );
		if ( '' !== trim( (string) $p['description'] ) ) {
			$product['description'] = $p['description'];
		}

		$session = WP_IM_Stripe::create_checkout_session( array(
			'mode'                => 'payment',
			'submit_type'         => 'pay',
			'locale'              => 'auto',
			'customer_email'      => $p['email'],
			'client_reference_id' => $p['reference'],
			'line_items'          => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => strtolower( $p['currency'] ),
						'unit_amount'  => WP_IM_Stripe::to_minor( $p['amount'], $p['currency'] ),
						'product_data' => $product,
					),
				),
			),
			'metadata'            => $p['metadata'],
			'payment_intent_data' => array(
				'description' => mb_substr( $p['name'] . ' — ' . $p['metadata']['wim_name'], 0, 300 ),
				'metadata'    => $p['metadata'],
			),
			'success_url'         => $p['success_url'],
			'cancel_url'          => $p['cancel_url'],
		) );

		if ( is_wp_error( $session ) || empty( $session['url'] ) ) {
			if ( is_wp_error( $session ) ) {
				error_log( 'WP Invoice Manager: Stripe checkout failed (' . $p['reference'] . '): ' . $session->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			$view_on_error['errors'] = array( __( 'We could not start the payment. Please try again in a moment, or contact us.', 'wp-invoice-manager' ) );
			self::render( $view_on_error );
		}

		// checkout.stripe.com is outside wp_safe_redirect()'s allow-list.
		wp_redirect( $session['url'], 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * A customer is back from Stripe: verify and record the session.
	 */
	private static function return_state( $session_id, $kind, $object_id ) {
		if ( ! preg_match( '/^cs_(test|live)_[A-Za-z0-9]+$/', $session_id ) ) {
			return array( 'state' => 'form' );
		}

		$session = WP_IM_Stripe::get_checkout_session( $session_id );
		$key     = 'invoice' === $kind ? 'wim_invoice' : 'wim_link';
		if ( is_wp_error( $session ) || ( $session['metadata']['wim_kind'] ?? '' ) !== $kind || (int) ( $session['metadata'][ $key ] ?? 0 ) !== (int) $object_id ) {
			return array( 'state' => 'form', 'errors' => array( __( 'We could not confirm that payment. If you were charged, please contact us with your email address.', 'wp-invoice-manager' ) ) );
		}

		$receipt = self::record( $session );
		if ( ! $receipt ) {
			return array( 'state' => 'processing' );
		}
		return array( 'state' => 'paid', 'receipt' => $receipt );
	}

	// ── Recording ───────────────────────────────────────────────────────────

	/**
	 * Record a paid Checkout Session once.
	 *
	 * @param array $session Stripe Checkout Session.
	 * @return array|null Receipt: amount, currency, invoice_id, email, date. Null if not ours / not paid.
	 */
	public static function record( array $session ) {
		$paid = in_array( $session['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true );
		$kind = $session['metadata']['wim_kind'] ?? '';
		if ( ! $paid || empty( $session['id'] ) || ! in_array( $kind, array( 'invoice', 'link' ), true ) ) {
			return null;
		}

		$existing = self::find_recorded( $session );
		if ( $existing ) {
			return self::receipt( $session, $existing );
		}

		// Option names are unique, so only one request can add the lock.
		$lock = 'wim_stripe_lock_' . md5( $session['id'] );
		if ( ! add_option( $lock, time(), '', 'no' ) ) {
			usleep( 500000 );
			$existing = self::find_recorded( $session );
			return $existing ? self::receipt( $session, $existing ) : null;
		}

		$invoice_id = 'invoice' === $kind ? self::record_invoice_payment( $session ) : self::record_link_payment( $session );
		delete_option( $lock );

		if ( ! $invoice_id ) {
			return null;
		}

		self::send_emails( $session, $invoice_id );
		do_action( 'wp_im_online_payment_recorded', $invoice_id, $session );

		return self::receipt( $session, $invoice_id );
	}

	/**
	 * Invoice ID this session was already recorded on, or 0.
	 */
	private static function find_recorded( array $session ) {
		if ( 'invoice' === ( $session['metadata']['wim_kind'] ?? '' ) ) {
			$invoice_id = (int) ( $session['metadata']['wim_invoice'] ?? 0 );
			foreach ( ( new WP_IM_Invoice( $invoice_id ) )->get_payments() as $payment ) {
				if ( ( $payment['session'] ?? '' ) === $session['id'] ) {
					return $invoice_id;
				}
			}
			return 0;
		}
		$ids = get_posts( array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => self::INVOICE_META_SESSION, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $session['id'],            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) );
		return $ids ? (int) $ids[0] : 0;
	}

	private static function session_payment( array $session ) {
		$currency = strtoupper( $session['currency'] ?? 'USD' );
		$intent   = $session['payment_intent'] ?? '';
		$intent   = is_array( $intent ) ? ( $intent['id'] ?? '' ) : (string) $intent;
		$mode     = ! empty( $session['livemode'] ) ? 'live' : 'test';
		return array(
			'amount'  => WP_IM_Stripe::from_minor( (int) ( $session['amount_total'] ?? 0 ), $currency ),
			'date'    => current_time( 'Y-m-d' ),
			'method'  => 'stripe',
			/* translators: %s: Stripe payment reference */
			'note'    => trim( sprintf( __( 'Paid online · %s', 'wp-invoice-manager' ), $intent ) . ( 'test' === $mode ? ' (test)' : '' ) ),
			'session' => $session['id'],
			'intent'  => $intent,
			'mode'    => $mode,
		);
	}

	private static function record_invoice_payment( array $session ) {
		$invoice = WP_IM_Invoice::get( (int) ( $session['metadata']['wim_invoice'] ?? 0 ) );
		if ( ! $invoice ) {
			return 0;
		}
		$payment = self::session_payment( $session );
		if ( strtoupper( $session['currency'] ?? '' ) !== strtoupper( $invoice['currency'] ) ) {
			$payment['note'] .= ' · ' . strtoupper( $session['currency'] );
		}
		return WP_IM_Invoice::add_payment( $invoice['post_id'], $payment ) ? $invoice['post_id'] : 0;
	}

	/**
	 * A link was paid: create a paid invoice for it.
	 */
	private static function record_link_payment( array $session ) {
		$link = WP_IM_Payment_Link::get( (int) ( $session['metadata']['wim_link'] ?? 0 ) );
		if ( ! $link ) {
			return 0;
		}

		$details = isset( $session['customer_details'] ) && is_array( $session['customer_details'] ) ? $session['customer_details'] : array();
		$email   = sanitize_email( $details['email'] ?? ( $session['customer_email'] ?? '' ) );
		$name    = sanitize_text_field( $session['metadata']['wim_name'] ?? ( $details['name'] ?? '' ) );
		$note    = sanitize_textarea_field( $session['metadata']['wim_note'] ?? '' );
		$payment = self::session_payment( $session );

		// Client details: the link's customer, else an existing customer with
		// this email (keeps their phone/address), else what the payer typed.
		$customer = $link['customer_id'] ? WP_IM_Customer::get( $link['customer_id'] ) : null;
		if ( ! $customer && $email ) {
			foreach ( WP_IM_Customer::search( $email ) as $match ) {
				if ( strtolower( (string) $match['email'] ) === strtolower( $email ) ) {
					$customer = $match;
					break;
				}
			}
		}
		$client = $customer
			? array( 'client_name' => $customer['name'], 'client_email' => $customer['email'] ? $customer['email'] : $email, 'client_phone' => $customer['phone'], 'client_address' => $customer['address'] )
			: array( 'client_name' => $name ? $name : $email, 'client_email' => $email, 'client_phone' => '', 'client_address' => '' );

		$notes = __( 'Paid online by card through a payment link.', 'wp-invoice-manager' );
		if ( $note ) {
			$notes .= '<br>' . esc_html( ( $link['note_label'] ? $link['note_label'] : __( 'Note', 'wp-invoice-manager' ) ) . ': ' . $note );
		}

		$today      = current_time( 'Y-m-d' );
		$invoice_id = WP_IM_Invoice::create( array_merge( $client, array(
			'status'         => 'paid',
			'invoice_date'   => $today,
			'due_date'       => $today,
			'biller_name'    => get_option( 'wp_im_company_name', get_bloginfo( 'name' ) ),
			'biller_email'   => get_option( 'wp_im_company_email', get_option( 'admin_email' ) ),
			'biller_phone'   => get_option( 'wp_im_company_phone', '' ),
			'biller_address' => get_option( 'wp_im_company_address', '' ),
			'currency'       => strtoupper( $session['currency'] ?? $link['currency'] ),
			'notes'          => $notes,
			'terms_selected' => array(),
			'items'          => array(
				array(
					'description' => $link['title'],
					'quantity'    => 1,
					'unit_price'  => $payment['amount'],
					'tax_rate'    => 0,
				),
			),
		) ) );

		if ( is_wp_error( $invoice_id ) ) {
			return 0;
		}

		update_post_meta( $invoice_id, WP_IM_Payment_Link::INVOICE_META_LINK, $link['id'] );
		update_post_meta( $invoice_id, self::INVOICE_META_SESSION, $session['id'] );
		WP_IM_Invoice::add_payment( $invoice_id, $payment );

		if ( ! $customer ) {
			WP_IM_Customer::upsert_from_invoice_data( $client );
		}
		if ( $link['single_use'] && ! $link['used_at'] ) {
			update_post_meta( $link['id'], '_wim_link_used_at', time() );
		}

		return $invoice_id;
	}

	private static function receipt( array $session, $invoice_id ) {
		$invoice = WP_IM_Invoice::get( $invoice_id );
		$details = isset( $session['customer_details'] ) && is_array( $session['customer_details'] ) ? $session['customer_details'] : array();
		$currency = strtoupper( $session['currency'] ?? ( $invoice['currency'] ?? 'USD' ) );
		return array(
			'amount'     => WP_IM_Stripe::from_minor( (int) ( $session['amount_total'] ?? 0 ), $currency ),
			'currency'   => $currency,
			'invoice_id' => (int) $invoice_id,
			'number'     => $invoice ? $invoice['number'] : '',
			'balance'    => $invoice ? $invoice['totals']['balance'] : 0,
			'status'     => $invoice ? $invoice['status'] : '',
			'email'      => sanitize_email( $details['email'] ?? ( $session['customer_email'] ?? '' ) ),
			'date'       => time(),
			'mode'       => ! empty( $session['livemode'] ) ? 'live' : 'test',
		);
	}

	// ── Emails ──────────────────────────────────────────────────────────────

	private static function send_emails( array $session, $invoice_id ) {
		$invoice = WP_IM_Invoice::get( $invoice_id );
		if ( ! $invoice ) {
			return;
		}
		$kind    = $session['metadata']['wim_kind'];
		$r       = self::receipt( $session, $invoice_id );
		$money   = WP_IM_Stripe::format( $r['amount'], $r['currency'] );
		$view    = WP_IM_Invoice::get_share_url( $invoice_id );
		$test    = 'test' === $r['mode'];
		$link    = 'link' === $kind ? WP_IM_Payment_Link::get( (int) $session['metadata']['wim_link'] ) : null;
		$company = get_option( 'wp_im_company_name', get_bloginfo( 'name' ) );
		$payer   = $session['metadata']['wim_name'] ?? $invoice['client_name'];

		// Customer receipt.
		if ( is_email( $r['email'] ) ) {
			$rows = array( __( 'Amount paid', 'wp-invoice-manager' ) => $money );
			if ( $link ) {
				$rows[ __( 'For', 'wp-invoice-manager' ) ] = $link['title'];
			}
			$rows[ __( 'Receipt / invoice', 'wp-invoice-manager' ) ] = $invoice['number'];
			if ( 'invoice' === $kind && $invoice['totals']['balance'] > 0 && 'paid' !== $invoice['status'] ) {
				$rows[ __( 'Balance still due', 'wp-invoice-manager' ) ] = WP_IM_Stripe::format( $invoice['totals']['balance'], $invoice['currency'] );
			}
			$rows[ __( 'Date', 'wp-invoice-manager' ) ] = wp_date( get_option( 'date_format' ) );

			self::mail(
				$r['email'],
				( $test ? '[TEST] ' : '' ) . ( 'invoice' === $kind
					/* translators: %s: invoice number */
					? sprintf( __( 'Payment received — Invoice %s', 'wp-invoice-manager' ), $invoice['number'] )
					/* translators: %s: company name */
					: sprintf( __( 'Your payment to %s', 'wp-invoice-manager' ), $company ) ),
				self::email_html(
					/* translators: %s: customer name */
					sprintf( __( 'Thank you, %s!', 'wp-invoice-manager' ), $payer ),
					__( 'We have received your payment. The details are below for your records — the payment was processed securely by Stripe.', 'wp-invoice-manager' ),
					$rows,
					$view,
					__( 'View receipt', 'wp-invoice-manager' )
				)
			);
		}

		// Notice to the business.
		$rows = array(
			__( 'Amount', 'wp-invoice-manager' )   => $money . ( $test ? ' (' . __( 'test mode', 'wp-invoice-manager' ) . ')' : '' ),
			__( 'Customer', 'wp-invoice-manager' ) => trim( $payer . ' <' . $r['email'] . '>' ),
			__( 'Invoice', 'wp-invoice-manager' )  => $invoice['number'] . ' · ' . ( WP_IM_Invoice::get_statuses()[ $invoice['status'] ] ?? $invoice['status'] ),
		);
		if ( $link ) {
			$rows[ __( 'Payment link', 'wp-invoice-manager' ) ] = $link['title'];
		}
		if ( ! empty( $session['metadata']['wim_note'] ) ) {
			$rows[ __( 'Note', 'wp-invoice-manager' ) ] = $session['metadata']['wim_note'];
		}
		self::mail(
			WP_IM_Stripe::notify_email(),
			( $test ? '[TEST] ' : '' ) . sprintf(
				/* translators: 1: amount, 2: invoice number */
				__( 'New online payment: %1$s — %2$s', 'wp-invoice-manager' ),
				$money,
				$link ? $link['title'] : $invoice['number']
			),
			self::email_html(
				__( 'New online payment', 'wp-invoice-manager' ),
				__( 'A customer just paid online.', 'wp-invoice-manager' ),
				$rows,
				add_query_arg( array( 'page' => 'wp-im-new-invoice', 'invoice_id' => $invoice_id ), admin_url( 'admin.php' ) ),
				__( 'Open invoice', 'wp-invoice-manager' )
			)
		);
	}

	private static function mail( $to, $subject, $html ) {
		if ( ! is_email( $to ) ) {
			return;
		}
		$type = static function () {
			return 'text/html';
		};
		add_filter( 'wp_mail_content_type', $type );
		wp_mail( $to, $subject, $html );
		remove_filter( 'wp_mail_content_type', $type );
	}

	/**
	 * Branded HTML email, same look as "Send to Client".
	 */
	public static function email_html( $heading, $intro, array $rows, $button_url = '', $button_label = '' ) {
		$accent      = get_option( 'wp_im_primary_color', '#e94560' );
		$header_bg   = get_option( 'wp_im_header_color', '#1a1a2e' );
		$header_text = get_option( 'wp_im_header_text_color', WP_IM_Invoice::readable_text_color( $header_bg ) );
		$logo_url    = get_option( 'wp_im_company_logo_url', '' );
		$logo_mode   = get_option( 'wp_im_logo_display_mode', 'image' );
		$company     = get_option( 'wp_im_company_name', get_bloginfo( 'name' ) );
		$show_image  = $logo_url && 'text' !== $logo_mode;
		$show_text   = ! $logo_url || 'image' !== $logo_mode;

		ob_start();
		?>
		<div style="font-family:'Segoe UI',Arial,sans-serif;background:#f8f9fc;padding:48px 20px">
			<div style="max-width:620px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.1)">
				<div style="background:<?php echo esc_attr( $header_bg ); ?>;padding:36px 44px;color:<?php echo esc_attr( $header_text ); ?>">
					<?php if ( $show_image ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company ); ?>" style="display:inline-block;vertical-align:middle;max-height:52px;width:auto<?php echo $show_text ? ';margin-right:16px' : ''; ?>">
					<?php endif; ?>
					<?php if ( $show_text ) : ?>
						<span style="display:inline-block;vertical-align:middle;font-size:24px;font-weight:800;letter-spacing:-.02em"><?php echo esc_html( $company ); ?></span>
					<?php endif; ?>
				</div>
				<div style="padding:40px 44px">
					<p style="margin:0 0 14px;color:#1e293b;font-size:20px;font-weight:700"><?php echo esc_html( $heading ); ?></p>
					<p style="margin:0 0 26px;color:#475569;font-size:15px;line-height:1.7"><?php echo esc_html( $intro ); ?></p>
					<table style="width:100%;border-collapse:collapse;margin-bottom:30px">
						<?php $first = true; foreach ( $rows as $label => $value ) : ?>
							<tr>
								<td style="padding:13px 0;color:#64748b;font-size:14.5px;<?php echo $first ? '' : 'border-top:1px solid #f1f5f9'; ?>"><?php echo esc_html( $label ); ?></td>
								<td style="padding:13px 0;color:#1e293b;font-size:15px;text-align:right;font-weight:700;<?php echo $first ? '' : 'border-top:1px solid #f1f5f9'; ?>"><?php echo esc_html( $value ); ?></td>
							</tr>
						<?php $first = false; endforeach; ?>
					</table>
					<?php if ( $button_url ) : ?>
						<div style="text-align:center">
							<a href="<?php echo esc_url( $button_url ); ?>" style="display:inline-block;background:<?php echo esc_attr( $accent ); ?>;color:#fff;padding:15px 40px;border-radius:8px;font-size:15px;font-weight:700;text-decoration:none"><?php echo esc_html( $button_label ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// ── Webhook ─────────────────────────────────────────────────────────────

	public static function register_webhook_route() {
		register_rest_route( 'wim/v1', '/stripe-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_webhook' ),
			'permission_callback' => '__return_true', // Authenticated by the Stripe signature.
		) );
	}

	public static function handle_webhook( WP_REST_Request $request ) {
		$payload = $request->get_body();
		$event   = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'bad payload' ), 400 );
		}

		$webhook = WP_IM_Stripe::webhook( ! empty( $event['livemode'] ) ? 'live' : 'test' );
		if ( ! WP_IM_Stripe::verify_signature( $payload, (string) $request->get_header( 'stripe_signature' ), $webhook['secret'] ?? '' ) ) {
			return new WP_REST_Response( array( 'error' => 'bad signature' ), 400 );
		}

		if ( in_array( $event['type'], array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded' ), true ) ) {
			$session = $event['data']['object'] ?? array();
			if ( ! empty( $session['metadata']['wim_kind'] ) ) {
				self::record( $session );
			}
		}
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	// ── Settings actions ────────────────────────────────────────────────────

	public static function handle_create_webhook() {
		check_admin_referer( 'wp_im_stripe_webhook' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		$result = WP_IM_Stripe::create_webhook( WP_IM_Stripe::mode() );
		self::back_to_settings( is_wp_error( $result ) ? $result->get_error_message() : '', 'stripe_webhook' );
	}

	public static function handle_check_connection() {
		check_admin_referer( 'wp_im_stripe_check' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		$account = WP_IM_Stripe::get_account();
		if ( ! is_wp_error( $account ) ) {
			set_transient( 'wim_stripe_account_' . WP_IM_Stripe::mode(), array(
				'id'       => $account['id'] ?? '',
				'name'     => $account['settings']['dashboard']['display_name'] ?? ( $account['business_profile']['name'] ?? '' ),
				'country'  => strtoupper( $account['country'] ?? '' ),
				'currency' => strtoupper( $account['default_currency'] ?? '' ),
				'charges'  => ! empty( $account['charges_enabled'] ),
			), DAY_IN_SECONDS );
		}
		self::back_to_settings( is_wp_error( $account ) ? $account->get_error_message() : '', 'stripe_connected' );
	}

	private static function back_to_settings( $error, $ok_message ) {
		if ( $error ) {
			set_transient( 'wim_stripe_error', $error, 60 );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'wp-im-settings', 'message' => $error ? 'stripe_error' : $ok_message ), admin_url( 'admin.php' ) ) . '#wim-online-payments' );
		exit;
	}

	// ── Output ──────────────────────────────────────────────────────────────

	private static function no_cache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}

	/**
	 * Output the payment page and stop.
	 *
	 * @param array $view kind (invoice|link), state, invoice, link, errors, input, status, receipt, cancelled
	 */
	private static function render( array $view ) {
		$view          = wp_parse_args( $view, array(
			'kind'      => 'link',
			'state'     => 'form',
			'invoice'   => null,
			'link'      => null,
			'errors'    => array(),
			'input'     => array(),
			'status'    => '',
			'receipt'   => null,
			'cancelled' => false,
		) );
		$defaults      = array( 'choice' => '', 'custom' => '', 'name' => '', 'email' => '', 'note' => '' );
		if ( 'invoice' === $view['kind'] && $view['invoice'] ) {
			$defaults['name']   = $view['invoice']['client_name'];
			$defaults['email']  = $view['invoice']['client_email'];
			$defaults['custom'] = number_format( $view['invoice']['totals']['balance'], WP_IM_Stripe::is_zero_decimal( $view['invoice']['currency'] ) ? 0 : 2, '.', '' );
		} elseif ( 'link' === $view['kind'] && $view['link'] && $view['link']['customer_id'] ) {
			$customer = WP_IM_Customer::get( $view['link']['customer_id'] );
			if ( $customer ) {
				$defaults['name']  = $customer['name'];
				$defaults['email'] = $customer['email'];
			}
		}
		$view['input'] = wp_parse_args( array_filter( $view['input'], 'strlen' ), $defaults );

		include WP_IM_PLUGIN_DIR . 'templates/pay-page.php';
		exit;
	}
}
