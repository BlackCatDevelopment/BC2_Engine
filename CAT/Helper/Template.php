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

use \CAT\Base as Base;
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
        protected static $template_menus = array();
        private static $_drivers       = array();
        private static $_driver        = null;

        public function __construct($compileDir=null, $cacheDir=null)
        {
            parent::__construct($compileDir, $cacheDir);

            // get current working directory
            $callstack = debug_backtrace();
            $this->workdir
                = (isset($callstack[0]) && isset($callstack[0]['file']))
                ? realpath(dirname($callstack[0]['file']))
                : realpath(dirname(__FILE__));

            if (file_exists($this->workdir.'/templates')) {
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
            if (!$template) {
                $template = Registry::get('DEFAULT_TEMPLATE');
            }

            $blocks    = array();
            $classname = '\CAT\Addon\Template\\'.$template;
            $filename  = Directory::sanitizePath(implode('/',array(
                CAT_ENGINE_PATH,
                CAT_TEMPLATES_FOLDER,
                $template,
                'inc',
                'class.'.$template.'.php'
            )));

            if (file_exists($filename)) {
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
            if (!(strcasecmp(substr($driver, strlen($driver) - strlen('driver')), 'driver')===0)) {
                $driver .= 'Driver';
            }

            if (!file_exists(dirname(__FILE__).'/Template/'.$driver.'.php')) {
                Base::printFatalError('No such template driver: ['.$driver.']');
            }
            self::$_driver = $driver;
            if (!isset(self::$_drivers[$driver]) || !is_object(self::$_drivers[$driver])) {
                require_once dirname(__FILE__).'/Template/DriverDecorator.php';
                require_once dirname(__FILE__).'/Template/'.$driver.'.php';
                $driver = '\CAT\Helper\Template\\'.$driver;
                self::$_drivers[$driver] = new DriverDecorator(new $driver());
                foreach (array_values(array('CAT_URL','CAT_ADMIN_URL','CAT_ENGINE_PATH')) as $item) {
                    if (defined($item)) {
                        self::$_drivers[$driver]->setGlobals($item, constant($item));
                    }
                }
                $defs = get_defined_constants(true);
                foreach ($defs['user'] as $const => $value) {
                    if (preg_match('~^(DEFAULT_|WEBSITE_|SHOW_|FRONTEND_|ENABLE_)~', $const)) { // DEFAULT_CHARSET etc.
                        self::$_drivers[$driver]->setGlobals($const, $value);
                        continue;
                    }
                    if (preg_match('~_FORMAT$~', $const)) { // DATE_FORMAT etc.
                        self::$_drivers[$driver]->setGlobals($const, $value);
                        continue;
                    }
                }
            }

            return self::$_drivers[$driver];
        }   // end function getInstance()

        /**
         * get available options for a template
         *
         * @access public
         * @param  int    $pageID - optional pageID to find the right tpl
         * @return
         **/
        public static function getAvailableOptions($pageID=null)
        {
            $form = self::getOptionsForm($pageID);
            if($form) {
                $opt = array();
                $elems = $form->getElements();
                foreach($elems as $i => $e) {
                    $opt[$e->getName()] = $e->getValue();
                }
                return $opt;
            }
        }   // end function getAvailableOptions()

        /**
         *
         * @access public
         * @return
         **/
        public static function getOptions($pageID)
        {
            if($pageID) {
                $tpl = self::getPageTemplate($pageID);
            } else {
                $tpl = \CAT\Registry::get('default_template');
            }
            $tpl_id = Addons::getDetails($tpl,"addon_id");
            // get template id
            $stmt = self::db()->query(
                'SELECT * FROM `:prefix:template_options` WHERE `tpl_id`=? AND `page_id`=?',
                array($tpl_id,$pageID)
            );
            $data = $stmt->fetchAll();
            $opt  = array();
            if(is_array($data) && count($data)>0) {
                foreach($data as $i => $item) {
                    $opt[$item['opt_name']] = $item['opt_value'];
                }
            }
            return $opt;
        }   // end function getOptions()

        /**
         *
         * @access public
         * @return
         **/
        public static function getOptionsForm($pageID=null)
        {
            if($pageID) {
                $tpl = self::getPageTemplate($pageID);
                $var = self::getVariant($pageID);
            } else {
                $tpl = \CAT\Registry::get('default_template');
                $var = \CAT\Registry::get('default_template_variant');
            }
            if(file_exists(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$tpl.'/'.CAT_TEMPLATES_FOLDER.'/'.$var.'/inc.forms.php'))
            {
                $form = \wblib\wbForms\Form::loadFromFile(
                    'tploptions',
                    CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$tpl.'/'.CAT_TEMPLATES_FOLDER.'/'.$var.'/inc.forms.php'
                );
                return $form;
            }

            return null;
        }   // end function getOptionsForm()

        /**
         * returns the template for the given page
         *
         * @access public
         * @param  integer  $page_id
         * @return string
         **/
        public static function getPageTemplate(int $pageID)
        {
            $tpl = \CAT\Helper\Page::properties($pageID,'template');
            return ( $tpl != '' ) ? $tpl : \CAT\Registry::get('DEFAULT_TEMPLATE');
        }   // end function getPageTemplate()

        /**
         * returns the full path to the page template, i.e. including variant
         *
         * @access public
         * @return
         **/
        public static function getPath(int $pageID, bool $fullpath=true)
        {
            $tpl = self::getPageTemplate($pageID);
            if($fullpath) {
                $var = self::getVariant($pageID);
                return CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$tpl.'/'.CAT_TEMPLATES_FOLDER.'/'.$var;
            }
            return CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$tpl;
        }   // end function getPath()

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
         * returns the template variant for the given page
         *
         * @access public
         * @param  int      $page_id
         * @return string
         **/
        public static function getVariant(int $pageID) : string
        {
            $variant = \CAT\Helper\Page::properties($pageID,'variant');
            if(!$variant) {
                $variant = \CAT\Registry::get('default_template_variant');
                if(!$variant) {
                    $variant = 'default';
                }
            }
            return $variant;
        }   // end function getVariant()

        /**
         *
         * @access public
         * @return
         **/
        public static function getVariants($for=null)
        {
            $variants = array();
            $info     = array();
            $paths    = array();

            if (!$for) {
                $for = Backend::isBackend()
                     ? Registry::get('default_theme')
                     : Registry::get('default_template');
            }
            // dirty hack for FormBuilder call
            if($for=='frontend') {
                $for = Registry::get('default_template');
            }

            if (is_numeric($for)) { // assume page_id
                $tpl_path = implode('/',array(
                    CAT_ENGINE_PATH,
                    CAT_TEMPLATES_FOLDER,
                    HPage::getPageTemplate($for),
                    CAT_TEMPLATES_FOLDER)).'/';
            } else {
                $tpl_path =implode('/',array(
                    CAT_ENGINE_PATH,
                    CAT_TEMPLATES_FOLDER,
                    $for,
                    CAT_TEMPLATES_FOLDER)).'/';
            }
            $paths = Directory::findDirectories($tpl_path, array('remove_prefix'=>true));

            if (count($paths)) {
                $variants = array_merge($variants, $paths);
            }

            return array_combine($variants,$variants);
        }   // end function getVariants()

        /**
         *
         * @access public
         * @return
         **/
        public static function hasOptions($pageID=null)
        {
            return (
                count(self::getAvialableOptions($pageID))>0
            );
        }   // end function hasOptions()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function saveOptions(int $pageID, array $opt)
        {
            $tpl    = self::getPageTemplate($pageID);
            $tpl_id = Addons::getDetails($tpl,'addon_id');
            if(!empty($tpl_id)) {
                foreach($opt as $key => $value) {
                    $key = str_replace('template_option_','',$key);
// !!!!! TODO: template_option_options brauchen wir nicht mehr
                    if($key == 'options') {
                        continue;
                    }
                    if(empty($value)) {
                        self::db()->query(
                            'DELETE FROM `:prefix:template_options` WHERE '.
                            '`tpl_id`=? AND `page_id`=? AND `opt_name`=?',
                            array($tpl_id,$pageID,$key)
                        );
                    } else {
                        self::db()->query(
                            'REPLACE INTO `:prefix:template_options` '
                            . '(`tpl_id`, `page_id`, `opt_name`, `opt_value`) '
                            . 'VALUES ( ?, ?, ?, ? ) ',
                            array($tpl_id,$pageID,$key,$value)
                        );
                    }
                }
            }
        }   // end function saveOptions()

        /**
         *
         * @access public
         * @return
         **/
        public static function get_template_block_name($template=null, $selected=1)
        {
            if (!$template) {
                $template = Registry::get('DEFAULT_TEMPLATE');
            }
            // include info.php for template info
            $template_location = ($template != '') ?
                CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$template.'/info.php' :
                CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('DEFAULT_TEMPLATE').'/info.php';
            if (file_exists($template_location)) {
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
        public static function get_template_menus($template=null, $selected=1)
        {
            if (!$template) {
                $template = Registry::get('DEFAULT_TEMPLATE');
            }

            $tpl_info
                = ($template != '')
                ? CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$template.'/info.php'
                : CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('DEFAULT_TEMPLATE').'/info.php'
                ;

            if (file_exists($tpl_info)) {
                require $tpl_info;
                if (!isset($menu[1]) || $menu[1] == '') {
                    $menu[1]	= 'Main';
                }

                $result = array();
                foreach ($menu as $number => $name) {
                    $result[$number] = $name;
                }
                return $result;
            } else {
                return false;
            }
        }   // end function get_template_menus()
    }   // end class Template
}
