<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/syncBetweenPlatforms.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/laz/lazApi.php';

markTime();
writeLog("➤➤ UPDATE LAZ PRODUCT QUANTITY");

if (isset($_POST['sku']) && isset($_POST['newQuantities']) && isset($_POST['warehouses'])) {

    $sku = $_POST['sku'];
	$result = '';
	$isUpdated = false;
	
    for ($i = 0; $i < count($_POST['warehouses']); $i++) {
        $warehouse   = $_POST['warehouses'][$i];
        $newQuantity = $_POST['newQuantities'][$i];

        writeLog("➤ SKU: " . $sku . " | Warehouse: " . $warehouse . " | New quantity: " . $newQuantity);

        try {
            $warehouseCode = LAZ_WAREHOUSE_CODE[$warehouse];
            updateLazProductQuantityWithWarehouseCode($sku, $warehouseCode, $newQuantity);

            writeLog("✔️ UPDATE LAZ QUANTITY SUCCESS");
            $result .= $warehouse . ": ✔️ SUCCESS<br>";
            $isUpdated = true;

        } catch (Exception $e) {
            writeLog($e->getMessage());
            $result .= $warehouse . ": " . $e->getMessage() . "<br>";
        }
    }

    if ($isUpdated) {
        try {
            syncLazToWoo($sku);
        } catch (Exception $e) {
            writeLog($e->getMessage());
            $result .= "Sync change to woo: " . $e->getMessage() . "<br>";
        }
    }

    echo $result;
    exit;
} else {
    writeLog("❌ FAIL: Lack of required parameters");
    echo ("❌ FAIL: Lack of required parameters");
    exit;
}
