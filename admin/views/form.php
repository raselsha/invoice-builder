<?php
/**
 * Admin view: Create / Edit Invoice form.
 *
 * Variables: $invoice (array|null), $statuses, $currencies
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$is_edit  = ! empty( $invoice );
$post_id  = $is_edit ? $invoice['post_id'] : 0;
$title    = $is_edit
	? sprintf( __( 'Edit Invoice: %s', 'wp-invoice-manager' ), $invoice['number'] )
	: __( 'New Invoice', 'wp-invoice-manager' );

// Defaults from settings
$def_currency = get_option( 'wp_im_default_currency', 'USD' );
$def_tax      = get_option( 'wp_im_default_tax', 0 );
$company_name = get_option( 'wp_im_company_name', get_bloginfo( 'name' ) );
$company_email= get_option( 'wp_im_company_email', get_option('admin_email') );
$company_addr = get_option( 'wp_im_company_address', '' );

$items = $is_edit ? $invoice['items'] : array();
if ( empty( $items ) ) {
	$items = array( array( 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => $def_tax ) );
}

function wim_val( $invoice, $key, $default = '' ) {
	return $invoice ? esc_attr( $invoice[ $key ] ?? $default ) : esc_attr( $default );
}
?>
<div class="wim-wrap">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-<?php echo $is_edit ? 'edit' : 'plus-alt'; ?>"></span>
			<?php echo esc_html( $title ); ?>
		</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-invoice-manager' ) ); ?>" class="wim-btn wim-btn-secondary">
			<span class="dashicons dashicons-arrow-left-alt2" style="margin-top:3px;font-size:14px;width:14px;height:14px"></span>
			<?php esc_html_e( 'Back to Invoices', 'wp-invoice-manager' ); ?>
		</a>
	</div>

	<?php if ( isset( $_GET['message'] ) ) :
		$msgs = array(
			'updated' => __( '✓ Invoice updated successfully.', 'wp-invoice-manager' ),
			'sent'    => __( '✓ Invoice sent to client.', 'wp-invoice-manager' ),
		);
	?>
		<div class="wim-notice wim-notice-success">
			<?php echo esc_html( $msgs[ sanitize_key( $_GET['message'] ) ] ?? '' ); ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wp_im_invoice_nonce', 'wp_im_invoice_action' ); ?>
		<input type="hidden" name="action" value="<?php echo $is_edit ? 'wp_im_update_invoice' : 'wp_im_create_invoice'; ?>">
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
		<?php endif; ?>

		<div class="wim-form-wrap">

			<!-- ── Invoice meta ── -->
			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Invoice Details', 'wp-invoice-manager' ); ?>
				</h3>
				<div class="wim-form-grid-3">
					<div class="wim-field">
						<label><?php esc_html_e( 'Invoice Date', 'wp-invoice-manager' ); ?></label>
						<input type="date" name="invoice_date"
							value="<?php echo wim_val( $invoice, 'invoice_date', date( 'Y-m-d' ) ); ?>"
							required>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Due Date', 'wp-invoice-manager' ); ?></label>
						<input type="date" name="due_date"
							value="<?php echo wim_val( $invoice, 'due_date', date( 'Y-m-d', strtotime( '+30 days' ) ) ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Status', 'wp-invoice-manager' ); ?></label>
						<select name="status">
							<?php foreach ( $statuses as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"
									<?php selected( wim_val( $invoice, 'status', 'draft' ), $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="wim-form-grid-3">
					<div class="wim-field">
						<label><?php esc_html_e( 'Currency', 'wp-invoice-manager' ); ?></label>
						<select name="currency">
							<?php foreach ( $currencies as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"
									<?php selected( wim_val( $invoice, 'currency', $def_currency ), $code ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Discount (flat)', 'wp-invoice-manager' ); ?></label>
						<input type="number" name="discount" id="wim-discount"
							value="<?php echo wim_val( $invoice, 'discount', '0' ); ?>"
							min="0" step="0.01">
					</div>
				</div>
			</div>

			<!-- ── Biller & Client ── -->
			<div class="wim-form-grid">
				<!-- Biller -->
				<div class="wim-section">
					<h3 class="wim-section-title">
						<span class="dashicons dashicons-admin-home"></span>
						<?php esc_html_e( 'Your Details (Biller)', 'wp-invoice-manager' ); ?>
					</h3>
					<div class="wim-field">
						<label><?php esc_html_e( 'Company / Name', 'wp-invoice-manager' ); ?></label>
						<input type="text" name="biller_name"
							value="<?php echo wim_val( $invoice, 'biller_name', $company_name ); ?>"
							required>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Email', 'wp-invoice-manager' ); ?></label>
						<input type="email" name="biller_email"
							value="<?php echo wim_val( $invoice, 'biller_email', $company_email ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Address', 'wp-invoice-manager' ); ?></label>
						<textarea name="biller_address"><?php echo $invoice ? esc_textarea( $invoice['biller_address'] ) : esc_textarea( $company_addr ); ?></textarea>
					</div>
				</div>

				<!-- Client -->
				<div class="wim-section">
					<h3 class="wim-section-title">
						<span class="dashicons dashicons-businessman"></span>
						<?php esc_html_e( 'Client Details', 'wp-invoice-manager' ); ?>
					</h3>
					<div class="wim-field">
						<label><?php esc_html_e( 'Client Name', 'wp-invoice-manager' ); ?></label>
						<input type="text" name="client_name"
							value="<?php echo wim_val( $invoice, 'client_name' ); ?>"
							required>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Email', 'wp-invoice-manager' ); ?></label>
						<input type="email" name="client_email"
							value="<?php echo wim_val( $invoice, 'client_email' ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Phone', 'wp-invoice-manager' ); ?></label>
						<input type="tel" name="client_phone"
							value="<?php echo wim_val( $invoice, 'client_phone' ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Address', 'wp-invoice-manager' ); ?></label>
						<textarea name="client_address"><?php echo $invoice ? esc_textarea( $invoice['client_address'] ) : ''; ?></textarea>
					</div>
				</div>
			</div>

			<!-- ── Line items ── -->
			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( 'Line Items', 'wp-invoice-manager' ); ?>
				</h3>
				<table class="wim-items-table">
					<thead>
						<tr>
							<th class="col-desc"><?php esc_html_e( 'Description', 'wp-invoice-manager' ); ?></th>
							<th class="col-qty"><?php esc_html_e( 'Qty', 'wp-invoice-manager' ); ?></th>
							<th class="col-price"><?php esc_html_e( 'Unit Price', 'wp-invoice-manager' ); ?></th>
							<th class="col-tax"><?php esc_html_e( 'Tax %', 'wp-invoice-manager' ); ?></th>
							<th class="col-total"><?php esc_html_e( 'Total', 'wp-invoice-manager' ); ?></th>
							<th class="col-del"></th>
						</tr>
					</thead>
					<tbody id="wim-items-body">
						<?php foreach ( $items as $i => $item ) : ?>
						<tr class="wim-item-row">
							<td class="col-desc">
								<input type="text" name="items[<?php echo $i; ?>][description]"
									value="<?php echo esc_attr( $item['description'] ); ?>"
									placeholder="<?php esc_attr_e( 'Item description', 'wp-invoice-manager' ); ?>">
							</td>
							<td class="col-qty">
								<input type="number" name="items[<?php echo $i; ?>][quantity]"
									value="<?php echo esc_attr( $item['quantity'] ); ?>"
									min="0" step="0.01" class="wim-qty">
							</td>
							<td class="col-price">
								<input type="number" name="items[<?php echo $i; ?>][unit_price]"
									value="<?php echo esc_attr( $item['unit_price'] ); ?>"
									min="0" step="0.01" class="wim-price">
							</td>
							<td class="col-tax">
								<input type="number" name="items[<?php echo $i; ?>][tax_rate]"
									value="<?php echo esc_attr( $item['tax_rate'] ); ?>"
									min="0" max="100" step="0.01" class="wim-tax">
							</td>
							<td class="col-total">
								<span class="wim-line-total">0.00</span>
							</td>
							<td class="col-del">
								<button type="button" class="wim-remove-row" title="<?php esc_attr_e( 'Remove', 'wp-invoice-manager' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="wim-add-item-row">
					<button type="button" id="wim-add-item" class="wim-btn wim-btn-secondary">
						<span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Add Item', 'wp-invoice-manager' ); ?>
					</button>
				</div>
			</div>

			<!-- ── Totals ── -->
			<div class="wim-totals">
				<div class="wim-totals-box">
					<div class="wim-totals-row">
						<span class="wim-totals-label"><?php esc_html_e( 'Subtotal', 'wp-invoice-manager' ); ?></span>
						<span id="wim-subtotal">0.00</span>
					</div>
					<div class="wim-totals-row">
						<span class="wim-totals-label"><?php esc_html_e( 'Tax', 'wp-invoice-manager' ); ?></span>
						<span id="wim-tax-total">0.00</span>
					</div>
					<div class="wim-totals-row">
						<span class="wim-totals-label"><?php esc_html_e( 'Discount', 'wp-invoice-manager' ); ?></span>
						<span>- <span id="wim-discount-display">0.00</span></span>
					</div>
					<div class="wim-totals-row total-final">
						<span class="wim-totals-label"><?php esc_html_e( 'Total', 'wp-invoice-manager' ); ?></span>
						<span id="wim-total">0.00</span>
					</div>
				</div>
			</div>

			<!-- ── Notes ── -->
			<div class="wim-section" style="margin-top:28px">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-format-aside"></span>
					<?php esc_html_e( 'Notes / Terms', 'wp-invoice-manager' ); ?>
				</h3>
				<div class="wim-field">
					<textarea name="notes" rows="4"><?php echo $invoice ? esc_textarea( $invoice['notes'] ) : ''; ?></textarea>
				</div>
			</div>

			<!-- ── Actions ── -->
			<div class="wim-form-actions">
				<button type="submit" class="wim-btn wim-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
					<?php echo $is_edit ? esc_html__( 'Update Invoice', 'wp-invoice-manager' ) : esc_html__( 'Save Invoice', 'wp-invoice-manager' ); ?>
				</button>

				<?php if ( $is_edit ) :
					$print_url = wp_nonce_url(
						add_query_arg( array(
							'action'     => 'wp_im_print_invoice',
							'invoice_id' => $post_id,
						), admin_url( 'admin-post.php' ) ),
						'wp_im_print_' . $post_id,
						'wp_im_print_nonce'
					);
				?>
					<a href="<?php echo esc_url( $print_url ); ?>" target="_blank" class="wim-btn wim-btn-secondary">
						<span class="dashicons dashicons-printer" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Print / PDF', 'wp-invoice-manager' ); ?>
					</a>

					<?php if ( ! empty( $invoice['client_email'] ) ) : ?>
					<button type="submit"
						formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						name="action" value="wp_im_send_invoice"
						class="wim-btn wim-btn-success">
						<span class="dashicons dashicons-email-alt" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Send to Client', 'wp-invoice-manager' ); ?>
					</button>
					<?php endif; ?>
				<?php endif; ?>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-invoice-manager' ) ); ?>" class="wim-btn wim-btn-secondary">
					<?php esc_html_e( 'Cancel', 'wp-invoice-manager' ); ?>
				</a>
			</div>

		</div><!-- .wim-form-wrap -->
	</form>

</div><!-- .wim-wrap -->
