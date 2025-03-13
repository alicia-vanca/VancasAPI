<?php
// Database
$servername = "127.0.0.1";
$username   = "________";
$password   = "________";
$database   = "________";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Connected successfully


function prepared_query($sql, $params = false, $types = "")
{
    $stmt  = $GLOBALS['conn']->prepare($sql);
    echo $GLOBALS['conn']->error;
    if ($params) {
        $types = $types ?: str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
};


function saveLazTokensIntoDb(
    $accessToken,
    $accessTokenExpiresIn,
    $refreshToken,
    $refreshTokenExpiresIn,
    $accessTokenExpirationAlert,
    $refreshTokenExpired
) {
    try {
        $sql =
            "INSERT INTO laz_tokens (id, access_token, access_token_expire_in, refresh_token, refresh_token_expire_in, access_token_expiration_alert, refresh_token_expired) VALUES(1, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE access_token = ?, access_token_expire_in = ?, refresh_token = ?, refresh_token_expire_in = ?, access_token_expiration_alert = ?, refresh_token_expired = ?";
        $stmt = prepared_query(
            $sql,
            [
                $accessToken,
                $accessTokenExpiresIn,
                $refreshToken,
                $refreshTokenExpiresIn,
                $accessTokenExpirationAlert,
                $refreshTokenExpired,
                $accessToken,
                $accessTokenExpiresIn,
                $refreshToken,
                $refreshTokenExpiresIn,
                $accessTokenExpirationAlert,
                $refreshTokenExpired,
            ],
            "sisiiisisiii"
        );

        writeLog("✔️ SUCCESS: Save Laz tokens into db");
    } catch (Exception $e) {
        writeLog("❌ FAIL: Save Laz tokens into db: " . $e->getMessage());
        throw $e;
    }
}



function saveTiktokTokensIntoDb(
    $accessToken,
    $accessTokenExpireIn,
    $refreshToken,
    $refreshTokenExpireIn,
    $accessTokenExpirationAlert,
    $refreshTokenExpired
) {
    try {
        $sql =
            "INSERT INTO tiktok_tokens (id, access_token, access_token_expire_in, refresh_token, refresh_token_expire_in, access_token_expiration_alert, refresh_token_expired) VALUES(1, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE access_token = ?, access_token_expire_in = ?, refresh_token = ?, refresh_token_expire_in = ?, access_token_expiration_alert = ?, refresh_token_expired = ?";
        $stmt = prepared_query(
            $sql,
            [
                $accessToken,
                $accessTokenExpireIn,
                $refreshToken,
                $refreshTokenExpireIn,
                $accessTokenExpirationAlert,
                $refreshTokenExpired,
                $accessToken,
                $accessTokenExpireIn,
                $refreshToken,
                $refreshTokenExpireIn,
                $accessTokenExpirationAlert,
                $refreshTokenExpired,
            ],
            "sisiiisisiii"
        );

        writeLog("✔️ SUCCESS: Save tiktok tokens into db");
    } catch (Exception $e) {
        writeLog("❌ FAIL: Save tiktok tokens into db: " . $e->getMessage());
        throw $e;
    }
}