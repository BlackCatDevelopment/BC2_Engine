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
         * JWT settings
         **/
        protected static $SECRET_KEY = '4gdr#hp2W\JNcEO$';
        protected static $ALGORITHM  = 'HS512';

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
                return self::generateToken($uid);
            }

            self::setError(sprintf('Login attempt failed for user with ID [%s]',$uid));

            return false;
        }   // end function authenticate()

        /**
         *
         * @access public
         * @return
         **/
        public static function generateToken(int $uid, string $tokenId = '')
        {
            $lifetime   = 60*60*24;          // one day
                $issuedAt   = time();
                $notBefore  = $issuedAt;
            $expire     = $issuedAt+$lifetime;
                $serverName = CAT_SITE_URL;
            $isFresh    = false;

            if(empty($tokenId)) {
                $tokenId    = base64_encode(random_bytes(32));
                $isFresh    = true;
                // save the tokenId
                self::db()->query(
                    'UPDATE `:prefix:rbac_users` SET `login_token`=? WHERE `user_id`=?',
                    array($tokenId, $uid)
                );
            }

                $data = array(
                    'iat'  => $issuedAt,         // Issued at: time when the token was generated
                    'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
                    'iss'  => $serverName,       // Issuer
                    'nbf'  => $notBefore,        // Not before
                    'exp'  => $expire,           // Expire
                    'data' => array(             // Data related to the logged user you can set your required data
		            'user_id'  => $uid,      // id from the users table,
                    'fresh'    => $isFresh,  // not used at the moment
                    'expires'  => $issuedAt+ini_get('session.gc_maxlifetime'),
                    )
                );

                $secretKey = base64_decode(self::$SECRET_KEY);

                $jwt = JWT::encode(
                    $data,            // Data to be encoded in the JWT
                    $secretKey,       // The signing key
                    self::$ALGORITHM
                );

            // set cookie
            self::log()->addDebug(sprintf('creating cookie with name [%s]',self::getCookieName()));
            $lifetime = time()+ini_get('session.gc_maxlifetime');
            setcookie(
                self::getCookieName(),
                $jwt,
                $lifetime,
                '/',
                CAT_SITE_URL,
                true,
                true
            );

                return $jwt;
        }   // end function generateToken()
        

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

            // note: there will be no $decoded data if the token is invalid
            //       or expired

            try {
                $decoded = (array) JWT::decode($token, $secretKey, array(self::$ALGORITHM));
            } catch (\InvalidArgumentException $e) {
                // 500 internal server error - my fault
                self::log()->addDebug('InvalidArgumentException: '.$e->getMessage());
                self::invalidate();
                return false;
            } catch (\Firebase\JWT\ExpiredException $e ) {
                self::log()->addDebug('ExpiredException: '.$e->getMessage());
                self::invalidate();
                return false;
            } catch (\Exception $e) {
                // 401 unauthorized - clients fault
                self::log()->addDebug('Exception: '.$e->getMessage());
                self::invalidate();
                return false;
            }

            if(isset($decoded['data']))
            {
                if(isset($decoded['data']->expires) && time()>$decoded['data']->expires) {
                    self::log()->addDebug(sprintf('the token has expired - curr [%s] > exp [%s]',time(),$decoded['data']->expires));
                    self::invalidate();
                    return false;
            }

                $uid = $decoded['data']->user_id;

                // check database for correct token
                $storedToken = self::db()->query(
                    'SELECT `login_token` FROM `:prefix:rbac_users` WHERE `user_id`=:uid',
                    array( 'uid' => $uid )
                )->fetchColumn();

                self::log()->addDebug(sprintf('stored jti [%s] decoded jti [%s]',$storedToken,$decoded['jti']));

                if($storedToken==$decoded['jti']) {
                    self::log()->addDebug(sprintf('storedToken == token, returning user_id',$uid));
                    // update
self::generateToken($uid,$storedToken);
                    return $uid;
                } else {
                    // invalidate
                    self::log()->addDebug('invalidate - storedToken != token');
                    return false;
                }
            } else {
                self::log()->addDebug('no key [data] in array [decoded]');
            }

            return false;
        }   // end function validate()

        protected static function setError($msg, $logmsg=null)
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

        /**
         *
         * @access private
         * @return
         **/
        private static function invalidate()
        {
            self::log()->addDebug('invalidate()');
            if(isset($_COOKIE[self::getCookieName()])) {
                unset($_COOKIE[self::getCookieName()]);
                setcookie(self::getCookieName(), '', time() - 3600, '/');
            }
        }   // end function invalidate()
        
    }
}
