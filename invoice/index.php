<?php include('header.php'); ?>
			
	
	<div class="row invoice-container">
		
		<div class="col-md-6  owner-info">
			<p>
				From<br>
				<strong>Umme Kulsum</strong><br>
				Dhaka, Bangladesh<br>
				info@lieusoft.com<br>
			</p>
		</div>
		<div class="col-md-6  client-info">
			<p>
				To</br>
				<strong>Baguette Bar</strong><br>
				544 Bearwood Road Smethwick, B66 4BT<br>
				Birmingham, UK</p>
		</div>
	</div>
	<div class="invoice_info">
		<p>
			Invoice Id: <strong><?= rand(0,9).date('dHs');?></strong><br>
			Invoice Date: <strong>15 Apr, 2026</strong><br>
			Due Date: <strong>20 Apr, 2026</strong><br>
		</p>
	</div>
	<!-- pricing info -->
	<div class="row pricing-info">
		<div class="table-container">
			<table class="table pricing-table">
				<th width="70%" class="text-left">Item</th>
				<th width="10%" class="text-right">Price (£)</th>
				<th width="10%" class="text-right">Qty</th>
				<th width="10%" class="text-right">Total (£)</th>
				<tr>
					<td class="text-left">Domain Registration (baguettebar.uk) + Unlimited Hosting (1 Year)</td>
					<td class="text-right">60.00</td>
					<td class="text-right">1</td>
					<td class="text-right">60.00</td>
				</tr>				
				<tr>
					<td class="text-left">Web Design + Menu Design + Logo Design</td>
					<td class="text-right">80.00</td>
					<td class="text-right">1</td>
					<td class="text-right">80.00</td>
				</tr>
				<tr>
					<td class="text-left">Website Development</td>
					<td class="text-right">180.00</td>
					<td class="text-right">1</td>
					<td class="text-right">180.00</td>
				</tr>				
				<tr>
					<td class="text-left">Maintenance + SEO + Digital marketing (facebook, tick-tock and instagram) (Discounted 1st Month)</td>
					<td class="text-right">270.00</td>
					<td class="text-right">1</td>
					<td class="text-right">270.00</td>
				</tr>				
			</table>
			<table class="table summery-table">
				<tr>
					<td width="60%" class="text-right"></td>
					<td width="20%" class="text-right">Subtotal</td>
					<td width="15%" class="text-right">590.00</td>
				</tr>	
				<tr class="">
					<td width="60%" class="text-right"></td>
					<td width="20%" class="text-right"><strong>Discount</strong></td>
					<td width="15%" class="text-right"><strong>170.00</strong></td>
				</tr>
				<tr class="">
					<td width="60%" class="text-right"></td>
					<td width="20%" class="text-right"><strong>Total</strong></td>
					<td width="15%" class="text-right"><strong>420.00</strong></td>
				</tr>			
			</table>
		</div>
	</div>
	<div class="row terms-condition">
		<div class="col-md-12">
			<div class="payment">
				<p><strong>Payment Details</strong></p>
				<p>Preferred Method: [Wise / Payoneer / Bank Transfer]</p>
				<p>
					<strong>Bank Info</strong><br>
					Account Name: Umme Kulsum<br>
					Account Number: 0200024551627<br>
					Bank Name: Agrani Bank PLC.<br>
					Branch: Mohammadpur (5081), Dhaka. <br>
					SWIFT/Routing: 010263282
				</p>
			</div>	
			<div class="notes">
				Notes:  
				For the maintenance and digital marketing cost <b>£270/Month</b> will be applicable from next month.
			</div>						
		</div>
	</div>
<?php include('footer.php'); ?>