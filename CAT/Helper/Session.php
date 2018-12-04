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

if (!class_exists('\CAT\Helper\Session', false))
{
    class Session extends \CAT\Base implements \SessionHandlerInterface
    {
        #protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $loglevel = \Monolog\Logger::DEBUG;

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
        /**
         * @var bool
         */
        protected $started = false;
        /**
         * @var bool
         */
        protected $closed  = false;
        /**
         * @var bool
         */
        protected $crypt   = false;
        /**
         * @var string
         */
        protected $data    = null;
        /**
         * @var string
         */
        private $domain    = null;
        /**
         * @var string
         */
        private $path      = '/';
        /**
         * @var bool
         */
        private $gcCalled  = false;

        /**
         * @var array list of statements; defined in getStatement()
         **/
        private static $stmt;

        public function __construct(bool $crypt = false)
        {
            $this->crypt = $crypt;
            // set our custom session functions.
            session_set_save_handler($this);
            // This line prevents unexpected effects when using objects as save handlers.
            register_shutdown_function('session_write_close');
            // set serialize handler
            ini_set('session.serialize_handler','php_serialize');
            // How many bits per character of the hash.
            // The possible values are '4' (0-9, a-f), '5' (0-9, a-v), and '6' (0-9, a-z, A-Z, "-", ",").
            ini_set('session.hash_bits_per_character', 5);
            // Force the session to only use cookies, not URL variables.
            ini_set('session.use_only_cookies', 1);
            // Hash algorithm to use for the session.
            // (use hash_algos() to get a list of available hashes.)
            $session_hash = null;
            // Check if hash is available
            foreach (self::$hash_algo_preferred as $i => $session_hash) {
                if (in_array($session_hash, hash_algos())) {
                    ini_set('session.hash_function', $session_hash);
                    self::$hash = $session_hash;
                    break;
                }
            }
        }

        /**
         * clear (close) the session
         *
         * @access public
         * @param  bool   $destroy
         * @return bool
         **/
        public function clear($destroy=false)
        {
            // clear out the session
            $_SESSION = array();
            $this->data    = array();
            $this->started = false;
            $this->closed  = true;
            if($destroy) {
                $this->destroy(session_id());
            }
            return true;
        }

        /**
         * start a new session; throws exception if an active session is
         * determined
         *
         * @access public
         * @return bool
         **/
        public function start()
        {
            self::log()->addDebug('start()');
            if ($this->started) {
                return true;
            }
            if (\PHP_SESSION_ACTIVE === session_status()) {
                throw new \RuntimeException('Failed to start the session: already started by PHP.');
            }
            if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
                throw new \RuntimeException(sprintf('Failed to start the session because headers have already been sent by "%s" at line %d.', $file, $line));
            }
            // get domain
            $parse  = parse_url(CAT_SITE_URL);
            if (isset($parse['host'])) {
                $this->domain = $parse['host'];
            } else {
                $this->domain = CAT_SITE_URL;
            }
            if (isset($parse['path'])) {
                $this->path   = $parse['path'];
            }
            // Set the parameters
            session_set_cookie_params(
                time()+ini_get('session.gc_maxlifetime'),
                $this->path,                                // path
                $this->domain,                              // domain
                (isset($_SERVER['HTTPS']) ? true : false),  // secure
                true                                        // httponly
            );
            // generate unique session name for this site
            $name = '_cat_sess_'.base64_encode(CAT_SITE_URL); //.'_'.random_bytes(8);
            // Change the session name
            session_name($name);
            // ok to try and start the session
            if (!session_start()) {
                throw new \RuntimeException('Failed to start the session');
            }

            // connect session hash to internal hash
            $this->data =& $_SESSION;

            $this->started = true;

            return true;
        }

        /**
         * read data from session
         *
         * @access public
         * @param  string  $name - session key to read
         * @param  mixed   $default - default value if session data is missing
         * @return mixed
         **/
        public function get($name, $default=null)
        {
            self::log()->addDebug(sprintf(
                'get() name [%s] default [%s]',
                $name,$default
            ));
            $data = $this->read(session_id());
            if(strlen($data)) {
                session_decode($data);
                $this->data =& $_SESSION;
            }
            if(isset($this->data[$name])) {
                return $this->data[$name];
            } else {
                return $default;
            }
        }   // end function get()

        /**
         * store session data
         *
         * @access public
         * @param  string  $name - session key to set
         * @param  mixed   $val  - value to set
         * @return void
         **/
        public function set($name,$val)
        {
            $this->data[$name] = $val;
        }   // end function set()

        /**
         *
         * @access public
         * @return
         **/
        public function started()
        {
            return $this->started;
        }   // end function started()

        /**
         * regenerate a session by changing the ID
         *
         * @access public
         * @param  bool    $destroy - wether to destroy the old session
         * param   int     $lifetime - optional session lifetime
         * @return bool
         **/
        public function regenerate($destroy=false,$lifetime=null)
        {
            // Cannot regenerate the session ID for non-active sessions.
            if (\PHP_SESSION_ACTIVE !== session_status()) {
                return false;
            }
            if (headers_sent()) {
                return false;
            }
            if (null !== $lifetime) {
                ini_set('session.cookie_lifetime', $lifetime);
            }
            if ($destroy) {
                //$this->metadataBag->stampNew();
            }

            self::log()->addDebug(sprintf(
                'regenerate() [%s]',
                session_id()
            ));

            // Set current session to expire in 10 seconds
            $sql = self::getStatement('obsolete');
            if (false!==$sql) {
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id', session_id(), \PDO::PARAM_STR);
                $stmt->execute();
            }

            // Create new session without destroying the old one
            // ($destroy == false)
            session_regenerate_id($destroy);

            // Grab current session ID and close both sessions to allow other scripts to use them
            $newSession = session_id();
            session_write_close();

            self::log()->addDebug(sprintf(
                'regenerate() new session id [%s]',
                session_id()
            ));

            // Set session ID to the new one, and start it back up again
            session_id($newSession);
            session_start();

            // re-connect session array to internal array
            $this->data =& $_SESSION;

            return true;
        }

/*******************************************************************************
 *    SessionHandlerInterface
 ******************************************************************************/

        /**
         * cleans up expired and obsolete sessions
         **/
        public function close() : bool
        {
            self::log()->addDebug('close()');
            $this->cleanup();
            return true;
        }

        public function destroy($sessionId) : bool
        {
            self::log()->addDebug(sprintf(
                'destroy() - destroying session %s',
                $sessionId
            ));
            $this->cleanup($sessionId);
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
                'gc(%s)',
                $maxlifetime
            ));
            $this->gcCalled = true;
            return true;
        }   // end function gc()

        public function open($savePath,$session_name) : bool
        {
            self::log()->addDebug(sprintf(
                'open() - session name [%s]',
                $session_name
            ));
            $this->cleanup();
            return true;
        }

        public function read($sessionId) : string
        {
            self::log()->addDebug(sprintf(
                'read() - reading session [%s]',
                $sessionId
            ));
            $sql = self::getStatement('read');
            if (false!==$sql) {
                try {
                    $stmt = self::db()->prepare($sql);
                    $stmt->bindValue(':id', $sessionId, \PDO::PARAM_STR);
                    $stmt->execute();
                    $session = $stmt->fetch();
                    if (is_array($session) && count($session)>0) {
                        if ($session['sess_obsolete'] == 'Y') {
                            self::log()->addDebug('session is marked as obsolete, delete it');
                            $this->destroy($sessionId);
                            return '';
                        }
                        if(!empty($session['sess_data'])) {
                            if($this->crypt === true) {
                                $data = self::decrypt($session['sess_data'], self::getKey($sessionId));
                            } else {
                                $data = $session['sess_data'];
                            }
                            return $data;
                        } else {
                            return '';
                        }
                    }
                } catch (\Exception $e) {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO
                    self::log()->addDebug(sprintf(
                        'catched exception [%s]',
                        $e->getMessage()
                    ));
                    return false;
                }
            }
            return '';
        }

        public function write($sessionId, $data)
        {
            self::log()->addDebug(sprintf(
                'write() - writing data to session [%s]',
                $sessionId
            ));
            self::log()->addDebug(print_r($data, 1));

            $sql = self::getStatement('write');
            $maxlifetime = (int) ini_get('session.gc_maxlifetime');
            if (false!==$sql) {
                try {
                    $key = '';
                    if($this->crypt === true) {
                        $key  = self::getKey($sessionId);
                        $data = self::encrypt((empty($data)?'':$data), $key);
                    }
                    $stmt = self::db()->prepare($sql);
                    $stmt->bindValue(':id', $sessionId, \PDO::PARAM_STR);
                    $stmt->bindParam(':data', $data, \PDO::PARAM_STR);
                    $stmt->bindParam(':lifetime', $maxlifetime, \PDO::PARAM_INT);
                    $stmt->bindValue(':time', time(), \PDO::PARAM_INT);
                    $stmt->bindValue(':key', $key, \PDO::PARAM_STR);
                    $stmt->execute();
                    return true;
                } catch (\Exception $e) {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO
                    return false;
                }
            }
            return false;
        }   // end function write()



        /*******************************************************************************
         * PRIVATE METHODS
         ******************************************************************************/

        /**
         *
         * @access private
         * @return
         **/
        private function cleanup($sessionID='')
        {
            $sql = self::getStatement('cleanup');
            if (false!==$sql) {
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id', $sessionID, \PDO::PARAM_STR);
                $stmt->bindValue(':time', time(), \PDO::PARAM_STR);
                $stmt->execute();
            }
        }   // end function cleanup()

        /**
         *
         * @access private
         * @return
         **/
        private static function decrypt(string $data, string $key)
        {
            if (!strlen($data)) {
                return '';
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
        private static function encrypt(string $data, string $key)
        {
            if (!strlen($data)) {
                return '';
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
            if (false!==$sql) {
                $stmt = \CAT\Base::db()->prepare($sql);
                $stmt->bindValue(':id', $sessionId, \PDO::PARAM_STR);
                $stmt->execute();
                $data = $stmt->fetch();
                $key  = $data['sess_key'];
            }
            if ($key) {
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
            if (!is_array(self::$stmt)) {
                self::$stmt = array(
                    'cleanup'  => 'DELETE FROM `:prefix:sessions` '
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

    }
}
