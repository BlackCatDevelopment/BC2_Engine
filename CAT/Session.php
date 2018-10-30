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

if(!class_exists('\CAT\Session',false))
{
    class Session extends Base implements \SessionHandlerInterface
    {
        #protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $loglevel = \Monolog\Logger::DEBUG;

        private        $domain;
        private        $path     = '/';
        private        $gcCalled = false;
        /**
         * @var array list of statements; defined in getStatement()
         **/
        private static $stmt;
        /**
         * @var list of cipher preferences, used for OpenSSL
         **/
        private static $openssl_preferred = array(
            'aes-256-ctr',
            'aes-128-gcm',
        );
        /**
         * @var
         **/
        private static $hash = null;
        /**
         * @var
         **/
        private static $hash_algo_preferred = array(
            'sha512',
            'sha384',
            'sha256'
        );


        public function __construct() {
            // set our custom session functions.
            session_set_save_handler($this);
            // This line prevents unexpected effects when using objects as save handlers.
            register_shutdown_function('session_write_close');
        }

        /**
         * start a new session
         **/
        public function start_session()
        {
            self::log()->addDebug('start_session()');

            // Hash algorithm to use for the session.
            // (use hash_algos() to get a list of available hashes.)
            $session_hash = null;

            // Check if hash is available
            foreach(self::$hash_algo_preferred as $i => $session_hash)
            {
                if (in_array($session_hash, hash_algos())) {
                    ini_set('session.hash_function', $session_hash);
                    self::$hash = $session_hash;
                    break;
                }
            }

            // How many bits per character of the hash.
            // The possible values are '4' (0-9, a-f), '5' (0-9, a-v), and '6' (0-9, a-z, A-Z, "-", ",").
            ini_set('session.hash_bits_per_character', 5);

            // Force the session to only use cookies, not URL variables.
            ini_set('session.use_only_cookies', 1);

            // get domain
            $parse  = parse_url(CAT_SITE_URL);
            if(isset($parse['host'])) { $this->domain = $parse['host']; }
            else                      { $this->domain = CAT_SITE_URL;   }
            if(isset($parse['path'])) { $this->path   = $parse['path']; }

            // Set the parameters
            session_set_cookie_params(
                time()+ini_get('session.gc_maxlifetime'),
                $this->path, // path
                $this->domain, // domain
                (isset($_SERVER['HTTPS']) ? true : false),  // secure
                true // httponly
            );

            // generate unique session name for this site
            $name = '_cat_'.base64_encode(CAT_SITE_URL);

            // Change the session name
            session_name($name);

            // Start the session
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            // Make sure the session hasn't expired, and destroy it if it has
        	if(!self::validateSession(session_id()))
        	{
self::log()->addDebug(sprintf('destroying invalid session %s',session_id()));
                #self::stop_session();
        		$_SESSION = array();
        		session_destroy();
        		session_start();
        	}
            return true;
        }

        /**
         *
         * @access public
         * @return
         **/
        public static function stop_session()
        {
            self::log()->addDebug('stop_session()');
            // invalidate cookie
            $params = session_get_cookie_params();
            setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
            session_destroy();
            session_write_close();
        }   // end function stop_session()
        

        public function open($save_path, $session_name) { return true; }

        /**
         * @inheritdoc
         **/
        public function close()
        {
            self::log()->addDebug('close()');
            if ($this->gcCalled)
            {
                $this->gcCalled = false;
                $sql = self::getStatement('delete');
                if(false!==$sql)
                {
                    $stmt = \CAT\Base::db()->prepare($sql);
                    $stmt->bindValue(':id'  , $sessionId, \PDO::PARAM_STR);
                    $stmt->bindValue(':time', time()    , \PDO::PARAM_STR);
                    $stmt->execute();
                }
            }
            return true;
        }   // end function close()

        /**
         * @inheritdoc
         **/
        public function read($sessionId)
        {
            self::log()->addDebug(sprintf(
                'read() - reading data from session [%s]',$sessionId
            ));
            $sql = self::getStatement('read');
            if(false!==$sql)
            {
                try {
                    $stmt = self::db()->prepare($sql);
                    $stmt->bindValue(':id', $sessionId, \PDO::PARAM_STR);
                    $stmt->execute();
                    $session = $stmt->fetch();
                    if(is_array($session) && count($session)>0)
                    {
                        if($session['sess_obsolete'] == 'Y') {
                            self::log()->addDebug('session is marked as obsolete, deleted it');
                            destroy($sessionId);
                            return '';
                        }
                        return empty($session['sess_data'])
                             ? ''
                             : self::decrypt($session['sess_data'],self::getKey($sessionId));
                    }
                } catch ( \Exception $e ) {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO
                    self::log()->addDebug(sprintf(
                        'catched exception [%s]', $e->getMessage()
                    ));
                    return false;
                }
            }
            return '';
        }

        /**
         * @inheritdoc
         **/
        public function write($sessionId,$data)
        {
            self::log()->addDebug(sprintf(
                'write() - writing data to session [%s]',$sessionId
            ));
            self::log()->addDebug(print_r($data,1));

            $sql = self::getStatement('write');
            $maxlifetime = (int) ini_get('session.gc_maxlifetime');
            if(false!==$sql)
            {
                try {
                    $key  = self::getKey($sessionId);
                    $data = self::encrypt((empty($data)?'':$data),$key);
                    $stmt = self::db()->prepare($sql);
                    $stmt->bindValue(':id'      , $sessionId  , \PDO::PARAM_STR);
                    $stmt->bindParam(':data'    , $data       , \PDO::PARAM_STR);
                    $stmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
                    $stmt->bindValue(':time'    , time()      , \PDO::PARAM_INT);
                    $stmt->bindValue(':key'     , $key        , \PDO::PARAM_STR);
                    $stmt->execute();
                    return true;
                } catch ( \Exception $e ) {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO
                    return false;
                }
            }
            return false;
        }   // end function write()

        /**
         * @inheritdoc
         **/
        public function destroy($sessionId) {
            self::log()->addDebug(sprintf(
                'destroy() - destroying session %s',$sessionId
            ));
            $sql = self::getStatement('delete');
            if(false!==$sql)
            {
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id'  , $sessionId, \PDO::PARAM_STR);
                $stmt->bindValue(':time', time()    , \PDO::PARAM_STR);
                $stmt->execute();
            }
            return true;
        }

        /**
         * We delay gc() to close() so that it is executed outside the
         * transactional and blocking read-write process. This way, pruning
         * expired sessions does not block them from being started while the
         * current session is used.
         **/
        public function gc($maxlifetime)
        {
            self::log()->addDebug(sprintf(
                'gc(%s)',$maxlifetime
            ));
            $this->gcCalled = true;
            return true;
        }   // end function gc()

        /**
         * create new session an mark the old one as obsolete
         **/
        public static function regenerateSession()
        {
            self::log()->addDebug(sprintf(
                'regenerateSession [%s]',session_id()
            ));

        	// Set current session to expire in 10 seconds
            $sql = self::getStatement('obsolete');
            if(false!==$sql)
            {
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id', session_id(), \PDO::PARAM_STR);
                $stmt->execute();
            }

        	// Create new session without destroying the old one
        	session_regenerate_id(false);

        	// Grab current session ID and close both sessions to allow other scripts to use them
        	$newSession = session_id();
        	session_write_close();

            self::log()->addDebug(sprintf(
                'regenerateSession new session id [%s]',session_id()
            ));

        	// Set session ID to the new one, and start it back up again
        	session_id($newSession);
        	session_start();
        }   // end function regenerateSession()


/*******************************************************************************
 * PRIVATE METHODS
 ******************************************************************************/

        /**
         *
         * @access private
         * @return
         **/
        private static function decrypt(string $data,string $key)
        {
            if(!strlen($data)) return '';
            if(extension_loaded('openssl')) {
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
                for($i=1;$i<$rounds;$i++) {
                    $hash[$i] = hash(self::$hash, $hash[$i - 1].$data00, true);
                    $result .= $hash[$i];
                }
                $key       = substr($result, 0, 32);
                $iv        = substr($result, 32, $ivlen);
                $decrypted = openssl_decrypt($ct, $cipher, $key, true, $iv);
                return $decrypted;
            }
            if(extension_loaded('mcrypt')) {
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
        private static function encrypt(string $data,string $key)
        {
            if(!strlen($data)) return '';
            if(extension_loaded('openssl'))
            {
                $cipher = self::getCipher();                  // get cipher
                $ivlen  = openssl_cipher_iv_length($cipher);  // set length
                $salt   = openssl_random_pseudo_bytes(16);    // Set a random salt
                $salted = '';
                $dx     = '';
                // Salt the key(32) and iv(16) = 48
                while(strlen($salted) < 32+$ivlen) {
                    $dx = hash(self::$hash, $dx.$key.$salt, true);
                    $salted .= $dx;
                }
                $key       = substr($salted, 0, 32);
                $iv        = substr($salted, 32, $ivlen);
                $encrypted = openssl_encrypt($data, $cipher, $key, true, $iv);
                $encrypted = base64_encode($salt . $encrypted);
                return $encrypted;
            }
            if(extension_loaded('mcrypt'))
            {
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
            foreach(array_values(self::$openssl_preferred) as $method)
                if(in_array($method,$avail))
                    return $method;
        }   // end function getCipher()

        /**
         * read or generate session encryption key
         *
         * @param  string  $id
         * @return string
         **/
        private static function getKey($sessionId)
        {
            $sql = self::getStatement('getkey');
            $key = null;
            if(false!==$sql)
            {
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id', $sessionId, \PDO::PARAM_STR);
                $stmt->execute();
                $data = $stmt->fetch();
                $key  = $data['sess_key'];
            }
            if($key) {
                return $key;
            } else {
                return hash('sha512', uniqid(mt_rand(1, mt_getrandmax()), true));
            }
        }   // end function getKey()

        /**
         * holds all database statements used in this class
         **/
        private static function getStatement($name)
        {
            if(!is_array(self::$stmt))
            {
                self::$stmt = array(
                    'delete'   => 'DELETE FROM `:prefix:sessions` '
                               .  'WHERE `sess_lifetime` + `sess_time` < :time '
                               .  'OR `sess_id`=:id '
                               .  'OR `sess_obsolete`="Y"',
                    'destroy'  => 'DELETE FROM `:prefix:sessions` WHERE `sess_id` = :id',
                    'getkey'   => 'SELECT `sess_key` FROM `:prefix:sessions` WHERE `sess_id` = :id',
                    'obsolete' => 'UPDATE `:prefix:sessions` SET `sess_obsolete`="Y", `sess_lifetime`=10 WHERE `sess_id` = :id',
                    'read'     => 'SELECT `sess_data`, `sess_lifetime`, `sess_time`, `sess_obsolete` FROM `:prefix:sessions` WHERE `sess_id` = :id FOR UPDATE',
                    'write'    => 'INSERT INTO `:prefix:sessions` (`sess_id`,`sess_data`,`sess_lifetime`,`sess_time`,`sess_key`) '
                               .  'VALUES (:id, :data, :lifetime, :time, :key) '
                               .  'ON DUPLICATE KEY UPDATE `sess_data` = VALUES(`sess_data`), '
                               .  '`sess_lifetime` = VALUES(`sess_lifetime`), '
                               .  '`sess_time` = VALUES(`sess_time`), '
                               .  '`sess_key`=VALUES(`sess_key`)'

                );
            }
            return (
                  isset(self::$stmt[$name])
                ? self::$stmt[$name]
                : false
            );
        }

        private static function validateSession($sessionId)
        {
            self::log()->addDebug(sprintf(
                'validateSession(%s)',$sessionId
            ));
            // cleanup expired sessions
            $sql = self::getStatement('delete');
            if(false!==$sql)
            {
                self::log()->addDebug('cleanup sessions');
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id'  , 'ignore', \PDO::PARAM_STR);
                $stmt->bindValue(':time', time()  , \PDO::PARAM_STR);
                $stmt->execute();
            }
            // if the session was killed, there will be no key
            $sql = self::getStatement('getkey');
            $key = null;
            if(false!==$sql)
            {
                self::log()->addDebug(sprintf(
                    'SQL: %s', $sql
                ));
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id', $sessionId, \PDO::PARAM_STR);
                $stmt->execute();
                $data = $stmt->fetch();
                $key  = $data['sess_key'];
                self::log()->addDebug(sprintf('KEY [%s]',$key));
                return !empty($key);
            }
            return false;
        }
    }
}