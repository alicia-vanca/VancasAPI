<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/laz/lazApi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/tiktok/tiktokApi.php';

// If tiktokRefreshToken expires within 7days, then need re-Authorization
if (time() > ($lazRefreshTokenExpiresIn - 604800)) {
    die(header('location: callback_url/laz/authLazada.php'));
}

// If tiktokRefreshToken expires within 30days, then need re-Authorization
if (time() > ($tiktokRefreshTokenExpireIn - 2592000)) {
    die(header('location: callback_url/tiktok/tiktokAuth.php'));
}
markTime();
writeLog("✔️ lazAccessToken  expires in " . convert_seconds($lazAccessTokenExpiresIn - time()));
writeLog("✔️ lazRefreshToken expires in " . convert_seconds($lazRefreshTokenExpiresIn - time()));
writeLog("-----------------------");
writeLog("✔️ tiktokAccessToken  expires in " . convert_seconds($tiktokAccessTokenExpireIn - time()));
writeLog("✔️ tiktokRefreshToken expires in " . convert_seconds($tiktokRefreshTokenExpireIn - time()));

function convert_seconds($seconds) 
{
    $dt1 = new DateTime("@0");
    $dt2 = new DateTime("@$seconds");
    return $dt1->diff($dt2)->format('%a days, %h hours, %i minutes and %s seconds');
}
?>

<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org"
	th:replace="~{fragments/layout :: layout (~{::body},'index')}">

<head>
    <title>ABC | XYZ</title>
    <meta charset="utf-8" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</head>	
<body>
	<div>
		<label for="sku">SKU:</label>
		<input type="text" id="sku" placeholder="SKU">
		<button id="refresh"><i class="fa fa-refresh"></i></button>
 		<br>
 		<div>
 			<h2>Woocommerce</h2>
 			<p id="wooTitle"></p>
 			<p id="wooVariation"></p>
			<label for="wooQuantity">Quantity:</label>
			<input id="wooQuantity" type="number" min="0" max="999">
			<button id="updateWooQuantity"><i class="fa fa-check"></i></button>
 			<p class="result" id="wooSuccess"></p>
 			<p class="result" id="wooWarning"></p>
 			<p class="result" id="wooError"></p>
		</div>
 		<div>
 			<h2>Lazada</h2>
 			<p id="lazTitle"></p>
 			<p id="lazVariation"></p>
 			<p id="lazTotalQuantity" hidden></p>
 			<table>
				<tbody>
  					<tr>
    					<td><label for="lazQuantity-HN">Kho HN:</label></td>
    					<td>
    						<input id="lazQuantity-HN" type="number" min="0" max="999">
					    	<button id="updateLazHnQuantity"><i class="fa fa-check"></i></button>
					    </td>
    					<td rowspan="2"  style="padding-left: 20px;">
					    	<button id="updateLazBothQuantity"><i class="fa fa-check"></i></button>
					    </td>
  					</tr>
  					<tr>
					    <td><label for="lazQuantity-HCM">Kho HCM:</label></td>
					    <td>
					    	<input id="lazQuantity-HCM" type="number" min="0" max="999">
					    	<button id="updateLazHcmQuantity"><i class="fa fa-check"></i></button>
					    </td>
  					</tr>
				</tbody>
			</table>
 			<p class="result" id="lazSuccess"></p>
 			<p class="result" id="lazWarning"></p>
 			<p class="result" id="lazError"></p>
		</div>
 		<div>
 			<h2>Tiktok</h2>
 			<p id="tiktokTitle"></p>
 			<p id="tiktokVariation"></p>
			<label for="tiktokQuantity">Quantity:</label>
			<input id="tiktokQuantity" type="number" min="0" max="999">
			<button id="updateTiktokQuantity"><i class="fa fa-check"></i></button>
 			<p class="result" id="tiktokSuccess"></p>
 			<p class="result" id="tiktokWarning"></p>
 			<p class="result" id="tiktokError"></p>
		</div>
 		<div>
 			<h2>Tiki</h2>
 			<p id="tikiTitle">tikiTitle</p>
 			<p id="tikiVariation">tikiVariation</p>
			<input id="tikiQuantity" type="number" min="0" max="999">
		</div>		
	</div>
	
	<div>
		<h2>Create/Sync Laz product from Woo</h2>
		<label for="wooSku">SKU:</label>
		<input type="text" id="wooSku" placeholder="SKU">
		<button id="createLazProductFromWoo"><i class="fa fa-check"></i></button>
		<p class="result" id="createLazResult"></p>		
	</div>
	
	<div>
		<h2>Create/Sync Tiktok product from Laz</h2>
		<label for="lazSku">SKU:</label>
		<input type="text" id="lazSku" placeholder="SKU">
		<button id="createTikTokProductFromLaz"><i class="fa fa-check"></i></button>
		<p class="result" id="createTiktokResult"></p>		
	</div>

	<script>
		function getInfo() {
			if ($('#sku').val()) {
				$('.result').text('');
				$("input").prop("disabled", true);
				var data = {};
				data['sku'] = $('#sku').val().trim();
				$.ajax({
					url : '/api_path/getProductInfo.php',
					data : data,
					type : 'post',
					success : function(result) {
						$("input").prop("disabled", false);
						console.log(result);
						$('#wooTitle').text($("<div/>").html(result.woo.name).text());
						$('#wooVariation').text(result.woo.attributes);
						$('#wooQuantity').val(result.woo.quantity);

						$('#lazTitle').text(result.laz.name);
						$('#lazVariation').text(result.laz.attributes);
						$('#lazTotalQuantity').text(result.laz.totalQuantity);
						$('#lazQuantity-HN').val(result.laz.HN);
						$('#lazQuantity-HCM').val(result.laz.HCM);

						$('#tiktokTitle').text($("<div/>").html(result.tiktok.name).text());
						$('#tiktokVariation').text(result.tiktok.attributes);
						$('#tiktokQuantity').val(result.tiktok.quantity);
					},
					dataType:"json"
				});
			}
		};
		$('#sku').change(getInfo);
		$('#refresh').click(getInfo);

		function updateWooQuantity() {
			if ($('#sku').val() && $('#wooQuantity').val()) {
				$('.result').text('');
				$("input").prop("disabled", true);
				var data = {};
				data['sku'] = $('#sku').val().trim();
				data['newQuantity'] = $('#wooQuantity').val();
				$.ajax({
					url : '/api_path/woo/updateWooQuantity.php',
					data : data,
					type : 'post',
					success : function(result) {
						$("input").prop("disabled", false);
						console.log(result);		
						$('#wooSuccess').text(result);
					}
				});
			}
		};
		$('#updateWooQuantity').click(updateWooQuantity);


		function updateLazQuantity(event) {
			if ($('#sku').val()) {
				var data = {};
                data['warehouses'] = {};
				data['newQuantities'] = {};

				data['sku'] = $('#sku').val().trim();

				var i = 0;
				if ('HN' == event.data.warehouse || 'BOTH' == event.data.warehouse) {
					data['warehouses'][i] = 'HN';
					data['newQuantities'][i++] = $('#lazQuantity-HN').val();
				};
				if ('HCM' == event.data.warehouse || 'BOTH' == event.data.warehouse) {
					data['warehouses'][i] = 'HCM';
					data['newQuantities'][i++] = $('#lazQuantity-HCM').val();
				}

				$('.result').text('');
				$("input").prop("disabled", true);

				$.ajax({
					url : '/api_path/laz/updateLazQuantity.php',
					data : data,
					type : 'post',
					success : function(result) {
						getInfo();
						setTimeout(function(){
    						$("input").prop("disabled", false);
							console.log(result);
							$('#lazSuccess').html(result); 
						}, 600);
					}
				});
			};
		};
		$('#updateLazHnQuantity').click({warehouse:'HN'}, updateLazQuantity);
		$('#updateLazHcmQuantity').click({warehouse:'HCM'}, updateLazQuantity);
		$('#updateLazBothQuantity').click({warehouse:'BOTH'}, updateLazQuantity);

		function updateTiktokQuantity() {
			if ($('#sku').val() && $('#tiktokQuantity').val()) {
				$('.result').text('');
				$("input").prop("disabled", true);
				var data = {};
				data['sku'] = $('#sku').val().trim();
				data['newQuantity'] = $('#tiktokQuantity').val();
				$.ajax({
					url : '/api_path/tiktok/updateTiktokQuantity.php',
					data : data,
					type : 'post',
					success : function(result) {
						$("input").prop("disabled", false);
						console.log(result);		
						$('#tiktokSuccess').text(result);
					}
				});
			}
		};
		$('#updateTiktokQuantity').click(updateTiktokQuantity);

		function createLazProductFromWoo() {
			if ($('#wooSku').val()) {
				$('.result').text('');
				$("input").prop("disabled", true);
				var data = {};
				data['sku'] = $('#wooSku').val().trim();
				$.ajax({
					url : '/api_path/laz/createLazProductFromWoo.php',
					data : data,
					type : 'post',
					success : function(result) {
						$("input").prop("disabled", false);
						console.log(result);		
						$('#createLazResult').text(result);
					}
				});
			}
		};
		$('#createLazProductFromWoo').click(createLazProductFromWoo);

		function createTikTokProductFromLaz() {
			if ($('#lazSku').val()) {
				$('.result').text('');
				$("input").prop("disabled", true);
				var data = {};
				data['sku'] = $('#lazSku').val().trim();
				$.ajax({
					url : '/api_path/tiktok/createTikTokProductFromLaz.php',
					data : data,
					type : 'post',
					success : function(result) {
						$("input").prop("disabled", false);
						console.log(result);		
						$('#createTiktokResult').text(result);
					}
				});
			}
		};
		$('#createTikTokProductFromLaz').click(createTikTokProductFromLaz);

	</script>

	<style>  

		input[type=number]::-webkit-inner-spin-button {
			opacity: 1;
		}
		input[type=number] {
			text-align: center;
		}

	</style>
</body>
</html>
