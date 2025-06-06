<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/woo/wooApi.php';

markTime();
writeLog("➤➤ UPDATE WOO PRODUCT QUANTITY | SKU: " . $_POST['sku'] . " | New quantity: " . $_POST['newQuantity']);

if (isset($_POST['sku']) && isset($_POST['newQuantity'])) {
    try {
        $sku         = $_POST['sku'];
        $newQuantity = $_POST['newQuantity'];

        updateWooProductQuantity($sku, $newQuantity);

        echo ("✔️ SUCCESS");
        exit;
    } catch (Exception $e) {
        echo $e->getMessage();
        exit;
    }
} else {
    writeLog("❌ FAIL: Lack of required parameters");
    echo ("❌ FAIL: Lack of required parameters");
    exit;
}
