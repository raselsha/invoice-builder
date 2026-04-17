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
	const META_BILLER_ADDRESS= '_invoice_biller_address';
	const META_CURRENCY      = '_invoice_currency';
	const META_NOTES         = '_invoice_notes';
	const META_DISCOUNT      = '_invoice_discount';

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
		// Generate invoice number
		$prefix  = get_option( 'wp_im_invoice_prefix', 'INV-' );
		$number  = get_option( 'wp_im_next_invoice_number', 1 );
		$inv_num = $prefix . str_pad( $number, 5, '0', STR_PAD_LEFT );

		$post_id = wp_insert_post( array(
			'post_type'   => WP_IM_Post_Type::POST_TYPE,
			'post_title'  => sanitize_text_field( $inv_num ),
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Increment counter
		update_option( 'wp_im_next_invoice_number', $number + 1 );

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
			self::META_BILLER_ADDRESS => sanitize_textarea_field( $data['biller_address'] ?? '' ),
			self::META_CURRENCY       => sanitize_text_field( $data['currency'] ?? 'USD' ),
			self::META_NOTES          => sanitize_textarea_field( $data['notes'] ?? '' ),
			self::META_DISCOUNT       => floatval( $data['discount'] ?? 0 ),
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
			if ( empty( $item['description'] ) ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'invoice_id'  => $this->post_id,
					'description' => sanitize_text_field( $item['description'] ),
					'quantity'    => floatval( $item['quantity'] ?? 1 ),
					'unit_price'  => floatval( $item['unit_price'] ?? 0 ),
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
	 * @return array { subtotal, tax_total, discount, total }
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

		$discount = floatval( get_post_meta( $this->post_id, self::META_DISCOUNT, true ) );
		$total    = $subtotal + $tax_total - $discount;

		return array(
			'subtotal'  => $subtotal,
			'tax_total' => $tax_total,
			'discount'  => $discount,
			'total'     => max( 0, $total ),
		);
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
			'biller_address' => get_post_meta( $this->post_id, self::META_BILLER_ADDRESS, true ),
			'currency'       => get_post_meta( $this->post_id, self::META_CURRENCY, true ),
			'notes'          => get_post_meta( $this->post_id, self::META_NOTES, true ),
			'discount'       => floatval( get_post_meta( $this->post_id, self::META_DISCOUNT, true ) ),
			'items'          => $this->get_items(),
			'totals'         => $this->calculate_totals(),
		);
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
