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

namespace CAT\Helper;

use \CAT\Base As Base;
use \CAT\Backend as Backend;
use \CAT\Registry as Registry;
use \CAT\Helper\Addons as Addons;
use \CAT\Helper\Page as HPage;
use \CAT\Helper\Directory as Directory;
use \CAT\Helper\Template\DriverDecorator as DriverDecorator;

if (!class_exists('\CAT\Helper\Template'))
{
    class Template extends Base
    {

        protected static $loglevel       = \Monolog\Logger::EMERGENCY;
        private   static $_drivers       = array();
        private   static $_driver        = NULL;
        protected static $template_menus = array();

        public function __construct($compileDir=null, $cacheDir=null)
        {
            parent::__construct($compileDir, $cacheDir);

            // get current working directory
            $callstack = debug_backtrace();
            $this->workdir
                = ( isset( $callstack[0] ) && isset( $callstack[0]['file'] ) )
                ? realpath( dirname( $callstack[0]['file'] ) )
                : realpath( dirname(__FILE__) );

            if(file_exists($this->workdir.'/templates'))
            {
                $this->setPath($this->workdir.'/templates');
            }
        }   // end function __construct()

        /**
         *
         * @access public
         * @return
         **/
        public static function getBlocks($template=null)
        {
            if(!$template) {
                $template = Registry::get('DEFAULT_TEMPLATE');
            }

            $blocks    = array();
            $classname = '\CAT\Addon\Template\\'.$template;
            $filename  = \CAT\Helper\Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.$template.'/inc/class.'.$template.'.php');

            if(file_exists($filename)) {
                include_once $filename;
                $blocks = $classname::getBlocks();
            }

            return $blocks;
        }   // end function getBlocks()

        /**
         *
         *
         *
         *
         **/
        public static function getInstance($driver)
        {
            if(!(strcasecmp(substr($driver, strlen($driver) - strlen('driver')),'driver')===0))
                $driver .= 'Driver';

            if(!file_exists(dirname(__FILE__).'/Template/'.$driver.'.php'))
            {
                Base::printFatalError('No such template driver: ['.$driver.']');
            }
            self::$_driver = $driver;
            if(!isset(self::$_drivers[$driver]) || !is_object(self::$_drivers[$driver]))
            {
                require_once dirname(__FILE__).'/Template/DriverDecorator.php';
                require_once dirname(__FILE__).'/Template/'.$driver.'.php';
                $driver = '\CAT\Helper\Template\\'.$driver;
                self::$_drivers[$driver] = new DriverDecorator(new $driver());
                foreach(array_values(array('CAT_URL','CAT_ADMIN_URL','CAT_ENGINE_PATH')) as $item)
                {
                    if(defined($item))
                    {
                        self::$_drivers[$driver]->setGlobals($item,constant($item));
                    }
                }
                $defs = get_defined_constants(true);
                foreach($defs['user'] as $const => $value ) {
                    if(preg_match('~^DEFAULT_~',$const)) { // DEFAULT_CHARSET etc.
                        self::$_drivers[$driver]->setGlobals($const,$value);
                        continue;
                    }
                    if(preg_match('~^WEBSITE_~',$const)) { // WEBSITE_HEADER etc.
                        self::$_drivers[$driver]->setGlobals($const,$value);
                        continue;
                    }
                    if(preg_match('~^SHOW_~',$const)) { // SHOW_SEARCH etc.
                        self::$_drivers[$driver]->setGlobals($const,$value);
                        continue;
                    }
                    if(preg_match('~^FRONTEND_~',$const)) { // FRONTEND_LOGIN etc.
                        self::$_drivers[$driver]->setGlobals($const,$value);
                        continue;
                    }
                    if(preg_match('~_FORMAT$~',$const)) { // DATE_FORMAT etc.
                        self::$_drivers[$driver]->setGlobals($const,$value);
                        continue;
                    }
                    if(preg_match('~^ENABLE_~',$const)) { // ENABLE_HTMLPURIFIER etc.
                        self::$_drivers[$driver]->setGlobals($const,$value);
                        continue;
                    }
                }
            }
            return self::$_drivers[$driver];
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public static function getTemplates($for='frontend')
        {
//******************************************************************************
// TODO: Rechte beruecksichtigen!
//******************************************************************************
            $templates = array();
            $addons = Addons::getAddons(
                (($for=='backend') ? 'theme' : 'template')
            );
            return $addons;
        }   // end function getTemplates()

        /**
         *
         * @access public
         * @return
         **/
        public static function getVariants($for=NULL)
        {
            $variants = array();
            $info     = array();
            $paths    = array();

            if(!$for)
                $for = Backend::isBackend()
                     ? Registry::get('DEFAULT_THEME')
                     : Registry::get('DEFAULT_TEMPLATE');

            if(is_numeric($for)) // assume page_id
                $tpl_path = CAT_ENGINE_PATH.'/templates/'.HPage::getPageTemplate($for).'/templates/';
            else
                $tpl_path = CAT_ENGINE_PATH.'/templates/'.$for.'/templates/';

            $paths = Directory::findDirectories($tpl_path,array('remove_prefix'=>true));

            if(count($paths))
                $variants = array_merge($variants,$paths);

            return $variants;
        }   // end function getVariants()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function get_template_block_name($template=NULL,$selected=1)
        {
            if(!$template) $template = Registry::get('DEFAULT_TEMPLATE');
            // include info.php for template info
			$template_location = ( $template != '' ) ?
				CAT_ENGINE_PATH.'/templates/'.$template.'/info.php' :
				CAT_ENGINE_PATH.'/templates/'.Registry::get('DEFAULT_TEMPLATE').'/info.php';
			if(file_exists($template_location))
            {
				require $template_location;
                $driver = self::getInstance(self::$_driver);
    			return (
                    isset($block[$selected]) ? $block[$selected] : $driver->lang()->translate('Main')
                );
            }
            return $driver->lang()->translate('Main');
        }   // end function get_template_block_name()

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// Die Funktion muss ueberarbeitet werden, wenn Templates keine info.php mehr
// haben.
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

    	/**
    	 * get all menus of a template
    	 *
    	 * @access public
    	 * @param  mixed $template (default: DEFAULT_TEMPLATE)
    	 * @param  int   $selected (default: 1)
    	 * @return void
    	 */
    	public static function get_template_menus($template=NULL, $selected=1)
    	{
            if(!$template) $template = Registry::get('DEFAULT_TEMPLATE');

			$tpl_info
                = ($template != '')
                ? CAT_ENGINE_PATH.'/templates/'.$template.'/info.php'
                : CAT_ENGINE_PATH.'/templates/'.Registry::get('DEFAULT_TEMPLATE').'/info.php'
                ;

			if(file_exists($tpl_info))
            {
				require $tpl_info;
    			if(!isset($menu[1]) || $menu[1] == '')
    				$menu[1]	= 'Main';

                $result = array();
    			foreach($menu as $number => $name)
    			{
    				$result[$number] = $name;
    			}
    			return $result;
    		}
    		else
            {
                return false;
            }
    	}   // end function get_template_menus()

    }   // end class Template
}
