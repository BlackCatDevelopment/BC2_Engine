<?php

/**
 *   @author          Black Cat Development
 *   @copyright       2013 - 2020 Black Cat Development
 *   @link            https://blackcat-cms.org
 *   @license         http://www.gnu.org/licenses/gpl.html
 *   @category        CAT_Core
 *   @package         CAT_Core
 **/

namespace CAT;
use \CAT\Base as Base;

if (!class_exists('\CAT\Hook'))
{
    class Hook extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;

        /**
         * @var list of known hooks
         **/
        private static $hooks       = null;
        /**
         * @var list of subscriptions
         **/
        private static $subscribers = array();

        /**
         *
         * @access public
         * @return
         **/
        public static function executeHook(string $name) : bool
        {
            self::log()->addDebug(sprintf('execute(%s)',$name));
            if(!self::hasHook($name)) {
                self::log()->addError('no such hook!');
                return false;
            }
            // check if the hook belongs to the caller
            $hook   = self::getHook($name);
            $caller = debug_backtrace();
            if(!isset($caller[1]['class'])) {
                self::log()->addError('no caller class, unable to execute hook!');
                return false;
            }
            if($caller[1]['class'] != $hook['hook_class']) {
                self::log()->addError(sprintf(
                    'the caller [%s] is not allowed to execute the hook! hook_class [%s]',
                    $caller[1]['class'],$hook['hook_class']
                ));
                return false;
            }

            $todo = self::getSubscriptions($name);
/*
Array
(
    [100] => Array
        (
            [0] => Array
                (
                    [id] => 1
                    [hook_id] => 1
                    [classname] => \CAT\Addon\toolBotTrap
                    [function] => trap
                    [priority] => 100
                    [hook_name] => router.before.dispatch
                    [hook_class] => \CAT\Router
                )

        )

)

*/



            if(is_array($todo) && count($todo)>0) {
                foreach($todo as $priority => $listeners) {
                    foreach($listeners as $listener) {
                        $class    = $listener['classname'];
                        $function = $listener['function'];
                        if (!is_callable(array($class,$function))) {
                            self::log()->addError(sprintf(
                                'unable to execute hook [%s], classname [%s], function [%s]',
                                $name, $class, $function
                            ));
                        } else {
                            call_user_func_array(array($class,$function), array());
                        }
                    }
                }
            }

            return true;
        }   // end function executeHook()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function registerHook(string $name, string $class) : bool
        {
            self::log()->addDebug(sprintf('registerHook(%s)',$name));
            if(self::hasHook($name)) {
                self::log()->addError(sprintf('Hook [%s] already exists!', $name));
                return false;
            } else {
                $stmt = self::db()->query(
                    'INSERT INTO `:prefix:hooks` ( `hook_name`, `hook_class` )'.
                    'VALUES(?,?)',
                    array($name,$class)
                );
                return (!$stmt->isError());
            }
        }   // end function registerHook()

        /**
         *
         * @access public
         * @return
         **/
        public static function subscribeHook(string $name, $listener, ?int $priority=1) : bool
        {
            self::log()->addDebug(sprintf(
                'subscribeHook(%s), priority [%s]', $name, $priority
            ));
            if(!self::hasHook($name)) {
                self::log()->addError('no such hook!');
                return false;
            }
            $hook = self::getHook($name);
            $stmt = self::db()->query(
                'INSERT INTO `:prefix:hook_subscriptions` '.
                '(`hook_id`,`classname`,`function`,`priority`) '.
                'VALUES(?,?,?,?)',
                array($hook['id'],$listener,$priority)
            );
            return true;
        }   // end function subscribeHook()

        /**
         *
         * @access public
         * @return
         **/
        public static function unsubscribeHook(string $name, string $classname) : bool
        {
            self::log()->addDebug(sprintf(
                'unsubscribeHook(%s)', $name
            ));
            if(!self::hasHook($name)) {
                self::log()->addError(sprintf('no such hook: %s',$name));
                return false;
            }
            // get hook data
            $hook = self::getHook($name);
            $todo = self::getSubscriptions($name);
            if(!is_array($todo) || count($todo)==0) {
                return false;
            }
            foreach($todo as $priority => $listeners) {
                foreach($listeners as $i => $listener) {
                    $class    = $listener[0];
                    if($class==$classname) {
                        self::db()->query(
                            'DELETE FROM `:prefix:hook_subscriptions` WHERE `hook_id`=? AND `classname`=?',
                            array($hook['id'],$classname)
                        );
                        unset($todo[$priority][$i]);
                        return true;
                    }
                }
            }
            return false; // should never be reached
        }   // end function unsubscribeHook()

        /**
         *
         * @access public
         * @return
         **/
        public static function getHook(string $name)
        {
            $stmt = self::db()->query(
                'SELECT * FROM `:prefix:hooks` WHERE `hook_name`=?',
                array($name)
            );
            $data = $stmt->fetch();
            return $data;
        }   // end function getHook()

        /**
         *
         * @access public
         * @return
         **/
        public static function getHooks()
        {
            self::readHooksFromDB();
            return self::$hooks;
        }   // end function getHooks()

        /**
         *
         * @access public
         * @return
         **/
        public static function hasHook(string $name) : bool
        {
            self::log()->addDebug(sprintf('hasHook(%s)',$name));
            self::readHooksFromDB();
            foreach(self::$hooks as $h) {
                if($h['hook_name']==$name) {
                    return true;
                }
            }
            return false;
        }   // end function hasHook()
        
        /**
         *
         * @access public
         * @return
         **/
        protected static function hasSubscription(string $name, string $classname) : bool
        {
            $subscriptions = self::readSubscriptionsFromDB();
            foreach($subscriptions as $row) {
                if($row['classname']==$classname) {
                    return true;
                }
            }
            return false;
        }   // end function hasSubscription()
        
        /**
         *
         * @access public
         * @return
         **/
        protected static function getSubscriptions(string $name) : array
        {
            if(!self::hasHook($name)) {
                return false;
            }
            self::$subscribers = self::readSubscriptionsFromDB();
            // sort by priority
            if(is_array(self::$subscribers) && isset(self::$subscribers[$name]) && is_array(self::$subscribers[$name])) {
                krsort(self::$subscribers[$name]);
                return self::$subscribers[$name];
            }
            return array();
        }   // end function getSubscriptions()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function readHooksFromDB()
        {
            if(!is_array(self::$hooks)) {
                $stmt = self::db()->query(
                    'SELECT * FROM `:prefix:hooks`'
                );
                self::$hooks = $stmt->fetchAll();
            }
            return self::$hooks;
        }   // end function readHooksFromDB()
        
        /**
         *
         * @access protected
         * @return
         **/
        protected static function readSubscriptionsFromDB() : array
        {
            $stmt = self::db()->query(
                'SELECT * FROM `:prefix:hook_subscriptions` AS `t1` '.
                'LEFT JOIN `:prefix:hooks` AS `t2` '.
                'ON `t1`.`hook_id`=`t2`.`id`'
            );
            $data = $stmt->fetchAll();
            if(is_array($data) && count($data)>0) {
                $s = array();
                foreach($data as $row) {
                    if(!isset($s[$row['hook_name']])) {
                        $s[$row['hook_name']] = array();
                    }
                    if(!isset($s[$row['hook_name']][$row['priority']])) {
                        $s[$row['hook_name']][$row['priority']] = array();
                    }
                    $s[$row['hook_name']][$row['priority']][] = $row;
                }
                return $s;
            }
            return array();
        }   // end function readSubscriptionsFromDB()

    } // class Hook
} // if class_exists()