<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api_path/include/utilities.php';
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/laz/lazConstants.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/lazopSdk/LazopSdk.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/api_path/include/dbConnect.php";

if (isset($_GET['code'])) {

    markTime();
    writeLog("➤➤ REQUEST LAZ TOKENS");

    try {

        $lazAuthClient = new LazopClient(LAZ_AUTH_URL, LAZ_APP_KEY, LAZ_APP_SECRET);

        $request = new LazopRequest('/auth/token/create');
        $request->addApiParam('code', $_GET['code']);

        $responseJsonString = $lazAuthClient->execute($request);
        
        // writeLog($responseJsonString);

        $authDataArray = json_decode($responseJsonString, true);
        $lazAccessToken                = $authDataArray['access_token'];
        $lazRefreshToken               = $authDataArray['refresh_token'];
        $lazAccessTokenExpiresIn       = time() + $authDataArray['expires_in'];
        $lazRefreshTokenExpiresIn      = time() + $authDataArray['refresh_expires_in'];
        $lazAccessTokenExpirationAlert = false;
        $lazRefreshTokenExpired        = false;

        saveLazTokensIntoDb($lazAccessToken, $lazAccessTokenExpiresIn, $lazRefreshToken, $lazRefreshTokenExpiresIn, false, false);

        writeLog("✔️ SUCCESS: Request laz tokens");

        $redirectUrl = 'https://callback_URL/vancasapi.php';
        die(header('location: ' . $redirectUrl));

    } catch (Exception $e) {

        writeLog("❌ FAIL: Request laz tokens | " . $e->getMessage());

    }

} else {

    $redirectUrl = 'https://auth.lazada.com/oauth/authorize?response_type=code&force_auth=true&redirect_uri=' . LAZ_CALLBACK_URL . '&client_id=' . LAZ_APP_KEY;
    die(header('location: ' . $redirectUrl));

}
