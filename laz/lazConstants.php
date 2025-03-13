<?php
defined('LAZ_APP_KEY') || define('LAZ_APP_KEY', 'put_key_here');
defined('LAZ_APP_SECRET') || define('LAZ_APP_SECRET', 'put_key_here');
defined('LAZ_AUTH_URL') || define('LAZ_AUTH_URL', 'https://auth.lazada.com/rest');
defined('LAZ_API_URL') || define('LAZ_API_URL', 'https://api.lazada.vn/rest');
defined('LAZ_CALLBACK_URL') || define('LAZ_CALLBACK_URL', 'https://put_callback_URL_here/laz/authLazada.php');
const LAZ_WAREHOUSE_CODE = ["HN" => "put_key_here", "HCM" => "put_key_here"];
const LAZ_ALLOWED_TRIES = 2;
const SKU_NOT_FOUND = "E207: SKU not exist";
