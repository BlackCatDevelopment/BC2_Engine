<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       Black Cat Development
   @link            https://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

namespace CAT;

use \CAT\Helper\Directory as Directory;

if (!defined('CAT_ENGINE_PATH')) {
    die;
}

define('CAT_ADMIN_URL', CAT_SITE_URL.'/'.CAT_BACKEND_PATH);

// Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// we require UTF-8
ini_set('default_charset', 'UTF-8');

//******************************************************************************
// register autoloader
//******************************************************************************
spl_autoload_register(function ($class) {
    if (!substr_compare($class, 'wblib', 0, 4)) { // wblib2 components
        $file = str_replace(
            '\\',
            '/',
            Directory::sanitizePath(
                CAT_ENGINE_PATH.'/modules/lib_wblib/'.str_replace(
                    array('\\','_'),
                    array('/','/'),
                    $class
                ).'.php'
            )
        );
        if (file_exists($file)) {
            @require $file;
        }
    } else {                                       // BC components
#echo "class: $class<br />";
        $file = CAT_ENGINE_PATH.'/'.$class.'.php';
        if (class_exists('\CAT\Helper\Directory', false) && $class!='\CAT\Helper\Directory') {
            $file = \CAT\Helper\Directory::sanitizePath($file);
        }
#echo "FILE: $file<br />";
        if (file_exists($file)) {
            require_once $file;
        } else {
#class: CAT\Addon\external_content\IManager
#FILE: P:/BlackCat2/cat_engine/CAT/Addon/external_content/IManager.php
            // it may be a module class
            if(substr_compare($class,'CAT\Addon',0,9) == 0) {
                $temp = explode('\\',$class);
                $dir  = $temp[array_key_last($temp)];
                $file = CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$dir.'/inc/class.'.pathinfo($file,PATHINFO_FILENAME).'.php';
#echo "MODULE FILE: $file<br />";
                if (class_exists('\CAT\Helper\Directory', false) && $class!='\CAT\Helper\Directory') {
                    $file = \CAT\Helper\Directory::sanitizePath($file);
                }
                if (file_exists($file)) {
#echo "require module file [$file]<br />";
                    require_once $file;
                }
            }
        }
    }
    // next in stack
});

//******************************************************************************
// Register jQuery / JavaScripts base path
//******************************************************************************
Registry::register(
    'CAT_JS_PATH',
    Directory::sanitizePath('/modules/lib_javascript/'),
    true
);
Registry::register(
    'CAT_JS_PLUGINS_PATH',
    CAT_JS_PATH.'/plugins/',
    true
);

//******************************************************************************
// Basic subfolders
//******************************************************************************
Registry::register('CAT_MODULES_FOLDER','modules',true);
Registry::register('CAT_LANGUAGES_FOLDER','languages',true);
Registry::register('CAT_TEMPLATES_FOLDER','templates',true);
Registry::register('CAT_TEMP_FOLDER',Directory::sanitizePath(CAT_ENGINE_PATH.'/temp'),true);

//******************************************************************************
// Get website settings and register as globals
//******************************************************************************
Base::loadSettings();
if (!Registry::exists('LANGUAGE') && Registry::exists('DEFAULT_LANGUAGE')) {
    Registry::register('LANGUAGE', Registry::get('DEFAULT_LANGUAGE'), true);
}

//******************************************************************************
// Set theme
//******************************************************************************
Registry::register('CAT_THEME_PATH', CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('DEFAULT_THEME'), true);

//******************************************************************************
// Set as constants for simpler use
//******************************************************************************
Registry::register('CAT_VERSION', Registry::get('CAT_VERSION'), true);
