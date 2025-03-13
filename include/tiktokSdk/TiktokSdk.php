<?php
/**
 * IOP SDK entry
 * please do not modified this file unless you know how to modify and how to recover
 * @author Vanca
 */

/**
 * log dir
 */
if (!defined("TIKTOK_SDK_WORK_DIR"))
{
	define("TIKTOK_SDK_WORK_DIR", dirname(__FILE__));
}

if (!defined("TIKTOK_AUTOLOADER_PATH"))
{
	define("TIKTOK_AUTOLOADER_PATH", dirname(__FILE__));
}

/**
* regist autoLoader
**/
require("TiktokSdkAutoloader.php");

?>