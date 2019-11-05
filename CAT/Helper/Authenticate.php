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
use \CAT\Registry as Registry;

if (!class_exists('\CAT\Helper\Authenticate', false))
{
    class Authenticate extends Base
    {
        // log level
        #protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        protected static $loglevel  = \Monolog\Logger::DEBUG;
        #
        // singleton
        private static $instance      = null;
        /**
         * last error
         **/
        private static $lasterror     = null;

        /**
         * Compare user's password with given password
         *
         * @access public
         * @param int    $uid
         * @param string $passwd
         * @param string $tfaToken
         * @return bool
         */
        public static function authenticate(int $uid,string $passwd,string $tfaToken=null)
        {
            self::log()->addDebug(sprintf('authenticate() - Trying to verify password for UserID [%s]', $uid));

            if (!$uid||!$passwd) {
                self::setError('An empty value was sent for authentication!');
                return false;
            }

            $storedHash = self::getPasswd($uid);

            if (password_verify($passwd, $storedHash)) { // user found and password ok
                self::log()->addDebug('authentication succeeded');
                return true;
            }

            self::setError(sprintf('Login attempt failed for user with ID [%s]',$uid));

            return false;
        }   // end function authenticate()

        /**
         * save error message for later use
         * @access protected
         * @param string $msg
         * @param string $logmsg
         * @return void
         **/
        protected static function setError(string $msg, string $logmsg='')
        {
            self::log()->addDebug($logmsg?$logmsg:$msg);
            self::$lasterror = $msg;
        }   // end function setError()

        /**
         * get hash
         *
         * @access private
         * @param string $passwd
         * @return string
        **/
        private static function getHash($passwd)
        {
            $options = [
                'cost' => 11,
            ];
            return password_hash($passwd,PASSWORD_BCRYPT,$options);
        }   // end function getHash()

        /**
         * Get hashed password from database
         *
         * @access private
         * @param int $uid
         * @return string
         **/
        private static function getPasswd($uid=null)
        {
            self::log()->addDebug(sprintf('fetching password for UID [%s]',$uid));
            $storedHash = self::db()->query(
                'SELECT `password` FROM `:prefix:rbac_users` WHERE `user_id`=:uid',
                array( 'uid' => $uid )
            )->fetchColumn();

            if (self::db()->isError()) {
                return false;
            } else {
                return $storedHash;
            }
        }   // end function getPasswd()
    }
}
