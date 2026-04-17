<?php
/**
 * Admin view: Invoice list.
 *
 * Variables available: $invoices, $statuses, $filter, $counts
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Calculate total revenue from paid invoices
$total_paid = 0;
$total_overdue = 0;
foreach ( WP_IM_Invoice::get_all() as $_p ) {
	$_inv = WP_IM_Invoice::get( $_p->ID );
	$_status = get_post_meta( $_p->ID, WP_IM_Invoice::META_STATUS, true );
	if ( 'paid' === $_status ) $total_paid += $_inv['totals']['total'];
	if ( 'overdue' === $_status ) $total_overdue += $_inv['totals']['total'];
}
?>
<div class="wim-wrap">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-media-spreadsheet"></span>
			<?php esc_html_e( 'Invoice Manager', 'wp-invoice-manager' ); ?>
		</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-im-new-invoice' ) ); ?>" class="wim-btn wim-btn-primary">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'New Invoice', 'wp-invoice-manager' ); ?>
		</a>
	</div>

	<?php if ( isset( $_GET['message'] ) ) : ?>
		<?php $msgs = array(
			'created' => __( '✓ Invoice created successfully.', 'wp-invoice-manager' ),
			'deleted' => __( '✓ Invoice deleted.', 'wp-invoice-manager' ),
			'updated' => __( '✓ Invoice updated.', 'wp-invoice-manager' ),
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
	<div class="wim-table-wrap">
		<table class="wim-table">
			<thead>
				<tr>
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
				?>
				<tr>
					<td><strong><?php echo esc_html( $inv['number'] ); ?></strong></td>
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
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-im-new-invoice', 'invoice_id' => $post->ID ), admin_url( 'admin.php' ) ) ); ?>"
							   class="wim-btn wim-btn-secondary wim-btn-sm">
								<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;margin-top:2px"></span>
								<?php esc_html_e( 'Edit', 'wp-invoice-manager' ); ?>
							</a>
							<a href="<?php echo esc_url( $print_url ); ?>"
							   target="_blank"
							   class="wim-btn wim-btn-secondary wim-btn-sm">
								<span class="dashicons dashicons-printer" style="font-size:14px;width:14px;height:14px;margin-top:2px"></span>
								<?php esc_html_e( 'Print', 'wp-invoice-manager' ); ?>
							</a>
							<a href="<?php echo esc_url( $delete_url ); ?>"
							   class="wim-btn wim-btn-danger wim-btn-sm wim-delete-link">
								<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;margin-top:2px"></span>
								<?php esc_html_e( 'Delete', 'wp-invoice-manager' ); ?>
							</a>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="7">
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
