<?php
$time_start = microtime(true);

require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/utilities.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/laz/lazConstants.php";

$headersArray = apache_request_headers();
$webhookAuthorization = $headersArray["Authorization"];

$jsonStringRawBody = file_get_contents("php://input");

if (lazWebhookAuth($webhookAuthorization, $jsonStringRawBody)) {
    responseOK();
    $time_end = microtime(true);
    $execution_time = number_format($time_end - $time_start, 12, ".", "");
    markTime();
    writeLog("Responsed in $execution_time seconds.");

    require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/syncBetweenPlatforms.php";
    require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/laz/lazApi.php";

    file_put_contents(
        $_SERVER["DOCUMENT_ROOT"] . "/api_path/logs/lazWebhook.txt",
        "\n-------------------------------------------\n",
        FILE_APPEND
    );
    file_put_contents(
        $_SERVER["DOCUMENT_ROOT"] . "/api_path/logs/lazWebhook.txt",
        "⏬ ⏬ ⏬ ⏬ " . date("H:i:s d.m.Y") . " ⏬ ⏬ ⏬ ⏬\n\n",
        FILE_APPEND
    );

    foreach ($headersArray as $header => $value) {
        file_put_contents(
            $_SERVER["DOCUMENT_ROOT"] . "/api_path/logs/lazWebhook.txt",
            "$header: $value\n",
            FILE_APPEND
        );
    }
    file_put_contents(
        $_SERVER["DOCUMENT_ROOT"] . "/api_path/logs/lazWebhook.txt",
        "\nRAW POST BODY:\n",
        FILE_APPEND
    );
    file_put_contents(
        $_SERVER["DOCUMENT_ROOT"] . "/api_path/logs/lazWebhook.txt",
        "$jsonStringRawBody\n\n",
        FILE_APPEND
    );

    $postDataArray = json_decode($jsonStringRawBody, true);
    $messageType = $postDataArray["message_type"];
    $webhookData = $postDataArray["data"];
    writeLog("messageType: " . $messageType);

    // Laz order created / cancelled
    if ($messageType == 0) {
        $orderId = $webhookData["trade_order_id"];
        $orderStatus = $webhookData["order_status"];
        writeLog("➤➤ LAZ WEBHOOK | ORDER: " . $orderId . " - " . $orderStatus);

        // Update Woo stock when an Order created / cancelled on Lazada
        if (in_array($orderStatus, ["unpaid", "pending", "canceled"])) {
            lazOrderUpdateHandle($orderId, $orderStatus);
        }
    }

    // Laz product created/updated
    if ($messageType == 3 || $messageType == 4) {
        $itemId = $webhookData["item_id"];
        $skuList = $webhookData["sku_list"];
        writeLog("➤➤ LAZ WEBHOOK | PRODUCT CREATED/UPDATED");

        foreach ($skuList as $updatedSku) {
            $sku = $updatedSku["seller_sku"];
            writeLog("-----------------------");
            writeLog("➤ " . $sku);
            try {
                // Temporary disabled due to messup stock when update product skus
                //syncLazToWooAndTiktok($sku);
            } catch (Exception $e) {
                writeLog(
                    "❌ FAIL: Sync laz to woo | " . $e->getMessage() . "\n            └─── SKU: " . $sku
                );
            }
        }
    }

    // Laz accessToken expiration alert
    if ($messageType == 8) {
        writeLog("➤➤ LAZ WEBHOOK | ACCESS TOKEN EXPIRATION ALERT");

        try {
            refreshTokens();
        } catch (Exceptione $e) {
            writeLog("❌ FAIL: " . $e->getMessage());
        }
    }
}

$time_end = microtime(true);
$execution_time = number_format($time_end - $time_start, 12, ".", "");
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

    echo "OK - Client will receive this.";

    //$serverProtocole = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL', FILTER_SANITIZE_STRING);
    //header($serverProtocole . '200');
    //header('Content-Encoding: none');
    header("Content-Length: " . ob_get_length());
    header("Connection: close");

    ob_end_flush();
    ob_flush();
    flush();

    // check if fastcgi_finish_request is callable
    if (is_callable("fastcgi_finish_request")) {
        /*
         * This works in Nginx
         */
        session_write_close();
        fastcgi_finish_request();

        return;
    }
}

function lazWebhookAuth($signature, $stringRawBody)
{
    return $signature == hash_hmac("SHA256", LAZ_APP_KEY . $stringRawBody, LAZ_APP_SECRET);
}
