<?php
/**
 * Admin view: Customer list.
 *
 * Variables available: $customers
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wim-wrap">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-groups"></span>
			<?php esc_html_e( 'Customers', 'wp-invoice-manager' ); ?>
		</h1>
		<button type="button" id="wim-add-customer-btn" class="wim-btn wim-btn-primary">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add New Customer', 'wp-invoice-manager' ); ?>
		</button>
	</div>

	<?php if ( isset( $_GET['message'] ) ) : ?>
		<?php $msgs = array(
			'saved'   => __( '✓ Customer saved.', 'wp-invoice-manager' ),
			'deleted' => __( '✓ Customer deleted.', 'wp-invoice-manager' ),
		); ?>
		<div class="wim-notice wim-notice-success">
			<?php echo esc_html( $msgs[ sanitize_key( $_GET['message'] ) ] ?? '' ); ?>
		</div>
	<?php endif; ?>

	<div class="wim-table-wrap">
		<table class="wim-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Email', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Address', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-invoice-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $customers ) ) : ?>
				<?php foreach ( $customers as $customer ) :
					$delete_url = wp_nonce_url(
						add_query_arg( array(
							'action'      => 'wp_im_delete_customer',
							'customer_id' => $customer['id'],
						), admin_url( 'admin-post.php' ) ),
						'wp_im_delete_customer_' . $customer['id'],
						'wp_im_delete_customer_nonce'
					);
					$new_invoice_url = add_query_arg( array(
						'page'        => 'wp-im-new-invoice',
						'customer_id' => $customer['id'],
					), admin_url( 'admin.php' ) );
				?>
				<tr>
					<td><strong><?php echo esc_html( $customer['name'] ); ?></strong></td>
					<td><?php echo esc_html( $customer['email'] ); ?></td>
					<td><?php echo esc_html( $customer['phone'] ); ?></td>
					<td><?php echo esc_html( wp_trim_words( WP_IM_Invoice::clean_address( $customer['address'] ), 6 ) ); ?></td>
					<td>
						<div class="col-actions">
							<a href="<?php echo esc_url( $new_invoice_url ); ?>" class="wim-btn wim-btn-secondary wim-btn-sm">
								<span class="dashicons dashicons-media-spreadsheet" style="font-size:14px;width:14px;height:14px;margin-top:2px"></span>
								<?php esc_html_e( 'New Invoice', 'wp-invoice-manager' ); ?>
							</a>
							<button type="button" class="wim-btn wim-btn-secondary wim-btn-sm wim-edit-customer-btn"
								data-id="<?php echo esc_attr( $customer['id'] ); ?>"
								data-name="<?php echo esc_attr( $customer['name'] ); ?>"
								data-email="<?php echo esc_attr( $customer['email'] ); ?>"
								data-phone="<?php echo esc_attr( $customer['phone'] ); ?>"
								data-address="<?php echo esc_attr( $customer['address'] ); ?>">
								<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;margin-top:2px"></span>
								<?php esc_html_e( 'Edit', 'wp-invoice-manager' ); ?>
							</button>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="wim-btn wim-btn-danger wim-btn-sm wim-delete-link">
								<span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;margin-top:2px"></span>
								<?php esc_html_e( 'Delete', 'wp-invoice-manager' ); ?>
							</a>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="5">
						<div class="wim-table-empty">
							<span class="dashicons dashicons-groups"></span>
							<p><?php esc_html_e( 'No customers yet. They\'ll be added automatically the first time you save an invoice for them, or you can add one now.', 'wp-invoice-manager' ); ?></p>
							<button type="button" id="wim-add-customer-btn-empty" class="wim-btn wim-btn-primary wim-add-customer-btn-alias">
								<?php esc_html_e( 'Add Customer', 'wp-invoice-manager' ); ?>
							</button>
						</div>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Add / Edit Customer modal -->
	<div class="wim-modal-overlay" id="wim-customer-modal-overlay">
		<div class="wim-modal" role="dialog" aria-modal="true" aria-labelledby="wim-customer-modal-title">
			<div class="wim-modal-header">
				<h2 id="wim-customer-modal-title"><?php esc_html_e( 'Add New Customer', 'wp-invoice-manager' ); ?></h2>
				<button type="button" class="wim-modal-close" id="wim-customer-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-invoice-manager' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wim-modal-body">
				<?php wp_nonce_field( 'wp_im_customer_action', 'wp_im_customer_nonce' ); ?>
				<input type="hidden" name="action" value="wp_im_save_customer">
				<input type="hidden" name="post_id" id="wim-customer-post-id" value="">

				<div class="wim-field">
					<label><?php esc_html_e( 'Name', 'wp-invoice-manager' ); ?></label>
					<input type="text" name="name" id="wim-customer-name" required>
				</div>
				<div class="wim-form-grid">
					<div class="wim-field">
						<label><?php esc_html_e( 'Email', 'wp-invoice-manager' ); ?></label>
						<input type="email" name="email" id="wim-customer-email">
					</div>
					<div class="wim-field">
						<label><?php esc_html_e( 'Phone', 'wp-invoice-manager' ); ?></label>
						<input type="tel" name="phone" id="wim-customer-phone">
					</div>
				</div>
				<div class="wim-field">
					<label><?php esc_html_e( 'Address', 'wp-invoice-manager' ); ?></label>
					<textarea name="address" id="wim-customer-address"></textarea>
				</div>

				<div class="wim-modal-footer">
					<button type="button" class="wim-btn wim-btn-secondary wim-modal-cancel"><?php esc_html_e( 'Cancel', 'wp-invoice-manager' ); ?></button>
					<button type="submit" class="wim-btn wim-btn-primary">
						<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
						<?php esc_html_e( 'Save Customer', 'wp-invoice-manager' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>

</div>
