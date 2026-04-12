<?php include('header.php'); ?>
			<?php 
				$client_name="";
				$client_position="";
				$client_address="";
				$item_name="";
				$price="";
				$discount="";
				$paid="";
				$due_time="";
			if (isset($_POST['submit'])) {
				$client_name=$_POST['client_name'];
				$client_position=$_POST['client_position'];
				$client_address=$_POST['client_address'];
				$item_name=$_POST['item_name'];
				$price=$_POST['price'];
				$discount=$_POST['discount'];
				$paid=$_POST['paid'];
				$due_time=$_POST['due_time'];
			} ?>
			<div class="content">
				<div class="title">
					<strong>Invoice #
						<?= rand(0,9).date('dHs');?>
					</strong>
				</div>
				<table width="100%">
					<tr>
						<td width="50%" valign="top">
							<h4 style="margin-top:0px"><?= $client_name ?></h4>
							<small><?= $client_position ?></small>
							<p><?= $client_address ?></p>
						</td>
						<td width="50%" align="right">
							<p>
							<strong>Md. Shahadat Hossain</strong><br>
							Lieusoft<br>
							Dhaka, Bangladesh
						</p>
						<div class="invoice_info">
							<p>
							Invoice Date: <strong><?= date('d-M-Y'); ?></strong><br>
							Due Date: <strong>
							<?php
							$date = new DateTime();
							if ($due_time!='') {
								$date->modify('+'.$due_time.' day');
								echo $date->format('d-M-Y');
							}
							else {
								$date->modify('+1 day');
								echo $date->format('d-M-Y');
							}
							?></strong><br>
							Payment Method:<strong> Cash/Bkash/Bank transfer</strong>
						</p>
						</div>
						</td>
					</tr>
				</table>

				<div class="data">
					<table width="100%" >
						<th width="50%">Item</th>
						<th width="15%">Price (BDT)</th>
						<th width="15%">Discount (BDT)</th>
						<th width="15%">Total (BDT)</th>
						<tr>
							<td class="text-left"><?= $item_name; ?></td>
							<td class="text-center"><?php
							if ($price!='') {
								echo number_format($price,2);
							}
							else{
								echo "0.0";
							}
							?></td>
							<td class="text-center">
							<?php $discounted=$price*$discount/100;
								$total=$price-$discounted;  
							?>
							<?= $discounted; ?>
							<small>(<?= $discount ?>%)</small>
							</td>
							<td class="text-center">
							<?= number_format($total,2); ?></td>
						</tr>				
					</table>
				</div>
				<div class="calculation">
					<table width="100%">
						<tr>
							<td width="60%"></td>
							<td width="20%" class="text-right">Subtotal</td>
							<td width="15%" class="text-center"><?= number_format($total,2);?></td>
						</tr>	
						<tr class="active">
							<td width="60%"></td>
							<td width="20%" class="text-right"><strong>Total</strong></td>
							<td width="15%" class="text-center"><strong><?= number_format($total,2); ?></strong></td>
						</tr>
						<tr>
							<td width="60%"></td>
							<td width="20%" class="text-right">Paid (Adv)</td>
							<td width="15%" class="text-center"><?php
							if ($paid!='') {
							 echo number_format($paid,2);
							} 
							else{
								echo "0.0";
							}
							?></td>
						</tr>
						<tr class="active">
							<td width="60%"></td>
							<td width="20%" class="text-right"><strong>Balance (Due)</strong></td>
							<td width="15%" class="text-center"><strong><?= number_format($total-$paid,2);?></strong></td>
						</tr>			
					</table>
				</div>
				<div class="row">
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

			</div>
			<script>
				window.load(printarea());
				function printarea() {
			    	window.print();
				}
			</script>
<?php include('footer.php'); ?>