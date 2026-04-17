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
