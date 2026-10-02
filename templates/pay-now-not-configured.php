<?php
/**
 * Public "Pay Now" landing page — shown until a real payment gateway
 * (bKash / SSLCommerz) is actually integrated. Settings has fields ready for
 * the credentials, but no live API call happens here yet.
 *
 * Variables: $invoice (array)
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$back_url = WP_IM_Invoice::get_share_url( $invoice['post_id'] );
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php esc_html_e( 'Online Payment', 'wp-invoice-manager' ); ?></title>
<style>
	* { box-sizing: border-box; margin: 0; padding: 0; }
	body {
		font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
		background: #f8f9fc;
		color: #1e293b;
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 100vh;
		padding: 24px;
	}
	.card {
		max-width: 440px;
		width: 100%;
		background: #fff;
		border-radius: 12px;
		box-shadow: 0 4px 40px rgba(0,0,0,.12);
		padding: 40px 36px;
		text-align: center;
	}
	.icon { font-size: 40px; margin-bottom: 16px; }
	h1 { font-size: 18px; margin-bottom: 12px; }
	p { color: #64748b; font-size: 13.5px; line-height: 1.7; margin-bottom: 24px; }
	.contact { background: #f8f9fc; border-radius: 8px; padding: 14px 18px; text-align: left; font-size: 13px; color: #475569; margin-bottom: 24px; }
	.contact strong { display: block; color: #1e293b; margin-bottom: 4px; }
	a.back { display: inline-block; color: #fff; background: #334155; padding: 10px 22px; border-radius: 6px; font-size: 13.5px; font-weight: 600; text-decoration: none; }
</style>
</head>
<body>
	<div class="card">
		<div class="icon">🚧</div>
		<h1><?php esc_html_e( "Online payment isn't set up yet", 'wp-invoice-manager' ); ?></h1>
		<p><?php esc_html_e( "We're not accepting card / mobile-banking payments on this link just yet. Please contact us directly to arrange payment for this invoice.", 'wp-invoice-manager' ); ?></p>
		<?php if ( ! empty( $invoice['biller_email'] ) || ! empty( $invoice['biller_phone'] ) ) : ?>
		<div class="contact">
			<strong><?php echo esc_html( $invoice['biller_name'] ); ?></strong>
			<?php if ( ! empty( $invoice['biller_email'] ) ) : ?><div><?php echo esc_html( $invoice['biller_email'] ); ?></div><?php endif; ?>
			<?php if ( ! empty( $invoice['biller_phone'] ) ) : ?><div><?php echo esc_html( $invoice['biller_phone'] ); ?></div><?php endif; ?>
		</div>
		<?php endif; ?>
		<a class="back" href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to Invoice', 'wp-invoice-manager' ); ?></a>
	</div>
</body>
</html>
