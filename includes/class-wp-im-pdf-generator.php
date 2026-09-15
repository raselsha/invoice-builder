<?php
/**
 * PDF Generator – renders an invoice as a printable HTML page.
 * Extend this class to plug in TCPDF / FPDF / Dompdf.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_IM_PDF_Generator {

	/** @var array Invoice data from WP_IM_Invoice::to_array() */
	private $invoice;

	/**
	 * Constructor.
	 *
	 * @param array $invoice
	 */
	public function __construct( array $invoice ) {
		$this->invoice = $invoice;
	}

	/**
	 * Output a printable HTML invoice and die.
	 */
	public function render_html() {
		$invoice  = $this->invoice;
		$totals   = $invoice['totals'];
		$symbol   = WP_IM_Invoice::currency_symbol( $invoice['currency'] );
		$statuses = WP_IM_Invoice::get_statuses();
		$status   = $statuses[ $invoice['status'] ] ?? $invoice['status'];
		$status_class = esc_attr( $invoice['status'] );

		$legacy_dark   = get_option( 'wp_im_dark_color', '#1a1a2e' );
		$primary_color = get_option( 'wp_im_primary_color', '#e94560' );
		$header_color  = get_option( 'wp_im_header_color', $legacy_dark );
		$date_color    = get_option( 'wp_im_date_color', '#0f3460' );

		$header_text_color = get_option( 'wp_im_header_text_color', WP_IM_Invoice::readable_text_color( $header_color ) );
		$date_text_color   = get_option( 'wp_im_date_text_color', WP_IM_Invoice::readable_text_color( $date_color ) );
		$header_text_muted = WP_IM_Invoice::hex_to_rgba( $header_text_color, .65 );
		$date_text_muted   = WP_IM_Invoice::hex_to_rgba( $date_text_color, .65 );

		$footer_text = strtr( get_option( 'wp_im_footer_text', WP_IM_Invoice::get_default_footer_text() ), array(
			'{site_name}' => get_bloginfo( 'name' ),
			'{date}'      => date_i18n( 'F j, Y' ),
		) );

		$all_terms = get_option( 'wp_im_terms_conditions', WP_IM_Invoice::get_default_terms() );
		$selected  = array_map( 'absint', (array) $invoice['terms_selected'] );
		$terms     = array();
		foreach ( $selected as $idx ) {
			if ( isset( $all_terms[ $idx ] ) ) {
				$terms[] = $all_terms[ $idx ];
			}
		}

		ob_start();
		include WP_IM_PLUGIN_DIR . 'templates/invoice-print.php';
		$html = ob_get_clean();

		// Prevent any other output
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
