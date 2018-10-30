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

if(!defined('CAT_ENGINE_PATH')) die;

define('CAT_ADMIN_URL',CAT_SITE_URL.'/'.CAT_BACKEND_PATH);

// Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// we require UTF-8
ini_set('default_charset','UTF-8');

//******************************************************************************
// register autoloader
//******************************************************************************
spl_autoload_register(function($class)
{
    if(!substr_compare($class, 'wblib', 0, 4)) // wblib2 components
    {
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
        if (file_exists($file))
            @require $file;
    }
    else                                       // BC components
    {
        $file = '/'.str_replace('_', '/', $class);
        $file = CAT_ENGINE_PATH.'/'.$file.'.php';
        if(class_exists('\CAT\Helper\Directory',false) && $class!='\CAT\Helper\Directory')
            $file = \CAT\Helper\Directory::sanitizePath($file);
#echo "FILE: $file<br />";
        if (file_exists($file))
            require_once $file;
    }
    // next in stack
});


//******************************************************************************
// Start a session
//******************************************************************************
if (!defined('SESSION_STARTED'))
{
    $session = new \CAT\Session();
    $session->start_session();
    Registry::register('SESSION_STARTED', true, true);
}

if (defined('ENABLED_ASP') && ENABLED_ASP && !isset($_SESSION['session_started']))
    $_SESSION['session_started'] = time();

//******************************************************************************
// Register jQuery / JavaScripts base path
//******************************************************************************
Registry::register(
    'CAT_JQUERY_PATH',
    Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/lib_javascript/'),
    true
);
Registry::register(
    'CAT_JS_PLUGINS_PATH',
    CAT_JQUERY_PATH.'/plugins/',
    true
);

//******************************************************************************
// Get website settings and register as globals
//******************************************************************************
Base::loadSettings();
if(!Registry::exists('LANGUAGE') && Registry::exists('DEFAULT_LANGUAGE'))
{
    Registry::register('LANGUAGE',Registry::get('DEFAULT_LANGUAGE'),true);
}

//******************************************************************************
// Set theme
//******************************************************************************
Registry::register('CAT_THEME_PATH'  ,CAT_ENGINE_PATH.'/templates/'.Registry::get('DEFAULT_THEME')   , true);

//******************************************************************************
// Set as constants for simpler use
//******************************************************************************
Registry::register('CAT_VERSION'     ,Registry::get('CAT_VERSION')                                   , true);

