<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/utilities.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/dbConnect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/laz/lazConstants.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/lazopSdk/LazopSdk.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/woo/wooApi.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/tiktok/tiktokApi.php";

$lazAuthClient = new LazopClient(LAZ_AUTH_URL, LAZ_APP_KEY, LAZ_APP_SECRET);
$lazClient = new LazopClient(LAZ_API_URL, LAZ_APP_KEY, LAZ_APP_SECRET);

$lazTokens = getLazAccessTokenFromDb();
$lazAccessToken = $lazTokens["access_token"];
$lazRefreshToken = $lazTokens["refresh_token"];
$lazAccessTokenExpiresIn = $lazTokens["access_token_expire_in"];
$lazRefreshTokenExpiresIn = $lazTokens["refresh_token_expire_in"];
$lazAccessTokenExpirationAlert = $lazTokens["access_token_expiration_alert"];
$lazRefreshTokenExpired = $lazTokens["refresh_token_expired"];

// If accessToken expires in less than 2days, then refresh tokens
if (time() > $lazAccessTokenExpiresIn - 172800) {    
    if (time() <= ($lazRefreshTokenExpiresIn - 604800)) {
        refreshTokens();
    }
}

function getLazAccessTokenFromDb()
{
    $sql =
        "SELECT access_token, access_token_expire_in, refresh_token, refresh_token_expire_in, access_token_expiration_alert, refresh_token_expired" .
        " FROM laz_tokens WHERE id = ?";
    $stmt = prepared_query($sql, [1]);
    $row = $stmt->get_result()->fetch_assoc();
    return $row;
}

function updateAccessTokenExpirationAlert($isAlerted)
{
    $GLOBALS["lazAccessTokenExpirationAlert"] = $isAlerted;
    try {
        $sql = "UPDATE laz_tokens SET (access_token_expiration_alert = ?) WHERE id = 1";
        $stmt = prepared_query($sql, [$isAlerted], "i");
        writeLog("✔️ SUCCESS: Update access_token_expiration_alert ($isAlerted)");
    } catch (Exception $e) {
        writeLog("❌ FAIL: Update access_token_expiration_alert ($isAlerted) | " . $e->getMessage());
    }
}

function updateRefreshTokenExpired($isExpired)
{
    try {
        $sql = "UPDATE laz_tokens SET (refresh_token_expired = ?) WHERE id = 1";
        $stmt = prepared_query($sql, [$isExpired], "i");
        writeLog("✔️ SUCCESS: Update refresh_token_expired ($isExpired)");
        writeLog("Affected rows: $stmt->affected_rows");
    } catch (Exception $e) {
        writeLog("❌ FAIL: Update refresh_token_expired ($isExpired) | " . $e->getMessage());
    }
}

function refreshTokens()
{
    // Only refresh when got alerted or token expired
    $GLOBALS["lazAccessTokenExpirationAlert"] = true;
    $GLOBALS["lazRefreshTokenExpired"] = true;

    $request = new LazopRequest("/auth/token/refresh");
    $request->addApiParam("refresh_token", $GLOBALS["lazRefreshToken"]);

    $authResponseJsonString = $GLOBALS["lazAuthClient"]->execute($request);
    $authResponseArray = json_decode($authResponseJsonString, true);

    if (isSuccess($authResponseArray)) {
        $GLOBALS["lazAccessToken"] = $authResponseArray["access_token"];
        $GLOBALS["lazRefreshToken"] = $authResponseArray["refresh_token"];
        $GLOBALS["lazAccessTokenExpiresIn"] = time() + $authResponseArray["expires_in"];
        $GLOBALS["lazRefreshTokenExpiresIn"] = time() + $authResponseArray["refresh_expires_in"];

        saveLazTokensIntoDb(
            $GLOBALS["lazAccessToken"],
            $GLOBALS["lazAccessTokenExpiresIn"],
            $GLOBALS["lazRefreshToken"],
            $GLOBALS["lazRefreshTokenExpiresIn"],
            true,
            true
        );
        writeLog("✔️ SUCCESS: Refresh laz tokens");
        return true;
    } else {
        writeLog("❌ FAIL: Refresh laz tokens | " . $authResponseArray["message"]);
    }
}

function getLazProductBySku($sku, $triesCount = 0)
{
    $request = new LazopRequest("/products/get", "GET");
    $request->addApiParam("sku_seller_list", "[\"" . $sku . "\"]");

    $responseJsonString = $GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isSuccess($responseArray)) {
        if ($responseArray["data"]["total_products"] == 1) {
            // file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/lazProduct.txt', $responseJsonString);

            $parentProduct = $responseArray["data"]["products"][0];

            foreach ($parentProduct["skus"] as $variation) {
                if ($variation["SellerSku"] == $sku) {

                    $variation["name"] = $parentProduct["attributes"]["name"];
                    $variation["item_id"] = $parentProduct["item_id"];

                    $variationAttributes = "";
                    foreach ($variation["saleProp"] as $key => $value) {
                        $variationAttributes .= $key . ": " . $value . "   |   ";
                    }
                    $variationAttributes .= "Số lượng: " . $variation["quantity"];
                    $variation["variationAttributes"] = $variationAttributes;

                    writeLog(
                        "✔️ SUCCESS: Get laz product" .
                            "\n            └─── SKU: " .
                            $sku .
                            "\n                 " .
                            $variation["name"] .
                            "\n                 " .
                            $variation["variationAttributes"]
                    );

                    return $variation;

                }
            }
        } elseif ($responseArray["data"]["total_products"] > 1) {
            writeLog(
                "❌ FAIL: Get laz product | Duplicate sku" .
                    "\n            └─── SKU: " .
                    $sku
            );
            throw new Exception("Duplicate sku");
        } else {
            writeLog(
                "❌ FAIL: Get laz product | Sku not exist" .
                    "\n            └─── SKU: " .
                    $sku
            );
            throw new Exception("Sku not exist");
        }
    } else {
        writeLog(
            "❌ FAIL (" .
                ++$triesCount .
                "): Get laz product | " .
                $responseArray["message"] .
                "\n            └─── SKU: " .
                $sku
        );

        if ($triesCount < LAZ_ALLOWED_TRIES && $responseArray["message"] !== SKU_NOT_FOUND) {
            writeLog(". . .trying again. . .");
            return getLazProductBySku($sku, $triesCount);
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception($responseArray["message"]);
        }
    }
}

function getLazProductById($id)
{
    $request = new LazopRequest("/product/item/get", "GET");
    $request->addApiParam("item_id", $id);

    $responseJsonString = $GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isSuccess($responseArray)) {
        // file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/lazProduct.txt', $responseJsonString);

        $productArray = $responseArray["data"];
        $variationAttributes = "";

        foreach ($productArray["variation"] as $variation) {
            $variationAttributes .= $variation["name"] . ": " . $variation["options"][0] . "   |   ";
        }
        $productArray["variationAttributes"] = rtrim($variationAttributes, "   |   ");

        writeLog(
            "✔️ SUCCESS: Get laz product" .
                "\n            └─── SKU: " .
                $productArray["skus"]["SellerSku"] .
                "\n                 " .
                $productArray["attributes"]["name"] .
                "\n                 " .
                $productArray["variationAttributes"]
        );
        return $productArray;
    } else {
        writeLog(
            "❌ FAIL: Get laz product | " . $responseArray["message"] . "\n            └─── SKU: " . $sku
        );
        throw new Exception($responseArray["message"]);
    }
}

function getLazFatherProductBySku($sku)
{
    // Get item_id from sku
    writeLog("Get laz item_id from sku");
    $product = getLazProductBySku($sku);
    $itemId = $product["item_id"];

    // Use item_id to get product (include all its variations)
    $request = new LazopRequest("/product/item/get", "GET");
    $request->addApiParam("item_id", $itemId);

    $responseJsonString = $GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isSuccess($responseArray)) {
        $product = $responseArray["data"];
        writeLog(
            "✔️ SUCCESS: Get laz father product" .
                "\n            └─── ID: " .
                $itemId .
                "\n                 " .
                $product["name"]
        );
        return $product;
    } else {
        writeLog(
            "❌ FAIL: Get laz father product | " .
                $responseArray["message"] .
                "\n            └─── ID: " .
                $itemId
        );
        throw new Exception($responseArray["message"]);
    }
}

function getLazWarehouseQuantity($sku, $warehouseName, $product = null)
{
    // Can skip a query if we already have the product data
    if ($product === null || $product["skus"][0]["SellerSku"] != $sku) {
        $product = getLazProductBySku($sku);
    }

    foreach ($product["multiWarehouseInventories"] as $warehouse) {
        if ($warehouse["warehouseCode"] == LAZ_WAREHOUSE_CODE[$warehouseName]) {
            return $warehouse["sellableQuantity"];
        }
    }

    return null;
}

function getLazOrderItems($orderId, $triesCount = 0)
{
    $request = new LazopRequest("/order/items/get", "GET");
    $request->addApiParam("order_id", $orderId);

    $responseJsonString = $GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isSuccess($responseArray)) {
        writeLog("✔️ SUCCESS: Get list items of laz order" . "\n            └─── Order ID: " . $orderId);
        return $responseArray["data"];
    } else {
        writeLog(
            "❌ FAIL (" .
                ++$triesCount .
                "): Get list items of laz order | " .
                $responseArray["message"] .
                "\n            └─── Order ID: " .
                $orderId
        );

        if ($triesCount < LAZ_ALLOWED_TRIES) {
            writeLog(". . .trying again. . .");
            return getLazOrderItems($orderId, $triesCount);
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception($responseArray["message"]);
        }
    }
}

/**
 * Update Laz quantity without warehouse code
 **/
function updateLazProductQuantity($sku, $newQuantity)
{
    $warehouseCode_descrease_first = LAZ_WAREHOUSE_CODE["HCM"];
    $warehouseCode_descrease_second = LAZ_WAREHOUSE_CODE["HN"];
    $warehouseCode_increase = LAZ_WAREHOUSE_CODE["HN"];
    $finalWarehouseCode = "";

    $product = getLazProductBySku($sku);

    $currentTotalQuantity = $product["quantity"];

    $theDifferent = $newQuantity - $currentTotalQuantity;

    if ($theDifferent == 0) {
        writeLog(
            "⚠ CANCEL: Update laz product quantity | Notthing changed" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["name"] .
                "\n                 " .
                $product["variationAttributes"] .
                "\n                 └─── Current total quantity: " .
                $currentTotalQuantity
        );
        throw new Exception("⚠ CANCEL: Update laz product quantity | Notthing changed");
    }

    if ($theDifferent < 0) {
        // In case of descrease quantity

        $currentSellableQuantity = 0;

        // check if 1st priority warehouse can handle the descrease
        foreach ($product["multiWarehouseInventories"] as $warehouse) {
            if ($warehouse["warehouseCode"] == $warehouseCode_descrease_first) {
                $currentSellableQuantity = $warehouse["sellableQuantity"];
                break;
            }
        }
        if ($currentSellableQuantity + $theDifferent >= 0) {
            // Decrease quantity of 1st priority warehouse
            $finalWarehouseCode = $warehouseCode_descrease_first;
        } else {
            // Decrease quantity of 2nd priority warehouse
            $finalWarehouseCode = $warehouseCode_descrease_second;
        }
    } else {
        // In case of increase quantity
        $finalWarehouseCode = $warehouseCode_increase;
    }

    return updateLazProductQuantityWithWarehouseCode($sku, $finalWarehouseCode, $newQuantity, $product);
}

/**
 * Update Laz's warehouse quantity from tiktok
 * Prevent reverse sync to Tiktok
 **/
function updateLazProductQuantityFromTiktok($sku, $warehouseName, $newQuantity)
{
    updateLazProductQuantityWithWarehouseCode(
        $sku,
        LAZ_WAREHOUSE_CODE[$warehouseName],
        $newQuantity,
        null,
        0,
        true
    );
}

/**
 * Update Laz quantity with warehouse code
 **/
function updateLazProductQuantityWithWarehouseCode(
    $sku,
    $warehouseCode,
    $newQuantity,
    $product = null,
    $triesCount = 0,
    $skipTiktokFlg = false
) {
    if ($newQuantity < 0 || $newQuantity === null) {
        writeLog(
            "❌ FAIL: Update laz product quantity | Invalid quantity input" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 └─── WarehouseCode: " .
                $warehouseCode .
                "\n                      └─── New quantity: " .
                $newQuantity
        );
        throw new Exception("❌ FAIL: Update laz product quantity | Invalid quantity input");
    }

    // Can skip a query if we already have the product data
    if ($product === null) {
        $product = getLazProductBySku($sku);
    }

    $theDifferent = $newQuantity;
    $newTotalQuantity = $newQuantity;

    foreach ($product["multiWarehouseInventories"] as $warehouse) {
        if ($warehouse["warehouseCode"] == $warehouseCode) {
            $theDifferent = $newQuantity - $warehouse["sellableQuantity"];

            if ($theDifferent == 0) {
                writeLog(
                    "⚠ CANCEL: Update laz product quantity | Notthing changed" .
                        "\n            └─── SKU: " .
                        $sku .
                        "\n                 " .
                        $product["name"] .
                        "\n                 " .
                        $product["variationAttributes"] .
                        "\n                 └─── WarehouseCode: " .
                        $warehouseCode .
                        "\n                      └─── Current quantity: " .
                        $warehouse["sellableQuantity"]
                );
                throw new Exception("⚠ CANCEL: Update laz product quantity | Notthing changed");
            }

            $newTotalQuantity = $warehouse["totalQuantity"] + $theDifferent;
            break;
        }
    }

    // Update the stock of tiktok too if there's a change in Laz's HN warehouse (ofcourse, not from Tiktok)
    if (!$skipTiktokFlg && $warehouseCode == LAZ_WAREHOUSE_CODE["HN"]) {
        writeLog("-----------------------");

        try {
            updateTiktokProductQuantity($sku, $newTotalQuantity);
        } catch (Exception $e) {
            writeLog(
                "❌ FAIL: Sync Laz to Tiktok | " .
                    $e->getMessage() .
                    "\n            └─── SKU: " .
                    $sku .
                    "\n                 " .
                    $product["name"] .
                    "\n                 " .
                    $product["variationAttributes"]
            );
        }

        // Only need to sync to tiktok once.
        $skipTiktokFlg = true;

        writeLog("-----------------------");
    }

    $request = new LazopRequest("/product/price_quantity/update");

    $payload = [
        "Request" => [
            "Product" => [
                "item_id" => $product["item_id"],
                "Skus" => [
                    "Sku" => [
                        "SkuId" => $product["SkuId"],
                        "SellerSku" => $sku,
                        "MultiWarehouseInventories" => [
                            "MultiWarehouseInventory" => [
                                "WarehouseCode" => $warehouseCode,
                                "Quantity" => $newTotalQuantity,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $request->addApiParam("payload", json_encode($payload));

    $responseArray = json_decode($GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]), true);

    if (isSuccess($responseArray)) {
        writeLog(
            "✔️ SUCCESS: Update laz product quantity" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["name"] .
                "\n                 " .
                $product["variationAttributes"] .
                "\n                 └─── WarehouseCode: " .
                $warehouseCode .
                "\n                      └─── Change: " .
                ($theDifferent > 0 ? "+$theDifferent" : $theDifferent) .
                " | New total quantity: " .
                $newTotalQuantity
        );
        return true;
    } else {
        writeLog(
            "❌ FAIL (" .
                ++$triesCount .
                "): Update laz product quantity | " .
                $responseArray["message"] .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["name"] .
                "\n                 " .
                $product["variationAttributes"] .
                "\n                 └─── WarehouseCode: " .
                $warehouseCode .
                "\n                      └─── New total quantity: " .
                $newTotalQuantity
        );

        if ($triesCount < LAZ_ALLOWED_TRIES) {
            writeLog(". . .trying again. . .");
            return updateLazProductQuantityWithWarehouseCode(
                $sku,
                $warehouseCode,
                $newQuantity,
                $product,
                $triesCount,
                $skipTiktokFlg
            );
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception($responseArray["message"]);
        }
    }
}

function copyProductFromWoo($sku)
{
    // Get woo product
    $wooFatherProduct = getWooFatherProductBySku($sku);

    $productImages = [];
    $imageCount = 0;
    foreach ($wooFatherProduct->images as $image) {
        $imageUrl = uploadImageToLaz($image->src);
        array_push($productImages, $imageUrl);

        if (++$imageCount == 8) {
            break;
        }
    }

    // Remove imgs from description
    $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
    $description = preg_replace($regex, " ", $wooFatherProduct->description);

    $request = new LazopRequest("/product/create");

    $payload = [
        "Request" => [
            "Product" => [
                "PrimaryCategory" => 6401,
                "Images" => [
                    "Image" => $productImages,
                ],
                "Attributes" => [
                    "name" => $wooFatherProduct->name,
                    "description" => $description,
                    "brand" => "No Brand",
                    "clothing_material" => "Polyester"
                ],
                "Skus" => [
                    "Sku" => [
                        0 => [
                            "SellerSku" => $sku . "_temp",
                            "price" => 100000,
                            "package_height" => 5,
                            "package_length" => 27,
                            "package_width" => 37,
                            "package_weight" => 0.7,
                        ],
                    ],
                ],
            ],
        ],
    ];

    $request->addApiParam("payload", json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $responseArray = json_decode($GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]), true);

    if (isSuccess($responseArray)) {
        writeLog(
            "✔️ SUCCESS: Create laz product from woo product" .
                "\n            └─── " .
                $wooFatherProduct->name
        );
        return true;
    } else {
        writeLog(
            "❌ FAIL: Create laz product | " .
                $responseArray["code"] .
                " " .
                $responseArray["message"] .
                "\n            └─── " .
                $wooFatherProduct->name
        );
        writeLog(json_encode($responseArray, JSON_PRETTY_PRINT));
        throw new Exception($responseArray["message"] . " | " . $responseArray["detail"][0]["message"]);
    }
}

function uploadImageToLaz($url)
{
    // Json payload not work
    // $payload = [
    //     "Request" => [
    //         "Image" => [
    //             "Url" => $url,
    //         ],
    //     ],
    // ];
    $payload = "<Request>
                    <Image>
                        <Url>
                            $url
                        </Url>     
                    </Image> 
                </Request>";

    $request = new LazopRequest("/image/migrate");
    $request->addApiParam("payload", $payload);

    $responseArray = json_decode($GLOBALS["lazClient"]->execute($request, $GLOBALS["lazAccessToken"]), true);

    if (isSuccess($responseArray)) {
        $lazImageUrl = $responseArray["data"]["image"]["url"];

        writeLog("✔️ SUCCESS: Upload image to laz" . "\n            └─── " . $lazImageUrl);

        return $lazImageUrl;
    } else {
        writeLog(
            "❌ FAIL: Upload image to laz | " . $responseArray["message"] . "\n            └─── Url: " . $url
        );
        throw new Exception($responseArray["message"]);
    }
}

function isSuccess($responseArray)
{
    if ($responseArray["code"] !== null && $responseArray["code"] != "0") {
        if ($responseArray["code"] == "IllegalAccessToken") {
            writeLog("⚠ Token expired");
            refreshTokens();
        }

        return false;
    } else {
        return true;
    }
}
