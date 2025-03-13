<?php
$time_start = microtime(true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/tiktok/tiktokConstants.php';

$headersArray         = apache_request_headers();
$webhookAuthorization = $headersArray['Authorization'];

$jsonStringRawBody = file_get_contents("php://input");
$postDataArray     = json_decode($jsonStringRawBody, true);

$shopId = $postDataArray['shop_id'];

markTime();

if (tiktokWebhookAuth($shopId)) {
    responseOK();
    $time_end       = microtime(true);
    $execution_time = number_format(($time_end - $time_start), 12, '.', '');

    writeLog("➤➤ TIKTOK WEBHOOK");
    writeLog("Responsed in $execution_time seconds.");

    require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/syncBetweenPlatforms.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/tiktok/tiktokApi.php';

    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/tiktokWebhook.txt', "\n-------------------------------------------\n", FILE_APPEND);
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/tiktokWebhook.txt', "⏬ ⏬ ⏬ ⏬ " . date("H:i:s d.m.Y") . " ⏬ ⏬ ⏬ ⏬\n\n", FILE_APPEND);

    foreach ($headersArray as $header => $value) {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/tiktokWebhook.txt', "$header: $value\n", FILE_APPEND);
    }
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/tiktokWebhook.txt', "\nRAW POST BODY:\n", FILE_APPEND);
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/api_path/logs/tiktokWebhook.txt', "$jsonStringRawBody\n\n", FILE_APPEND);

    $postDataArray = json_decode($jsonStringRawBody, true);
    $messageType   = $postDataArray['type'];
    $webhookData   = $postDataArray['data'];
    writeLog("messageType: " . $messageType);

    // Tiktok order update
    if ($messageType == 1) {
        $orderId     = $webhookData['order_id'];
        $orderStatus = $webhookData['order_status'];
        writeLog("➤ TIKTOK WEBHOOK | ORDER: " . $orderId . " - " . $orderStatus);

        // Update Woo stock when an Order created / cancelled on Lazada
        if (in_array($orderStatus, ['UNPAID', 'AWAITING_SHIPMENT', 'CANCEL'])) {
            tiktokOrderUpdateHandle($orderId, $orderStatus);
        }
    }

} else {
    writeLog("❌: TIKTOK AUTH FAILED | ShopId: " . $shopId);
}

$time_end       = microtime(true);
$execution_time = number_format(($time_end - $time_start), 12, '.', '');
writeLog("Done in $execution_time seconds.");

exit();

/**
 * respondOK.
 */
function responseOK()
{
    ignore_user_abort(true);

    ob_start();
    http_response_code(200);

    echo ("OK - Client will receive this.");

    //$serverProtocole = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING);
    //header($serverProtocole . '200');
    //header('Content-Encoding: none');
    header('Content-Length: ' . ob_get_length());
    header('Connection: close');

    ob_end_flush();
    ob_flush();
    flush();

    // check if fastcgi_finish_request is callable
    if (is_callable('fastcgi_finish_request')) {
        /*
         * This works in Nginx
         */
        session_write_close();
        fastcgi_finish_request();

        return;
    }
}

function tiktokWebhookAuth($shopId)
{
    return $shopId == SHOP_ID;
}
