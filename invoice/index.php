<?php include('header.php'); ?>
			
	<div class="">
		<div class="title"><h4>Invoice #
			<?= rand(0,9).date('dHs');?> </h4>
		</div>
	</div>
	<div class="row invoice-container">
		<div class="col-md-6 client-info">
			<p><strong>G.M.NIZAM UDDIN</strong><br>
			<small>Founder Principal</small><br>
			Cardiff International School<br> 
			Dhaka, Bangladesh</p>
		</div>
		<div class="col-md-6 owner-info">
			<p>
				<strong>Md. Shahadat Hossain</strong><br>
				Lieusoft<br>
				Dhaka, Bangladesh
			</p>
		</div>
	</div>
	<div class="invoice_info">
		<p>
			Invoice Date: <strong>04-Feb-2017</strong><br>
			Due Date: <strong>05-Feb-2017</strong><br>
			Payment Method:<strong> Cash/Bkash/Bank transfer</strong>
		</p>
	</div>
	<!-- pricing info -->
	<div class="row pricing-info">
		<div class="table-container">
			<table class="table pricing-table">
				<th width="50%" class="text-left">Item</th>
				<th width="15%" class="text-right">Price (BDT)</th>
				<th width="15%" class="text-right">Qty</th>
				<th width="15%" class="text-right">Total (BDT)</th>
				<tr>
					<td class="text-left">Web site maintenance</td>
					<td class="text-right">15000.00</td>
					<td class="text-right">0.00</td>
					<td class="text-right">15,000.00</td>
				</tr>				
			</table>
			<table class="table summery-table">
				<tr>
					<td width="60%" class="text-right"></td>
					<td width="20%" class="text-right">Subtotal</td>
					<td width="15%" class="text-right">15,000.00</td>
				</tr>	
				<tr class="">
					<td width="60%" class="text-right"></td>
					<td width="20%" class="text-right"><strong>Discount</strong></td>
					<td width="15%" class="text-right"><strong>15,000.00</strong></td>
				</tr>
				<tr class="">
					<td width="60%" class="text-right"></td>
					<td width="20%" class="text-right"><strong>Total</strong></td>
					<td width="15%" class="text-right"><strong>15,000</strong></td>
				</tr>			
			</table>
		</div>
	</div>
	<div class="row terms-condition">
		<div class="col-md-12">
			<div class="terms">
				<p><strong>Terms & Conditions</strong></p>
				<p>
					<ol>
						
						<li>Layout and color shall be changed on demand. Charge applicable.</li>
						<li>Keep safe your cpanel. Lieusoft has no responsibility for data backup.</li>
						
					</ol>
				</p>
			</div>							
		</div>
	</div>
<?php include('footer.php'); ?>