<?php
/**
 * Small view-rendering helpers shared across admin/views/*.php templates.
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wim_val' ) ) {
	/**
	 * Escaped attribute value for an invoice field, with a fallback default
	 * when there's no invoice yet (new/unsaved).
	 *
	 * @param array|null $invoice
	 * @param string     $key
	 * @param string     $default
	 * @return string
	 */
	function wim_val( $invoice, $key, $default = '' ) {
		return $invoice ? esc_attr( $invoice[ $key ] ?? $default ) : esc_attr( $default );
	}
}

if ( ! function_exists( 'wim_render_select' ) ) {
	/**
	 * Render a custom-styled dropdown that replaces a native <select> visually.
	 * The real <select> stays in the markup (hidden) and is what submits with
	 * the form; admin.js keeps a styled trigger + option panel in sync with it.
	 *
	 * @param string $name    Form field name.
	 * @param array  $options value => label pairs.
	 * @param string $current Currently selected value.
	 */
	function wim_render_select( $name, array $options, $current ) {
		?>
		<div class="wim-select">
			<select name="<?php echo esc_attr( $name ); ?>" class="wim-select-native">
				<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="wim-select-trigger">
				<span class="wim-select-value"><?php echo esc_html( $options[ $current ] ?? reset( $options ) ); ?></span>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="wim-select-panel">
				<?php foreach ( $options as $value => $label ) : ?>
				<button type="button" class="wim-select-option <?php echo ( (string) $current === (string) $value ) ? 'is-selected' : ''; ?>" data-value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
