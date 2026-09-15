<?php
/**
 * Admin view: Settings page.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$currencies    = WP_IM_Invoice::get_currencies();
$terms         = get_option( 'wp_im_terms_conditions', WP_IM_Invoice::get_default_terms() );
if ( empty( $terms ) ) {
	$terms = array( '' );
}
$legacy_dark   = get_option( 'wp_im_dark_color', '#1a1a2e' );
$primary_color = get_option( 'wp_im_primary_color', '#e94560' );
$header_color  = get_option( 'wp_im_header_color', $legacy_dark );
$date_color    = get_option( 'wp_im_date_color', $legacy_dark );

// Text colors default to whatever reads best on the current bg, but can be overridden manually.
$header_text_color = get_option( 'wp_im_header_text_color', WP_IM_Invoice::readable_text_color( $header_color ) );
$date_text_color   = get_option( 'wp_im_date_text_color', WP_IM_Invoice::readable_text_color( $date_color ) );

$color_presets = array( '#e94560', '#0f3460', '#1a1a2e', '#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#64748b' );

/**
 * Render one "Primary/Header/Date" color field: swatch + hex input +
 * a full saturation/value + hue picker popover, plus quick presets.
 *
 * @param string $name    Form field name.
 * @param string $role    data-role used by JS to target this picker (primary|header|date).
 * @param string $value   Current hex value.
 * @param array  $presets Preset hex swatches shown at the bottom of the popover.
 */
function wim_render_color_picker( $name, $role, $value, array $presets ) {
	?>
	<div class="wim-color-picker">
		<button type="button" class="wim-color-swatch" style="background:<?php echo esc_attr( $value ); ?>" aria-label="<?php esc_attr_e( 'Choose color', 'wp-invoice-manager' ); ?>"></button>
		<input type="text" class="wim-color-hex" data-role="<?php echo esc_attr( $role ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" maxlength="7" autocomplete="off">
		<div class="wim-color-popover">
			<div class="wim-color-sv-area">
				<div class="wim-color-sv-cursor"></div>
			</div>
			<div class="wim-color-hue-slider">
				<div class="wim-color-hue-cursor"></div>
			</div>
			<div class="wim-color-presets">
				<?php foreach ( $presets as $preset ) : ?>
				<button type="button" class="wim-color-swatch-option" data-color="<?php echo esc_attr( $preset ); ?>" style="background:<?php echo esc_attr( $preset ); ?>"></button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}

// Curated, coordinated palettes – "modern color system" quick picks.
$color_themes = array(
	'crimson' => array( 'label' => __( 'Crimson', 'wp-invoice-manager' ),  'primary' => '#e94560', 'header' => '#1a1a2e', 'date' => '#0f3460' ),
	'ocean'   => array( 'label' => __( 'Ocean', 'wp-invoice-manager' ),    'primary' => '#2563eb', 'header' => '#0f172a', 'date' => '#1e3a8a' ),
	'emerald' => array( 'label' => __( 'Emerald', 'wp-invoice-manager' ), 'primary' => '#059669', 'header' => '#064e3b', 'date' => '#047857' ),
	'sunset'  => array( 'label' => __( 'Sunset', 'wp-invoice-manager' ),  'primary' => '#f97316', 'header' => '#451a03', 'date' => '#7c2d12' ),
	'violet'  => array( 'label' => __( 'Violet', 'wp-invoice-manager' ),  'primary' => '#7c3aed', 'header' => '#1e1b4b', 'date' => '#4c1d95' ),
	'slate'   => array( 'label' => __( 'Slate', 'wp-invoice-manager' ),   'primary' => '#0891b2', 'header' => '#0f172a', 'date' => '#164e63' ),
	'mono'    => array( 'label' => __( 'Black & White', 'wp-invoice-manager' ), 'primary' => '#000000', 'header' => '#000000', 'date' => '#262626' ),
	'gray'    => array( 'label' => __( 'Gray', 'wp-invoice-manager' ),   'primary' => '#64748b', 'header' => '#334155', 'date' => '#475569' ),
);
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

		<div class="wim-form-grid wim-settings-grid">

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
						<?php wim_render_select( 'default_currency', $currencies, get_option( 'wp_im_default_currency', 'USD' ) ); ?>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Default Tax Rate (%)', 'wp-invoice-manager' ); ?></label>
						<input type="number" name="default_tax"
							value="<?php echo esc_attr( get_option( 'wp_im_default_tax', 0 ) ); ?>"
							min="0" max="100" step="0.01">
					</div>
				</div>
				<div class="wim-field">
					<label><?php esc_html_e( 'Invoice Footer Text', 'wp-invoice-manager' ); ?></label>
					<input type="text" name="footer_text"
						value="<?php echo esc_attr( get_option( 'wp_im_footer_text', WP_IM_Invoice::get_default_footer_text() ) ); ?>">
					<p class="wim-field-hint"><?php esc_html_e( 'Shown at the bottom of the printed / PDF invoice. Use {site_name} and {date} as placeholders.', 'wp-invoice-manager' ); ?></p>
				</div>
			</div>

		</div><!-- .wim-form-wrap -->

		<div class="wim-form-wrap wim-form-wrap-terms">

			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-media-text"></span>
					<?php esc_html_e( 'Terms & Conditions', 'wp-invoice-manager' ); ?>
				</h3>

				<div id="wim-terms-body">
					<?php foreach ( $terms as $i => $term ) :
						$has_text = '' !== trim( $term );
					?>
					<div class="wim-term-row" data-mode="<?php echo $has_text ? 'view' : 'edit'; ?>">
						<span class="wim-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-invoice-manager' ); ?>">
							<span class="dashicons dashicons-menu"></span>
						</span>
						<span class="wim-term-index"><?php echo (int) ( $i + 1 ); ?>.</span>
						<textarea name="terms[]" rows="2" <?php echo $has_text ? 'readonly' : ''; ?>
							placeholder="<?php esc_attr_e( 'Enter a term or condition', 'wp-invoice-manager' ); ?>"><?php echo esc_textarea( $term ); ?></textarea>
						<div class="wim-term-row-actions">
							<button type="button" class="wim-term-edit-btn" title="<?php esc_attr_e( 'Edit', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</button>
							<button type="button" class="wim-remove-row wim-remove-term" title="<?php esc_attr_e( 'Remove', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					</div>
					<?php endforeach; ?>
				</div>

				<div class="wim-add-item-row">
					<button type="button" id="wim-add-term" class="wim-btn wim-btn-secondary">
						<span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Add Term', 'wp-invoice-manager' ); ?>
					</button>
				</div>
			</div>

		</div><!-- .wim-form-wrap-terms -->

		</div><!-- .wim-settings-grid -->

		<div class="wim-form-wrap" style="margin-top:24px">

			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-art"></span>
					<?php esc_html_e( 'Invoice Colors', 'wp-invoice-manager' ); ?>
				</h3>

				<div class="wim-field">
					<label><?php esc_html_e( 'Quick Themes', 'wp-invoice-manager' ); ?></label>
					<div class="wim-theme-row">
						<?php foreach ( $color_themes as $key => $theme ) : ?>
						<button type="button" class="wim-theme-swatch"
							data-primary="<?php echo esc_attr( $theme['primary'] ); ?>"
							data-header="<?php echo esc_attr( $theme['header'] ); ?>"
							data-date="<?php echo esc_attr( $theme['date'] ); ?>"
							data-header-text="<?php echo esc_attr( WP_IM_Invoice::readable_text_color( $theme['header'] ) ); ?>"
							data-date-text="<?php echo esc_attr( WP_IM_Invoice::readable_text_color( $theme['date'] ) ); ?>"
							title="<?php echo esc_attr( $theme['label'] ); ?>">
							<span class="wim-theme-chips">
								<span style="background:<?php echo esc_attr( $theme['header'] ); ?>"></span>
								<span style="background:<?php echo esc_attr( $theme['date'] ); ?>"></span>
								<span style="background:<?php echo esc_attr( $theme['primary'] ); ?>"></span>
							</span>
							<span class="wim-theme-label"><?php echo esc_html( $theme['label'] ); ?></span>
						</button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="wim-field">
					<label><?php esc_html_e( 'Primary / Accent Color', 'wp-invoice-manager' ); ?></label>
					<?php wim_render_color_picker( 'primary_color', 'primary', $primary_color, $color_presets ); ?>
					<p class="wim-field-hint"><?php esc_html_e( 'Invoice number, notes border, print button.', 'wp-invoice-manager' ); ?></p>
				</div>

				<div class="wim-color-pair">
					<div class="wim-color-pair-title"><?php esc_html_e( 'Header — top banner with your company name', 'wp-invoice-manager' ); ?></div>
					<div class="wim-form-grid">
						<div class="wim-field">
							<label><?php esc_html_e( 'Background Color', 'wp-invoice-manager' ); ?></label>
							<?php wim_render_color_picker( 'header_color', 'header', $header_color, $color_presets ); ?>
						</div>
						<div class="wim-field">
							<label><?php esc_html_e( 'Text Color', 'wp-invoice-manager' ); ?></label>
							<?php wim_render_color_picker( 'header_text_color', 'header-text', $header_text_color, $color_presets ); ?>
						</div>
					</div>
				</div>

				<div class="wim-color-pair">
					<div class="wim-color-pair-title"><?php esc_html_e( 'Date & Status Bar — invoice date, due date & status', 'wp-invoice-manager' ); ?></div>
					<div class="wim-form-grid">
						<div class="wim-field">
							<label><?php esc_html_e( 'Background Color', 'wp-invoice-manager' ); ?></label>
							<?php wim_render_color_picker( 'date_color', 'date', $date_color, $color_presets ); ?>
						</div>
						<div class="wim-field">
							<label><?php esc_html_e( 'Text Color', 'wp-invoice-manager' ); ?></label>
							<?php wim_render_color_picker( 'date_text_color', 'date-text', $date_text_color, $color_presets ); ?>
						</div>
					</div>
				</div>
			</div>

		</div><!-- .wim-form-wrap (branding) -->

		<div class="wim-form-actions">
			<button type="submit" class="wim-btn wim-btn-primary">
				<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
				<?php esc_html_e( 'Save Settings', 'wp-invoice-manager' ); ?>
			</button>
		</div>
	</form>

</div>
