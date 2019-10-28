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
use \CAT\Backend as Backend;
use \CAT\Helper\Validate as Validate;

if (!class_exists('\CAT\Backend\Menus')) {
    class Menus extends Base
    {
        protected static $loglevel       = \Monolog\Logger::EMERGENCY;

        /**
         *
         * @access public
         * @return
         **/
        public static function add()
        {
            echo "Not implemented yet<br />";
        }   // end function add()

        /**
         *
         * @access public
         * @return
         **/
        public static function delete()
        {
            echo "Not implemented yet<br />";
        }   // end function delete()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            if(!self::user()->hasPerm('menus_edit')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            $menuID  = self::getMenuID();

            if(!self::asJSON())
            {
                echo "not implemented yet";
            }

        }   // end function edit()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if (!self::user()->hasPerm('menutypes_list')) {
                self::printError('You are not allowed for the requested action!');
            }

            $menus = \CAT\Helper\Menu::get(1);

            if(!self::asJSON())
            {
                Backend::show(
                    'backend_menus',
                    array(
                        'menus' => $menus
                    )
                );
            }
        }   // end function index()

        protected static function getMenuID()
        {
            $menuID  = Validate::get('menu_id', 'numeric');

            if (!$menuID) {
                $menuID = self::router()->getParam(-1);
            }

            if (!$menuID) {
                $menuID = self::router()->getRoutePart(-1);
            }

            if (!$menuID || !is_numeric($menuID)) {
                $menuID = null;
            }

            return intval($menuID);
        }   // end function getMenuID()
    }
}
