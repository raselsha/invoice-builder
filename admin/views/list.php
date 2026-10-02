<?php
/**
 * Admin view: Invoice list.
 *
 * Variables available: $invoices, $statuses, $filter, $counts
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Note: revenue/outstanding totals are NOT summed here across all invoices —
// different invoices can be in different currencies, and adding e.g. USD and
// BDT amounts together would be meaningless. See the Reports page, which
// breaks these down per-currency instead (WP_IM_Invoice::get_stats_by_currency()).

$export_csv_url = wp_nonce_url(
	add_query_arg( array( 'action' => 'wp_im_export_csv' ), admin_url( 'admin-post.php' ) ),
	'wp_im_export_csv',
	'wp_im_export_nonce'
);

$bulk_statuses = WP_IM_Invoice::get_statuses();
?>
<div class="wim-wrap">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-media-spreadsheet"></span>
			<?php esc_html_e( 'Invoice Manager', 'wp-invoice-manager' ); ?>
		</h1>
		<div style="display:flex;gap:10px">
			<a href="<?php echo esc_url( $export_csv_url ); ?>" class="wim-btn wim-btn-secondary">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'wp-invoice-manager' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-im-new-invoice' ) ); ?>" class="wim-btn wim-btn-primary">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'New Invoice', 'wp-invoice-manager' ); ?>
			</a>
		</div>
	</div>

	<?php if ( isset( $_GET['message'] ) ) : ?>
		<?php $msgs = array(
			'created'   => __( '✓ Invoice created successfully.', 'wp-invoice-manager' ),
			'deleted'   => __( '✓ Invoice deleted.', 'wp-invoice-manager' ),
			'updated'   => __( '✓ Invoice updated.', 'wp-invoice-manager' ),
			'bulk_done' => __( '✓ Bulk action applied.', 'wp-invoice-manager' ),
		); ?>
		<div class="wim-notice wim-notice-success">
			<?php echo esc_html( $msgs[ sanitize_key( $_GET['message'] ) ] ?? '' ); ?>
		</div>
	<?php endif; ?>

	<!-- Stats cards -->
	<div class="wim-stats">
		<div class="wim-stat-card all">
			<div class="wim-stat-value"><?php echo esc_html( $counts['all'] ); ?></div>
			<div class="wim-stat-label"><?php esc_html_e( 'Total Invoices', 'wp-invoice-manager' ); ?></div>
		</div>
		<div class="wim-stat-card paid">
			<div class="wim-stat-value"><?php echo esc_html( $counts['paid'] ); ?></div>
			<div class="wim-stat-label"><?php esc_html_e( 'Paid', 'wp-invoice-manager' ); ?></div>
		</div>
		<div class="wim-stat-card overdue">
			<div class="wim-stat-value"><?php echo esc_html( $counts['overdue'] ); ?></div>
			<div class="wim-stat-label"><?php esc_html_e( 'Overdue', 'wp-invoice-manager' ); ?></div>
		</div>
		<div class="wim-stat-card draft">
			<div class="wim-stat-value"><?php echo esc_html( $counts['draft'] + $counts['sent'] ); ?></div>
			<div class="wim-stat-label"><?php esc_html_e( 'Pending', 'wp-invoice-manager' ); ?></div>
		</div>
	</div>

	<!-- Status filter tabs -->
	<div class="wim-status-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-invoice-manager' ) ); ?>"
		   class="wim-status-tab <?php echo ( ! $filter || 'all' === $filter ) ? 'active' : ''; ?>">
			<?php esc_html_e( 'All', 'wp-invoice-manager' ); ?>
			<span class="count"><?php echo esc_html( $counts['all'] ); ?></span>
		</a>
		<?php foreach ( $statuses as $key => $label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-invoice-manager', 'status' => $key ), admin_url( 'admin.php' ) ) ); ?>"
			   class="wim-status-tab <?php echo ( $filter === $key ) ? 'active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
				<span class="count"><?php echo esc_html( $counts[ $key ] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Invoice table -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wim-bulk-form">
		<?php wp_nonce_field( 'wp_im_bulk_action', 'wp_im_bulk_nonce' ); ?>
		<input type="hidden" name="action" value="wp_im_bulk_action">

		<?php if ( ! empty( $invoices ) ) : ?>
		<div class="wim-bulk-bar">
			<div style="width:160px">
				<?php
				$bulk_options = array( '' => __( 'Bulk actions…', 'wp-invoice-manager' ) );
				foreach ( $bulk_statuses as $key => $label ) {
					$bulk_options[ $key ] = sprintf( __( 'Set status: %s', 'wp-invoice-manager' ), $label );
				}
				$bulk_options['delete'] = __( 'Delete', 'wp-invoice-manager' );
				wim_render_select( 'bulk_action', $bulk_options, '' );
				?>
			</div>
			<button type="submit" class="wim-btn wim-btn-secondary wim-btn-sm" id="wim-bulk-apply">
				<?php esc_html_e( 'Apply', 'wp-invoice-manager' ); ?>
			</button>
			<span class="wim-bulk-count"></span>
		</div>
		<?php endif; ?>

	<div class="wim-table-wrap">
	<div class="wim-table-scroll">
		<table class="wim-table">
			<thead>
				<tr>
					<th class="col-check"><input type="checkbox" id="wim-select-all"></th>
					<th><?php esc_html_e( 'Invoice #', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Client', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Date', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Due Date', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-invoice-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $invoices ) ) : ?>
				<?php foreach ( $invoices as $post ) :
					$inv    = WP_IM_Invoice::get( $post->ID );
					$symbol = WP_IM_Invoice::currency_symbol( $inv['currency'] );
					$print_url = wp_nonce_url(
						add_query_arg( array(
							'action'     => 'wp_im_print_invoice',
							'invoice_id' => $post->ID,
						), admin_url( 'admin-post.php' ) ),
						'wp_im_print_' . $post->ID,
						'wp_im_print_nonce'
					);
					$delete_url = wp_nonce_url(
						add_query_arg( array(
							'action'     => 'wp_im_delete_invoice',
							'invoice_id' => $post->ID,
						), admin_url( 'admin-post.php' ) ),
						'wp_im_delete_' . $post->ID,
						'wp_im_delete_nonce'
					);
					$share_url = WP_IM_Invoice::get_share_url( $post->ID );
					$regen_url = wp_nonce_url(
						add_query_arg( array(
							'action'     => 'wp_im_regenerate_share_link',
							'invoice_id' => $post->ID,
						), admin_url( 'admin-post.php' ) ),
						'wp_im_share_' . $post->ID,
						'wp_im_share_nonce'
					);
					$duplicate_url = wp_nonce_url(
						add_query_arg( array(
							'action'     => 'wp_im_duplicate_invoice',
							'invoice_id' => $post->ID,
						), admin_url( 'admin-post.php' ) ),
						'wp_im_duplicate_' . $post->ID,
						'wp_im_duplicate_nonce'
					);
				?>
				<tr>
					<td class="col-check"><input type="checkbox" class="wim-row-check" name="invoice_ids[]" value="<?php echo esc_attr( $post->ID ); ?>"></td>
					<td>
						<span class="wim-invoice-number">
							<?php if ( ! empty( $inv['recurring_frequency'] ) ) : ?>
								<span class="dashicons dashicons-update wim-recurring-icon" title="<?php esc_attr_e( 'Recurring invoice', 'wp-invoice-manager' ); ?>"></span>
							<?php endif; ?>
							<strong><?php echo esc_html( $inv['number'] ); ?></strong>
						</span>
					</td>
					<td>
						<?php echo esc_html( $inv['client_name'] ); ?><br>
						<small style="color:var(--wim-muted)"><?php echo esc_html( $inv['client_email'] ); ?></small>
					</td>
					<td><?php echo esc_html( $inv['invoice_date'] ); ?></td>
					<td><?php echo esc_html( $inv['due_date'] ); ?></td>
					<td>
						<span class="wim-badge wim-badge-<?php echo esc_attr( $inv['status'] ); ?>">
							<?php echo esc_html( $statuses[ $inv['status'] ] ?? $inv['status'] ); ?>
						</span>
					</td>
					<td class="col-amount"><?php echo esc_html( $symbol . number_format( $inv['totals']['total'], 2 ) ); ?></td>
					<td>
						<div class="col-actions">
							<a href="<?php echo esc_url( $print_url ); ?>"
							   target="_blank"
							   class="wim-icon-btn"
							   title="<?php esc_attr_e( 'View', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'View', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-visibility"></span>
							</a>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-im-new-invoice', 'invoice_id' => $post->ID ), admin_url( 'admin.php' ) ) ); ?>"
							   class="wim-icon-btn"
							   title="<?php esc_attr_e( 'Edit', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</a>
							<a href="<?php echo esc_url( $print_url ); ?>"
							   target="_blank"
							   class="wim-icon-btn"
							   title="<?php esc_attr_e( 'Print', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Print', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-printer"></span>
							</a>
							<a href="<?php echo esc_url( $duplicate_url ); ?>"
							   class="wim-icon-btn"
							   title="<?php esc_attr_e( 'Duplicate', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Duplicate', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-admin-page"></span>
							</a>
							<div class="wim-share-wrap">
								<button type="button" class="wim-icon-btn wim-share-btn"
									title="<?php esc_attr_e( 'Share', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Share', 'wp-invoice-manager' ); ?>">
									<span class="dashicons dashicons-share"></span>
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
									<a href="<?php echo esc_url( $regen_url ); ?>" class="wim-share-regenerate"
										onclick="return confirm('<?php echo esc_js( __( 'This will invalidate the current link — anyone using the old one will lose access. Continue?', 'wp-invoice-manager' ) ); ?>');">
										<?php esc_html_e( 'Regenerate link', 'wp-invoice-manager' ); ?>
									</a>
								</div>
							</div>
							<a href="<?php echo esc_url( $delete_url ); ?>"
							   class="wim-icon-btn wim-icon-btn-danger wim-delete-link"
							   title="<?php esc_attr_e( 'Delete', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'wp-invoice-manager' ); ?>">
								<span class="dashicons dashicons-trash"></span>
							</a>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8">
						<div class="wim-table-empty">
							<span class="dashicons dashicons-media-spreadsheet"></span>
							<p><?php esc_html_e( 'No invoices found. Create your first invoice!', 'wp-invoice-manager' ); ?></p>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-im-new-invoice' ) ); ?>" class="wim-btn wim-btn-primary">
								<?php esc_html_e( 'Create Invoice', 'wp-invoice-manager' ); ?>
							</a>
						</div>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	</div>
	</form>

</div>
