<?php
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/utilities.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/dbConnect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/tiktok/tiktokConstants.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/tiktokSdk/TiktokSdk.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/laz/lazApi.php";

$tiktokAuthClient = new TiktokClient(TIKTOK_AUTH_URL, TIKTOK_APP_KEY, TIKTOK_APP_SECRET);
$tiktokClient = new TiktokClient(TIKTOK_API_URL, TIKTOK_APP_KEY, TIKTOK_APP_SECRET);

$tiktokTokens = getTiktokAccessTokenFromDb();
$tiktokAccessToken = $tiktokTokens["access_token"];
$tiktokRefreshToken = $tiktokTokens["refresh_token"];
$tiktokAccessTokenExpireIn = $tiktokTokens["access_token_expire_in"];
$tiktokRefreshTokenExpireIn = $tiktokTokens["refresh_token_expire_in"];
$tiktokAccessTokenExpirationAlert = $tiktokTokens["access_token_expiration_alert"];
$tiktokRefreshTokenExpired = $tiktokTokens["refresh_token_expired"];

// If accessToken expires in less than 2days, then refresh tokens
if (time() > $tiktokAccessTokenExpireIn - 172800) {
    refreshTiktokTokens();
}

function getTiktokAccessTokenFromDb()
{
    $sql =
        "SELECT access_token, access_token_expire_in, refresh_token, refresh_token_expire_in, access_token_expiration_alert, refresh_token_expired FROM tiktok_tokens WHERE id = ?";
    $stmt = prepared_query($sql, [1]);
    $row = $stmt->get_result()->fetch_assoc();
    return $row;
}

function updateTiktokAccessTokenExpirationAlert($isAlerted)
{
    $GLOBALS["tiktokAccessTokenExpirationAlert"] = $isAlerted;
    try {
        $sql = "UPDATE tiktok_tokens SET (access_token_expiration_alert = ?) WHERE id = 1";
        $stmt = prepared_query($sql, [$isAlerted], "i");
        writeLog("✔️ SUCCESS: Update tiktok access_token_expiration_alert ($isAlerted)");
    } catch (Exception $e) {
        writeLog("❌ FAIL: Update tiktok access_token_expiration_alert ($isAlerted) | " . $e->getMessage());
    }
}

function updateTiktokRefreshTokenExpired($isExpired)
{
    try {
        $sql = "UPDATE tiktok_tokens SET (refresh_token_expired = ?) WHERE id = 1";
        $stmt = prepared_query($sql, [$isExpired], "i");
        writeLog("✔️ SUCCESS: Update Tiktok refresh_token_expired ($isExpired)");
        writeLog("Affected rows: $stmt->affected_rows");
    } catch (Exception $e) {
        writeLog("❌ FAIL: Update tiktok refresh_token_expired ($isExpired) | " . $e->getMessage());
    }
}

function refreshTiktokTokens()
{
    markTime();
    writeLog("➤➤ REFRESH TIKTOK TOKENS");

    $request = new TiktokRequest("/api/v2/token/refresh", "GET");
    $request->addApiParam("refresh_token", $GLOBALS["tiktokRefreshToken"]);
    $request->addApiParam("grant_type", "refresh_token");

    $authResponseJsonString = $GLOBALS["tiktokAuthClient"]->execute($request);
    $authResponseArray = json_decode($authResponseJsonString, true);

    if (isTiktokCallSuccess($authResponseArray)) {
        $GLOBALS["tiktokAccessToken"] = $authResponseArray["data"]["access_token"];
        $GLOBALS["tiktokAccessTokenExpireIn"] = $authResponseArray["data"]["access_token_expire_in"];
        $GLOBALS["tiktokRefreshToken"] = $authResponseArray["data"]["refresh_token"];
        $GLOBALS["tiktokRefreshTokenExpireIn"] = $authResponseArray["data"]["refresh_token_expire_in"];

        saveTiktokTokensIntoDb(
            $GLOBALS["tiktokAccessToken"],
            $GLOBALS["tiktokAccessTokenExpireIn"],
            $GLOBALS["tiktokRefreshToken"],
            $GLOBALS["tiktokRefreshTokenExpireIn"],
            true,
            true
        );
        writeLog("✔️ SUCCESS: Refresh tiktok tokens");
        return true;
    } else {
        if ($authResponseArray["code"] == 36004005) {
            $redirectUrl = 'https://services.tiktokshop.com/open/authorize?service_id=put_shop_ID_here';
            die(header('location: ' . $redirectUrl));
        } else {
            writeLog("❌ FAIL: Refresh tiktok tokens | " . $authResponseJsonString);
            throw new Exception($authResponseArray["message"]);
        }
    }
}

function getTiktokProductIdBySku($sku, $triesCount = 0)
{
    $request = new TiktokRequest("/api/products/search");
    $request->addApiParam("seller_sku_list", [$sku]);
    $request->addApiParam("page_number", 1);
    $request->addApiParam("page_size", 2);

    $responseJsonString = $GLOBALS["tiktokClient"]->execute($request, $GLOBALS["tiktokAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isTiktokCallSuccess($responseArray)) {
        $responseData = $responseArray["data"];

        // SKU_NOT_FOUND
        if ($responseArray["data"]["total"] != 1) {
            // ❌ FAIL: Get tiktok product ID | Sku not found
            return null;
        }

        $productArray = $responseData["products"][0];

        // ✔️ SUCCESS: Get tiktok product ID
        return $productArray["id"];
    } else {
        writeLog(
            "❌ FAIL" .
                ($triesCount = 0 ? "" : " (" . $triesCount . ")") .
                ": Get tiktok product ID | " .
                $responseArray["message"] .
                "\n            └─── SKU: " .
                $sku
        );

        if ($triesCount++ < TIKTOK_ALLOWED_TRIES) {
            writeLog(". . .trying again. . .");
            return getTiktokProductIdBySku($sku, $triesCount);
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception($responseArray["message"]);
        }
    }
}

// sku: any variation's seller_sku
function getTiktokFatherProductBySku($sku)
{
    $productId = getTiktokProductIdBySku($sku);

    if ($productId === null) {
        writeLog(
            "❌ FAIL: Get tiktok father product by SKU | SKU not exist" . "\n            └─── SKU: " . $sku
        );
        throw new Exception("SKU not exist on Tiktok");
    }

    return getTiktokFatherProductByProductId($productId);
}

// productId: product_id
function getTiktokFatherProductByProductId($productId, $triesCount = 0)
{
    if ($productId === null) {
        writeLog("❌ FAIL: Get tiktok father product by Product ID | Product ID cannot be null");
        throw new Exception("productId cannot be null");
    }

    $request = new TiktokRequest("/api/products/details", "GET");
    $request->addApiParam("product_id", $productId);

    $responseJsonString = $GLOBALS["tiktokClient"]->execute($request, $GLOBALS["tiktokAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isTiktokCallSuccess($responseArray)) {
        return $responseArray["data"];
    } else {
        writeLog(
            "❌ FAIL" .
                ($triesCount = 0 ? "" : " (" . $triesCount . ")") .
                ": Get tiktok father product by Product ID | " .
                $responseArray["message"] .
                "\n            └─── Product ID: " .
                $productId
        );

        if ($triesCount++ < TIKTOK_ALLOWED_TRIES && $responseArray["message"] != "product not find") {
            writeLog(". . .trying again. . .");
            return getTiktokFatherProductByProductId($productId, $triesCount);
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception($responseArray["message"]);
        }
    }
}

function getProductFromTiktokFatherProduct($sku, $fatherProduct)
{
    foreach ($fatherProduct["skus"] as $variation) {
        // Find the correct variation
        if ($variation["seller_sku"] == $sku) {
            // Save product_id and product_name for future use
            $variation["product_id"] = $fatherProduct["product_id"];
            $variation["product_name"] = $fatherProduct["product_name"];

            // Save variationAttributes for more detail logs
			$variationAttributes = "";
            foreach ($variation["sales_attributes"] as $attribute) {
                $variationAttributes .= $attribute["name"] . ": " . $attribute["value_name"] . "   |   ";
            }
            $variationAttributes .= "Số lượng: " . $variation['stock_infos'][0]['available_stock'];
            $variation["variationAttributes"] = $variationAttributes;

            return $variation;
        }
    }
    return null;
}

// sku: variation's seller_sku
// productId: product_id
function getTiktokProductBySku($sku, $productId = null)
{
    // Can skip a query if we've already known the Product_ID
    if ($productId !== null) {
        $fatherProduct = getTiktokFatherProductByProductId($productId);
    } else {
        $fatherProduct = getTiktokFatherProductBySku($sku);
    }

    $product = getProductFromTiktokFatherProduct($sku, $fatherProduct);

    if ($product !== null) {
        writeLog(
            "✔️ SUCCESS: Get tiktok product" .
                "\n            └─── SKU: " .
                $product["seller_sku"] .
                "\n                 " .
                $product["product_name"] .
                "\n                 " .
                $product["variationAttributes"]
        );

        return $product;
    } else {
        // If there is no variation have exact sku as input
        writeLog("❌ FAIL: Get tiktok product | SKU not exist" . "\n            └─── SKU: " . $sku);
        throw new Exception("SKU not exist on Tiktok");
    }
}

/**
 * Update tiktok quantity
 **/
function updateTiktokProductQuantity($sku, $newQuantity, $product = null)
{
    if ($newQuantity < 0 || $newQuantity === null) {
        writeLog(
            "❌ FAIL: Update tiktok product quantity | Invalid quantity input" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 └─── New quantity: " .
                $newQuantity
        );
        throw new Exception("❌ FAIL: Update tiktok product quantity | Invalid quantity input");
    }

    if ($product !== null && $product["seller_sku"] != $sku) {
        writeLog(
            "❌ FAIL: Update tiktok product quantity | Inputs conplict" .
                "\n            └─── Input raw SKU: " .
                $sku .
                "\n                 Input product's sku: " .
                $product["seller_sku"]
        );
        throw new Exception("❌ FAIL: Update tiktok product quantity | Inputs conplict");
    }

    // Can skip a query if we already have the product data
    if ($product === null) {
        $product = getTiktokProductBySku($sku);
    }

    $theDifferent = $newQuantity - $product["stock_infos"][0]["available_stock"];

    if ($theDifferent == 0) {
        writeLog(
            "⚠ CANCEL: Update tiktok product quantity | Notthing changed" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["product_name"] .
                "\n                 " .
                $product["variationAttributes"] .
                "\n                 └─── Current quantity: " .
                $newQuantity
        );
        throw new Exception("⚠ CANCEL: Update tiktok product quantity | Notthing changed");
    }

    $request = new TiktokRequest("/api/products/stocks", "PUT");

    // Params:
    //     "product_id":1729600076393777642,
    //     "skus":[
    //         {
    //             "id":1729600076394039786,
    //             "stock_infos":[
    //                 {
    //                     "available_stock":1
    //                 },
    //             ],
    //         },
    //     ]

    // param 1
    $request->addApiParam("product_id", $product["product_id"]);

    $warehouseInfo = new stdClass();
    $warehouseInfo->available_stock = $newQuantity;

    $stockInfos = [$warehouseInfo];

    $variation = new stdClass();
    $variation->stock_infos = $stockInfos;
    $variation->id = $product["id"];

    // param 2
    $request->addApiParam("skus", [$variation]);

    $responseJsonString = $GLOBALS["tiktokClient"]->execute($request, $GLOBALS["tiktokAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isTiktokCallSuccess($responseArray)) {
        writeLog(
            "✔️ SUCCESS: Update tiktok product quantity" .
                "\n            └─── SKU: " .
                $sku .
                "\n                 " .
                $product["product_name"] .
                "\n                 " .
                $product["variationAttributes"] .
                "\n                 └─── Change: " .
                ($theDifferent > 0 ? "+" . $theDifferent : $theDifferent) .
                " | New quantity: " .
                $newQuantity
        );
        return true;
    } else {
        writeLog(
            "❌ FAIL: Update tiktok product quantity | " .
                $responseArray["message"] .
                "\n            └─── SKU: " .
                $sku
        );
        throw new Exception("❌ FAIL: Update tiktok product quantity | " . $responseArray["message"]);
    }
}

function getTiktokOrderItems($orderId, $triesCount = 0)
{
    $request = new TiktokRequest("/api/orders/detail/query", "POST");
    $request->addApiParam("order_id_list", [$orderId]);

    $responseJsonString = $GLOBALS["tiktokClient"]->execute($request, $GLOBALS["tiktokAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isTiktokCallSuccess($responseArray)) {
        $orderDetail = $responseArray["data"]["order_list"][0];

        writeLog("✔️ SUCCESS: Get list items of tiktok order" . "\n            └─── Order ID: " . $orderId);

        return $orderDetail["item_list"];
    } else {
        writeLog(
            "❌ FAIL" .
                ($triesCount = 0 ? "" : " (" . $triesCount . ")") .
                ": Get list items of tiktok order | " .
                $responseArray["message"] .
                "\n            └─── Order ID: " .
                $orderId
        );

        if ($triesCount++ < TIKTOK_ALLOWED_TRIES) {
            writeLog(". . .trying again. . .");
            return getTiktokOrderItems($orderId, $triesCount);
        } else {
            // When function've used all allowed tries but still fail
            throw new Exception($responseArray["message"]);
        }
    }
}

function copyProductFromLaz($sku)
{
    $lazProduct = getLazFatherProductBySku($sku);

    try {
        $tiktokProduct = getTiktokFatherProductBySku($sku);

        // If sku existed, then sync all variation's quatity

        writeLog("➤ SYNC TIKTOK PRODUCT FROM LAZ");

        // Loop all variation of lazProduct
        foreach ($lazProduct["skus"] as $lazVariation) {
            $sku = $lazVariation["SellerSku"];

            // Loop all warehouse
            foreach ($lazVariation["multiWarehouseInventories"] as $warehouse) {
                // Sync laz HN warehouse to tiktok
                if ($warehouse["warehouseCode"] == LAZ_WAREHOUSE_CODE["HN"]) {
                    // Cet tiktok variation from father Product to save a query
                    $tiktokChildProduct = getProductFromTiktokFatherProduct($sku, $tiktokProduct);

                    // Update tiktok variation's quantity
                    try {
                        updateTiktokProductQuantity($sku, $warehouse["quantity"], $tiktokChildProduct);
                    } catch (Exception $e) {
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Create tiktok product
        writeLog("➤ CREATE TIKTOK PRODUCT FROM LAZ PRODUCT");

        $request = new TiktokRequest("/api/products", "POST");

        $request->addApiParam("product_name", $lazProduct["attributes"]["name"]);
        $request->addApiParam("package_weight", $lazProduct["skus"][0]["package_weight"]);
        $request->addApiParam("is_cod_open", true);

        // Remove imgs from description
        $regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@";
        $description = preg_replace($regex, " ", $lazProduct["attributes"]["description"]);
        // Remove li
        $liTags = ["<li>", "</li>"];
        $description = str_replace($liTags, "", $description);
        $request->addApiParam("description", $description);

        // Set product images
        $images = [];
        foreach ($lazProduct["images"] as $imageUrl) {
            try {
                $imageDetail = uploadImage($imageUrl, 1);

                $imageObj = new stdClass();
                $imageObj->id = $imageDetail["img_id"];
                array_push($images, $imageObj);
            } catch (Exception $e) {
                writeLog("Skip~");
            }
        }
        $request->addApiParam("images", $images);

        // Default: Giá phơi đồ
        $request->addApiParam("category_id", 600758);

        // Set dummy sku
        $dummySku = new stdClass();
        $dummySku->original_price = $lazProduct["skus"][0]["price"];

        $stockInfo = new stdClass();
        $stockInfo->warehouse_id = 7212554293852194566;
        $stockInfo->available_stock = 0;
        $dummySku->stock_infos = [$stockInfo];

        $salesAttribute = new stdClass();
        $salesAttribute->attribute_id = 100000;
        $salesAttribute->attribute_name = "Màu sắc";
        $salesAttribute->custom_value = "Color";
        $dummySku->sales_attributes = [$salesAttribute];

        $request->addApiParam("skus", [$dummySku]);

        // Execute
        $responseJsonString = $GLOBALS["tiktokClient"]->execute($request, $GLOBALS["tiktokAccessToken"]);
        $responseArray = json_decode($responseJsonString, true);

        if (isTiktokCallSuccess($responseArray)) {
            $productInfo = $responseArray["data"];

            writeLog(
                "✔️ SUCCESS: Create tiktok product" .
                    "\n            └─── ID: " .
                    $productInfo["product_id"] .
                    "\n                 " .
                    $lazProduct["attributes"]["name"]
            );

            return true;
        } else {
            writeLog(
                "❌ FAIL: Create tiktok product | " .
                    $responseArray["code"] .
                    " " .
                    $responseArray["message"] .
                    "\n            └─── " .
                    $lazProduct["attributes"]["name"]
            );
            throw new Exception($responseArray["message"]);
        }
    }
}

// 1:"PRODUCT_IMAGE" The ratio of horizontal and vertical is recommended to be 1:1
// 2:"DESCRIPTION_IMAGE"
// 3:"ATTRIBUTE_IMAGE " The ratio of horizontal and vertical is recommended to be 1:1
// 4:"CERTIFICATION_IMAGE"
// 5:"SIZE_CHART_IMAGE"
function uploadImage($url, $imgScene)
{
    $imageData = base64_encode(file_get_contents($url));

    $request = new TiktokRequest("/api/products/upload_imgs", "POST");

    $request->addApiParam("img_data", $imageData);
    $request->addApiParam("img_scene", $imgScene);

    $responseJsonString = $GLOBALS["tiktokClient"]->execute($request, $GLOBALS["tiktokAccessToken"]);
    $responseArray = json_decode($responseJsonString, true);

    if (isTiktokCallSuccess($responseArray)) {
        $imageDetail = $responseArray["data"];

        writeLog(
            "✔️ SUCCESS: Upload image to tiktok" .
                "\n            └─── ID: " .
                $imageDetail["img_id"] .
                "\n                 " .
                $imageDetail["img_url"]
        );

        return $imageDetail;
    } else {
        writeLog(
            "❌ FAIL: Upload image to tiktok | " .
                $responseArray["message"] .
                "\n            └─── Url: " .
                $url
        );
        throw new Exception($responseArray["message"]);
    }
}

function isTiktokCallSuccess($responseArray)
{
    if ($responseArray["code"] !== null && $responseArray["code"] != "0") {
        if ($responseArray["code"] == "IllegalAccessToken") {
            writeLog("⚠ Token expired");
            refreshTiktokTokens();
        }

        return false;
    } else {
        return true;
    }
}
