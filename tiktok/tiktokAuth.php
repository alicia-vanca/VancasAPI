<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/tiktok/tiktokConstants.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/dbConnect.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/tiktokSdk/TiktokSdk.php";

if (isset($_GET['code'])) {

    markTime();
    writeLog("➤➤ REQUEST TIKTOK TOKENS");

    try {
        
        $tiktokAuthClient = new TiktokClient(TIKTOK_AUTH_URL, TIKTOK_APP_KEY, TIKTOK_APP_SECRET);

        $request = new TiktokRequest('/api_path/v2/token/get', 'GET');
        $request->addApiParam('auth_code', $_GET['code']);
        $request->addApiParam('grant_type', 'authorized_code');

        $executeResult = $tiktokAuthClient->execute($request);

        $authDataArray = json_decode($executeResult, true);
        if ($authDataArray['code'] !== null && $authDataArray['code'] != 0) {
            throw new Exception($authDataArray['message']);
        }

        $tiktokAccessToken                = $authDataArray['data']['access_token'];
        $tiktokAccessTokenExpireIn        = $authDataArray['data']['access_token_expire_in'];
        $tiktokRefreshToken               = $authDataArray['data']['refresh_token'];
        $tiktokRefreshTokenExpireIn       = $authDataArray['data']['refresh_token_expire_in'];
        $tiktokAccessTokenExpirationAlert = false;
        $tiktokRefreshTokenExpired        = false;

        // writeLog($executeResult);

        saveTiktokTokensIntoDb($tiktokAccessToken, $tiktokAccessTokenExpireIn, $tiktokRefreshToken, $tiktokRefreshTokenExpireIn, false, false);

        writeLog("✔️ SUCCESS: Request tiktok tokens");

        $redirectUrl = 'https://callback_URL_here/vancasapi.php';
        die(header('location: ' . $redirectUrl));

    } catch (Exception $e) {

        writeLog("❌ FAIL: Request tiktok tokens | " . $e->getMessage());

    }

} else {

    $redirectUrl = 'https://services.tiktokshop.com/open/authorize?service_id=put_SHOP_ID_here';
    die(header('location: ' . $redirectUrl));

}
