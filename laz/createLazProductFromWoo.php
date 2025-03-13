<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/utilities.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/tiktok/tiktokApi.php";

markTime();
writeLog("➤➤ CREATE/SYNC LAZ PRODUCT FROM WOO PRODUCT | SKU: " . $_POST["sku"]);

if (isset($_POST["sku"])) {
    try {
        $sku = $_POST["sku"];

        copyProductFromWoo($sku);

        echo "✔️ SUCCESS";
        exit();
    } catch (Exception $e) {
        echo $e->getMessage();
        exit();
    }
} else {
    writeLog("❌ FAIL: Lack of required parameters");
    echo "❌ FAIL: Lack of required parameters";
    exit();
}
