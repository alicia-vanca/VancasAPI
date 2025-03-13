<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/woo/wooApi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/laz/lazApi.php';

/* Only allow POST method */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    /*
    Up to you which header to send, some prefer 404 even if
    the files does exist for security
     */
    header('HTTP/1.0 403 Forbidden', true, 403);

    /* choose the appropriate page to redirect users */
    die(header('location: /error.php'));
}

//print_r($_POST);
//file_put_contents('./log_'.date("j.n.Y").'.log', print_r($_POST, true), FILE_APPEND);

$headersArray     = apache_request_headers();
$webhookTopic     = $headersArray['X-Wc-Webhook-Topic'];
$webhookSignature = $headersArray['X-Wc-Webhook-Signature'];

$jsonStringRawBody = file_get_contents("php://input");
// file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/rawPost.txt', $jsonStringRawBody);

$postDataArray = json_decode($jsonStringRawBody, true);

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/wooWebhook.txt', "---------------------------------------------\n", FILE_APPEND);
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/wooWebhook.txt', "HEADERS:\n", FILE_APPEND);
foreach ($headersArray as $header => $value) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/wooWebhook.txt', "$header: $value\n", FILE_APPEND);
}
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/wooWebhook.txt', "\nPOST DATA:\n", FILE_APPEND);
foreach ($postDataArray as $key => $value) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/wooWebhook.txt', "$key: $value\n", FILE_APPEND);
}

if (wooWebhookAuth($webhookSignature, $jsonStringRawBody) && "product.updated" == $webhookTopic) {

    $productType = $postDataArray['type'];
    if ("variation" == $productType || "simple" == $productType) {
        $sku = $postDataArray['sku'];
        if (trim($sku) != '') {

            $newQuantity = $postDataArray['stock_quantity'];

            markTime();
            writeLog("➤➤ WOO WEBHOOK | PRODUCT.UPDATED | SKU: " . $sku . " | New quantity: " . $newQuantity);

            try {
                updateLazProductQuantity($sku, $newQuantity);

                writeLog("➤ Success");
                exit;
            } catch (Exception $e) {
                writeLog("➤ " . $e->getMessage());
                exit;
            }
        }
    }

}
