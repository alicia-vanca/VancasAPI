<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/utilities.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/laz/lazApi.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/woo/wooApi.php";

// For email function
//require_once __DIR__ .'/../../wp-load.php';
require_once $_SERVER["DOCUMENT_ROOT"] . "/wp-load.php";

function syncLazToWoo($sku)
{
    writeLog("-----------------------");

    $product = getLazProductBySku($sku);
    updateWooProductQuantity($sku, $product["quantity"]);
    return true;
}

function syncLazToWooAndTiktok($sku)
{
    $product = getLazProductBySku($sku);

    $newWooQuantity = $product["quantity"];
    try {
        updateWooProductQuantity($sku, $newWooQuantity);
    } catch (Exception $e) {
        writeLog(
            "❌ FAIL: Sync laz to woo | " .
                $e->getMessage() .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["attributes"]["name"] .
                "\n                 " .
                $product["variationAttributes"]
        );
    }

    $newTiktokQuantity = getLazWarehouseQuantity($sku, "HN", $product);
    try {
        updateTiktokProductQuantity($sku, $newTiktokQuantity);
    } catch (Exception $e) {
        writeLog(
            "❌ FAIL: Sync laz to tiktok | " .
                $e->getMessage() .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["attributes"]["name"] .
                "\n                 " .
                $product["variationAttributes"]
        );
    }

    return true;
}

// sku: seller_sku
// productId: tiktok's product_id
function syncTiktokToLaz($sku, $productId)
{
    writeLog("-----------------------");

    $product = getTiktokProductBySku($sku, $productId);
    $newQuantity = $product["stock_infos"][0]["available_stock"];

    // Sync quantity of tiktok product to Laz's HN warehouse
    try {
        updateLazProductQuantityFromTiktok($sku, "HN", $newQuantity);

        // Sync new info from laz to woo
        try {
            syncLazToWoo($sku);
        } catch (Exception $e) {
            writeLog(
                "❌ FAIL: Sync laz to woo | " .
                    $e->getMessage() .
                    "\n            └─── SKU: " .
                    $sku .
                    "\n                 " .
                    $product["product_name"] .
                    "\n                 " .
                    $product["variationAttributes"]
            );
        }
    } catch (Exception $e) {
        writeLog(
            "❌ FAIL: Sync tiktok to laz | " .
                $e->getMessage() .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["product_name"] .
                "\n                 " .
                $product["variationAttributes"]
        );
    }

    return true;
}

function lazOrderUpdateHandle($orderId, $orderStatus = null)
{
    // Get order items
    $orderItems = getLazOrderItems($orderId);

    // Sync new quantity of each product to woo
    foreach ($orderItems as $item) {
        try {
            writeLog("-----------------------");
            syncLazToWooAndTiktok($item["sku"]);
        } catch (Exception $e) {
            writeLog(
                "❌ FAIL: Sync laz to woo and tiktok | " .
                    $e->getMessage() .
                    "\n            └─── SKU: " .
                    $item["sku"]
            );
        }
    }

    // Send email to notice about new order
    $headers = ["Content-Type: text/html; charset=UTF-8"];
    $subject = "Đơn hàng Lazada: $orderId";
    $emailContentHeader = "Đơn hàng Lazada: <br>$orderId";
    if ($orderStatus !== null) {
        $emailContentHeader .= "- $orderStatus";
    }
    $emailContentBody = "Chi tiết đơn hàng: ";
    $body = renderEmail($emailContentHeader, $emailContentBody, $orderItems);
    wp_mail("________@gmail.com", $subject, $body, $headers);
    wp_mail("________@gmail.com", $subject, $body, $headers);

    return true;
}

// orderId: tiktok's order ID
function tiktokOrderUpdateHandle($orderId, $orderStatus = null)
{
    // Get order items
    $orderItems = getTiktokOrderItems($orderId);

    // Sync new quantity of each product to Laz's HN warehouse
    foreach ($orderItems as $item) {
        try {
            syncTiktokToLaz($item["seller_sku"], $item["product_id"]);
        } catch (Exception $e) {
            writeLog(
                "❌ FAIL: Sync tiktok to laz | " .
                    $e->getMessage() .
                    "\n            └─── SKU: " .
                    $item["seller_sku"]
            );
        }
    }

    // Send email to notice about new order
    $headers = ["Content-Type: text/html; charset=UTF-8"];
    $subject = "Đơn hàng Tiktok: $orderId";
    $emailContentHeader = "Đơn hàng Lazada: <br>$orderId";
    if ($orderStatus !== null) {
        $emailContentHeader .= "- $orderStatus";
    }
    $emailContentBody = "Chi tiết đơn hàng: ";
    $body = renderEmail($emailContentHeader, $emailContentBody, $orderItems);
    wp_mail("________@gmail.com", $subject, $body, $headers);
    wp_mail("________@gmail.com", $subject, $body, $headers);

    return true;
}
