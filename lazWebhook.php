<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/globalVariables.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/laz/lazApi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/woo/wooApi.php';

$headersArray         = apache_request_headers();
$webhookAuthorization = $headersArray['Authorization'];

$jsonStringRawBody = file_get_contents("php://input");
$postDataArray     = json_decode($jsonStringRawBody, true);

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/lazWebhook.txt', "\n-------------------------------------------\n", FILE_APPEND);
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/lazWebhook.txt', "⏬ ⏬ ⏬ ⏬ " . date("H:i:s d.m.Y") . " ⏬ ⏬ ⏬ ⏬\n\n", FILE_APPEND);

foreach ($headersArray as $header => $value) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/lazWebhook.txt', "$header: $value\n", FILE_APPEND);
}

if (lazWebhookAuth($webhookAuthorization, $jsonStringRawBody)) {

    echo "ok";
    
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/lazWebhook.txt', "\nRAW POST BODY:\n", FILE_APPEND);
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/lazWebhook.txt', "$jsonStringRawBody\n\n", FILE_APPEND);

    $messageType = $postDataArray['message_type'];
    // Laz product updated
    if ($messageType == 4) {
        $itemId  = $postDataArray['data']['item_id'];
        $skuList = $postDataArray['data']['sku_list'];
        markTime();
        writeLog("➤➤ LAZ WEBHOOK | PRODUCT.UPDATED");

        foreach ($skuList as $updatedSku) {
            $sku = $updatedSku['seller_sku'];
            writeLog("➤ " . $sku);
            try {
                syncLazToWoo($sku);
            } catch (Exception $e) {
                writeLog("❌ FAIL: Sync laz to woo | " . $e->getMessage()
                    . "\n            └─── SKU: " . $sku);
            }
        }
    }
}
