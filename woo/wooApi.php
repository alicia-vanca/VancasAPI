<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/utilities.php";

defined("WOO_STORE_URL") || define("WOO_STORE_URL", "https://store_url");
defined("WOO_CONSUMER_KEY") || define("WOO_CONSUMER_KEY", "put_consumer_key_here");
defined("WOO_CONSUMER_SECRET") ||
    define("WOO_CONSUMER_SECRET", "put_secret_key_here");

// Webhook secret
defined("WOO_PRODUCT_UPDATED_SECRET") ||
    define("WOO_PRODUCT_UPDATED_SECRET", "put_update_secret_key_here");

const WOO_ALLOWED_TRIES = 2;

// Setup:
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/vendor/autoload.php";
use Automattic\WooCommerce\Client;
$wooClient = new Client(
    WOO_STORE_URL, // Your store URL
    WOO_CONSUMER_KEY, // Your consumer key
    WOO_CONSUMER_SECRET, // Your consumer secret
    [
        "wp_api" => true, // Enable the WP REST API integration
        "version" => "wc/v3", // WooCommerce WP REST API version
    ]
);

function getWooProduct($sku, $triesCount = 0)
{
    $data = [
        "sku" => $sku,
    ];

    try {
        $response = $GLOBALS["wooClient"]->get("products", $data);
    } catch (Exception $e) {
        // Request timeout,...
        writeLog(
            "❌ FAIL (" .
                ++$triesCount .
                "): Get woo product | " .
                $e->getMessage() .
                "\n            └─── SKU: " .
                $sku
        );

        if ($triesCount < WOO_ALLOWED_TRIES) {
            writeLog(". . .trying again. . .");
            return getWooProduct($sku, $triesCount);
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception("❌ FAIL: Get woo product | " . $e->getMessage());
        }
    }

    if (count($response) == 1) {
        // Save variationAttributes for more detail logs
		$variationAttributes = "";
        foreach ($response[0]->attributes as $attribute) {
            $variationAttributes .= $attribute->name . ": " . $attribute->option . "   |   ";
        }
        $variationAttributes .= "Số lượng: " . $response[0]->stock_quantity;
        $response[0]->variationAttributes = $variationAttributes;

        writeLog(
            "✔️ SUCCESS: Get woo product" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $response[0]->name .
                "\n                 " .
                $response[0]->variationAttributes
        );

        return $response[0];
    } else {
        writeLog("❌ FAIL: Get woo product | SKU not exist" . "\n            └─── SKU: " . $sku);
        throw new Exception("❌ FAIL: Get woo product | SKU not exist");
    }
}

/**
 * Get woo father product
 **/
function getWooFatherProductBySku($sku)
{
    $parentId = getWooProduct($sku)->parent_id;

    $fatherProduct = $GLOBALS["wooClient"]->get("products/$parentId");

    return $fatherProduct;
}

/**
 * Update Woo quantity
 **/
function updateWooProductQuantity($sku, $newQuantity, $product = null)
{
    if ($newQuantity < 0 || $newQuantity === null) {
        writeLog(
            "❌ FAIL: Update woo product quantity | Invalid quantity input" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 └─── New quantity: " .
                $newQuantity
        );
        throw new Exception("❌ FAIL: Update woo product quantity | Invalid quantity input");
    }

    // Can skip a query if we already have the product data
    if ($product === null || $product->sku != $sku) {
        $product = getWooProduct($sku);
    }

    $theDifferent = $newQuantity - $product->stock_quantity;

    if ($theDifferent == 0) {
        writeLog(
            "⚠ CANCEL: Update woo product quantity | Notthing changed" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product->name .
                "\n                 " .
                $product->variationAttributes .
                "\n                 └─── Current quantity: " .
                $product->stock_quantity
        );
        throw new Exception("⚠ CANCEL: Update woo product quantity | Notthing changed");
    }

    $data = [
        "stock_quantity" => $newQuantity,
    ];
    try {
        if ("simple" == $product->type) {
            $GLOBALS["wooClient"]->put("products/" . $product->id, $data);
        }
        if ("variation" == $product->type) {
            $GLOBALS["wooClient"]->put(
                "products/" . $product->parent_id . "/variations/" . $product->id,
                $data
            );
        }

        writeLog(
            "✔️ SUCCESS: Update woo product quantity" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product->name .
                "\n                 " .
                $product->variationAttributes .
                "\n                 └─── Change: " .
                ($theDifferent > 0 ? "+" . $theDifferent : $theDifferent) .
                " | New quantity: " .
                $newQuantity
        );
        return true;
    } catch (Exception $e) {
        writeLog(
            "❌ FAIL: Update woo product quantity | " . $e->getMessage() . "\n            └─── SKU: " . $sku
        );
        throw $e;
    }
}

function wooWebhookAuth($signature, $stringRawBody)
{
    return $signature == base64_encode(hash_hmac("SHA256", $stringRawBody, WOO_PRODUCT_UPDATED_SECRET, true));
}
