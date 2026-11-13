<?php
/**
 * Standalone online-payment page, for an invoice (/invoice/{token}/pay/)
 * and for a payment link (/pay/{token}/). Variables: $view — see
 * WP_IM_Online_Payments::render().
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wim_kind    = $view['kind'];
$wim_state   = $view['state'];
$wim_input   = $view['input'];
$wim_invoice = $view['invoice'];
$wim_link    = $view['link'];

$wim_company   = get_option( 'wp_im_company_name', get_bloginfo( 'name' ) );
$wim_logo      = get_option( 'wp_im_company_logo_url', '' );
$wim_logo_mode = get_option( 'wp_im_logo_display_mode', 'image' );
$wim_support   = get_option( 'wp_im_company_email', get_option( 'admin_email' ) );
$wim_primary   = sanitize_hex_color( get_option( 'wp_im_primary_color', '#e94560' ) );
$wim_primary   = $wim_primary ? $wim_primary : '#e94560';
$wim_test      = 'test' === WP_IM_Stripe::mode();
$wim_icon      = get_site_icon_url( 64 );

if ( 'invoice' === $wim_kind && $wim_invoice ) {
	$wim_c       = $wim_invoice['currency'];
	$wim_balance = $wim_invoice['totals']['balance'];
	$wim_partial = WP_IM_Online_Payments::invoice_allows_partial( $wim_invoice );
	$wim_view    = WP_IM_Invoice::get_share_url( $wim_invoice['post_id'] );
	$wim_action  = WP_IM_Invoice::get_pay_now_url( $wim_invoice['post_id'] );
	/* translators: %s: invoice number */
	$wim_title   = sprintf( __( 'Invoice %s', 'wp-invoice-manager' ), $wim_invoice['number'] );
	$wim_start   = $wim_partial ? '' : WP_IM_Stripe::format( $wim_balance, $wim_c );
} else {
	$wim_c      = $wim_link ? $wim_link['currency'] : 'USD';
	$wim_action = $wim_link ? $wim_link['url'] : '';
	$wim_title  = $wim_link ? $wim_link['title'] : __( 'Payment link not found', 'wp-invoice-manager' );
	$wim_start  = ( $wim_link && 'fixed' === $wim_link['mode'] ) ? WP_IM_Stripe::format( $wim_link['amount'], $wim_c ) : '';
}
$wim_symbol = WP_IM_Invoice::currency_symbol( $wim_c );
$wim_zero   = WP_IM_Stripe::is_zero_decimal( $wim_c );

$wim_svg = static function ( $name ) {
	$paths = array(
		'lock'  => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
		'info'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'alert' => '<path d="M12 4 2.5 20h19L12 4Z"/><path d="M12 10v4M12 17h.01"/>',
		'doc'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
	);
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[ $name ] . '</svg>';
};
$wim_fmt_input = static function ( $amount ) use ( $wim_zero ) {
	return rtrim( rtrim( number_format( (float) $amount, $wim_zero ? 0 : 2, '.', '' ), '0' ), '.' );
};
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( ( 'paid' === $wim_state ? __( 'Payment received', 'wp-invoice-manager' ) : $wim_title ) . ' · ' . $wim_company ); ?></title>
	<?php if ( $wim_icon ) : ?><link rel="icon" href="<?php echo esc_url( $wim_icon ); ?>"><?php endif; ?>
	<link rel="stylesheet" href="<?php echo esc_url( WP_IM_PLUGIN_URL . 'public/css/pay.css?ver=' . WP_IM_VERSION ); ?>">
	<style>:root{--wim-pay-primary:<?php echo esc_html( $wim_primary ); ?>;}</style>
</head>
<body class="wim-pay wim-pay--<?php echo esc_attr( $wim_state ); ?>">

<?php if ( $wim_test ) : ?>
	<div class="wim-pay-test"><?php esc_html_e( 'Test mode — no real money is taken. Use card 4242 4242 4242 4242, any future date and any CVC.', 'wp-invoice-manager' ); ?></div>
<?php endif; ?>

<main class="wim-pay-wrap">
	<a class="wim-pay-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php if ( $wim_logo && 'text' !== $wim_logo_mode ) : ?>
			<img src="<?php echo esc_url( $wim_logo ); ?>" alt="<?php echo esc_attr( $wim_company ); ?>">
		<?php endif; ?>
		<?php if ( ! $wim_logo || 'image' !== $wim_logo_mode ) : ?>
			<span><?php echo esc_html( $wim_company ); ?></span>
		<?php endif; ?>
	</a>

	<div class="wim-pay-card">

	<?php if ( 'missing' === $wim_state ) : ?>

		<div class="wim-pay-state">
			<span class="wim-pay-state__icon is-warn"><?php echo $wim_svg( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h1><?php esc_html_e( 'Payment link not found', 'wp-invoice-manager' ); ?></h1>
			<p><?php esc_html_e( 'Please check that the link is complete, or ask us for a new one.', 'wp-invoice-manager' ); ?></p>
		</div>

	<?php elseif ( 'unavailable' === $wim_state ) : ?>

		<div class="wim-pay-state">
			<span class="wim-pay-state__icon is-muted"><?php echo $wim_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h1><?php echo esc_html( $wim_title ); ?></h1>
			<p><?php esc_html_e( 'Online payment isn’t available right now. Please contact us and we’ll help you pay another way.', 'wp-invoice-manager' ); ?></p>
		</div>

	<?php elseif ( 'closed' === $wim_state ) : ?>

		<div class="wim-pay-state">
			<span class="wim-pay-state__icon <?php echo 'used' === $view['status'] ? 'is-ok' : 'is-muted'; ?>"><?php echo $wim_svg( 'used' === $view['status'] ? 'check' : 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h1><?php echo esc_html( $wim_title ); ?></h1>
			<p>
				<?php
				if ( 'invoice' === $wim_kind ) {
					'used' === $view['status']
						? esc_html_e( 'This invoice is already paid. Thank you!', 'wp-invoice-manager' )
						: esc_html_e( 'This invoice can’t be paid online. Please contact us.', 'wp-invoice-manager' );
				} elseif ( 'used' === $view['status'] ) {
					esc_html_e( 'This payment link has already been paid, so it is now closed. Thank you!', 'wp-invoice-manager' );
				} elseif ( 'expired' === $view['status'] ) {
					esc_html_e( 'This payment link has expired. Please ask us for a new one.', 'wp-invoice-manager' );
				} else {
					esc_html_e( 'This payment link is not active at the moment. Please contact us.', 'wp-invoice-manager' );
				}
				?>
			</p>
			<?php if ( 'invoice' === $wim_kind ) : ?>
				<a class="wim-pay-btn wim-pay-btn--ghost" href="<?php echo esc_url( $wim_view ); ?>"><?php echo $wim_svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'View invoice', 'wp-invoice-manager' ); ?></a>
			<?php endif; ?>
		</div>

	<?php elseif ( 'paid' === $wim_state ) : ?>

		<?php $wim_r = $view['receipt']; ?>
		<div class="wim-pay-state">
			<span class="wim-pay-state__icon is-ok is-pop"><?php echo $wim_svg( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h1><?php esc_html_e( 'Payment received', 'wp-invoice-manager' ); ?></h1>
			<p class="wim-pay-state__amount"><?php echo esc_html( WP_IM_Stripe::format( $wim_r['amount'], $wim_r['currency'] ) ); ?></p>
			<p><?php esc_html_e( 'Thank you! Your payment went through.', 'wp-invoice-manager' ); ?></p>
		</div>
		<dl class="wim-pay-receipt">
			<?php if ( 'link' === $wim_kind ) : ?>
				<div><dt><?php esc_html_e( 'For', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( $wim_link['title'] ); ?></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Receipt / invoice', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( $wim_r['number'] ); ?></dd></div>
			<?php if ( 'invoice' === $wim_kind && $wim_r['balance'] > 0 && 'paid' !== $wim_r['status'] ) : ?>
				<div><dt><?php esc_html_e( 'Balance still due', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( WP_IM_Stripe::format( $wim_r['balance'], $wim_c ) ); ?></dd></div>
			<?php endif; ?>
			<div><dt><?php esc_html_e( 'Date', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( wp_date( get_option( 'date_format' ), $wim_r['date'] ) ); ?></dd></div>
			<?php if ( $wim_r['email'] ) : ?>
				<div><dt><?php esc_html_e( 'Receipt sent to', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( $wim_r['email'] ); ?></dd></div>
			<?php endif; ?>
		</dl>
		<a class="wim-pay-btn wim-pay-btn--ghost" href="<?php echo esc_url( WP_IM_Invoice::get_share_url( $wim_r['invoice_id'] ) ); ?>"><?php echo $wim_svg( 'doc' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'View receipt', 'wp-invoice-manager' ); ?></a>

	<?php elseif ( 'processing' === $wim_state ) : ?>

		<div class="wim-pay-state">
			<span class="wim-pay-state__icon is-muted"><?php echo $wim_svg( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<h1><?php esc_html_e( 'Payment is processing', 'wp-invoice-manager' ); ?></h1>
			<p><?php esc_html_e( 'Your payment has been submitted and is being confirmed by your bank. We will email you as soon as it clears.', 'wp-invoice-manager' ); ?></p>
		</div>

	<?php else : ?>

		<header class="wim-pay-head">
			<span class="wim-pay-eyebrow"><?php echo esc_html( 'invoice' === $wim_kind ? __( 'Invoice payment', 'wp-invoice-manager' ) : __( 'Payment request', 'wp-invoice-manager' ) ); ?></span>
			<h1><?php echo esc_html( $wim_title ); ?></h1>
			<?php if ( 'invoice' === $wim_kind ) : ?>
				<p class="wim-pay-desc">
					<?php
					echo esc_html( $wim_invoice['biller_name'] );
					if ( $wim_invoice['due_date'] ) {
						/* translators: %s: due date */
						echo ' · ' . esc_html( sprintf( __( 'due %s', 'wp-invoice-manager' ), wp_date( get_option( 'date_format' ), strtotime( $wim_invoice['due_date'] . ' 12:00:00' ) ) ) );
					}
					?>
					· <a href="<?php echo esc_url( $wim_view ); ?>"><?php esc_html_e( 'View invoice', 'wp-invoice-manager' ); ?></a>
				</p>
			<?php elseif ( '' !== trim( $wim_link['description'] ) ) : ?>
				<div class="wim-pay-desc"><?php echo wp_kses_post( wpautop( esc_html( $wim_link['description'] ) ) ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( $view['cancelled'] ) : ?>
			<div class="wim-pay-notice is-info"><?php echo $wim_svg( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Payment cancelled — you have not been charged. You can try again below.', 'wp-invoice-manager' ); ?></span></div>
		<?php endif; ?>

		<?php if ( $view['errors'] ) : ?>
			<div class="wim-pay-notice is-error" role="alert"><?php echo $wim_svg( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<ul><?php foreach ( $view['errors'] as $wim_error ) : ?><li><?php echo esc_html( $wim_error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php endif; ?>

		<form class="wim-pay-form" method="post" action="<?php echo esc_url( $wim_action ); ?>" data-wim-pay-form data-symbol="<?php echo esc_attr( $wim_symbol ); ?>" data-zero="<?php echo $wim_zero ? '1' : '0'; ?>">

			<?php if ( 'invoice' === $wim_kind ) : ?>

				<dl class="wim-pay-summary">
					<div><dt><?php esc_html_e( 'Invoice total', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( WP_IM_Stripe::format( $wim_invoice['totals']['total'], $wim_c ) ); ?></dd></div>
					<?php if ( $wim_invoice['totals']['paid'] > 0 ) : ?>
						<div><dt><?php esc_html_e( 'Already paid', 'wp-invoice-manager' ); ?></dt><dd>− <?php echo esc_html( WP_IM_Stripe::format( $wim_invoice['totals']['paid'], $wim_c ) ); ?></dd></div>
					<?php endif; ?>
					<div class="is-total"><dt><?php esc_html_e( 'Balance due', 'wp-invoice-manager' ); ?></dt><dd><?php echo esc_html( WP_IM_Stripe::format( $wim_balance, $wim_c ) ); ?></dd></div>
				</dl>

				<?php if ( $wim_partial ) : ?>
					<div class="wim-pay-field">
						<label class="wim-pay-label" for="wim-custom"><?php esc_html_e( 'Amount to pay now', 'wp-invoice-manager' ); ?></label>
						<div class="wim-pay-money is-lg">
							<span class="wim-pay-money__symbol"><?php echo esc_html( $wim_symbol ); ?></span>
							<input type="text" id="wim-custom" name="wim_custom" inputmode="decimal" autocomplete="off" required value="<?php echo esc_attr( $wim_input['custom'] ); ?>" data-wim-custom data-open>
							<span class="wim-pay-money__code"><?php echo esc_html( $wim_c ); ?></span>
						</div>
						<p class="wim-pay-hint"><?php esc_html_e( 'You can pay the full balance or part of it now.', 'wp-invoice-manager' ); ?></p>
					</div>
				<?php endif; ?>

			<?php elseif ( 'fixed' === $wim_link['mode'] ) : ?>

				<div class="wim-pay-fixed">
					<span><?php esc_html_e( 'Amount to pay', 'wp-invoice-manager' ); ?></span>
					<strong><?php echo esc_html( $wim_start ); ?></strong>
				</div>
				<input type="hidden" name="wim_choice" value="fixed">

			<?php elseif ( 'choices' === $wim_link['mode'] ) : ?>

				<fieldset class="wim-pay-field">
					<legend class="wim-pay-label"><?php esc_html_e( 'Choose an amount', 'wp-invoice-manager' ); ?></legend>
					<div class="wim-pay-chips">
						<?php foreach ( $wim_link['choices'] as $wim_option ) : ?>
							<?php $wim_value = $wim_fmt_input( $wim_option ); ?>
							<label class="wim-pay-chip">
								<input type="radio" name="wim_choice" value="<?php echo esc_attr( $wim_value ); ?>" data-amount="<?php echo esc_attr( $wim_value ); ?>" required <?php checked( $wim_input['choice'], $wim_value ); ?>>
								<span><?php echo esc_html( WP_IM_Stripe::format( $wim_option, $wim_c ) ); ?></span>
							</label>
						<?php endforeach; ?>
						<?php if ( $wim_link['allow_custom'] ) : ?>
							<label class="wim-pay-chip">
								<input type="radio" name="wim_choice" value="custom" data-custom <?php checked( $wim_input['choice'], 'custom' ); ?>>
								<span><?php esc_html_e( 'Other', 'wp-invoice-manager' ); ?></span>
							</label>
						<?php endif; ?>
					</div>
					<?php if ( $wim_link['allow_custom'] ) : ?>
						<div class="wim-pay-money" data-wim-custom-wrap <?php echo 'custom' === $wim_input['choice'] ? '' : 'hidden'; ?>>
							<span class="wim-pay-money__symbol"><?php echo esc_html( $wim_symbol ); ?></span>
							<input type="text" name="wim_custom" inputmode="decimal" autocomplete="off" placeholder="0.00" value="<?php echo esc_attr( 'custom' === $wim_input['choice'] ? $wim_input['custom'] : '' ); ?>" aria-label="<?php esc_attr_e( 'Your amount', 'wp-invoice-manager' ); ?>" data-wim-custom>
							<span class="wim-pay-money__code"><?php echo esc_html( $wim_c ); ?></span>
						</div>
					<?php endif; ?>
				</fieldset>

			<?php else : ?>

				<div class="wim-pay-field">
					<label class="wim-pay-label" for="wim-custom"><?php esc_html_e( 'Amount', 'wp-invoice-manager' ); ?></label>
					<div class="wim-pay-money is-lg">
						<span class="wim-pay-money__symbol"><?php echo esc_html( $wim_symbol ); ?></span>
						<input type="text" id="wim-custom" name="wim_custom" inputmode="decimal" autocomplete="off" required placeholder="0.00" value="<?php echo esc_attr( $wim_input['custom'] ); ?>" data-wim-custom data-open>
						<span class="wim-pay-money__code"><?php echo esc_html( $wim_c ); ?></span>
					</div>
					<p class="wim-pay-hint">
						<?php
						/* translators: %s: smallest amount */
						$wim_hint = array( sprintf( __( 'Minimum %s', 'wp-invoice-manager' ), WP_IM_Stripe::format( WP_IM_Payment_Link::min_amount( $wim_link ), $wim_c ) ) );
						if ( null !== $wim_link['max'] ) {
							/* translators: %s: largest amount */
							$wim_hint[] = sprintf( __( 'maximum %s', 'wp-invoice-manager' ), WP_IM_Stripe::format( WP_IM_Payment_Link::max_amount( $wim_link ), $wim_c ) );
						}
						echo esc_html( implode( ', ', $wim_hint ) );
						?>
					</p>
				</div>

			<?php endif; ?>

			<div class="wim-pay-grid">
				<div class="wim-pay-field">
					<label class="wim-pay-label" for="wim-name"><?php esc_html_e( 'Your name', 'wp-invoice-manager' ); ?></label>
					<input class="wim-pay-input" type="text" id="wim-name" name="wim_name" autocomplete="name" required value="<?php echo esc_attr( $wim_input['name'] ); ?>">
				</div>
				<div class="wim-pay-field">
					<label class="wim-pay-label" for="wim-email"><?php esc_html_e( 'Email', 'wp-invoice-manager' ); ?></label>
					<input class="wim-pay-input" type="email" id="wim-email" name="wim_email" autocomplete="email" required value="<?php echo esc_attr( $wim_input['email'] ); ?>">
				</div>
			</div>

			<?php if ( 'link' === $wim_kind && 'off' !== $wim_link['note'] ) : ?>
				<div class="wim-pay-field">
					<label class="wim-pay-label" for="wim-note">
						<?php echo esc_html( $wim_link['note_label'] ? $wim_link['note_label'] : __( 'Note', 'wp-invoice-manager' ) ); ?>
						<?php if ( 'optional' === $wim_link['note'] ) : ?><span class="wim-pay-optional"><?php esc_html_e( 'optional', 'wp-invoice-manager' ); ?></span><?php endif; ?>
					</label>
					<textarea class="wim-pay-input" id="wim-note" name="wim_note" rows="2" maxlength="480" <?php echo 'required' === $wim_link['note'] ? 'required' : ''; ?>><?php echo esc_textarea( $wim_input['note'] ); ?></textarea>
				</div>
			<?php endif; ?>

			<button type="submit" class="wim-pay-btn wim-pay-btn--pay" data-wim-pay data-label-pay="<?php esc_attr_e( 'Pay', 'wp-invoice-manager' ); ?>" data-label-wait="<?php esc_attr_e( 'Opening secure checkout…', 'wp-invoice-manager' ); ?>" data-start="<?php echo esc_attr( $wim_start ); ?>">
				<?php echo $wim_svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span data-wim-pay-label><?php echo esc_html( trim( __( 'Pay', 'wp-invoice-manager' ) . ' ' . $wim_start ) ); ?></span>
			</button>

			<p class="wim-pay-secure"><?php esc_html_e( 'You will pay on Stripe’s secure checkout — card, Apple Pay or Google Pay. We never see or store your card details.', 'wp-invoice-manager' ); ?></p>
		</form>

	<?php endif; ?>

	</div>

	<footer class="wim-pay-foot">
		<span class="wim-pay-foot__stripe"><?php echo $wim_svg( 'lock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Secured by Stripe', 'wp-invoice-manager' ); ?></span>
		<?php if ( is_email( $wim_support ) ) : ?>
			<span><?php esc_html_e( 'Questions?', 'wp-invoice-manager' ); ?> <a href="mailto:<?php echo esc_attr( $wim_support ); ?>"><?php echo esc_html( $wim_support ); ?></a></span>
		<?php endif; ?>
	</footer>
</main>

<script src="<?php echo esc_url( WP_IM_PLUGIN_URL . 'public/js/pay.js?ver=' . WP_IM_VERSION ); ?>" defer></script>
</body>
</html>
