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
use \CAT\Base as Base;
use \CAT\Registry as Registry;
use \CAT\Helper\CQRCode as CQRCode;
use \CAT\Helper\CQRCodeProvider as CQRCodeProvider;

if(!class_exists('Authenticate',false))
{
    class Authenticate extends Base
    {
        // log level
        protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        #protected static $loglevel  = \Monolog\Logger::DEBUG;
        #
        // singleton
        private static $instance        = NULL;
        /**
         * last error
         **/
        private static $lasterror     = null;

        /*******************************************************************************
         * http://aaronsaray.com/blog/2009/02/12/password-complexity-class/
         ******************************************************************************/
        /** constants - are arbritrary numbers - but used for bitwise **/
        const REQUIRE_LOWERCASE       = 4;
        const REQUIRE_UPPERCASE       = 8;
        const REQUIRE_NUMBER          = 16;
        const REQUIRE_SPECIALCHAR     = 32;
        //const REQUIRE_DIFFPASS       = 64;
        const REQUIRE_DIFFUSER        = 128;
        const REQUIRE_UNIQUE          = 256;
        protected $_passwordDiffLevel = 3;
        protected $_uniqueChrRequired = 4;
        protected $_complexityLevel   = 0;
        protected $_issues            = array();

        protected $tfa;
        protected $tfaSecret            = NULL;

        protected $_hashOpt        = array(
            'algo'    => PASSWORD_BCRYPT,
            'cost'    => 10
        );


        /**
         * inheritable constructor; allows to set object variables
         **/
        public function __construct ( $options = array() ) {
            parent::__construct();
            $this->reset(); // make sure there is no old data

            $this->_hashOpt    = (object) $this->_hashOpt;
        }   // end function __construct()


        /**
         * get singleton
         **/
        public static function getInstance()
        {
            if (!self::$instance)
            {
                self::$instance = new self();
            }
            return self::$instance;
        }   // end function getInstance()

        /**
         * Compare user's password with given password
         * @access public
         * @param int $uid
         * @param string $passwd
         * @param string $tfaToken
         * @return bool
         */
        public static function authenticate($uid, $passwd, $tfaToken = NULL)
        {
            self::log()->debug(sprintf('Trying to verify password for UserID [%s]',$uid));

            if(!$uid||!$passwd)
            {
                self::setError('An empty value was sent for authentication!');
                return false;
            }

            $storedHash     = self::getPasswd($uid);
			$storedToken    = self::getSecret($uid);

            if(password_verify($passwd,$storedHash)) // user found and password ok
            {
                // init user object
                self::user()->initUser($uid);

                // if TFA is enabled and token is given
                if($tfaToken)
                {
                    $tfa = new \RobThree\Auth\TwoFactorAuth(WEBSITE_TITLE);
                    if($tfa->verifyCode($storedToken,$tfaToken) !== true)
                    {
                        self::setError(
                            'Two step authentication failed!',
                            'Token verification failed'
                        );
                        return false;
                    }
                    else
                    {
                        // if TFA is enabled but token is missing
                        self::setError(
                            'Two step authentication failed!',
                            'Missing token'
                        );
                        return false;
                    }
                }
                $_SESSION['USER_ID'] = $uid;
                \CAT\Session::regenerateSession();

                return true;
            }
            else
            {
                self::setError(
                    'Authentication failed!',
                    'No such user, user not active, or invalid password!'
                );
            }
            return false;
        }   // end function authenticate()

        /**
         * get hash
         *
         * @access private
         * @param string $passwd
         * @return string
        **/
        private static function getHash($passwd)
        {
            return password_hash($passwd, $this->_hashOpt->algo, array('cost'=>$this->_hashOpt->cost));
        }   // end function getHash()

        /**
         * Get hashed password from database
         *
         * @access private
         * @param int $uid
         * @return string
         **/
        private static function getPasswd($uid=NULL)
        {
            $storedHash = self::db()->query(
                'SELECT `password` FROM `:prefix:rbac_users` WHERE `user_id`=:uid',
                array( 'uid' => $uid )
            )->fetchColumn();

            if(self::db()->isError()) return false;
            else                      return $storedHash;

        }   // end function getPasswd()

        /**
         * Check if the user is authenticated
         *
         * @access private
         * @param int $uid
         * @return string
         **/
        private static function getSecret($uid)
        {
            // TFA enabled globally?
            if(
                   !Registry::exists('TFA_ENABLED')
                || (Registry::get('TFA_ENABLED')===false)
            ) {
                return true;
            }

            // TFA enabled for current user?
            $getTFA    = self::db()->query(
                'SELECT `tfa_enabled`, `tfa_secret` FROM `:prefix:rbac_users` WHERE `user_id`=:uid',
                array('name'=>$uid )
            );

            if(!self::db()->isError() && $getTFA->rowCount() != 0) // user found and password ok
            {
                $tfa = $getTFA->fetch(\PDO::FETCH_ASSOC);
                if( $tfa['tfa_enabled'] == 'Y') {
                    // missing secret?
                    if(!strlen($tfa['tfa_secret']))
                    {
                        $tfa['tfa_secret'] = $this->getTFAObject()->createSecret();
                        self::db()->query(
                            'UPDATE `:prefix:rbac_users` SET `tfa_secret`=? WHERE `user_id`=?',
                            array($tfa['tfa_secret'],$uid)
                        );
                    }
                    return $tfa['tfa_secret'];
                }
                else return true;
            }
            else return false;

        }   // end function getTFASecret()

        /**
         * Check if the user is authenticated
         *
         * @access private
         * @param int $uid
         * @return string
         **/
        private function getTFAObject()
        {
            if ( is_object($this->tfa) ) return $this->tfa;
            $ignore    = new CQRCode(); // just to make sure the helper is loaded
            $mp        = new CQRCodeProvider(); // needed for image creation
            $this->tfa = new \RobThree\Auth\TwoFactorAuth(WEBSITE_TITLE, 6, 30, 'sha1', $mp);
            return $this->tfa;
        }   // end function getTFAObject()


        /**
         * Check if the user is authenticated
         *
         * @access private
         * @param int $uid
         * @return string
         **/
        public function createQRCode($uid)
        {
            return $this->getTFAObject()->getQRCodeImageAsDataUri(
                    CAT_USER::getInstance($uid)->get('display_name'),
                    $this->setSecret()
            );
        }   // end function createQRCode()

        /**
         * Check if the user is authenticated
         *
         * @access public
         * @return boolean
         **/
        public function is_authenticated()
        {
        }   // end function is_authenticated()


        /**
         * returns the standard options
         * @return integer
         */
        public function getComplexityStandard()
        {
            return self::REQUIRE_LOWERCASE + self::REQUIRE_UPPERCASE + self::REQUIRE_NUMBER;
        }
        /**
         *returns all of the options
         *@return integer
         */
        public function getComplexityStrict()
        {
            $r = new ReflectionClass($this);
            $complexity = 0;
            foreach ($r->getConstants() as $constant) {
                $complexity += $constant;
            }
            return $complexity;
        }
        public function setComplexity($complexityLevel)
        {
            $this->_complexityLevel=$complexityLevel;
        }
        /**
         * checks for complexity level. If returns false, it has populated the _issues array
         */
        public function complexEnough($newPass, $username, $oldPass = NULL)
        {
            $enough = TRUE;
            $r      = new ReflectionClass($this);
            foreach ($r->getConstants() as $name=>$constant) {
                /** means we have to check that type then **/
                if ($this->_complexityLevel & $constant) {
                    /** REQUIRE_MIN becomes _requireMin() **/
                    $parts    = explode('_', $name, 2);
                    $funcName = "_{$parts[0]}" . ucwords($parts[1]);
                    $result   = call_user_func_array(array($this, $funcName), array($newPass, $oldPass, $username));
                    if ($result !== TRUE) {
                        $enough = FALSE;
                        $this->_issues[] = $result;
                    }
                }
            }
            return $enough;
        }
        public function getPasswordIssues()
        {
            return $this->_issues;
        }

        /***********************************************************************
         * PROTECTED
         **********************************************************************/
        /**
         *
         * @access public
         * @return
         **/
        protected static function setError($msg,$logmsg=NULL)
        {
            self::log()->debug($logmsg?$logmsg:$msg);
            self::$lasterror = $msg;
        }   // end function setError()

        protected function _requireLowercase($newPass)
        {
            if (!preg_match('/[a-z]/', $newPass)) {
                return 'Password requires a lowercase letter.';
            }
            return true;
        }
        protected function _requireUppercase($newPass)
        {
            if (!preg_match('/[A-Z]/', $newPass)) {
                return 'Password requires an uppercase letter.';
            }
            return true;
        }
        protected function _requireNumber($newPass)
        {
            if (!preg_match('/[0-9]/', $newPass)) {
                return 'Password requires a number.';
            }
            return true;
        }
        protected function _requireSpecialChar($newPass)
        {
            if (!preg_match('/[^a-zA-Z0-9]/', $newPass)) {
                return 'Password requires a special character.';
            }
            return true;
        }
        protected function _requireDiffpass($newPass, $oldPass)
        {
            if (strlen($newPass) - similar_text($oldPass,$newPass) < $this->_passwordDiffLevel || stripos($newPass, $oldPass) !== FALSE) {
                return 'Password must be a bit more different than the last password.';
            }
            return true;
        }
        protected function _requireDiffuser($newPass, $oldPass, $username)
        {
            if (stripos($newPass, $username) !== FALSE) {
                return 'Password should not contain your username.';
            }
            return true;
        }
        protected function _requireUnique($newPass)
        {
            $uniques = array_unique(str_split($newPass));
            if (count($uniques) < $this->_uniqueChrRequired) {
                return 'Password must contain more unique characters.';
            }
            return true;
        }
    }
}