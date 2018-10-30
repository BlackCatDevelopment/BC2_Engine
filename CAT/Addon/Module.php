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

namespace CAT\Addon;

use \CAT\Base as Base;
use \CAT\Helper\Addons as Addons;
use \CAT\Helper\Directory as Directory;

if (!class_exists('\CAT\Addon\Module', false)) {
    abstract class Module extends Base implements IAddon
    {
        protected static $type        = '';
        protected static $directory   = '';
        protected static $name        = '';
        protected static $version     = '';
        protected static $description = "";
        protected static $author      = "";
        protected static $guid        = "";
        protected static $license     = "";
        /**
         *
         */
        public function __construct()
        {
            parent::__construct();
        }
        public function __destruct()
        {
            parent::__destruct();
        }

        /**
         * gets the details of an addon
         *
         * @access public
         * @param  string  $value - required info item
         * @return string
         */
        public static function getInfo(string $value=null) : array
        {
            if ($value) {
                return static::$$value;
            }
            // get 'em all
            $info = array();
            foreach (array_values(array(
                'name', 'directory', 'version', 'author', 'license', 'description', 'guid', 'home', 'platform', 'type'
            )) as $key) {
                if (isset(static::$$key) && strlen(static::$$key)) {
                    $info[$key] = static::$$key;
                }
            }
            return $info;
        }   // end function getInfo()

        /**
         * inititialize module
         *
         * if you overload this method, remember to add
         *     parent::initialize($section)
         * as this method sets the template path and load additional language
         * files from the template
         *
         * @access public
         * @param  array   section data
         * @return void
         **/
        public static function initialize(array $section)
        {
            if (!empty($section)) {
                if (!isset($section['variant'])) {
                    $section['variant'] = 'default';
                }
                $tpl_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$section['module'].'/templates/'.$section['variant']);
                $lang_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$section['module'].'/templates/'.$section['variant'].'/languages');
                if (is_dir($tpl_path)) {
                    self::tpl()->setPath($tpl_path);
                }
                if (is_dir($lang_path)) {
                    self::addLangFile($lang_path);
                }
                $def_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$section['module'].'/templates/default');
                if (is_dir($def_path)) {
                    self::tpl()->setFallbackPath($def_path);
                }
            }
        }   // end function initialize()

        /**
         * Default install routine
         */
        public static function install() : array
        {
            $class  = get_called_class();
            $errors = array();

            // add database entry
            self::db()->query(
                'REPLACE INTO `:prefix:addons` VALUES( null, :type, :directory, :name, :time, :time, "Y","N")',
                array(
                    'type' => $class::$type,
                    'directory' => $class::$directory,
                    'name' => $class::$name,
                    'time' => time()
                )
            );
            if (self::db()->isError()) {
                $errors[] = self::db()->getError();
                return $errors;
            }
            
            $sqlfile = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.static::$directory.'/inc/install.sql');
            if (file_exists($sqlfile)) {
                $errors = self::sqlProcess();
            }

            return $errors;
        }   // end function install()

        /**
         * Default modify routine
         */
        public static function modify(array $section)
        {
        }

        /**
         * Default uninstall routine
         */
        public static function uninstall()
        {
            $errors  = array();
            $sqlfile = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.static::$directory.'/inc/uninstall.sql');
            if (file_exists($sqlfile)) {
                $errors	= self::sqlProcess();
            }
            return $errors;
        }

        /**
         *
         */
        public static function upgrade()
        {
        }
        /**
         *
         */
        public static function save(int $section_id)
        {
        }
    }
}
