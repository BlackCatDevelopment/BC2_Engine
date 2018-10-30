<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       2018 Black Cat Development
   @link            https://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

namespace CAT;
use \CAT\Base as Base;

if (!class_exists('\CAT\Registry', false))
{
    class Registry extends Base
    {
        // singleton
        private   static $instance = NULL;
        private   static $REGISTRY = array();
        private   static $GLOBALS  = array();
        protected static $loglevel = \Monolog\Logger::EMERGENCY;

        /**
         * get singleton
         *
         * @access public
         * @return object
         **/
        public static function getInstance()
        {
            if (!Registry::$instance)
                Registry::$instance = new Registry();
            return Registry::$instance;
        }   // end function getInstance()

        /**
         * check if $key is defined; same as exists() but similar to defined(CONSTANT)
         *
         * @access public
         * @param  string  $key
         * @return boolean
         **/
        public static function defined($key)
        {
            return Registry::exists($key);
        }   // end function defined()

        /**
         * dump all; this is for debugging only as it uses var_dump()
         *
         * @access public
         * @return void
         **/
        public static function dump()
        {
            var_dump(Registry::$REGISTRY);
        }   // end function dump()

        /**
         * check if a global var exists; same as defined()
         *
         * @access public
         * @param  string  $key
         * @param  boolean $empty_allowed
         * @return boolean
         *
         **/
        public static function exists($key,$empty_allowed=true)
        {
            $key = strtoupper($key);
            if(isset(Registry::$REGISTRY[$key]) || defined($key))
            {
                if(
                       ! $empty_allowed
                    && (
                            (
                              isset(Registry::$REGISTRY[$key]) && Registry::$REGISTRY[$key] == ''
                            )
                         ||
                            (
                              defined($key) && constant($key) == ''
                            )
                       )
                ) {
                    return false;
                }
                return true;
            }
            return false;
        }   // end function exists()

        /**
         * get globally stored data
         *
         * @access public
         * @param  string  $key
         * @param  string  $validate - function to check value with
         *                            i.e. 'array' => is_array()
         * @param  mixed   $default - default value to return if the key is not found
         **/
        public static function get($key, $validate=NULL, $default=NULL)
        {
            $return_value = NULL;
            $key          = strtoupper($key);

            if(isset(Registry::$REGISTRY[$key]))
            {
                if($validate)
                {
                    $return_value = Validate::check(Registry::$REGISTRY[$key],$validate);
                } else {
                    $return_value = Registry::$REGISTRY[$key];
                }
            }

            // try to get the value from the settings table
            if(!$return_value && ! \CAT\Helper\DB::connectionFailed())
            {
                $result = self::db()->query(
                    'SELECT `t1`.`name`, `t1`.`default_value`, '
                    .'`t2`.`value` AS `global`, `t3`.`value` AS `site` '
                    .'FROM `:prefix:settings` AS `t1` '
                    .'LEFT JOIN `:prefix:settings_global` AS `t2` '
                    .'ON `t1`.`name`=`t2`.`name` '
                    .'LEFT JOIN `:prefix:settings_site` as `t3` '
                    .'ON `t1`.`name`=`t3`.`name` '
                    .'WHERE `t1`.`name`=?',
                    array(strtolower($key))
                );

                $row = $result->fetch();
                if($row['name'] && strlen($row['name']))
                {
                    // value from 'site' over 'global' to 'default'
                    $value = (strlen($row['site']) ? $row['site']
                                : (strlen($row['global']) ? $row['global']
                                    : $row['default_value'] )
                             );
                    if($validate)
                        $return_value = Validate::check($value,$validate);
                    else
                        $return_value = $value;
                    if($return_value)
                        Registry::$REGISTRY[$key] = $value;
                }
            }

            // return default value (if any)
            if(!$return_value)
            {
                if($validate && $validate == 'array')
                {
                    if($default && is_array($default))
                        return $default;
                    else
                        return array();
                }
                return ( $default ? $default : NULL );
            }

            return $return_value;
        }   // end function get()

        /**
         * this acts like PHP define(), but calls self::register() to set
         * internal registry key, too
         **/
        public static function define($key, $value=NULL)
        {
            return Registry::register($key,$value,true,true);
        }   // end function define()

        /**
         * register globally stored data
         *
         * @access public
         * @param  string  $key
         * @param  mixed   $value
         * @param  boolean $as_const - use define() to set as constant; this is
         *                             for backward compatibility as WB works
         *                             with global constants very much
         *                             default: false
         * @param  boolean $is_set   - from settings table
         *                             default: false
         **/
        public static function register($key, $value=NULL, $as_const=false, $is_set=false)
        {
            if(!is_array($key))
            {
                $key = strtoupper($key);
                $key = array($key => $value);
            }
            foreach($key as $name => $value)
            {
                Registry::$REGISTRY[$name] = $value;
                if($as_const && ! defined($name)) define($name,$value);
                if($is_set) self::$GLOBALS[$name] = $value;
            }
        }   // end function register()

        /**
         * same as register(), just shorter
         **/
        public static function set($key,$value=NULL,$as_const=false)
        {
            return Registry::register($key,$value,$as_const);
        }   // end function set()

        /**
         *
         * @access public
         * @return
         **/
        public static function getSettings()
        {
            return Registry::$GLOBALS;
        }   // end function getSettings()

    }   // end class Registry
}