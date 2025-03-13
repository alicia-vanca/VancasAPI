<?php
class TiktokClient
{
    public $appkey;

    public $secretKey;

    public $gatewayUrl;

    public $connectTimeout;

    public $readTimeout;

    protected $signMethod = "sha256";

    protected $sdkVersion = "tiktok-sdk-php-20230320";

    public $logLevel;

    public function getAppkey()
    {
        return $this->appkey;
    }

    public function __construct($url = "", $appkey = "", $secretKey = "")
    {
        $length = strlen($url);
        if ($length == 0) {
            throw new Exception("url is empty", 0);
        }
        $this->gatewayUrl = $url;
        $this->appkey     = $appkey;
        $this->secretKey  = $secretKey;
        $this->logLevel   = Constants::$log_level_error;
    }

    protected function generateSign($apiName, $params)
    {
        ksort($params);

        $stringToBeSigned = '';
        $stringToBeSigned .= $apiName;
        foreach ($params as $k => $v) {
            $stringToBeSigned .= "$k$v";
        }
        unset($k, $v);

        return strtoupper($this->hmac_sha256($stringToBeSigned, $this->secretKey));
    }

    public function hmac_sha256($data, $key)
    {
        return hash_hmac('sha256', $data, $key);
    }

    public function curl_get($url, $apiFields = null, $headerFields = null)
    {
        $ch = curl_init();

        // foreach ($apiFields as $key => $value) {
        //     $url .= "&" . "$key=$value";
        // }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        if ($headerFields) {
            $headers = array();
            foreach ($headerFields as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            unset($headers);
        }

        if ($this->readTimeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->readTimeout);
        }

        if ($this->connectTimeout) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        curl_setopt($ch, CURLOPT_USERAGENT, $this->sdkVersion);

        //https ignore ssl check ?
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $output = curl_exec($ch);

        $errno = curl_errno($ch);

        if ($errno) {
            curl_close($ch);
            throw new Exception($errno, 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (200 !== $httpStatusCode) {
                throw new Exception($reponse, $httpStatusCode);
            }
        }

        return $output;
    }

    public function curl_post($url, $postFields = null, $headerFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->readTimeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->readTimeout);
        }

        if ($this->connectTimeout) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        if ($headerFields) {
            $headers = array();
            foreach ($headerFields as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            unset($headers);
        }

        curl_setopt($ch, CURLOPT_USERAGENT, $this->sdkVersion);

        //https ignore ssl check ?
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));

        $response = curl_exec($ch);
        unset($data);

        $errno = curl_errno($ch);
        if ($errno) {
            curl_close($ch);
            throw new Exception($errno, 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (200 !== $httpStatusCode) {
                throw new Exception($response, $httpStatusCode);
            }
        }

        return $response;
    }

    public function curl_put($url, $postFields = null, $headerFields = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($this->readTimeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->readTimeout);
        }

        if ($this->connectTimeout) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        }

        if ($headerFields) {
            $headers = array();
            foreach ($headerFields as $key => $value) {
                $headers[] = "$key: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            unset($headers);
        }

        curl_setopt($ch, CURLOPT_USERAGENT, $this->sdkVersion);

        //https ignore ssl check ?
        if (strlen($url) > 5 && strtolower(substr($url, 0, 5)) == "https") {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postFields));

        $response = curl_exec($ch);
        unset($data);

        $errno = curl_errno($ch);
        if ($errno) {
            curl_close($ch);
            throw new Exception($errno, 0);
        } else {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (200 !== $httpStatusCode) {
                throw new Exception($response, $httpStatusCode);
            }
        }

        return $response;
    }

    public function execute(TiktokRequest $request, $accessToken = null)
    {
        $sysParams["app_key"]   = $this->appkey;
        $sysParams["timestamp"] = time();

        $apiParams = $request->udfParams;

        if ($request->httpMethod == 'GET') {
            $sysParams = array_merge($sysParams, $apiParams);
        }

        // Generate signature
        $sysParams["sign"] = $this->generateSHA256($request->apiName, $sysParams, $this->secretKey);

        // Exclude access_token from signature-generating
        if (null !== $accessToken) {
            $sysParams["access_token"] = $accessToken;
        }

        // Only need app_secret when request/refresh tokens
        if ($request->apiName == '/api/v2/token/get' || $request->apiName == '/api/v2/token/refresh') {
            $sysParams["app_secret"] = $this->secretKey;
        }

        $this->logApiError($sysParams["sign"], '', '');

        $requestUrl = $this->gatewayUrl;
        if ($this->endWith($requestUrl, "/")) {
            $requestUrl = substr($requestUrl, 0, -1);
        }

        $requestUrl .= $request->apiName . '?';

        if ($this->logLevel == Constants::$log_level_debug) {
            $sysParams["debug"] = 'true';
        }

        foreach ($sysParams as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode($sysParamValue) . "&";
        }

        $requestUrl = substr($requestUrl, 0, -1);

        $resp = '';

        try
        {
            if ($request->httpMethod == 'POST') {
                $resp = $this->curl_post($requestUrl, $apiParams, $request->headerParams);
            } else if ($request->httpMethod == 'GET') {
                $resp = $this->curl_get($requestUrl, $apiParams, $request->headerParams);
            } else if ($request->httpMethod == 'PUT') {
                $resp = $this->curl_put($requestUrl, $apiParams, $request->headerParams);
            }
        } catch (Exception $e) {
            $this->logApiError($requestUrl, "HTTP_ERROR_" . $e->getCode(), $e->getMessage());
            throw $e;
        }

        unset($apiParams);

        $respObject = json_decode($resp);
        if (isset($respObject->code) && $respObject->code != "0") {
            $this->logApiError($requestUrl, $respObject->code, $respObject->message);
        } else {
            if ($this->logLevel == Constants::$log_level_debug || $this->logLevel == Constants::$log_level_info) {
                $this->logApiError($requestUrl, '', '');
            }
        }
        return $resp;
    }

    protected function logApiError($requestUrl, $errorCode, $responseTxt)
    {
        $localIp                   = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : "CLI";
        $logger                    = new TiktokLogger;
        $logger->conf["log_file"]  = rtrim(TIKTOK_SDK_WORK_DIR, '\\/') . '/' . "logs/tiktokSdk." . date("Y-m-d") . ".log";
        $logger->conf["separator"] = " ^_^ ";
        $logData                   = array(
            date("Y-m-d H:i:s"),
            $this->appkey,
            $localIp,
            PHP_OS,
            $this->sdkVersion,
            $requestUrl,
            $errorCode,
            str_replace("\n", "", $responseTxt),
        );
        $logger->log($logData);
    }

    public function msectime()
    {
        list($msec, $sec) = explode(' ', microtime());
        return $sec . '000';
    }

    public function endWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return false;
        }
        return (substr($haystack, -$length) === $needle);
    }

/**
 ** path: API path, for example /api/orders
 ** queries: Extract all query param EXCEPT 'sign','access_token',query param,not body
 ** secret: App secter
 **/
    public function generateSHA256($path, $queries, $secret)
    {

        //Reorder the params based on alphabetical order.
        ksort($queries);
        // keys:     = make([]string, len(queries))
        // idx:      = 0
        // for k, _: = rangequeries{
        //     keys[idx] = k
        //     idx++
        // }
        // sort . Slice(keys, func(i, jint)bool {
        //     return keys[i] < keys[j]
        // })

        //Concat all the param in the format of {key}{value} and append the request path to the beginning
        $input = '';
        foreach ($queries as $key => $val) {
            $input .= $key . $val;
        }
        $input = $path . $input;

        //Wrap string generated in up with app_secret.
        $input = $secret . $input . $secret;

        //Encode the digest byte stream in hexadecimal and use sha256 to generate sign with salt(secret)
        return hash_hmac("SHA256", $input, $secret);
    }
}
