<?php

class TiktokSdkAutoloader{
  
  /**
     * Autoloade Class in SDK.
     * PS: only load SDK class
     * @param string $class class name
     * @return void
     */
    public static function autoload($class) {
        $name = $class;
        if(false !== strpos($name,'\\')){
          $name = strstr($class, '\\', true);
        }
        
        $filename = TIKTOK_AUTOLOADER_PATH."/tiktok/".$name.".php";
        if(is_file($filename)) {
            include $filename;
            return;
        }
    }
    
}

spl_autoload_register('TiktokSdkAutoloader::autoload');
?>