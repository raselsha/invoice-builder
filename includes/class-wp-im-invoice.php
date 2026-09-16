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
	const META_TERMS_SELECTED= '_invoice_terms_selected';
	const META_SHARE_TOKEN   = '_invoice_share_token';

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
			self::META_BILLER_PHONE   => sanitize_text_field( $data['biller_phone'] ?? '' ),
			self::META_BILLER_ADDRESS => sanitize_textarea_field( $data['biller_address'] ?? '' ),
			self::META_CURRENCY       => sanitize_text_field( $data['currency'] ?? 'USD' ),
			self::META_NOTES          => trim( wp_kses( (string) ( $data['notes'] ?? '' ), self::notes_allowed_html() ) ),
			self::META_DISCOUNT       => floatval( $data['discount'] ?? 0 ),
			self::META_TERMS_SELECTED => array_map( 'absint', (array) ( $data['terms_selected'] ?? array() ) ),
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
			'biller_phone'   => get_post_meta( $this->post_id, self::META_BILLER_PHONE, true ),
			'biller_address' => get_post_meta( $this->post_id, self::META_BILLER_ADDRESS, true ),
			'currency'       => get_post_meta( $this->post_id, self::META_CURRENCY, true ),
			'notes'          => get_post_meta( $this->post_id, self::META_NOTES, true ),
			'discount'       => floatval( get_post_meta( $this->post_id, self::META_DISCOUNT, true ) ),
			'terms_selected' => (array) get_post_meta( $this->post_id, self::META_TERMS_SELECTED, true ),
			'share_token'    => get_post_meta( $this->post_id, self::META_SHARE_TOKEN, true ),
			'items'          => $this->get_items(),
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
