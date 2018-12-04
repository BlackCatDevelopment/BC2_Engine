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
use \CAT\Helper\Authenticate as Authenticate;
use \CAT\Helper\Validate as Validate;

if ( ! class_exists( 'Users', false ) )
{
	class Users extends Base
	{

        // log level
        #protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        protected static $loglevel  = \Monolog\Logger::DEBUG;

        protected static $instance;
        protected static $curruser;

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance()
        {
            if(!is_object(self::$instance))
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public function getID()
        {
            if(!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return 2;
            }
            return self::$curruser->getID();
        }   // end function getID()

        /**
         * checks if the user has access to the given module
         * $module may be an addon_id or directory name
         *
         * @access public
         * @param  mixed   $module (addon_id or directory)
         * @return boolean
         **/
        public function hasModulePerm($module)
        {
            if(!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->hasModulePerm($module);
        }   // end function hasModulePerm()

        /**
         * checks if the current user has the given permission
         *
         * @access public
         * @param  string  $group     - permission group
         * @param  string  $perm      - required permission
         **/
        public function hasPagePerm(int $pageID, string $perm)
        {
            if(!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->hasPagePerm($pageID,$perm);
        }   // end function hasPagePerm()

        /**
         * checks if the current user has the given permission
         *
         * @access public
         * @param  string  $group     - permission group
         * @param  string  $perm      - required permission
         **/
        public function hasPerm(string $perm)
        {
            if(!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->hasPerm($perm);
        }   // end function hasPerm()

        /**
         * Check if the user is authenticated
         *
         * @access public
         * @return boolean
         **/
        public function isAuthenticated()
        {
            self::log()->addDebug('isAuthenticated()');

            $token = Validate::get('_cat_access_token'); // check form data
            if(empty($token)) {
                self::log()->addDebug('no token sent as form data');
                $headers = self::getResponseHeaders(); // token from header
                if(isset($headers['Authorization'])) {
                    $token = str_ireplace('Bearer ','',$headers['Authorization']);
                    self::log()->addDebug('got token from response headers');
                } else {
                    self::log()->addDebug('no token sent as header');
                }
            } else {
                self::log()->addDebug('got token from form data');
            }

            if(empty($token)) {
                if(isset($_COOKIE[self::getCookieName()])) {
                    $token = $_COOKIE[self::getCookieName()];
                    self::log()->addDebug(sprintf('got token from cookie [%s]',self::getCookieName()));
                } else {
                    self::log()->addDebug(sprintf('no token in cookie data [%s]',self::getCookieName()));
                }
            }

            if(!empty($token)) {
                $user_id = Authenticate::validate($token);
                self::log()->addDebug(sprintf('got userID [%s]',$user_id));
            }

            if(!empty($user_id)) {
                self::$curruser = new \CAT\Objects\User($user_id);
                self::log()->addDebug('>>> setting auth header');
                header('Authorization: Bearer '.$token);
                $_COOKIE[self::getCookieName()] = $token;
                return true;
            }
            return false;
        }   // end function isAuthenticated()

        /**
         *
         * @access public
         * @return
         **/
        public function isOwner($page_id)
        {
            if(!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->isOwner($page_id);
        }   // end function isOwner()

        /**
         * delete a user
         *
         * @access public
         * @param  integer $user_id
         * @return mixed   true on success, db error string otherwise
         **/
        public static function deleteUser($user_id)
        {
       		self::db()->query(
                "DELETE FROM `:prefix:rbac_users` WHERE `user_id`=:id",
                array('id'=>$user_id)
            );
            return ( self::db()->isError() ? self::db()->getError() : true );
        }   // end function deleteUser()

        /**
         *
         * @access public
         * @return
         **/
        public static function exists($user_id)
        {
            $data = self::getUsers(array('user_id'=>$user_id),true);
            if($data && is_array($data) && count($data))
                return true;
            return false;
        }   // end function exists()

        /**
         *
         * @access public
         * @return
         **/
        public static function get($key)
        {
            if(!self::$curruser) {
                return false;
            }
            return self::$curruser->get($key);
        }   // end function get()

        /**
         *
         * @access public
         * @return
         **/
        public static function getDetails($user_id)
        {
            $data = self::getUsers(array('user_id'=>$user_id),true);
            if($data && is_array($data) && count($data))
                return $data[0];
            return array();
        }   // end function getDetails()
        
        /**
         * get users from DB; has several options to define what is requested
         *
         * @access public
         * @param  array    $opt
         * @param  boolean  $extended (default: false)
         * @return array
         **/
        public static function getUsers($opt=NULL,$extended=false)
        {
            $q    = 'SELECT `t1`.* FROM `:prefix:rbac_users` AS `t1` ';
            $p    = array();
            if(is_array($opt))
            {
                if(isset($opt['group_id']))
                {
                    $q .= 'LEFT OUTER JOIN `:prefix:rbac_usergroups` AS `t2` '
                       .  'ON `t1`.`user_id`=`t2`.`user_id` '
                       .  'WHERE ((`t2`.`group_id`'
                       .  ( isset($opt['not_in_group']) ? '!' : '' )
                       .  '=:id'
                       ;
                    $p['id'] = $opt['group_id'];
                    if(isset($opt['not_in_group']))
                    {
                        // skip users in admin group and protected users
                        $q .= ' AND `t2`.`group_id`  != 1'
                           .  ' AND `t1`.`protected` != "Y")'
                           .  ' OR `t2`.`group_id` IS NULL )';
                    }
                    else
                    {
                        $q .= '))';
                    }
                }
                if(isset($opt['user_id']))
                {
                    $q .= ' WHERE `t1`.`user_id`=:uid';
                    $p['uid'] = $opt['user_id'];
                }
            }
            $sth  = self::db()->query($q,$p);
            $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
            foreach($data as $i => $user) {
                if(strlen($user['wysiwyg'])) { // resolve wysiwyg editor
                    $data[$i]['wysiwyg'] = Addons::getDetails($user['wysiwyg'],'name');
                }
                if($extended) {
                    $sth = self::db()->query(
                        'SELECT * FROM `:prefix:rbac_user_extend` WHERE `user_id`=?',
                        array($user['user_id'])
                    );
                    $ext = $sth->fetchAll(\PDO::FETCH_ASSOC);
                    $data[$i]['extended'] = array();
                    foreach($ext as $item) {
                        $data[$i]['extended'][$item['option']] = $item['value'];
                    }
                }
            }
            return $data;
        }   // end function getUsers()

        /**
         *
         * @access public
         * @return
         **/
        public static function getUserGroups($id)
        {
            $q = 'SELECT `t3`.* '
               . 'FROM `:prefix:rbac_users` AS t1 '
               . 'JOIN `:prefix:rbac_usergroups` AS t2 '
               . 'ON `t1`.`user_id`=`t2`.`user_id` '
               . 'JOIN `:prefix:rbac_groups` AS t3 '
               . 'ON `t2`.`group_id`=`t3`.`group_id` '
               . 'WHERE `t1`.`user_id`=:id'
               ;
            $sth = self::db()->query($q,array('id'=>$id));
            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        }   // end function getUserGroups()

        /**
         * authenticate user
         *
         * @access public
         * @return boolean
         **/
        public static function login()
        {
            $field	= Validate::sanitizePost('username_fieldname');
            $user	= htmlspecialchars(Validate::sanitizePost($field), ENT_QUOTES);
            $name	= preg_match('/[\;\=\&\|\<\> ]/', $user) ? '' : $user;

            // If no name was given or not allowed chars were sent
            if ($name == '') {
                return false;
            } else {
                $uid = self::db()->query(
                    'SELECT `user_id` FROM `:prefix:rbac_users` WHERE `username`=:username',
                    array( 'username' => $name )
                )->fetchColumn();

                // Get fieldname of password and the password itself
                $field	= Validate::sanitizePost('password_fieldname');
                $passwd	= Validate::sanitizePost($field);

                // Get the token
                $field = Validate::sanitizePost('token_fieldname');
                $token = htmlspecialchars(Validate::sanitizePost($field), ENT_QUOTES);

                // check whether the password is correct
                $token = Authenticate::authenticate($uid, $passwd, $token);
                if($token) {
                    self::db()->query(
                        'UPDATE `:prefix:rbac_users` SET `login_when`=?, `login_ip`=?, `login_token`=? WHERE `user_id`=?',
                        array(time(), $_SERVER['REMOTE_ADDR'], $token, $uid)
                    );
                    self::$curruser = new \CAT\Objects\User($uid);
                    setcookie(
                        self::getCookieName(),
                        $token,
                        time()+ini_get('session.gc_maxlifetime'),
                        '/',
                        CAT_SITE_URL
                    );
                    return $token;
                } else {
                    self::printFatalError('No such user, user not active, or invalid password!');
                }
                return false;
            }
        }   // end function login()

        /**
         * handle user login
         **/
        public static function logout()
        {
            self::session()->clear(true);

            self::db()->query(
                'UPDATE `:prefix:rbac_users` SET `login_when`=?, `login_ip`=?, `login_token`=? WHERE `user_id`=?',
                array(0, 0, null, self::user()->getID())
            );

            // invalidate session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 42000, '/');
            }

            // invalidate token
            setcookie(self::getCookieName(), '', time() - 42000, '/');

            // redirect to admin login
            if (!self::asJSON()) {
                $redirect = str_ireplace('/logout/', '/login/', $_SERVER['SCRIPT_NAME']);
                die(header('Location: '.CAT_ADMIN_URL.'/login'));
            } else {
                header('Content-type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'message' => 'ok'
                ));
            }
        }   // end function logout()
        
    }
}
