<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/woo/wooApi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/laz/lazApi.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/tiktok/tiktokApi.php';

markTime();

writeLog("➤➤ GET PRODUCT INFO | SKU: " . $_POST['sku']);

if (isset($_POST['sku'])) {

    $response['woo']    = getInfoFromWoo($_POST['sku']);
    $response['laz']    = getInfoFromLaz($_POST['sku']);
    $response['tiktok'] = getInfoFromTiktok($_POST['sku']);

    echo json_encode($response);
    exit;
}

function getInfoFromWoo($sku)
{
    try {
        $product = getWooProduct($sku);
    } catch (Exception $e) {
        // ❌ FAIL: Get woo product
        $info = array("name" => $e->getMessage(), "quantity" => "", "attributes" => "");

        return $info;
    }

    $info = array("name" => $product->name, "quantity" => $product->stock_quantity, "attributes" => $product->variationAttributes);

    // ✔ SUCCESS: Get woo product info
    return $info;
}

function getInfoFromLaz($sku)
{
    try {
        $product = getLazProductBySku($sku);
    } catch (Exception $e) {
        // ❌ FAIL: Get laz product
        $info = array("name" => $e->getMessage(), "quantity" => "", "attributes" => "");

        return $info;
    }

    $info['name'] = $product['name'];
    $info['attributes'] = $product['variationAttributes'];
    $info['totalQuantity'] = $product['quantity'];

    foreach ($product['multiWarehouseInventories'] as $warehouse) {
        $location        = array_search($warehouse['warehouseCode'], LAZ_WAREHOUSE_CODE);
        $info[$location] = $warehouse['quantity'];
    }

    // ✔️ SUCCESS: Get laz product info
    return $info;
}

function getInfoFromTiktok($sku)
{
    try {
        $product = getTiktokProductBySku($sku);
    } catch (Exception $e) {
        // ❌ FAIL: Get tiktok product
        $info = array("name" => $e->getMessage(), "quantity" => "", "attributes" => "");

        return $info;
    }

    $info['name'] = $product['product_name'];
    $info['attributes'] = $product['variationAttributes'];
    $info['quantity'] = $product['stock_infos'][0]['available_stock'];

    // ✔ SUCCESS: Get tiktok product info

    return $info;
}
