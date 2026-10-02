<?php
/**
 * Invoice model – handles all CRUD and business logic.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_Invoice {

	// ── Meta keys ────────────────────────────────────────────────────────────
	const META_NUMBER        = '_invoice_number';
	const META_STATUS        = '_invoice_status';
	const META_DATE          = '_invoice_date';
	const META_DUE_DATE      = '_invoice_due_date';
	const META_CLIENT_NAME   = '_invoice_client_name';
	const META_CLIENT_EMAIL  = '_invoice_client_email';
	const META_CLIENT_PHONE  = '_invoice_client_phone';
	const META_CLIENT_ADDRESS= '_invoice_client_address';
	const META_BILLER_NAME   = '_invoice_biller_name';
	const META_BILLER_EMAIL  = '_invoice_biller_email';
	const META_BILLER_PHONE  = '_invoice_biller_phone';
	const META_BILLER_ADDRESS= '_invoice_biller_address';
	const META_CURRENCY      = '_invoice_currency';
	const META_NOTES         = '_invoice_notes';
	const META_DISCOUNT      = '_invoice_discount';
	const META_DISCOUNT_TYPE = '_invoice_discount_type';
	const META_TERMS_SELECTED= '_invoice_terms_selected';
	const META_SHARE_TOKEN   = '_invoice_share_token';
	const META_PAYMENTS      = '_invoice_payments';
	const META_RECURRING_FREQUENCY = '_invoice_recurring_frequency';
	const META_RECURRING_NEXT_DATE = '_invoice_recurring_next_date';
	const META_RECURRING_END_DATE  = '_invoice_recurring_end_date';
	const META_RECURRING_PARENT    = '_invoice_recurring_parent';
	const META_SHARE_VIEWS         = '_invoice_share_views';
	const META_LAST_REMINDER_SENT  = '_invoice_last_reminder_sent';

	/** @var int */
	private $post_id;

	/**
	 * Constructor.
	 *
	 * @param int $post_id Existing invoice post ID, or 0 for new.
	 */
	public function __construct( $post_id = 0 ) {
		$this->post_id = absint( $post_id );
	}

	// ────────────────────────────────────────────────────────────────────────
	// Static factory helpers
	// ────────────────────────────────────────────────────────────────────────

	/**
	 * Create a new invoice from submitted data.
	 *
	 * @param array $data Sanitised POST data.
	 * @return int|WP_Error New post ID or error.
	 */
	public static function create( array $data ) {
		// Generate invoice number. The prefix may contain a {year} token; when
		// "reset numbering every year" is on, the counter itself is also kept
		// per-year (a fresh sequence starting at 1 each year) instead of one
		// counter running forever — e.g. INV-2026-00001, INV-2027-00001, …
		$prefix_raw   = get_option( 'wp_im_invoice_prefix', 'INV-' );
		$year         = current_time( 'Y' );
		$prefix       = str_replace( '{year}', $year, $prefix_raw );
		$reset_yearly = get_option( 'wp_im_reset_number_yearly', false );
		$counter_key  = $reset_yearly ? 'wp_im_next_invoice_number_' . $year : 'wp_im_next_invoice_number';
		$number       = get_option( $counter_key, 1 );
		$inv_num      = $prefix . str_pad( $number, 5, '0', STR_PAD_LEFT );

		$post_id = wp_insert_post( array(
			'post_type'   => WP_IM_Post_Type::POST_TYPE,
			'post_title'  => sanitize_text_field( $inv_num ),
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Increment counter
		update_option( $counter_key, $number + 1 );

		$invoice = new self( $post_id );
		$invoice->save_meta( $data, $inv_num );
		$invoice->save_items( $data['items'] ?? array() );

		return $post_id;
	}

	/**
	 * Update an existing invoice.
	 *
	 * @param int   $post_id Invoice post ID.
	 * @param array $data    Sanitised POST data.
	 * @return bool
	 */
	public static function update( $post_id, array $data ) {
		$post_id = absint( $post_id );
		if ( ! get_post( $post_id ) ) {
			return false;
		}

		$invoice = new self( $post_id );
		$inv_num = get_post_meta( $post_id, self::META_NUMBER, true );
		$invoice->save_meta( $data, $inv_num );
		$invoice->save_items( $data['items'] ?? array() );

		return true;
	}

	/**
	 * Delete an invoice and its items.
	 *
	 * @param int $post_id
	 * @return bool
	 */
	public static function delete( $post_id ) {
		global $wpdb;
		$post_id = absint( $post_id );

		wp_delete_post( $post_id, true );

		$wpdb->delete(
			$wpdb->prefix . 'invoice_items',
			array( 'invoice_id' => $post_id ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Duplicate an invoice: copies client, biller, items, discount, currency,
	 * notes and terms into a brand-new draft dated today. Recurrence settings,
	 * payments, and share/view history are intentionally not copied.
	 *
	 * @param int $post_id Source invoice.
	 * @return int|false New invoice post ID, or false if the source is gone.
	 */
	public static function duplicate( $post_id ) {
		$source = self::get( absint( $post_id ) );
		if ( ! $source ) {
			return false;
		}

		$today  = current_time( 'Y-m-d' );
		$new_id = self::create( array(
			'status'         => 'draft',
			'invoice_date'   => $today,
			'due_date'       => gmdate( 'Y-m-d', strtotime( "{$today} +30 days" ) ),
			'client_name'    => $source['client_name'],
			'client_email'   => $source['client_email'],
			'client_phone'   => $source['client_phone'],
			'client_address' => $source['client_address'],
			'biller_name'    => $source['biller_name'],
			'biller_email'   => $source['biller_email'],
			'biller_phone'   => $source['biller_phone'],
			'biller_address' => $source['biller_address'],
			'currency'       => $source['currency'],
			'notes'          => $source['notes'],
			'discount'       => $source['discount'],
			'discount_type'  => $source['discount_type'],
			'terms_selected' => $source['terms_selected'],
			'items'          => $source['items'],
		) );

		return is_wp_error( $new_id ) ? false : $new_id;
	}

	/**
	 * Get all invoice posts with meta.
	 *
	 * @param array $args WP_Query args override.
	 * @return WP_Post[]
	 */
	public static function get_all( array $args = array() ) {
		$defaults = array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		return get_posts( wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Get single invoice data as array.
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	public static function get( $post_id ) {
		$post = get_post( absint( $post_id ) );
		if ( ! $post || WP_IM_Post_Type::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$invoice = new self( $post->ID );
		return $invoice->to_array();
	}

	// ────────────────────────────────────────────────────────────────────────
	// Instance helpers
	// ────────────────────────────────────────────────────────────────────────

	/**
	 * Persist all meta fields.
	 *
	 * @param array  $data
	 * @param string $inv_num
	 */
	private function save_meta( array $data, $inv_num ) {
		$map = array(
			self::META_NUMBER         => $inv_num,
			self::META_STATUS         => sanitize_text_field( $data['status'] ?? 'draft' ),
			self::META_DATE           => sanitize_text_field( $data['invoice_date'] ?? '' ),
			self::META_DUE_DATE       => sanitize_text_field( $data['due_date'] ?? '' ),
			self::META_CLIENT_NAME    => sanitize_text_field( $data['client_name'] ?? '' ),
			self::META_CLIENT_EMAIL   => sanitize_email( $data['client_email'] ?? '' ),
			self::META_CLIENT_PHONE   => sanitize_text_field( $data['client_phone'] ?? '' ),
			self::META_CLIENT_ADDRESS => sanitize_textarea_field( $data['client_address'] ?? '' ),
			self::META_BILLER_NAME    => sanitize_text_field( $data['biller_name'] ?? '' ),
			self::META_BILLER_EMAIL   => sanitize_email( $data['biller_email'] ?? '' ),
			self::META_BILLER_PHONE   => sanitize_text_field( $data['biller_phone'] ?? '' ),
			self::META_BILLER_ADDRESS => sanitize_textarea_field( $data['biller_address'] ?? '' ),
			self::META_CURRENCY       => sanitize_text_field( $data['currency'] ?? 'USD' ),
			self::META_NOTES          => trim( wp_kses( (string) ( $data['notes'] ?? '' ), self::notes_allowed_html() ) ),
			self::META_DISCOUNT       => floatval( $data['discount'] ?? 0 ),
			self::META_DISCOUNT_TYPE  => 'percent' === ( $data['discount_type'] ?? 'flat' ) ? 'percent' : 'flat',
			self::META_TERMS_SELECTED => array_map( 'absint', (array) ( $data['terms_selected'] ?? array() ) ),
			self::META_RECURRING_FREQUENCY => ! empty( $data['is_recurring'] ) ? sanitize_text_field( $data['recurring_frequency'] ?? 'monthly' ) : '',
			self::META_RECURRING_NEXT_DATE  => sanitize_text_field( $data['recurring_next_date'] ?? '' ),
			self::META_RECURRING_END_DATE   => sanitize_text_field( $data['recurring_end_date'] ?? '' ),
		);

		foreach ( $map as $key => $value ) {
			update_post_meta( $this->post_id, $key, $value );
		}
	}

	/**
	 * Save line items to custom table.
	 *
	 * @param array $items
	 */
	private function save_items( array $items ) {
		global $wpdb;
		$table = $wpdb->prefix . 'invoice_items';

		// Delete existing items for this invoice
		$wpdb->delete( $table, array( 'invoice_id' => $this->post_id ), array( '%d' ) );

		foreach ( $items as $item ) {
			$description = wp_kses( (string) ( $item['description'] ?? '' ), self::description_allowed_html() );
			$description = trim( $description );
			$quantity    = floatval( $item['quantity'] ?? 0 );
			$unit_price  = floatval( $item['unit_price'] ?? 0 );

			// Skip a row only if it's entirely empty — don't silently drop a
			// row just because the description was left blank while a
			// quantity/price was filled in (that used to make the saved
			// total disagree with what the form showed before saving).
			if ( '' === $description && 0.0 === $quantity && 0.0 === $unit_price ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'invoice_id'  => $this->post_id,
					'description' => $description,
					'quantity'    => $quantity,
					'unit_price'  => $unit_price,
					'tax_rate'    => floatval( $item['tax_rate'] ?? 0 ),
				),
				array( '%d', '%s', '%f', '%f', '%f' )
			);
		}
	}

	/**
	 * Fetch line items from DB.
	 *
	 * @return array
	 */
	public function get_items() {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}invoice_items WHERE invoice_id = %d ORDER BY id ASC",
				$this->post_id
			),
			ARRAY_A
		);
	}

	/**
	 * Calculate totals.
	 *
	 * @return array { subtotal, tax_total, discount, total, paid, balance }
	 */
	public function calculate_totals() {
		$items     = $this->get_items();
		$subtotal  = 0.0;
		$tax_total = 0.0;

		foreach ( $items as $item ) {
			$line      = floatval( $item['quantity'] ) * floatval( $item['unit_price'] );
			$subtotal += $line;
			$tax_total += $line * ( floatval( $item['tax_rate'] ) / 100 );
		}

		$discount_raw  = floatval( get_post_meta( $this->post_id, self::META_DISCOUNT, true ) );
		$discount_type = get_post_meta( $this->post_id, self::META_DISCOUNT_TYPE, true );
		$discount      = 'percent' === $discount_type ? ( $subtotal * $discount_raw / 100 ) : $discount_raw;
		$total         = max( 0, $subtotal + $tax_total - $discount );

		$paid = 0.0;
		foreach ( $this->get_payments() as $payment ) {
			$paid += floatval( $payment['amount'] );
		}

		return array(
			'subtotal'  => $subtotal,
			'tax_total' => $tax_total,
			'discount'  => $discount,
			'total'     => $total,
			'paid'      => $paid,
			'balance'   => max( 0, $total - $paid ),
		);
	}

	/**
	 * Fetch recorded payments for this invoice, newest first.
	 *
	 * @return array[]
	 */
	public function get_payments() {
		$payments = get_post_meta( $this->post_id, self::META_PAYMENTS, true );
		if ( ! is_array( $payments ) ) {
			return array();
		}
		$payments = array_values( array_filter( $payments, 'is_array' ) );
		usort( $payments, function ( $a, $b ) {
			return strcmp( $b['date'] ?? '', $a['date'] ?? '' );
		} );
		return $payments;
	}

	/**
	 * Record a payment against an invoice (a deposit or a balance payment —
	 * there's no distinction, just a running log) and auto-update its status.
	 *
	 * @param int   $post_id
	 * @param array $data { amount, date, method, note }
	 * @return string|false New payment id, or false if the amount was invalid.
	 */
	public static function add_payment( $post_id, array $data ) {
		$post_id = absint( $post_id );
		$amount  = floatval( $data['amount'] ?? 0 );

		if ( $amount <= 0 ) {
			return false;
		}

		$payment = array(
			'id'     => wp_generate_password( 8, false, false ),
			'amount' => $amount,
			'date'   => sanitize_text_field( $data['date'] ?? current_time( 'Y-m-d' ) ),
			'method' => sanitize_text_field( $data['method'] ?? '' ),
			'note'   => sanitize_text_field( $data['note'] ?? '' ),
		);

		// Note: (array) cast on '' (no meta yet) would give array(0 => ''),
		// a phantom non-array entry — check is_array() explicitly instead.
		$payments = get_post_meta( $post_id, self::META_PAYMENTS, true );
		if ( ! is_array( $payments ) ) {
			$payments = array();
		}
		$payments[] = $payment;
		update_post_meta( $post_id, self::META_PAYMENTS, $payments );

		self::recompute_status( $post_id );

		return $payment['id'];
	}

	/**
	 * Remove a recorded payment and auto-update the invoice's status.
	 *
	 * @param int    $post_id
	 * @param string $payment_id
	 * @return bool
	 */
	public static function delete_payment( $post_id, $payment_id ) {
		$post_id  = absint( $post_id );
		$payments = get_post_meta( $post_id, self::META_PAYMENTS, true );
		if ( ! is_array( $payments ) ) {
			$payments = array();
		}

		$payments = array_values( array_filter( $payments, function ( $p ) use ( $payment_id ) {
			return is_array( $p ) && ( $p['id'] ?? '' ) !== $payment_id;
		} ) );

		update_post_meta( $post_id, self::META_PAYMENTS, $payments );
		self::recompute_status( $post_id );

		return true;
	}

	/**
	 * Re-derive an invoice's status from how much of it has been paid.
	 * Never touches a "cancelled" invoice. Only falls back to "sent" (not
	 * further back to "draft") when payments are removed down to zero.
	 *
	 * @param int $post_id
	 */
	private static function recompute_status( $post_id ) {
		$post_id = absint( $post_id );
		$status  = get_post_meta( $post_id, self::META_STATUS, true );

		if ( 'cancelled' === $status ) {
			return;
		}

		$totals = ( new self( $post_id ) )->calculate_totals();

		if ( $totals['total'] > 0 && $totals['paid'] >= $totals['total'] ) {
			$new_status = 'paid';
		} elseif ( $totals['paid'] > 0 ) {
			$new_status = 'partial';
		} elseif ( in_array( $status, array( 'paid', 'partial' ), true ) ) {
			$new_status = 'sent';
		} else {
			$new_status = $status;
		}

		if ( $new_status !== $status ) {
			update_post_meta( $post_id, self::META_STATUS, $new_status );
		}
	}

	/**
	 * Payment methods offered on the "Record Payment" form.
	 *
	 * @return array
	 */
	public static function get_payment_methods() {
		return array(
			'bank'  => __( 'Bank Transfer', 'wp-invoice-manager' ),
			'bkash' => __( 'bKash', 'wp-invoice-manager' ),
			'cash'  => __( 'Cash', 'wp-invoice-manager' ),
			'card'  => __( 'Card', 'wp-invoice-manager' ),
			'other' => __( 'Other', 'wp-invoice-manager' ),
		);
	}

	/**
	 * Frequencies offered for a recurring invoice.
	 *
	 * @return array
	 */
	public static function get_recurring_frequencies() {
		return array(
			'monthly'   => __( 'Monthly', 'wp-invoice-manager' ),
			'quarterly' => __( 'Quarterly (every 3 months)', 'wp-invoice-manager' ),
			'yearly'    => __( 'Yearly', 'wp-invoice-manager' ),
		);
	}

	/**
	 * Advance a Y-m-d date by one recurring-frequency interval.
	 *
	 * @param string $date
	 * @param string $frequency
	 * @return string
	 */
	private static function advance_date( $date, $frequency ) {
		$intervals = array(
			'monthly'   => '+1 month',
			'quarterly' => '+3 months',
			'yearly'    => '+1 year',
		);
		$interval = $intervals[ $frequency ] ?? '+1 month';
		return gmdate( 'Y-m-d', strtotime( $date . ' ' . $interval ) );
	}

	/**
	 * Find recurring-invoice templates that are due to generate their next
	 * invoice today (or earlier — e.g. the site was offline when it was due).
	 *
	 * @return int[] Post IDs.
	 */
	public static function get_due_recurring_templates() {
		$today = current_time( 'Y-m-d' );

		return get_posts( array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => self::META_RECURRING_FREQUENCY,
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => self::META_RECURRING_NEXT_DATE,
					'value'   => $today,
					'compare' => '<=',
					'type'    => 'DATE',
				),
			),
		) );
	}

	/**
	 * Generate the next invoice from a recurring template: copies client,
	 * biller, items, discount, currency, notes and terms; the new invoice
	 * starts as a draft dated today, with the same invoice→due-date gap as
	 * the template. Advances the template's own next-run date, and turns
	 * off its recurrence once past its optional end date.
	 *
	 * @param int $template_id
	 * @return int|false New invoice post ID, or false if the template is gone.
	 */
	public static function generate_from_recurring( $template_id ) {
		$template_id = absint( $template_id );
		$template    = self::get( $template_id );

		if ( ! $template ) {
			return false;
		}

		$today       = current_time( 'Y-m-d' );
		$offset_days = 30;
		if ( $template['invoice_date'] && $template['due_date'] ) {
			$diff = ( strtotime( $template['due_date'] ) - strtotime( $template['invoice_date'] ) ) / DAY_IN_SECONDS;
			if ( $diff > 0 ) {
				$offset_days = (int) $diff;
			}
		}

		$new_id = self::create( array(
			'status'         => 'draft',
			'invoice_date'   => $today,
			'due_date'       => gmdate( 'Y-m-d', strtotime( "{$today} +{$offset_days} days" ) ),
			'client_name'    => $template['client_name'],
			'client_email'   => $template['client_email'],
			'client_phone'   => $template['client_phone'],
			'client_address' => $template['client_address'],
			'biller_name'    => $template['biller_name'],
			'biller_email'   => $template['biller_email'],
			'biller_phone'   => $template['biller_phone'],
			'biller_address' => $template['biller_address'],
			'currency'       => $template['currency'],
			'notes'          => $template['notes'],
			'discount'       => $template['discount'],
			'terms_selected' => $template['terms_selected'],
			'items'          => $template['items'],
		) );

		if ( is_wp_error( $new_id ) ) {
			return false;
		}

		update_post_meta( $new_id, self::META_RECURRING_PARENT, $template_id );

		$frequency = get_post_meta( $template_id, self::META_RECURRING_FREQUENCY, true );
		$next_date = self::advance_date( $today, $frequency );
		update_post_meta( $template_id, self::META_RECURRING_NEXT_DATE, $next_date );

		$end_date = get_post_meta( $template_id, self::META_RECURRING_END_DATE, true );
		if ( $end_date && strtotime( $next_date ) > strtotime( $end_date ) ) {
			update_post_meta( $template_id, self::META_RECURRING_FREQUENCY, '' );
		}

		return $new_id;
	}

	/**
	 * Find sent/partially-paid invoices whose due date has passed — these
	 * should flip to "overdue" automatically rather than sitting stale.
	 *
	 * @return int[] Post IDs.
	 */
	public static function get_newly_overdue_ids() {
		$today = current_time( 'Y-m-d' );

		return get_posts( array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				array(
					'key'     => self::META_STATUS,
					'value'   => array( 'sent', 'partial' ),
					'compare' => 'IN',
				),
				array(
					'key'     => self::META_DUE_DATE,
					'value'   => '',
					'compare' => '!=',
				),
				array(
					'key'     => self::META_DUE_DATE,
					'value'   => $today,
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		) );
	}

	/**
	 * Find overdue invoices due a reminder email — one with a client email,
	 * and either never reminded or not reminded in the last 7 days.
	 *
	 * @return int[] Post IDs.
	 */
	public static function get_overdue_for_reminder() {
		$today = current_time( 'Y-m-d' );
		$ids   = get_posts( array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => self::META_STATUS,
					'value' => 'overdue',
				),
				array(
					'key'     => self::META_CLIENT_EMAIL,
					'value'   => '',
					'compare' => '!=',
				),
			),
		) );

		$due = array();
		foreach ( $ids as $id ) {
			$last_sent = get_post_meta( $id, self::META_LAST_REMINDER_SENT, true );
			if ( ! $last_sent || ( strtotime( $today ) - strtotime( $last_sent ) ) >= 7 * DAY_IN_SECONDS ) {
				$due[] = $id;
			}
		}
		return $due;
	}

	/**
	 * Per-currency summary across all invoices — deliberately never sums
	 * amounts across different currencies together.
	 *
	 * @return array currency => { invoiced, paid, outstanding, count }
	 */
	public static function get_stats_by_currency() {
		$stats = array();
		foreach ( self::get_all() as $post ) {
			$inv = self::get( $post->ID );
			$cur = $inv['currency'];
			if ( ! isset( $stats[ $cur ] ) ) {
				$stats[ $cur ] = array( 'invoiced' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'count' => 0 );
			}
			$stats[ $cur ]['count']++;
			if ( 'cancelled' === $inv['status'] ) {
				continue;
			}
			$stats[ $cur ]['invoiced']    += $inv['totals']['total'];
			$stats[ $cur ]['paid']        += $inv['totals']['paid'];
			$stats[ $cur ]['outstanding'] += $inv['totals']['balance'];
		}
		return $stats;
	}

	/**
	 * Revenue (money actually received) per month for the last N months,
	 * bucketed by currency so different currencies are never added together.
	 *
	 * @param int $months
	 * @return array { months: string[], by_currency: array currency => [ 'Y-m' => amount ] }
	 */
	public static function get_monthly_revenue( $months = 6 ) {
		$keys = array();
		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$keys[] = gmdate( 'Y-m', strtotime( "-{$i} months" ) );
		}

		$by_currency = array();
		foreach ( self::get_all() as $post ) {
			$currency = get_post_meta( $post->ID, self::META_CURRENCY, true ) ?: 'USD';
			$invoice  = new self( $post->ID );
			foreach ( $invoice->get_payments() as $payment ) {
				$month = substr( $payment['date'], 0, 7 );
				if ( ! in_array( $month, $keys, true ) ) {
					continue;
				}
				if ( ! isset( $by_currency[ $currency ] ) ) {
					$by_currency[ $currency ] = array_fill_keys( $keys, 0.0 );
				}
				$by_currency[ $currency ][ $month ] += floatval( $payment['amount'] );
			}
		}

		return array( 'months' => $keys, 'by_currency' => $by_currency );
	}

	/**
	 * Top clients by total invoiced amount, kept separate per currency so a
	 * BDT client and a USD client are never compared on the same number.
	 *
	 * @param int $limit
	 * @return array[] { name, currency, total }
	 */
	public static function get_top_clients( $limit = 5 ) {
		$clients = array();
		foreach ( self::get_all() as $post ) {
			$inv = self::get( $post->ID );
			if ( 'cancelled' === $inv['status'] || '' === $inv['client_name'] ) {
				continue;
			}
			$key = $inv['client_name'] . '|' . $inv['currency'];
			if ( ! isset( $clients[ $key ] ) ) {
				$clients[ $key ] = array( 'name' => $inv['client_name'], 'currency' => $inv['currency'], 'total' => 0.0 );
			}
			$clients[ $key ]['total'] += $inv['totals']['total'];
		}
		usort( $clients, function ( $a, $b ) {
			return $b['total'] <=> $a['total'];
		} );
		return array_slice( array_values( $clients ), 0, $limit );
	}

	/**
	 * Overdue invoices grouped into age buckets by days past due.
	 *
	 * @return array { '0-30'|'31-60'|'61-90'|'90+': array[] }
	 */
	public static function get_overdue_aging() {
		$buckets = array(
			'0-30'  => array(),
			'31-60' => array(),
			'61-90' => array(),
			'90+'   => array(),
		);

		$today = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$posts = self::get_all( array(
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'key' => self::META_STATUS, 'value' => 'overdue' ),
			),
		) );

		foreach ( $posts as $post ) {
			$inv = self::get( $post->ID );
			if ( ! $inv['due_date'] ) {
				continue;
			}
			$days   = max( 0, (int) floor( ( $today - strtotime( $inv['due_date'] ) ) / DAY_IN_SECONDS ) );
			$bucket = $days <= 30 ? '0-30' : ( $days <= 60 ? '31-60' : ( $days <= 90 ? '61-90' : '90+' ) );
			$buckets[ $bucket ][] = array(
				'number'   => $inv['number'],
				'client'   => $inv['client_name'],
				'currency' => $inv['currency'],
				'balance'  => $inv['totals']['balance'],
				'days'     => $days,
			);
		}

		return $buckets;
	}

	/**
	 * Export full invoice data as array.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'post_id'        => $this->post_id,
			'number'         => get_post_meta( $this->post_id, self::META_NUMBER, true ),
			'status'         => get_post_meta( $this->post_id, self::META_STATUS, true ),
			'invoice_date'   => get_post_meta( $this->post_id, self::META_DATE, true ),
			'due_date'       => get_post_meta( $this->post_id, self::META_DUE_DATE, true ),
			'client_name'    => get_post_meta( $this->post_id, self::META_CLIENT_NAME, true ),
			'client_email'   => get_post_meta( $this->post_id, self::META_CLIENT_EMAIL, true ),
			'client_phone'   => get_post_meta( $this->post_id, self::META_CLIENT_PHONE, true ),
			'client_address' => get_post_meta( $this->post_id, self::META_CLIENT_ADDRESS, true ),
			'biller_name'    => get_post_meta( $this->post_id, self::META_BILLER_NAME, true ),
			'biller_email'   => get_post_meta( $this->post_id, self::META_BILLER_EMAIL, true ),
			'biller_phone'   => get_post_meta( $this->post_id, self::META_BILLER_PHONE, true ),
			'biller_address' => get_post_meta( $this->post_id, self::META_BILLER_ADDRESS, true ),
			'currency'       => get_post_meta( $this->post_id, self::META_CURRENCY, true ),
			'notes'          => get_post_meta( $this->post_id, self::META_NOTES, true ),
			'discount'       => floatval( get_post_meta( $this->post_id, self::META_DISCOUNT, true ) ),
			'discount_type'  => get_post_meta( $this->post_id, self::META_DISCOUNT_TYPE, true ) ?: 'flat',
			'terms_selected' => (array) get_post_meta( $this->post_id, self::META_TERMS_SELECTED, true ),
			'share_token'    => get_post_meta( $this->post_id, self::META_SHARE_TOKEN, true ),
			'share_view_count'  => $this->get_share_view_count(),
			'share_last_viewed' => $this->get_last_viewed(),
			'recurring_frequency' => get_post_meta( $this->post_id, self::META_RECURRING_FREQUENCY, true ),
			'recurring_next_date' => get_post_meta( $this->post_id, self::META_RECURRING_NEXT_DATE, true ),
			'recurring_end_date'  => get_post_meta( $this->post_id, self::META_RECURRING_END_DATE, true ),
			'recurring_parent'    => get_post_meta( $this->post_id, self::META_RECURRING_PARENT, true ),
			'items'          => $this->get_items(),
			'payments'       => $this->get_payments(),
			'totals'         => $this->calculate_totals(),
		);
	}

	/**
	 * Get this invoice's public share token, generating one on first use.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function get_or_create_share_token( $post_id ) {
		$post_id = absint( $post_id );
		$token   = get_post_meta( $post_id, self::META_SHARE_TOKEN, true );
		if ( ! $token ) {
			$token = wp_generate_password( 32, false, false );
			update_post_meta( $post_id, self::META_SHARE_TOKEN, $token );
		}
		return $token;
	}

	/**
	 * Replace an invoice's share token with a new one, invalidating the old link.
	 *
	 * @param int $post_id
	 * @return string New token.
	 */
	public static function regenerate_share_token( $post_id ) {
		$token = wp_generate_password( 32, false, false );
		update_post_meta( absint( $post_id ), self::META_SHARE_TOKEN, $token );
		return $token;
	}

	/**
	 * Find an invoice by its public share token — the pretty-URL ("/invoice/{token}/")
	 * route resolves to an invoice this way instead of a separate invoice_id param.
	 *
	 * @param string $token
	 * @return array|null
	 */
	public static function get_by_share_token( $token ) {
		$token = sanitize_text_field( $token );
		if ( '' === $token ) {
			return null;
		}

		$posts = get_posts( array(
			'post_type'      => WP_IM_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => self::META_SHARE_TOKEN,
					'value' => $token,
				),
			),
		) );

		return $posts ? self::get( $posts[0] ) : null;
	}

	/**
	 * Public, pretty "view invoice" URL — /invoice/{token}/ — resolved by the
	 * rewrite rule registered in WP_IM_Post_Type.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function get_share_url( $post_id ) {
		$token = self::get_or_create_share_token( $post_id );
		return home_url( 'invoice/' . rawurlencode( $token ) . '/' );
	}

	/**
	 * Public, pretty "pay now" URL — /invoice/{token}/pay/.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function get_pay_now_url( $post_id ) {
		$token = self::get_or_create_share_token( $post_id );
		return home_url( 'invoice/' . rawurlencode( $token ) . '/pay/' );
	}

	/**
	 * Log one open of the public share link (for "has the client seen this
	 * invoice?" tracking). Keeps only the most recent 50 timestamps.
	 *
	 * @param int $post_id
	 */
	public static function record_share_view( $post_id ) {
		$post_id = absint( $post_id );
		$views   = get_post_meta( $post_id, self::META_SHARE_VIEWS, true );
		$views   = is_array( $views ) ? $views : array();
		$views[] = current_time( 'mysql' );
		if ( count( $views ) > 50 ) {
			$views = array_slice( $views, -50 );
		}
		update_post_meta( $post_id, self::META_SHARE_VIEWS, $views );
	}

	/**
	 * How many times the share link has been opened.
	 *
	 * @return int
	 */
	public function get_share_view_count() {
		$views = get_post_meta( $this->post_id, self::META_SHARE_VIEWS, true );
		return is_array( $views ) ? count( $views ) : 0;
	}

	/**
	 * Timestamp (MySQL datetime) the share link was last opened, or ''.
	 *
	 * @return string
	 */
	public function get_last_viewed() {
		$views = get_post_meta( $this->post_id, self::META_SHARE_VIEWS, true );
		if ( ! is_array( $views ) || empty( $views ) ) {
			return '';
		}
		return end( $views );
	}

	/**
	 * Get available statuses.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		return array(
			'draft'    => __( 'Draft', 'wp-invoice-manager' ),
			'sent'     => __( 'Sent', 'wp-invoice-manager' ),
			'partial'  => __( 'Partially Paid', 'wp-invoice-manager' ),
			'paid'     => __( 'Paid', 'wp-invoice-manager' ),
			'overdue'  => __( 'Overdue', 'wp-invoice-manager' ),
			'cancelled'=> __( 'Cancelled', 'wp-invoice-manager' ),
		);
	}

	/**
	 * Get available currencies.
	 *
	 * @return array
	 */
	public static function get_currencies() {
		return array(
			'USD' => 'USD – US Dollar ($)',
			'EUR' => 'EUR – Euro (€)',
			'GBP' => 'GBP – British Pound (£)',
			'BDT' => 'BDT – Bangladeshi Taka (৳)',
			'JPY' => 'JPY – Japanese Yen (¥)',
			'CAD' => 'CAD – Canadian Dollar (C$)',
			'AUD' => 'AUD – Australian Dollar (A$)',
			'INR' => 'INR – Indian Rupee (₹)',
		);
	}

	/**
	 * Default Terms & Conditions lines used to seed the settings repeater.
	 *
	 * @return string[]
	 */
	public static function get_default_terms() {
		return array(
			__( 'Domain and hosting services are renewed annually.', 'wp-invoice-manager' ),
			__( 'Renewal prices may change based on domain registrar, server costs, and international exchange rates.', 'wp-invoice-manager' ),
			__( 'Annual support & maintenance includes WordPress updates, plugin updates, security monitoring, backups, and minor bug fixes.', 'wp-invoice-manager' ),
			__( 'New features, custom development, or major design changes are not included and will be billed separately.', 'wp-invoice-manager' ),
			__( 'Third-party service fees (domain, SMS gateway, email service, payment gateway, etc.) are not included unless mentioned in this invoice.', 'wp-invoice-manager' ),
			__( 'The client is responsible for providing all required website content (logo, images, doctor information, chamber schedule, etc.).', 'wp-invoice-manager' ),
			__( 'Payment made for domain registration and setup is non-refundable once the service has been activated.', 'wp-invoice-manager' ),
		);
	}

	/**
	 * Whether a #rrggbb hex color is light enough that dark text reads
	 * better on it than white text (YIQ brightness formula).
	 *
	 * @param string $hex
	 * @return bool
	 */
	public static function is_light_color( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return false;
		}
		$r   = hexdec( substr( $hex, 0, 2 ) );
		$g   = hexdec( substr( $hex, 2, 2 ) );
		$b   = hexdec( substr( $hex, 4, 2 ) );
		$yiq = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
		return $yiq >= 150;
	}

	/**
	 * Convert a #rrggbb hex color to an "rgba(r,g,b,a)" string.
	 *
	 * @param string $hex
	 * @param float  $alpha
	 * @return string
	 */
	public static function hex_to_rgba( $hex, $alpha ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			$hex = 'ffffff';
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, $alpha );
	}

	/**
	 * Pick a readable text color for content sitting on top of an arbitrary
	 * background color (e.g. the invoice header / status bar) – used only
	 * as the initial default; the user can override it explicitly.
	 *
	 * @param string $bg_hex
	 * @return string
	 */
	public static function readable_text_color( $bg_hex ) {
		return self::is_light_color( $bg_hex ) ? '#1e293b' : '#ffffff';
	}

	/**
	 * Allowed HTML for a line-item description — enough for the small
	 * bold/link/line-break rich-text field on the invoice form, nothing more.
	 *
	 * @return array
	 */
	public static function description_allowed_html() {
		return array(
			'b'      => array(),
			'strong' => array(),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'     => array(),
		);
	}

	/**
	 * Allowed HTML for the Notes / Terms rich-text field — the line-item
	 * set plus bullet/numbered lists.
	 *
	 * @return array
	 */
	public static function notes_allowed_html() {
		return array_merge( self::description_allowed_html(), array(
			'ul' => array(),
			'ol' => array(),
			'li' => array(),
		) );
	}

	/**
	 * Trim and collapse stray blank lines in a multi-line address so a run
	 * of empty lines (e.g. left over from copy/paste) doesn't show up as an
	 * awkward gap on the printed invoice.
	 *
	 * @param string $address
	 * @return string
	 */
	public static function clean_address( $address ) {
		$address = str_replace( "\r\n", "\n", (string) $address );
		$address = preg_replace( '/\n{2,}/', "\n", $address );
		return trim( $address );
	}

	/**
	 * Default printable-invoice footer text. Supports {site_name} and {date} tokens.
	 *
	 * @return string
	 */
	public static function get_default_footer_text() {
		return __( 'Generated by WP Invoice Manager • {site_name} • {date}', 'wp-invoice-manager' );
	}

	/**
	 * Get currency symbol.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function currency_symbol( $code ) {
		$symbols = array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'BDT' => '৳',
			'JPY' => '¥',
			'CAD' => 'C$',
			'AUD' => 'A$',
			'INR' => '₹',
		);
		return $symbols[ $code ] ?? $code;
	}
}
