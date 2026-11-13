<?php
/**
 * Admin view: Payment Links — the list, and the create/edit form.
 *
 * Variables: $editing (bool), $link (array|null), $link_id (int)
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$wim_page_url = admin_url( 'admin.php?page=wp-im-payment-links' );
$wim_error    = get_transient( 'wim_link_error_' . get_current_user_id() );
if ( $wim_error ) {
	delete_transient( 'wim_link_error_' . get_current_user_id() );
}
$wim_messages = array(
	'link_created' => __( '✓ Payment link created — copy it below and send it to your customer.', 'wp-invoice-manager' ),
	'link_updated' => __( '✓ Payment link saved.', 'wp-invoice-manager' ),
	'link_deleted' => __( '✓ Payment link deleted.', 'wp-invoice-manager' ),
);
$wim_message  = sanitize_key( $_GET['message'] ?? '' );

$wim_plain = static function ( $amount ) {
	return null === $amount || '' === $amount ? '' : rtrim( rtrim( number_format( (float) $amount, 2, '.', '' ), '0' ), '.' );
};

$wim_toggle = static function ( $name, $checked, $label, $help ) {
	?>
	<label class="wim-pl-toggle">
		<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?>>
		<span class="wim-pl-toggle__track" aria-hidden="true"><span class="wim-pl-toggle__thumb"></span></span>
		<span class="wim-pl-toggle__text"><strong><?php echo esc_html( $label ); ?></strong><small><?php echo esc_html( $help ); ?></small></span>
	</label>
	<?php
};
?>
<div class="wim-wrap wim-pl">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-admin-links"></span>
			<?php echo esc_html( $editing ? ( $link ? __( 'Edit Payment Link', 'wp-invoice-manager' ) : __( 'New Payment Link', 'wp-invoice-manager' ) ) : __( 'Payment Links', 'wp-invoice-manager' ) ); ?>
		</h1>
		<div style="display:flex;gap:10px">
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( $wim_page_url ); ?>" class="wim-btn wim-btn-secondary">&larr; <?php esc_html_e( 'All links', 'wp-invoice-manager' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( 'edit', 1, $wim_page_url ) ); ?>" class="wim-btn wim-btn-primary">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'New Payment Link', 'wp-invoice-manager' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( isset( $wim_messages[ $wim_message ] ) ) : ?>
		<div class="wim-notice wim-notice-success"><?php echo esc_html( $wim_messages[ $wim_message ] ); ?></div>
	<?php endif; ?>
	<?php if ( $wim_error ) : ?>
		<div class="wim-notice wim-notice-error"><?php echo esc_html( $wim_error ); ?></div>
	<?php endif; ?>
	<?php if ( ! WP_IM_Stripe::is_ready() ) : ?>
		<div class="wim-notice wim-notice-info">
			<?php esc_html_e( 'Online payments are switched off, so links show “not available” to customers.', 'wp-invoice-manager' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-im-settings#wim-online-payments' ) ); ?>"><?php esc_html_e( 'Turn on Stripe in Settings', 'wp-invoice-manager' ); ?> &rarr;</a>
		</div>
	<?php endif; ?>

<?php if ( ! $editing ) : /* ───────────────────────── LIST ───────────────────────── */ ?>

	<?php $wim_links = WP_IM_Payment_Link::get_all(); ?>
	<div class="wim-table-wrap">
		<div class="wim-table-scroll">
		<table class="wim-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Link', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Received', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-invoice-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( $wim_links ) : ?>
				<?php foreach ( $wim_links as $wim_post ) :
					$wim_l      = WP_IM_Payment_Link::get( $wim_post );
					$wim_status = WP_IM_Payment_Link::status( $wim_l );
					list( $wim_count, $wim_total ) = WP_IM_Payment_Link::totals( $wim_l['id'] );
					$wim_edit   = add_query_arg( array( 'edit' => 1, 'link_id' => $wim_l['id'] ), $wim_page_url );
					$wim_delete = wp_nonce_url( add_query_arg( array( 'action' => 'wp_im_delete_payment_link', 'link_id' => $wim_l['id'] ), admin_url( 'admin-post.php' ) ), 'wp_im_delete_link_' . $wim_l['id'], 'wp_im_delete_link_nonce' );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $wim_edit ); ?>"><strong><?php echo esc_html( $wim_l['title'] ); ?></strong></a><br>
						<small style="color:var(--wim-muted)"><?php echo esc_html( wp_date( get_option( 'date_format' ), $wim_l['created'] ) ); ?><?php echo $wim_l['single_use'] ? ' · ' . esc_html__( 'one-time', 'wp-invoice-manager' ) : ''; ?></small>
					</td>
					<td><?php echo esc_html( WP_IM_Payment_Link::summary( $wim_l ) ); ?></td>
					<td>
						<span class="wim-badge wim-pl-badge-<?php echo esc_attr( $wim_status ); ?>"><?php echo esc_html( WP_IM_Payment_Link::status_label( $wim_status ) ); ?></span>
						<?php if ( WP_IM_Payment_Link::setup_problem( $wim_l ) ) : ?>
							<br><small style="color:var(--wim-danger)" title="<?php echo esc_attr( WP_IM_Payment_Link::setup_problem( $wim_l ) ); ?>"><?php esc_html_e( 'Needs an amount', 'wp-invoice-manager' ); ?></small>
						<?php endif; ?>
						<?php if ( $wim_l['expires'] && 'active' === $wim_status ) : ?>
							<br><small style="color:var(--wim-muted)"><?php echo esc_html( sprintf( /* translators: %s: date */ __( 'until %s', 'wp-invoice-manager' ), wp_date( get_option( 'date_format' ), strtotime( $wim_l['expires'] . ' 12:00:00' ) ) ) ); ?></small>
						<?php endif; ?>
					</td>
					<td class="col-amount">
						<?php if ( $wim_count ) : ?>
							<?php echo esc_html( WP_IM_Stripe::format( $wim_total, $wim_l['currency'] ) ); ?><br>
							<small style="color:var(--wim-muted);font-weight:400"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s payment', '%s payments', $wim_count, 'wp-invoice-manager' ), number_format_i18n( $wim_count ) ) ); ?></small>
						<?php else : ?>
							<span style="color:var(--wim-muted);font-weight:400">—</span>
						<?php endif; ?>
					</td>
					<td>
						<div class="col-actions">
							<button type="button" class="wim-icon-btn" data-wim-copy="<?php echo esc_attr( $wim_l['url'] ); ?>" title="<?php esc_attr_e( 'Copy link', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Copy link', 'wp-invoice-manager' ); ?>"><span class="dashicons dashicons-admin-page"></span></button>
							<button type="button" class="wim-icon-btn" data-wim-qr-open="<?php echo esc_attr( $wim_l['url'] ); ?>" data-wim-qr-name="<?php echo esc_attr( sanitize_title( $wim_l['title'] ) ); ?>" title="<?php esc_attr_e( 'QR code', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'QR code', 'wp-invoice-manager' ); ?>"><span class="dashicons dashicons-screenoptions"></span></button>
							<a class="wim-icon-btn" href="<?php echo esc_url( $wim_l['url'] ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Open payment page', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Open payment page', 'wp-invoice-manager' ); ?>"><span class="dashicons dashicons-external"></span></a>
							<a class="wim-icon-btn" href="<?php echo esc_url( $wim_edit ); ?>" title="<?php esc_attr_e( 'Edit', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'wp-invoice-manager' ); ?>"><span class="dashicons dashicons-edit"></span></a>
							<a class="wim-icon-btn wim-icon-btn-danger" href="<?php echo esc_url( $wim_delete ); ?>" data-wim-confirm title="<?php esc_attr_e( 'Delete', 'wp-invoice-manager' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'wp-invoice-manager' ); ?>"><span class="dashicons dashicons-trash"></span></a>
						</div>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="5" class="wim-table-empty">
						<span class="dashicons dashicons-admin-links"></span>
						<?php esc_html_e( 'No payment links yet. Create one, copy it, and send it to a customer — they pay online and a paid invoice is filed for you.', 'wp-invoice-manager' ); ?>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		</div>
	</div>

	<dialog class="wim-pl-dialog" data-wim-qr-dialog>
		<div class="wim-pl-dialog__body">
			<button type="button" class="wim-pl-dialog__close" data-wim-dialog-close aria-label="<?php esc_attr_e( 'Close', 'wp-invoice-manager' ); ?>">&times;</button>
			<h2><?php esc_html_e( 'QR code', 'wp-invoice-manager' ); ?></h2>
			<div class="wim-pl-qr" data-wim-qr-target></div>
			<p class="wim-pl-dialog__url" data-wim-qr-url></p>
			<button type="button" class="wim-btn wim-btn-primary" data-wim-qr-download><?php esc_html_e( 'Download PNG', 'wp-invoice-manager' ); ?></button>
		</div>
	</dialog>

<?php else : /* ───────────────────────── EDITOR ───────────────────────── */ ?>

	<?php
	$wim_l = $link ? $link : array(
		'id' => 0, 'title' => '', 'description' => '', 'mode' => 'fixed', 'currency' => get_option( 'wp_im_default_currency', 'USD' ),
		'amount' => 0, 'choices' => array(), 'allow_custom' => false, 'min' => null, 'max' => null, 'note' => 'off', 'note_label' => '',
		'customer_id' => 0, 'expires' => '', 'single_use' => false, 'active' => true, 'used_at' => 0, 'url' => '',
	);
	$wim_symbol    = WP_IM_Invoice::currency_symbol( $wim_l['currency'] );
	$wim_customers = array( '0' => __( '— Anyone (customer types their details) —', 'wp-invoice-manager' ) );
	foreach ( WP_IM_Customer::get_all() as $wim_customer ) {
		$wim_customers[ (string) $wim_customer['id'] ] = $wim_customer['name'] . ( $wim_customer['email'] ? ' — ' . $wim_customer['email'] : '' );
	}
	$wim_modes = array(
		'fixed'   => array( __( 'Fixed amount', 'wp-invoice-manager' ), __( 'The customer pays exactly what you set.', 'wp-invoice-manager' ), 'dashicons-tag' ),
		'choices' => array( __( 'Preset options', 'wp-invoice-manager' ), __( 'They pick one, e.g. 100 · 200 · 300.', 'wp-invoice-manager' ), 'dashicons-screenoptions' ),
		'open'    => array( __( 'Customer decides', 'wp-invoice-manager' ), __( 'They type in any amount.', 'wp-invoice-manager' ), 'dashicons-edit' ),
	);
	?>
	<div class="wim-pl-layout">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wim-form-wrap wim-pl-editor" data-wim-pl-editor>
			<?php wp_nonce_field( 'wp_im_payment_link', 'wp_im_payment_link_nonce' ); ?>
			<input type="hidden" name="action" value="wp_im_save_payment_link">
			<input type="hidden" name="link_id" value="<?php echo esc_attr( $wim_l['id'] ); ?>">

			<div class="wim-section">
				<h3 class="wim-section-title"><span class="dashicons dashicons-info"></span><?php esc_html_e( 'What is it for?', 'wp-invoice-manager' ); ?></h3>
				<div class="wim-field">
					<label for="wim-pl-title"><?php esc_html_e( 'Name', 'wp-invoice-manager' ); ?></label>
					<input type="text" id="wim-pl-title" name="title" required value="<?php echo esc_attr( $wim_l['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Website design — 50% advance', 'wp-invoice-manager' ); ?>">
				</div>
				<div class="wim-field">
					<label for="wim-pl-desc"><?php esc_html_e( 'Description', 'wp-invoice-manager' ); ?> <span class="wim-pl-muted"><?php esc_html_e( '(optional — shown to the customer)', 'wp-invoice-manager' ); ?></span></label>
					<textarea id="wim-pl-desc" name="description" rows="3" placeholder="<?php esc_attr_e( 'e.g. Advance for the new website, as agreed on WhatsApp.', 'wp-invoice-manager' ); ?>"><?php echo esc_textarea( $wim_l['description'] ); ?></textarea>
				</div>
			</div>

			<div class="wim-section">
				<h3 class="wim-section-title"><span class="dashicons dashicons-money-alt"></span><?php esc_html_e( 'How much', 'wp-invoice-manager' ); ?></h3>
				<div class="wim-pl-modes" role="radiogroup">
					<?php foreach ( $wim_modes as $wim_value => $wim_mode ) : ?>
						<label class="wim-pl-mode">
							<input type="radio" name="mode" value="<?php echo esc_attr( $wim_value ); ?>" <?php checked( $wim_l['mode'], $wim_value ); ?> data-wim-mode>
							<span class="wim-pl-mode__card">
								<span class="dashicons <?php echo esc_attr( $wim_mode[2] ); ?>"></span>
								<strong><?php echo esc_html( $wim_mode[0] ); ?></strong>
								<small><?php echo esc_html( $wim_mode[1] ); ?></small>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="wim-form-grid wim-pl-row">
					<div class="wim-field">
						<label><?php esc_html_e( 'Currency', 'wp-invoice-manager' ); ?></label>
						<div data-wim-currency><?php wim_render_select( 'currency', WP_IM_Invoice::get_currencies(), $wim_l['currency'] ); ?></div>
					</div>
					<div class="wim-field" data-wim-show="fixed">
						<label for="wim-pl-amount"><?php esc_html_e( 'Amount', 'wp-invoice-manager' ); ?></label>
						<div class="wim-pl-money"><span data-wim-symbol><?php echo esc_html( $wim_symbol ); ?></span><input type="text" inputmode="decimal" id="wim-pl-amount" name="amount" value="<?php echo esc_attr( $wim_l['amount'] ? $wim_plain( $wim_l['amount'] ) : '' ); ?>" placeholder="0.00"></div>
					</div>
				</div>

				<div class="wim-field" data-wim-show="choices">
					<label for="wim-pl-choice"><?php esc_html_e( 'Amount options', 'wp-invoice-manager' ); ?></label>
					<div data-wim-chips>
						<input type="hidden" name="choices" value="<?php echo esc_attr( implode( ',', array_map( $wim_plain, $wim_l['choices'] ) ) ); ?>" data-wim-chips-value>
						<div class="wim-pl-chips" data-wim-chips-list></div>
						<div class="wim-pl-money wim-pl-chips__add">
							<span data-wim-symbol><?php echo esc_html( $wim_symbol ); ?></span>
							<input type="text" inputmode="decimal" id="wim-pl-choice" placeholder="<?php esc_attr_e( 'Add amount', 'wp-invoice-manager' ); ?>" data-wim-chips-input>
							<button type="button" class="wim-btn wim-btn-secondary" data-wim-chips-add><?php esc_html_e( 'Add', 'wp-invoice-manager' ); ?></button>
						</div>
					</div>
					<p class="wim-field-hint"><?php esc_html_e( 'Type an amount and press Enter. Add as many as you like.', 'wp-invoice-manager' ); ?></p>
					<?php $wim_toggle( 'allow_custom', $wim_l['allow_custom'], __( 'Also let them enter another amount', 'wp-invoice-manager' ), __( 'Adds an “Other” option next to your amounts.', 'wp-invoice-manager' ) ); ?>
				</div>

				<div class="wim-form-grid wim-pl-row" data-wim-show="open">
					<div class="wim-field">
						<label for="wim-pl-min"><?php esc_html_e( 'Minimum', 'wp-invoice-manager' ); ?> <span class="wim-pl-muted"><?php esc_html_e( '(optional)', 'wp-invoice-manager' ); ?></span></label>
						<div class="wim-pl-money"><span data-wim-symbol><?php echo esc_html( $wim_symbol ); ?></span><input type="text" inputmode="decimal" id="wim-pl-min" name="min" value="<?php echo esc_attr( $wim_plain( $wim_l['min'] ) ); ?>" placeholder="—"></div>
					</div>
					<div class="wim-field">
						<label for="wim-pl-max"><?php esc_html_e( 'Maximum', 'wp-invoice-manager' ); ?> <span class="wim-pl-muted"><?php esc_html_e( '(optional)', 'wp-invoice-manager' ); ?></span></label>
						<div class="wim-pl-money"><span data-wim-symbol><?php echo esc_html( $wim_symbol ); ?></span><input type="text" inputmode="decimal" id="wim-pl-max" name="max" value="<?php echo esc_attr( $wim_plain( $wim_l['max'] ) ); ?>" placeholder="—"></div>
					</div>
				</div>
			</div>

			<div class="wim-section">
				<h3 class="wim-section-title"><span class="dashicons dashicons-admin-users"></span><?php esc_html_e( 'Customer', 'wp-invoice-manager' ); ?></h3>
				<div class="wim-field">
					<label><?php esc_html_e( 'Who is paying?', 'wp-invoice-manager' ); ?></label>
					<?php wim_render_select( 'customer_id', $wim_customers, (string) $wim_l['customer_id'] ); ?>
					<p class="wim-field-hint"><?php esc_html_e( 'Pick a customer to fill in their name and email for them, and file the paid invoice under them.', 'wp-invoice-manager' ); ?></p>
				</div>
				<div class="wim-form-grid">
					<div class="wim-field">
						<label><?php esc_html_e( 'Note field', 'wp-invoice-manager' ); ?></label>
						<?php wim_render_select( 'note', array( 'off' => __( 'Don’t ask', 'wp-invoice-manager' ), 'optional' => __( 'Ask (optional)', 'wp-invoice-manager' ), 'required' => __( 'Ask (required)', 'wp-invoice-manager' ) ), $wim_l['note'] ); ?>
					</div>
					<div class="wim-field">
						<label for="wim-pl-note-label"><?php esc_html_e( 'Note label', 'wp-invoice-manager' ); ?></label>
						<input type="text" id="wim-pl-note-label" name="note_label" value="<?php echo esc_attr( $wim_l['note_label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Invoice number', 'wp-invoice-manager' ); ?>">
					</div>
				</div>
			</div>

			<div class="wim-section">
				<h3 class="wim-section-title"><span class="dashicons dashicons-clock"></span><?php esc_html_e( 'Availability', 'wp-invoice-manager' ); ?></h3>
				<div class="wim-field">
					<label for="wim-pl-expires"><?php esc_html_e( 'Link works until', 'wp-invoice-manager' ); ?></label>
					<div class="wim-pl-date" data-wim-date>
						<input type="hidden" name="expires" value="<?php echo esc_attr( $wim_l['expires'] ); ?>" data-wim-date-value>
						<button type="button" id="wim-pl-expires" class="wim-pl-date__trigger" aria-haspopup="dialog" aria-expanded="false">
							<span class="dashicons dashicons-calendar-alt"></span>
							<span data-wim-date-label><?php echo esc_html( $wim_l['expires'] ? wp_date( get_option( 'date_format' ), strtotime( $wim_l['expires'] . ' 12:00:00' ) ) : __( 'No end date', 'wp-invoice-manager' ) ); ?></span>
						</button>
						<button type="button" class="wim-pl-date__clear" data-wim-date-clear aria-label="<?php esc_attr_e( 'Remove end date', 'wp-invoice-manager' ); ?>" <?php echo $wim_l['expires'] ? '' : 'hidden'; ?>>&times;</button>
						<div class="wim-pl-cal" data-wim-cal hidden></div>
					</div>
					<p class="wim-field-hint"><?php esc_html_e( 'After this day the link stops taking payments.', 'wp-invoice-manager' ); ?></p>
				</div>
				<?php $wim_toggle( 'single_use', $wim_l['single_use'], __( 'One-time link', 'wp-invoice-manager' ), __( 'Closes automatically after one successful payment — handy instead of an invoice.', 'wp-invoice-manager' ) ); ?>
				<?php $wim_toggle( 'active', $wim_l['active'], __( 'Link is on', 'wp-invoice-manager' ), __( 'Turn off to stop payments without deleting the link.', 'wp-invoice-manager' ) ); ?>
				<?php if ( $wim_l['used_at'] ) : ?>
					<div class="wim-pl-used">
						<?php echo esc_html( sprintf( /* translators: %s: date */ __( 'Paid and closed on %s.', 'wp-invoice-manager' ), wp_date( get_option( 'date_format' ), $wim_l['used_at'] ) ) ); ?>
						<label><input type="checkbox" name="reopen" value="1"> <?php esc_html_e( 'Reopen for another payment', 'wp-invoice-manager' ); ?></label>
					</div>
				<?php endif; ?>
			</div>

			<div class="wim-form-actions">
				<button type="submit" class="wim-btn wim-btn-primary">
					<span class="dashicons dashicons-saved" style="font-size:14px;width:14px;height:14px;margin-top:3px"></span>
					<?php echo esc_html( $link ? __( 'Save Link', 'wp-invoice-manager' ) : __( 'Create Link', 'wp-invoice-manager' ) ); ?>
				</button>
			</div>
		</form>

		<aside class="wim-pl-side">
			<?php if ( $link ) :
				$wim_status = WP_IM_Payment_Link::status( $link );
				list( $wim_count, $wim_total ) = WP_IM_Payment_Link::totals( $link['id'] );
			?>
				<div class="wim-form-wrap wim-pl-share">
					<h3 class="wim-section-title"><span class="dashicons dashicons-share"></span><?php esc_html_e( 'Share', 'wp-invoice-manager' ); ?></h3>
					<span class="wim-badge wim-pl-badge-<?php echo esc_attr( $wim_status ); ?>"><?php echo esc_html( WP_IM_Payment_Link::status_label( $wim_status ) ); ?></span>
					<input type="text" class="wim-pl-share__url" readonly value="<?php echo esc_attr( $link['url'] ); ?>" onfocus="this.select()" aria-label="<?php esc_attr_e( 'Payment link', 'wp-invoice-manager' ); ?>">
					<div class="wim-pl-share__actions">
						<button type="button" class="wim-btn wim-btn-primary" data-wim-copy="<?php echo esc_attr( $link['url'] ); ?>"><span class="dashicons dashicons-admin-page"></span><span><?php esc_html_e( 'Copy link', 'wp-invoice-manager' ); ?></span></button>
						<a class="wim-btn wim-btn-secondary" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Open', 'wp-invoice-manager' ); ?></a>
					</div>
					<div class="wim-pl-qr" data-wim-qr="<?php echo esc_attr( $link['url'] ); ?>" data-wim-qr-name="<?php echo esc_attr( sanitize_title( $link['title'] ) ); ?>"></div>
					<button type="button" class="wim-btn wim-btn-secondary wim-pl-qr__download" data-wim-qr-download><span class="dashicons dashicons-download"></span><?php esc_html_e( 'Download QR (PNG)', 'wp-invoice-manager' ); ?></button>
				</div>

				<div class="wim-form-wrap wim-pl-received">
					<h3 class="wim-section-title"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Received', 'wp-invoice-manager' ); ?></h3>
					<?php if ( $wim_count ) : ?>
						<p class="wim-pl-received__total"><?php echo esc_html( WP_IM_Stripe::format( $wim_total, $link['currency'] ) ); ?> <small><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s payment', '%s payments', $wim_count, 'wp-invoice-manager' ), number_format_i18n( $wim_count ) ) ); ?></small></p>
						<ul class="wim-pl-received__list">
							<?php
							$wim_invoices = get_posts( array( 'post_type' => WP_IM_Post_Type::POST_TYPE, 'posts_per_page' => 8, 'fields' => 'ids', 'meta_key' => WP_IM_Payment_Link::INVOICE_META_LINK, 'meta_value' => $link['id'] ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
							foreach ( $wim_invoices as $wim_invoice_id ) :
								$wim_inv = WP_IM_Invoice::get( $wim_invoice_id );
							?>
								<li>
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-im-new-invoice', 'invoice_id' => $wim_invoice_id ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $wim_inv['number'] ); ?></a>
									<span><?php echo esc_html( $wim_inv['client_name'] ); ?></span>
									<strong><?php echo esc_html( WP_IM_Stripe::format( $wim_inv['totals']['paid'], $wim_inv['currency'] ) ); ?></strong>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="wim-field-hint"><?php esc_html_e( 'No payments yet. Each payment creates a paid invoice, listed here.', 'wp-invoice-manager' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="wim-form-wrap wim-pl-help">
					<h3 class="wim-section-title"><span class="dashicons dashicons-lightbulb"></span><?php esc_html_e( 'How it works', 'wp-invoice-manager' ); ?></h3>
					<ol>
						<li><?php esc_html_e( 'Create the link and copy it (or download its QR code).', 'wp-invoice-manager' ); ?></li>
						<li><?php esc_html_e( 'Send it on WhatsApp, email or anywhere.', 'wp-invoice-manager' ); ?></li>
						<li><?php esc_html_e( 'The customer pays by card on Stripe’s secure checkout.', 'wp-invoice-manager' ); ?></li>
						<li><?php esc_html_e( 'A paid invoice is created automatically and both of you get an email.', 'wp-invoice-manager' ); ?></li>
					</ol>
				</div>
			<?php endif; ?>
		</aside>
	</div>

<?php endif; ?>
</div>
