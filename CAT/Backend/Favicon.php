<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       Black Cat Development
   @link            http://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

namespace CAT\Backend;
use \CAT\Base as Base;

if(!class_exists('\CAT\Backend\Favicon'))
{
    class Favicon extends Base
    {
        protected static $loglevel     = \Monolog\Logger::EMERGENCY;
        public    static $sizes        = array(
            'desktop' => array(
                'favicon' => array(
                    'ico' => array(16),
                    'png' => array(16,32,96),
                ),
            ),
            'android' => array(
                'android-chrome' => array(
                    'png' => array(36,48,72,96,144,192),
                ),
            ),
            'windows' => array(
                'mstile' => array(
                    'png' => array(70,144,150,310,array(310,150)),
                ),
            ),
            'apple' => array(
                'apple-touch-icon' => array(
                    'png' => array(57,60,72,76,114,120,152,180),
                ),
            ),
        );
        protected static $configfiles = array(
            'windows' => array(
                'browserconfig.xml',
            ),
            'webapp' => array(
                'manifest.json'
            ),
        );

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if(!self::user()->hasPerm('manage_favicons'))
                \CAT\Helper\JSON::printError('You are not allowed for the requested action!');

            $seen = self::findFiles();

            if(!self::asJSON())
            {
                \CAT\Backend::printHeader();
                self::tpl()->output(
                    'backend_settings_favicons',
                    array(
                        'seen' => $seen
                    )
                );
                \CAT\Backend::printFooter();
            }
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function findFiles($filter=false)
        {
            $seen = array();
            foreach(self::$sizes as $group => $prefixes) {
                foreach($prefixes as $prefix => $suffixes) {
                    foreach($suffixes as $suffix => $sizes) {
                        foreach($sizes as $size) {
                            $s  = (is_array($size) ? $size[0].'x'.$size[1] : $size.'x'.$size);
                            $filename
                                = $prefix.'-'.$s.'.'.$suffix;
                            if(!isset($seen[$group])) $seen[$group] = array();
                            if(file_exists(CAT_PATH.'/'.$filename)) {
                                $seen[$group][$filename] = $s;
                            } else {
                                if(!$filter) {
                                    $seen[$group][$filename] = false;
                                }
                            }
                        }
                    }
                }
            }
            foreach(self::$configfiles as $group => $items) {
                foreach($items as $name) {
                    if(file_exists(CAT_PATH.'/'.$name)) {
                        $seen[$group][$name] = true;
                    } else {
                        if(!$filter) {
                            $seen[$group][$name] = false;
                        }
                    }
                }
            }
            return $seen;
        }   // end function findFiles()
    }
}

/*

Windows 8.0:
<meta name="msapplication-TileColor" content="#2b5797">
<meta name="msapplication-TileImage" content="https://cdn.css-tricks.com/mstile-144x144.png">

Windows 8.1: browserconfig.xml
<?xml version="1.0" encoding="utf-8"?>
<browserconfig>
  <msapplication>
    <tile>
      <square70x70logo src="https://cdn.css-tricks.com/mstile-70x70.png"/>
      <square150x150logo src="https://cdn.css-tricks.com/mstile-150x150.png"/>
      <square310x310logo src="https://cdn.css-tricks.com/mstile-310x310.png"/>
      <wide310x150logo src="https://cdn.css-tricks.com/mstile-310x150.png"/>
      <TileColor>#2b5797</TileColor>
    </tile>
  </msapplication>
</browserconfig>


<meta name="msapplication-config" content="/IEConfig.xml" />
<link rel="apple-touch-icon" sizes="57x57" href="/apple-touch-icon-57x57.png" />
<link rel="apple-touch-icon" sizes="60x60" href="/apple-touch-icon-60x60.png" />
<link rel="apple-touch-icon" sizes="72x72" href="/apple-touch-icon-72x72.png" />
<link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon-114x114.png" />
<link rel="apple-touch-icon" sizes="76x76" href="/apple-touch-icon-76x76.png" />
<link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png" />
<link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon-180x180.png" />
<link rel="icon" type="image/png" href="/favicon-32x32.png" sizes="32x32" />
<link rel="icon" type="image/png" href="/android-chrome-192x192.png" sizes="192x192" />
<link rel="icon" type="image/png" href="/favicon-16x16.png" sizes="16x16" />
<link rel="manifest" href="/manifest.json" />
<meta name="application-name" content="Hieu Le Favicon" />
<meta name="msapplication-TileColor" content="#F0F0F0" />
<meta name="msapplication-TileImage" content="/mstile-144x144.png" />
<meta name="msapplication-square70x70logo" content="/mstile-70x70.png" />
<meta name="msapplication-square150x150logo" content="/mstile-150x150.png" />
<meta name="msapplication-wide310x150logo" content="/mstile-310x150.png" />
<meta name="msapplication-square310x310logo" content="/mstile-310x310.png" />
*/