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
$company_phone= get_option( 'wp_im_company_phone', '' );
$company_addr = get_option( 'wp_im_company_address', '' );

$items  = $is_edit ? $invoice['items'] : array();
if ( empty( $items ) ) {
	$items = array( array( 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => $def_tax ) );
}
$symbol = WP_IM_Invoice::currency_symbol( $is_edit ? $invoice['currency'] : $def_currency );

// Client field defaults: an existing invoice keeps its own values; a brand-new
// invoice can be prefilled from a customer (search-select, or a "New Invoice"
// quick-link from the Customers list).
$prefill_customer  = $prefill_customer ?? null;
$client_name_val   = $is_edit ? $invoice['client_name']    : ( $prefill_customer['name']    ?? '' );
$client_email_val  = $is_edit ? $invoice['client_email']   : ( $prefill_customer['email']   ?? '' );
$client_phone_val  = $is_edit ? $invoice['client_phone']   : ( $prefill_customer['phone']   ?? '' );
$client_address_val= $is_edit ? $invoice['client_address'] : ( $prefill_customer['address'] ?? '' );

// Recurring invoice settings — only meaningful for a saved invoice that
// isn't itself already a child generated from a template.
$recurring_frequencies  = WP_IM_Invoice::get_recurring_frequencies();
$recurring_parent_id    = $is_edit ? absint( $invoice['recurring_parent'] ?? 0 ) : 0;
$is_recurring            = $is_edit && ! empty( $invoice['recurring_frequency'] );
$recurring_frequency_val = $is_edit ? ( $invoice['recurring_frequency'] ?? 'monthly' ) : 'monthly';
$recurring_next_date_val = $is_edit && $invoice['recurring_next_date'] ? $invoice['recurring_next_date'] : date( 'Y-m-d', strtotime( '+1 month' ) );
$recurring_end_date_val  = $is_edit ? ( $invoice['recurring_end_date'] ?? '' ) : '';
if ( '' === $recurring_frequency_val ) {
	$recurring_frequency_val = 'monthly';
}

// Terms & Conditions checklist – a brand-new invoice starts with everything checked;
// an existing invoice keeps exactly what was saved for it (even if that's none).
$all_terms      = $all_terms ?? array();
$selected_terms = $is_edit
	? array_map( 'absint', (array) $invoice['terms_selected'] )
	: array_keys( $all_terms );
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
			'updated'            => __( '✓ Invoice updated successfully.', 'wp-invoice-manager' ),
			'sent'               => __( '✓ Invoice sent to client.', 'wp-invoice-manager' ),
			'share_regenerated'  => __( '✓ Share link regenerated — the old link no longer works.', 'wp-invoice-manager' ),
			'payment_recorded'   => __( '✓ Payment recorded.', 'wp-invoice-manager' ),
			'payment_deleted'    => __( '✓ Payment removed.', 'wp-invoice-manager' ),
			'duplicated'         => __( '✓ Invoice duplicated — this is the new copy.', 'wp-invoice-manager' ),
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
						<?php wim_render_select( 'status', $statuses, $invoice ? ( $invoice['status'] ?? 'draft' ) : 'draft' ); ?>
					</div>
				</div>
				<div class="wim-form-grid-3">
					<div class="wim-field">
						<label><?php esc_html_e( 'Currency', 'wp-invoice-manager' ); ?></label>
						<?php wim_render_select( 'currency', $currencies, $invoice ? ( $invoice['currency'] ?? $def_currency ) : $def_currency ); ?>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Discount', 'wp-invoice-manager' ); ?></label>
						<div class="wim-discount-row">
							<input type="number" name="discount" id="wim-discount"
								value="<?php echo wim_val( $invoice, 'discount', '0' ); ?>"
								min="0" step="0.01">
							<div class="wim-discount-type">
								<?php wim_render_select( 'discount_type', array( 'flat' => __( 'Flat', 'wp-invoice-manager' ), 'percent' => '%' ), $is_edit ? ( $invoice['discount_type'] ?? 'flat' ) : 'flat' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- ── Recurring ── -->
			<div class="wim-section">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Recurring', 'wp-invoice-manager' ); ?>
				</h3>
				<?php if ( $recurring_parent_id ) :
					$parent_invoice = WP_IM_Invoice::get( $recurring_parent_id );
				?>
				<p class="wim-field-hint">
					<?php
					printf(
						/* translators: %s: parent invoice number */
						esc_html__( 'Auto-generated from recurring invoice %s.', 'wp-invoice-manager' ),
						$parent_invoice ? esc_html( $parent_invoice['number'] ) : '#' . (int) $recurring_parent_id
					);
					?>
				</p>
				<?php else : ?>
				<div class="wim-field">
					<label class="wim-checkbox-label">
						<input type="checkbox" name="is_recurring" id="wim-is-recurring" value="1" <?php checked( $is_recurring ); ?>>
						<?php esc_html_e( 'Make this a recurring invoice', 'wp-invoice-manager' ); ?>
					</label>
				</div>
				<div id="wim-recurring-fields" class="wim-form-grid-3"<?php echo $is_recurring ? '' : ' style="display:none"'; ?>>
					<div class="wim-field">
						<label><?php esc_html_e( 'Frequency', 'wp-invoice-manager' ); ?></label>
						<?php wim_render_select( 'recurring_frequency', $recurring_frequencies, $recurring_frequency_val ); ?>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Next Invoice Date', 'wp-invoice-manager' ); ?></label>
						<input type="date" name="recurring_next_date" value="<?php echo esc_attr( $recurring_next_date_val ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'End Date (optional)', 'wp-invoice-manager' ); ?></label>
						<input type="date" name="recurring_end_date" value="<?php echo esc_attr( $recurring_end_date_val ); ?>">
					</div>
				</div>
				<p class="wim-field-hint"><?php esc_html_e( 'A new draft invoice (copying the client, items, and terms below) is generated automatically on the next invoice date, then the date advances by the chosen frequency.', 'wp-invoice-manager' ); ?></p>
				<?php endif; ?>
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
						<label><?php esc_html_e( 'Phone', 'wp-invoice-manager' ); ?></label>
						<input type="tel" name="biller_phone"
							value="<?php echo wim_val( $invoice, 'biller_phone', $company_phone ); ?>">
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

					<?php if ( ! $is_edit ) : ?>
					<div class="wim-field wim-customer-search-wrap">
						<label><?php esc_html_e( 'Search Customer', 'wp-invoice-manager' ); ?></label>
						<div class="wim-customer-search">
							<span class="dashicons dashicons-search wim-customer-search-icon"></span>
							<input type="text" id="wim-customer-search-input" autocomplete="off"
								placeholder="<?php esc_attr_e( 'Type a name, email, or phone…', 'wp-invoice-manager' ); ?>">
							<div class="wim-customer-search-results"></div>
						</div>
					</div>
					<?php endif; ?>

					<div class="wim-field">
						<label><?php esc_html_e( 'Client Name', 'wp-invoice-manager' ); ?></label>
						<input type="text" name="client_name" id="wim-client-name"
							value="<?php echo esc_attr( $client_name_val ); ?>"
							required>
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Email', 'wp-invoice-manager' ); ?></label>
						<input type="email" name="client_email" id="wim-client-email"
							value="<?php echo esc_attr( $client_email_val ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Phone', 'wp-invoice-manager' ); ?></label>
						<input type="tel" name="client_phone" id="wim-client-phone"
							value="<?php echo esc_attr( $client_phone_val ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Address', 'wp-invoice-manager' ); ?></label>
						<textarea name="client_address" id="wim-client-address"><?php echo esc_textarea( $client_address_val ); ?></textarea>
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
							<th class="col-drag"></th>
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
							<td class="col-drag">
								<span class="wim-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'wp-invoice-manager' ); ?>">
									<span class="dashicons dashicons-menu"></span>
								</span>
							</td>
							<td class="col-desc">
								<div class="wim-rte">
									<div class="wim-rte-toolbar">
										<button type="button" class="wim-rte-btn" data-cmd="bold" title="<?php esc_attr_e( 'Bold', 'wp-invoice-manager' ); ?>"><b>B</b></button>
										<button type="button" class="wim-rte-btn wim-rte-link-btn" title="<?php esc_attr_e( 'Add link', 'wp-invoice-manager' ); ?>">
											<span class="dashicons dashicons-admin-links"></span>
										</button>
									</div>
									<div class="wim-rte-editable" contenteditable="true" data-placeholder="<?php esc_attr_e( 'Item description', 'wp-invoice-manager' ); ?>"><?php echo wp_kses( $item['description'], WP_IM_Invoice::description_allowed_html() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
									<input type="hidden" class="wim-rte-input" name="items[<?php echo $i; ?>][description]"
										value="<?php echo esc_attr( $item['description'] ); ?>">
								</div>
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

			<?php if ( $is_edit ) :
				$payment_methods = WP_IM_Invoice::get_payment_methods();
				$paid_amt        = $invoice['totals']['paid'];
				$balance_amt     = $invoice['totals']['balance'];
			?>
			<!-- ── Payments ── -->
			<div class="wim-section" style="margin-top:28px">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-money-alt"></span>
					<?php esc_html_e( 'Payments', 'wp-invoice-manager' ); ?>
				</h3>

				<div class="wim-payment-summary">
					<div class="wim-payment-summary-item">
						<span class="wim-payment-summary-label"><?php esc_html_e( 'Paid', 'wp-invoice-manager' ); ?></span>
						<span class="wim-payment-summary-value wim-payment-paid"><?php echo esc_html( $symbol . number_format( $paid_amt, 2 ) ); ?></span>
					</div>
					<div class="wim-payment-summary-item">
						<span class="wim-payment-summary-label"><?php esc_html_e( 'Balance Due', 'wp-invoice-manager' ); ?></span>
						<span class="wim-payment-summary-value <?php echo $balance_amt > 0 ? 'wim-payment-balance' : 'wim-payment-paid'; ?>"><?php echo esc_html( $symbol . number_format( $balance_amt, 2 ) ); ?></span>
					</div>
					<button type="button" id="wim-record-payment-btn" class="wim-btn wim-btn-primary">
						<span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Record Payment', 'wp-invoice-manager' ); ?>
					</button>
				</div>

				<?php if ( ! empty( $invoice['payments'] ) ) : ?>
				<table class="wim-payments-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'wp-invoice-manager' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-invoice-manager' ); ?></th>
							<th><?php esc_html_e( 'Method', 'wp-invoice-manager' ); ?></th>
							<th><?php esc_html_e( 'Note', 'wp-invoice-manager' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $invoice['payments'] as $payment ) :
							$delete_payment_url = wp_nonce_url(
								add_query_arg( array(
									'action'     => 'wp_im_delete_payment',
									'invoice_id' => $post_id,
									'payment_id' => $payment['id'],
								), admin_url( 'admin-post.php' ) ),
								'wp_im_delete_payment_' . $payment['id'],
								'wp_im_delete_payment_nonce'
							);
						?>
						<tr>
							<td><?php echo esc_html( $payment['date'] ); ?></td>
							<td><strong><?php echo esc_html( $symbol . number_format( floatval( $payment['amount'] ), 2 ) ); ?></strong></td>
							<td><?php echo esc_html( $payment_methods[ $payment['method'] ] ?? $payment['method'] ); ?></td>
							<td><?php echo esc_html( $payment['note'] ); ?></td>
							<td>
								<a href="<?php echo esc_url( $delete_payment_url ); ?>" class="wim-remove-row wim-delete-link" title="<?php esc_attr_e( 'Delete', 'wp-invoice-manager' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</a>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php else : ?>
				<p class="wim-field-hint"><?php esc_html_e( 'No payments recorded yet.', 'wp-invoice-manager' ); ?></p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- ── Notes ── -->
			<div class="wim-section" style="margin-top:28px">
				<h3 class="wim-section-title">
					<span class="dashicons dashicons-format-aside"></span>
					<?php esc_html_e( 'Notes / Terms', 'wp-invoice-manager' ); ?>
				</h3>
				<div class="wim-field">
					<div class="wim-rte wim-rte-large">
						<div class="wim-rte-toolbar">
							<button type="button" class="wim-rte-btn" data-cmd="bold" title="<?php esc_attr_e( 'Bold', 'wp-invoice-manager' ); ?>"><b>B</b></button>
							<button type="button" class="wim-rte-btn" data-cmd="insertUnorderedList" title="<?php esc_attr_e( 'Bullet list', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-editor-ul"></span>
							</button>
							<button type="button" class="wim-rte-btn" data-cmd="insertOrderedList" title="<?php esc_attr_e( 'Numbered list', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-editor-ol"></span>
							</button>
							<button type="button" class="wim-rte-btn wim-rte-link-btn" title="<?php esc_attr_e( 'Add link', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-admin-links"></span>
							</button>
						</div>
						<div class="wim-rte-editable" contenteditable="true" data-placeholder="<?php esc_attr_e( 'Notes for the client, payment instructions, etc.', 'wp-invoice-manager' ); ?>"><?php echo $invoice ? wp_kses( $invoice['notes'], WP_IM_Invoice::notes_allowed_html() ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<input type="hidden" class="wim-rte-input" name="notes" value="<?php echo esc_attr( $invoice ? $invoice['notes'] : '' ); ?>">
					</div>
				</div>

				<?php if ( ! empty( $all_terms ) ) : ?>
				<div class="wim-field">
					<label><?php esc_html_e( 'Terms & Conditions to print with this invoice', 'wp-invoice-manager' ); ?></label>
					<div class="wim-terms-checklist">
						<?php foreach ( $all_terms as $i => $term ) : ?>
						<label class="wim-term-check">
							<input type="checkbox" name="terms_selected[]" value="<?php echo (int) $i; ?>"
								<?php checked( in_array( (int) $i, $selected_terms, true ) ); ?>>
							<span><?php echo esc_html( $term ); ?></span>
						</label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
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

					<?php
					$duplicate_url = wp_nonce_url(
						add_query_arg( array(
							'action'     => 'wp_im_duplicate_invoice',
							'invoice_id' => $post_id,
						), admin_url( 'admin-post.php' ) ),
						'wp_im_duplicate_' . $post_id,
						'wp_im_duplicate_nonce'
					);
					?>
					<a href="<?php echo esc_url( $duplicate_url ); ?>" class="wim-btn wim-btn-secondary">
						<span class="dashicons dashicons-admin-page" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Duplicate', 'wp-invoice-manager' ); ?>
					</a>

					<?php if ( ! empty( $share_url ) ) :
						$regen_url = wp_nonce_url(
							add_query_arg( array(
								'action'     => 'wp_im_regenerate_share_link',
								'invoice_id' => $post_id,
							), admin_url( 'admin-post.php' ) ),
							'wp_im_share_' . $post_id,
							'wp_im_share_nonce'
						);
					?>
					<div class="wim-share-wrap">
						<button type="button" class="wim-btn wim-btn-secondary wim-share-btn">
							<span class="dashicons dashicons-share" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
							<?php esc_html_e( 'Share', 'wp-invoice-manager' ); ?>
						</button>
						<div class="wim-share-popover">
							<label><?php esc_html_e( 'Shareable link — anyone with this link can view the invoice, no login needed.', 'wp-invoice-manager' ); ?></label>
							<div class="wim-share-row">
								<input type="text" class="wim-share-url" value="<?php echo esc_url( $share_url ); ?>" readonly onclick="this.select();">
								<div class="wim-share-icon-actions">
									<button type="button" class="wim-share-icon-btn wim-share-copy" title="<?php esc_attr_e( 'Copy link', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Copy link', 'wp-invoice-manager' ); ?>">
										<span class="dashicons dashicons-admin-page"></span>
									</button>
									<a href="<?php echo esc_url( $share_url ); ?>" target="_blank" rel="noopener" class="wim-share-icon-btn wim-share-open" title="<?php esc_attr_e( 'Open link in new tab', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Open link in new tab', 'wp-invoice-manager' ); ?>">
										<span class="dashicons dashicons-external"></span>
									</a>
								</div>
							</div>
							<a href="<?php echo esc_url( $share_url ); ?>" target="_blank" rel="noopener" class="wim-share-link-text"><?php echo esc_html( $share_url ); ?></a>
							<p class="wim-share-howto"><?php esc_html_e( '1) Click Copy — 2) Paste it in WhatsApp, SMS, or email and send it to your client. They can open it and view/print the invoice without logging in.', 'wp-invoice-manager' ); ?></p>
							<p class="wim-share-howto">
								<?php if ( ! empty( $invoice['share_view_count'] ) ) : ?>
									<span class="dashicons dashicons-visibility" style="font-size:13px;width:13px;height:13px;vertical-align:-2px"></span>
									<?php
									printf(
										/* translators: 1: number of times opened, 2: last-viewed date/time */
										esc_html( _n( 'Viewed %1$d time — last on %2$s', 'Viewed %1$d times — last on %2$s', $invoice['share_view_count'], 'wp-invoice-manager' ) ),
										(int) $invoice['share_view_count'],
										esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $invoice['share_last_viewed'] ) )
									);
									?>
								<?php else : ?>
									<?php esc_html_e( 'Not viewed by the client yet.', 'wp-invoice-manager' ); ?>
								<?php endif; ?>
							</p>
							<a href="<?php echo esc_url( $regen_url ); ?>" class="wim-share-regenerate"
								onclick="return confirm('<?php echo esc_js( __( 'This will invalidate the current link — anyone using the old one will lose access. Continue?', 'wp-invoice-manager' ) ); ?>');">
								<?php esc_html_e( 'Regenerate link', 'wp-invoice-manager' ); ?>
							</a>
						</div>
					</div>
					<?php endif; ?>

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

	<?php if ( $is_edit ) : ?>
	<!-- Record Payment modal -->
	<div class="wim-modal-overlay" id="wim-payment-modal-overlay">
		<div class="wim-modal" role="dialog" aria-modal="true" aria-labelledby="wim-payment-modal-title">
			<div class="wim-modal-header">
				<h2 id="wim-payment-modal-title"><?php esc_html_e( 'Record Payment', 'wp-invoice-manager' ); ?></h2>
				<button type="button" class="wim-modal-close" id="wim-payment-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-invoice-manager' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wim-modal-body">
				<?php wp_nonce_field( 'wp_im_payment_action', 'wp_im_payment_nonce' ); ?>
				<input type="hidden" name="action" value="wp_im_record_payment">
				<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">

				<div class="wim-form-grid">
					<div class="wim-field">
						<label><?php esc_html_e( 'Amount', 'wp-invoice-manager' ); ?></label>
						<input type="number" name="amount" min="0.01" step="0.01" required
							value="<?php echo esc_attr( $balance_amt > 0 ? number_format( $balance_amt, 2, '.', '' ) : '' ); ?>">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Date', 'wp-invoice-manager' ); ?></label>
						<input type="date" name="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
					</div>
				</div>
				<div class="wim-field">
					<label><?php esc_html_e( 'Method', 'wp-invoice-manager' ); ?></label>
					<?php wim_render_select( 'method', $payment_methods, 'bank' ); ?>
				</div>
				<div class="wim-field">
					<label><?php esc_html_e( 'Note', 'wp-invoice-manager' ); ?></label>
					<input type="text" name="note" placeholder="<?php esc_attr_e( 'Reference / transaction ID, etc. (optional)', 'wp-invoice-manager' ); ?>">
				</div>

				<div class="wim-modal-footer">
					<button type="button" class="wim-btn wim-btn-secondary wim-modal-cancel"><?php esc_html_e( 'Cancel', 'wp-invoice-manager' ); ?></button>
					<button type="submit" class="wim-btn wim-btn-primary">
						<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Save Payment', 'wp-invoice-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>

</div><!-- .wim-wrap -->
