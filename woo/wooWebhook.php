<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/include/utilities.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/laz/lazApi.php';

/* Only allow POST method */
if (strcmp("POST", $_SERVER['REQUEST_METHOD']) && realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME'])) {
    /*
    Up to you which header to send, some prefer 404 even if
    the files does exist for security
     */
    header('HTTP/1.0 403 Forbidden', true, 403);

    /* choose the appropriate page to redirect users */
    die(header('location: /error.php'));
}

//print_r($_POST);
//file_put_contents('./log_'.date("j.n.Y").'.log', print_r($_POST, true), FILE_APPEND);

$headersArray     = apache_request_headers();
$webhookTopic     = $headersArray['X-WC-Webhook-Topic'];
$webhookSignature = $headersArray['X-WC-Webhook-Signature'];

$jsonStringRawBody = file_get_contents("php://input");
file_put_contents('./logs/rawPost.txt', $jsonStringRawBody);

$postDataArray = json_decode(jsonStringRawBody, true);
$webhookType   = $postDataArray[type];

file_put_contents('./logs/postData.txt', "---------------------------------------------\n", FILE_APPEND);
file_put_contents('./logs/postData.txt', "HEADERS:\n", FILE_APPEND);
foreach ($headersArray as $header => $value) {
    file_put_contents('./logs/postData.txt', "$header: $value\n", FILE_APPEND);
}

file_put_contents('./logs/postData.txt', "\nPOST DATA:\n", FILE_APPEND);
foreach ($postDataArray as $key => $value) {
    file_put_contents('./logs/postData.txt', "$key: $value\n", FILE_APPEND);
}
