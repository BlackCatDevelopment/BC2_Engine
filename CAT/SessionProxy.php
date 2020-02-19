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

namespace CAT;

use \Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy As SessionHandlerProxy;

if (!class_exists('\CAT\SessionProxy', false))
{
    class SessionProxy extends SessionHandlerProxy
    {

        /**
         * @var list of cipher preferences, used for OpenSSL
         **/
        private static $openssl_preferred = array(
            'aes-256-ctr',
            'aes-128-gcm',
        );
        /**
         * @var hash for use with OpenSSL
         * !!!!! TODO: generate on installation !!!!!
         **/
        private static $openssl_hash = null;
        /**
         * @var bool
         */
        private $crypt   = false;
        /**
         * @var string
         */
        private $key;

        public function __construct(\SessionHandlerInterface $handler, string $key, ?bool $crypt=false)
        {
            $this->key   = $key;
            $this->crypt = $crypt;
            parent::__construct($handler);
        }

        public function read($sessionId)
        {
            $data = parent::read($sessionId);
            return $this->decrypt($data, $this->key);
        }

        public function write($sessionId, $data)
        {
            $data = $this->encrypt($data, $this->key);
            return parent::write($sessionId, $data);
        }

        /**
         *
         * @access private
         * @return
         **/
        private function decrypt(string $data, string $key)
        {
            if (!strlen($data)) {
                return '';
            }
            if($this->crypt===false) {
                return $data;
            }
            if (extension_loaded('openssl')) {
                $cipher  = self::getCipher();
                $ivlen   = openssl_cipher_iv_length($cipher);
                $data    = base64_decode($data);
                $salt    = substr($data, 0, 16);
                $ct      = substr($data, 16);
                $rounds  = 3; // depends on key length
                $data00  = $key.$salt;
                $hash    = array();
                $hash[0] = hash(self::$hash, $data00, true);
                $result  = $hash[0];
                for ($i=1;$i<$rounds;$i++) {
                    $hash[$i] = hash(self::$hash, $hash[$i - 1].$data00, true);
                    $result .= $hash[$i];
                }
                $key       = substr($result, 0, 32);
                $iv        = substr($result, 32, $ivlen);
                $decrypted = openssl_decrypt($ct, $cipher, $key, true, $iv);
                return $decrypted;
            }
            if (extension_loaded('mcrypt')) {
                $salt      = \CAT\Registry::get('session_salt');
                $key       = substr(hash(self::$hash, $salt.$key.$salt), 0, 32);
                $iv        = random_bytes(32);
                $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($data), MCRYPT_MODE_ECB, $iv);
                $decrypted = rtrim($decrypted, "\0");
                return $decrypted;
            }
            return $data;
        }   // end function decrypt()

        /**
         * encrypt session data
         * @param  mixed   $data
         * @param  string  $key
         * @return string
         **/
        private function encrypt(string $data, string $key)
        {
            if (!strlen($data)) {
                return '';
            }
            if($this->crypt===false) {
                return $data;
            }
            if (extension_loaded('openssl')) {
                $cipher = self::getCipher();                  // get cipher
                $ivlen  = openssl_cipher_iv_length($cipher);  // set length
                $salt   = openssl_random_pseudo_bytes(16);    // Set a random salt
                $salted = '';
                $dx     = '';
                // Salt the key(32) and iv(16) = 48
                while (strlen($salted) < 32+$ivlen) {
                    $dx = hash(self::$hash, $dx.$key.$salt, true);
                    $salted .= $dx;
                }
                $key       = substr($salted, 0, 32);
                $iv        = substr($salted, 32, $ivlen);
                $encrypted = openssl_encrypt($data, $cipher, $key, true, $iv);
                $encrypted = base64_encode($salt . $encrypted);
                return $encrypted;
            }
            if (extension_loaded('mcrypt')) {
                $salt      = \CAT\Registry::get('session_salt');
                $key       = substr(hash(self::$hash, $salt.$key.$salt), 0, 32);
                $iv_size   = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
                $iv        = mcrypt_create_iv($iv_size, MCRYPT_RAND);
                $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $data, MCRYPT_MODE_ECB, $iv));
                return $encrypted;
            }
            return $data;
        }   // end function encrypt()

        /**
         * get cipher for openssl extension
         **/
        private static function getCipher()
        {
            $avail = openssl_get_cipher_methods();
            foreach (array_values(self::$openssl_preferred) as $method) {
                if (in_array($method, $avail)) {
                    return $method;
                }
            }
        }   // end function getCipher()
    }
}
