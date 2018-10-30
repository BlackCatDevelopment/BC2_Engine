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
use \CAT\Helper\Socialmedia as Helper;
use \CAT\Helper\Validate as Validate;

if(!class_exists('\CAT\Backend\Socialmedia'))
{
    class Socialmedia extends Base
    {
        protected static $loglevel       = \Monolog\Logger::EMERGENCY;

        /**
         *
         * @access public
         * @return
         **/
        public static function add()
        {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// Merker:
// Derzeit sind die Links global, das heisst nur der globale Admin (nicht der
// Site Admin) kann sie verwalten.
// Die URLs sind aber pro Site aenderbar bzw. deaktivierbar.
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

            // check permissions
            if(!self::user()->hasPerm('socialmedia_add'))
                self::printFatalError('You are not allowed for the requested action!');

            // check if service already exists
            $new = \CAT\Helper\Validate::get('socialmedia_name', 'string', true);
            if(Helper::exists($new)) {
                self::printFatalError('A service with the given name already exists.');
            }

            // save
            self::db()->query(
                'INSERT INTO `:prefix:socialmedia` ( `name` ) VALUES ( ? )',
                array($new)
            );

            self::router()->reroute(CAT_BACKEND_PATH.'/socialmedia');

        }   // end function add()

        /**
         *
         * @access public
         * @return
         **/
        public static function delete()
        {
            // check permissions
            if(!self::user()->hasPerm('socialmedia_delete'))
                self::printFatalError('You are not allowed for the requested action!');

            $id = self::getServiceID();

            if($id) {
                self::db()->query(
                    'DELETE FROM  `:prefix:socialmedia` WHERE `id`=:id',
                    array('id'=>$id)
                );
            }

            self::router()->reroute(CAT_BACKEND_PATH.'/socialmedia');

        }   // end function delete()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            // check permissions
            if(!self::user()->hasPerm('socialmedia_edit'))
                self::printFatalError('You are not allowed for the requested action!');

            // field name
            $field = \CAT\Helper\Validate::get('name','string');
            // new value
            $value = \CAT\Helper\Validate::get('value','string');
            // id
            $id    = \CAT\Helper\Validate::get('pk','numeric');

            if($field && $value && $id) {
                $table = 'socialmedia';
                if($field=='account') {
                    $table .= '_site';
                }
                self::db()->query(
                    'UPDATE `:prefix:'.$table.'` SET `:field:`=:value WHERE `id`=:id',
                    array('field'=>$field,'value'=>$value,'id'=>$id)
                );
            }
        }   // end function edit()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if(!self::user()->hasPerm('socialmedia_list'))
                self::printError('You are not allowed for the requested action!');

            // get available services
            $services = Helper::getServices("1");

            if(!self::asJSON())
            {
                \CAT\Backend::printHeader();
                self::tpl()->output(
                    'backend_settings_socialmedia',
                    array(
                        'services' => $services
                    )
                );
                \CAT\Backend::printFooter();
            }
        }   // end function index()

        protected static function getServiceID()
        {
            $serviceID  = Validate::get('socialmedia_id','numeric');

            if(!$serviceID)
                $serviceID = self::router()->getParam(-1);

            if(!$serviceID)
                $serviceID = self::router()->getRoutePart(-1);

            if(!$serviceID || !is_numeric($serviceID) || !Helper::exists($serviceID))
                $serviceID = NULL;

            return intval($serviceID);
        }   // end function getServiceID()

    }
}