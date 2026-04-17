<?php
/**
 * Admin view: Settings page.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$currencies = WP_IM_Invoice::get_currencies();
?>
<div class="wim-wrap wim-settings-wrap">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-admin-settings"></span>
			<?php esc_html_e( 'Invoice Settings', 'wp-invoice-manager' ); ?>
		</h1>
	</div>

	<?php if ( isset( $_GET['message'] ) && 'saved' === $_GET['message'] ) : ?>
		<div class="wim-notice wim-notice-success">
			<?php esc_html_e( '✓ Settings saved successfully.', 'wp-invoice-manager' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wp_im_settings_nonce', 'wp_im_settings_action' ); ?>
		<input type="hidden" name="action" value="wp_im_save_settings">

		<div class="wim-form-wrap">

			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-admin-home"></span>
					<?php esc_html_e( 'Company Information', 'wp-invoice-manager' ); ?>
				</h3>
				<div class="wim-field">
					<label><?php esc_html_e( 'Company Name', 'wp-invoice-manager' ); ?></label>
					<input type="text" name="company_name"
						value="<?php echo esc_attr( get_option( 'wp_im_company_name', get_bloginfo( 'name' ) ) ); ?>">
				</div>
				<div class="wim-field">
					<label><?php esc_html_e( 'Company Email', 'wp-invoice-manager' ); ?></label>
					<input type="email" name="company_email"
						value="<?php echo esc_attr( get_option( 'wp_im_company_email', get_option( 'admin_email' ) ) ); ?>">
				</div>
				<div class="wim-field">
					<label><?php esc_html_e( 'Company Address', 'wp-invoice-manager' ); ?></label>
					<textarea name="company_address"><?php echo esc_textarea( get_option( 'wp_im_company_address', '' ) ); ?></textarea>
				</div>
			</div>

			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-tag"></span>
					<?php esc_html_e( 'Invoice Defaults', 'wp-invoice-manager' ); ?>
				</h3>
				<div class="wim-form-grid-3">
					<div class="wim-field">
						<label><?php esc_html_e( 'Invoice Number Prefix', 'wp-invoice-manager' ); ?></label>
						<input type="text" name="invoice_prefix"
							value="<?php echo esc_attr( get_option( 'wp_im_invoice_prefix', 'INV-' ) ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Default Currency', 'wp-invoice-manager' ); ?></label>
						<select name="default_currency">
							<?php foreach ( $currencies as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"
									<?php selected( get_option( 'wp_im_default_currency', 'USD' ), $code ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Default Tax Rate (%)', 'wp-invoice-manager' ); ?></label>
						<input type="number" name="default_tax"
							value="<?php echo esc_attr( get_option( 'wp_im_default_tax', 0 ) ); ?>"
							min="0" max="100" step="0.01">
					</div>
				</div>
			</div>

			<div class="wim-form-actions">
				<button type="submit" class="wim-btn wim-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
					<?php esc_html_e( 'Save Settings', 'wp-invoice-manager' ); ?>
				</button>
			</div>
		</div>
	</form>

</div>
