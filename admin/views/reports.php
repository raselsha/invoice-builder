<?php
/**
 * Admin view: Reports / Dashboard.
 *
 * Variables: $currency_stats, $monthly_revenue, $top_clients, $aging
 *
 * @package WP_Invoice_Manager
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$aging_labels = array(
	'0-30'  => __( '0–30 days', 'wp-invoice-manager' ),
	'31-60' => __( '31–60 days', 'wp-invoice-manager' ),
	'61-90' => __( '61–90 days', 'wp-invoice-manager' ),
	'90+'   => __( '90+ days', 'wp-invoice-manager' ),
);
?>
<div class="wim-wrap">

	<div class="wim-header">
		<h1>
			<span class="dashicons dashicons-chart-bar"></span>
			<?php esc_html_e( 'Reports', 'wp-invoice-manager' ); ?>
		</h1>
	</div>

	<!-- Per-currency summary -->
	<?php if ( empty( $currency_stats ) ) : ?>
		<div class="wim-table-empty">
			<span class="dashicons dashicons-chart-bar"></span>
			<p><?php esc_html_e( 'No invoices yet — reports will appear here once you create some.', 'wp-invoice-manager' ); ?></p>
		</div>
	<?php else : ?>

	<?php foreach ( $currency_stats as $currency => $stat ) :
		$symbol = WP_IM_Invoice::currency_symbol( $currency );
	?>
	<div class="wim-section" style="margin-bottom:20px">
		<h3 class="wim-section-title">
			<span class="dashicons dashicons-money-alt"></span>
			<?php echo esc_html( $currency ); ?>
			<span style="font-weight:400;color:var(--wim-muted);font-size:12px">(<?php echo esc_html( sprintf( _n( '%d invoice', '%d invoices', $stat['count'], 'wp-invoice-manager' ), $stat['count'] ) ); ?>)</span>
		</h3>
		<div class="wim-stats">
			<div class="wim-stat-card all">
				<div class="wim-stat-value"><?php echo esc_html( $symbol . number_format( $stat['invoiced'], 2 ) ); ?></div>
				<div class="wim-stat-label"><?php esc_html_e( 'Total Invoiced', 'wp-invoice-manager' ); ?></div>
			</div>
			<div class="wim-stat-card paid">
				<div class="wim-stat-value"><?php echo esc_html( $symbol . number_format( $stat['paid'], 2 ) ); ?></div>
				<div class="wim-stat-label"><?php esc_html_e( 'Received', 'wp-invoice-manager' ); ?></div>
			</div>
			<div class="wim-stat-card overdue">
				<div class="wim-stat-value"><?php echo esc_html( $symbol . number_format( $stat['outstanding'], 2 ) ); ?></div>
				<div class="wim-stat-label"><?php esc_html_e( 'Outstanding', 'wp-invoice-manager' ); ?></div>
			</div>
		</div>
	</div>
	<?php endforeach; ?>

	<div class="wim-form-grid">
		<!-- Monthly revenue -->
		<div class="wim-section">
			<h3 class="wim-section-title">
				<span class="dashicons dashicons-chart-line"></span>
				<?php esc_html_e( 'Revenue Received — Last 6 Months', 'wp-invoice-manager' ); ?>
			</h3>
			<?php if ( empty( $monthly_revenue['by_currency'] ) ) : ?>
				<p class="wim-field-hint"><?php esc_html_e( 'No payments recorded yet.', 'wp-invoice-manager' ); ?></p>
			<?php else : ?>
				<?php foreach ( $monthly_revenue['by_currency'] as $currency => $months ) :
					$symbol = WP_IM_Invoice::currency_symbol( $currency );
					$max    = max( array_merge( $months, array( 0.01 ) ) );
				?>
				<div class="wim-chart" style="margin-bottom:18px">
					<div class="wim-chart-currency"><?php echo esc_html( $currency ); ?></div>
					<div class="wim-chart-bars">
						<?php foreach ( $months as $month => $amount ) :
							$pct = max( 2, round( ( $amount / $max ) * 100 ) );
						?>
						<div class="wim-chart-bar-col">
							<div class="wim-chart-bar-track">
								<div class="wim-chart-bar" style="height:<?php echo esc_attr( $pct ); ?>%" title="<?php echo esc_attr( $symbol . number_format( $amount, 2 ) ); ?>"></div>
							</div>
							<div class="wim-chart-bar-value"><?php echo esc_html( number_format( $amount, 0 ) ); ?></div>
							<div class="wim-chart-bar-label"><?php echo esc_html( date_i18n( 'M', strtotime( $month . '-01' ) ) ); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<!-- Top clients -->
		<div class="wim-section">
			<h3 class="wim-section-title">
				<span class="dashicons dashicons-groups"></span>
				<?php esc_html_e( 'Top Clients', 'wp-invoice-manager' ); ?>
			</h3>
			<?php if ( empty( $top_clients ) ) : ?>
				<p class="wim-field-hint"><?php esc_html_e( 'No client activity yet.', 'wp-invoice-manager' ); ?></p>
			<?php else : ?>
				<table class="wim-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Client', 'wp-invoice-manager' ); ?></th>
							<th><?php esc_html_e( 'Total Invoiced', 'wp-invoice-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top_clients as $client ) : ?>
						<tr>
							<td><?php echo esc_html( $client['name'] ); ?></td>
							<td class="col-amount"><?php echo esc_html( WP_IM_Invoice::currency_symbol( $client['currency'] ) . number_format( $client['total'], 2 ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<!-- Overdue aging -->
	<div class="wim-section" style="margin-top:20px">
		<h3 class="wim-section-title">
			<span class="dashicons dashicons-warning"></span>
			<?php esc_html_e( 'Overdue Aging', 'wp-invoice-manager' ); ?>
		</h3>
		<div class="wim-form-grid-3" style="grid-template-columns:repeat(4,1fr)">
			<?php foreach ( $aging as $bucket => $items ) :
				$bucket_total = array();
				foreach ( $items as $item ) {
					$bucket_total[ $item['currency'] ] = ( $bucket_total[ $item['currency'] ] ?? 0 ) + $item['balance'];
				}
			?>
			<div class="wim-stat-card <?php echo '90+' === $bucket ? 'overdue' : 'draft'; ?>">
				<div class="wim-stat-value"><?php echo esc_html( count( $items ) ); ?></div>
				<div class="wim-stat-label"><?php echo esc_html( $aging_labels[ $bucket ] ); ?></div>
				<?php foreach ( $bucket_total as $cur => $amt ) : ?>
					<div style="font-size:11px;color:var(--wim-muted);margin-top:4px"><?php echo esc_html( WP_IM_Invoice::currency_symbol( $cur ) . number_format( $amt, 2 ) ); ?></div>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</div>

		<?php
		$all_overdue = array_merge( ...array_values( $aging ) );
		if ( ! empty( $all_overdue ) ) :
		?>
		<table class="wim-table" style="margin-top:18px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Invoice #', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Client', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Days Overdue', 'wp-invoice-manager' ); ?></th>
					<th><?php esc_html_e( 'Balance', 'wp-invoice-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_overdue as $item ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $item['number'] ); ?></strong></td>
					<td><?php echo esc_html( $item['client'] ); ?></td>
					<td><?php echo esc_html( $item['days'] ); ?></td>
					<td class="col-amount"><?php echo esc_html( WP_IM_Invoice::currency_symbol( $item['currency'] ) . number_format( $item['balance'], 2 ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<?php endif; ?>

</div>
