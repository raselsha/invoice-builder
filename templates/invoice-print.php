<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo esc_html( $invoice['number'] ); ?></title>
<style>
	* { box-sizing: border-box; margin: 0; padding: 0; }
	body {
		font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
		font-size: 13px;
		color: #1e293b;
		background: #f8f9fc;
		padding: 40px;
	}
	.invoice-shell {
		max-width: 800px;
		margin: 0 auto;
		background: #fff;
		border-radius: 12px;
		overflow: hidden;
		box-shadow: 0 4px 40px rgba(0,0,0,.12);
	}
	/* Header */
	.inv-header {
		background: #1a1a2e;
		color: #fff;
		padding: 40px 48px;
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
	}
	.inv-header .brand { font-size: 22px; font-weight: 800; letter-spacing: -.02em; }
	.inv-header .brand small { display: block; font-size: 12px; font-weight: 400; color: rgba(255,255,255,.6); margin-top: 4px; }
	.inv-meta { text-align: right; }
	.inv-meta .inv-number { font-size: 28px; font-weight: 900; color: #e94560; }
	.inv-meta .inv-label { font-size: 11px; color: rgba(255,255,255,.5); text-transform: uppercase; letter-spacing: .08em; }
	/* Status ribbon */
	.inv-status-bar {
		background: #0f3460;
		padding: 10px 48px;
		display: flex;
		gap: 32px;
		font-size: 12px;
		color: rgba(255,255,255,.7);
	}
	.inv-status-bar strong { color: #fff; display: block; font-size: 13px; }
	.badge {
		display: inline-block;
		padding: 2px 12px;
		border-radius: 20px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: .05em;
	}
	.badge-draft     { background: #f1f5f9; color: #475569; }
	.badge-sent      { background: #dbeafe; color: #1d4ed8; }
	.badge-paid      { background: #d1fae5; color: #065f46; }
	.badge-overdue   { background: #fee2e2; color: #991b1b; }
	.badge-cancelled { background: #f3f4f6; color: #9ca3af; }
	/* Body */
	.inv-body { padding: 40px 48px; }
	/* Parties */
	.parties {
		display: flex;
		gap: 40px;
		margin-bottom: 36px;
	}
	.party { flex: 1; }
	.party-label {
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: .1em;
		color: #94a3b8;
		margin-bottom: 8px;
	}
	.party-name { font-size: 16px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
	.party-detail { color: #64748b; line-height: 1.6; white-space: pre-line; }
	/* Items table */
	.items-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
	.items-table thead th {
		background: #f8f9fc;
		padding: 10px 12px;
		text-align: left;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: .07em;
		color: #64748b;
		border-top: 2px solid #e2e8f0;
		border-bottom: 2px solid #e2e8f0;
	}
	.items-table th:not(:first-child) { text-align: right; }
	.items-table tbody td {
		padding: 12px;
		border-bottom: 1px solid #f1f5f9;
		color: #334155;
	}
	.items-table tbody td:not(:first-child) { text-align: right; }
	/* Totals */
	.totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 36px; }
	.totals-box { min-width: 260px; }
	.totals-row {
		display: flex;
		justify-content: space-between;
		padding: 8px 0;
		border-bottom: 1px solid #f1f5f9;
		font-size: 13px;
		color: #64748b;
	}
	.totals-row.grand-total {
		border-bottom: none;
		border-top: 2px solid #1a1a2e;
		margin-top: 6px;
		padding-top: 12px;
		font-size: 18px;
		font-weight: 800;
		color: #1a1a2e;
	}
	/* Notes */
	.inv-notes {
		background: #f8f9fc;
		border-left: 4px solid #e94560;
		padding: 16px 20px;
		border-radius: 0 8px 8px 0;
		color: #475569;
		font-size: 12px;
		line-height: 1.7;
	}
	.inv-notes-label { font-weight: 700; color: #1e293b; margin-bottom: 6px; text-transform: uppercase; font-size: 11px; letter-spacing: .06em; }
	/* Footer */
	.inv-footer {
		background: #f8f9fc;
		padding: 20px 48px;
		text-align: center;
		font-size: 11px;
		color: #94a3b8;
		border-top: 1px solid #e2e8f0;
		margin-top: 40px;
	}
	/* Print */
	@media print {
		body { background: white; padding: 0; }
		.invoice-shell { box-shadow: none; border-radius: 0; }
		.no-print { display: none !important; }
	}
</style>
</head>
<body>

<div class="no-print" style="text-align:center;margin-bottom:20px">
	<button onclick="window.print()"
		style="background:#e94560;color:#fff;border:none;padding:10px 28px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer">
		🖨 Print / Save as PDF
	</button>
	<button onclick="window.close()"
		style="background:#f1f5f9;color:#475569;border:none;padding:10px 20px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;margin-left:10px">
		✕ Close
	</button>
</div>

<div class="invoice-shell">

	<div class="inv-header">
		<div class="brand">
			<?php echo esc_html( $invoice['biller_name'] ); ?>
			<small><?php echo esc_html( $invoice['biller_email'] ); ?></small>
		</div>
		<div class="inv-meta">
			<div class="inv-label">Invoice</div>
			<div class="inv-number"><?php echo esc_html( $invoice['number'] ); ?></div>
		</div>
	</div>

	<div class="inv-status-bar">
		<div>
			<span style="font-size:11px;text-transform:uppercase;letter-spacing:.06em">Status</span>
			<strong><span class="badge badge-<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status ); ?></span></strong>
		</div>
		<div>
			<span>Invoice Date</span>
			<strong><?php echo esc_html( $invoice['invoice_date'] ); ?></strong>
		</div>
		<div>
			<span>Due Date</span>
			<strong><?php echo esc_html( $invoice['due_date'] ? $invoice['due_date'] : '—' ); ?></strong>
		</div>
		<div>
			<span>Currency</span>
			<strong><?php echo esc_html( $invoice['currency'] ); ?></strong>
		</div>
	</div>

	<div class="inv-body">

		<div class="parties">
			<div class="party">
				<div class="party-label">From</div>
				<div class="party-name"><?php echo esc_html( $invoice['biller_name'] ); ?></div>
				<div class="party-detail"><?php echo esc_html( $invoice['biller_address'] ); ?></div>
			</div>
			<div class="party" style="text-align:right">
				<div class="party-label">Bill To</div>
				<div class="party-name"><?php echo esc_html( $invoice['client_name'] ); ?></div>
				<div class="party-detail">
					<?php echo esc_html( $invoice['client_email'] ); ?><br>
					<?php if ( $invoice['client_phone'] ) echo esc_html( $invoice['client_phone'] ) . '<br>'; ?>
					<?php echo esc_html( $invoice['client_address'] ); ?>
				</div>
			</div>
		</div>

		<table class="items-table">
			<thead>
				<tr>
					<th>Description</th>
					<th>Qty</th>
					<th>Unit Price</th>
					<th>Tax %</th>
					<th>Amount</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $invoice['items'] as $item ) :
					$line    = floatval( $item['quantity'] ) * floatval( $item['unit_price'] );
					$tax_amt = $line * ( floatval( $item['tax_rate'] ) / 100 );
					$row_total = $line + $tax_amt;
				?>
				<tr>
					<td><?php echo esc_html( $item['description'] ); ?></td>
					<td><?php echo esc_html( floatval( $item['quantity'] ) ); ?></td>
					<td><?php echo esc_html( $symbol . number_format( floatval( $item['unit_price'] ), 2 ) ); ?></td>
					<td><?php echo esc_html( floatval( $item['tax_rate'] ) . '%' ); ?></td>
					<td><?php echo esc_html( $symbol . number_format( $row_total, 2 ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="totals-wrap">
			<div class="totals-box">
				<div class="totals-row">
					<span>Subtotal</span>
					<span><?php echo esc_html( $symbol . number_format( $totals['subtotal'], 2 ) ); ?></span>
				</div>
				<div class="totals-row">
					<span>Tax</span>
					<span><?php echo esc_html( $symbol . number_format( $totals['tax_total'], 2 ) ); ?></span>
				</div>
				<?php if ( $totals['discount'] > 0 ) : ?>
				<div class="totals-row">
					<span>Discount</span>
					<span>- <?php echo esc_html( $symbol . number_format( $totals['discount'], 2 ) ); ?></span>
				</div>
				<?php endif; ?>
				<div class="totals-row grand-total">
					<span>Total Due</span>
					<span><?php echo esc_html( $symbol . number_format( $totals['total'], 2 ) ); ?></span>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $invoice['notes'] ) ) : ?>
		<div class="inv-notes">
			<div class="inv-notes-label">Notes &amp; Terms</div>
			<?php echo nl2br( esc_html( $invoice['notes'] ) ); ?>
		</div>
		<?php endif; ?>

	</div><!-- .inv-body -->

	<div class="inv-footer">
		Generated by WP Invoice Manager &bull; <?php echo esc_html( get_bloginfo( 'name' ) ); ?> &bull; <?php echo esc_html( date( 'F j, Y' ) ); ?>
	</div>

</div><!-- .invoice-shell -->

</body>
</html>
