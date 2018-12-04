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
use \Firebase\JWT\JWT;

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
         * password hashing options
         **/
        protected static $hashOpt     = array(
            'algo'    => PASSWORD_BCRYPT,
            'cost'    => 10
        );

        protected static $SECRET_KEY = 'Your-Secret-Key';
        protected static $ALGORITHM  = 'HS512';

        /**
         * Compare user's password with given password
         * @access public
         * @param int $uid
         * @param string $passwd
         * @param string $tfaToken
         * @return bool
         */
        public static function authenticate(int $uid,string $passwd,string $tfaToken=null)
        {
            self::log()->debug(sprintf('Trying to verify password for UserID [%s]', $uid));

            if (!$uid||!$passwd) {
                self::setError('An empty value was sent for authentication!');
                return false;
            }

            $storedHash = self::getPasswd($uid);

            if (password_verify($passwd, $storedHash)) { // user found and password ok
                $tokenId    = base64_encode(random_bytes(32));
                $issuedAt   = time();
                $notBefore  = $issuedAt;
                $expire     = time()+ini_get('session.gc_maxlifetime');
                $serverName = CAT_SITE_URL;

                /*
                 * Create the token as an array
                 */
                $data = array(
                    'iat'  => $issuedAt,         // Issued at: time when the token was generated
                    'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
                    'iss'  => $serverName,       // Issuer
                    'nbf'  => $notBefore,        // Not before
                    'exp'  => $expire,           // Expire
                    'data' => array(             // Data related to the logged user you can set your required data
    		            'user_id' => $uid, // id from the users table
                    )
                );

                $secretKey = base64_decode(self::$SECRET_KEY);

                $jwt = JWT::encode(
                    $data,            // Data to be encoded in the JWT
                    $secretKey,       // The signing key
                    self::$ALGORITHM
                );
                return $jwt;
            }
            return false;
        }   // end function authenticate()

        /**
         *
         * @access public
         * @return
         **/
        public static function validate($token)
        {
            self::log()->addDebug(sprintf('validate() - %s',$token));

            $secretKey = base64_decode(self::$SECRET_KEY);
            $decoded   = array();

            try {
                $decoded = (array) JWT::decode($token, $secretKey, array(self::$ALGORITHM));
                self::log()->addDebug(print_r($decoded,1));
            } catch (\InvalidArgumentException $e) {
                // 500 internal server error
                // your fault
                self::log()->addDebug('InvalidArgumentException: '.$e->getMessage());
            } catch (\Exception $e) {
                // 401 unauthorized
                // clients fault
                self::log()->addDebug('Exception: '.$e->getMessage());
            }

            if(isset($decoded['data'])) {
                $uid = $decoded['data']->user_id;
                // check database for correct token
                $storedToken = self::db()->query(
                    'SELECT `login_token` FROM `:prefix:rbac_users` WHERE `user_id`=:uid',
                    array( 'uid' => $uid )
                )->fetchColumn();
                if($storedToken==$token) {
                    self::log()->addDebug(sprintf('storedToken == token, returning user_id',$uid));
                    return $uid;
                } else {
                    // invalidate
                    self::log()->addDebug('invalidate');
                    self::db()->query(
                        'UPDATE `:prefix:rbac_users` SET `login_when`=?, `login_ip`=?, `login_token`=? WHERE `user_id`=?',
                        array(0, 0, null, $uid)
                    );
                    return false;
                }
            } else {
                self::log()->addDebug('no key [data] in array [decoded]');
            }

            return false;
        }   // end function validate()

        protected static function setError($msg, $logmsg=null)
        {
            self::log()->debug($logmsg?$logmsg:$msg);
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
            return password_hash(
                $passwd,
                self::$hashOpt['algo'],
                array('cost'=>self::$hashOpt['cost'])
            );
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
