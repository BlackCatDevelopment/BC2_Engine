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

if (! class_exists('Users', false)) {
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
            if (!is_object(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return string
         **/
        public function getDefaultPage()
        {
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->getDefaultPage();
        }   // end function getDefaultPage()

        /**
         *
         * @access public
         * @return string
         **/
        public function getHomeFolder()
        {
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->getHomeFolder();
        }   // end function getHomeFolder()

        /**
         *
         * @access public
         * @return
         **/
        public function getID()
        {
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
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
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
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
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->hasPagePerm($pageID, $perm);
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
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
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

            if (!isset($_COOKIE) || !count($_COOKIE)) {
                self::log()->addDebug('no cookie = not authenticated');
                return false;
            }

            // first call of self::session() will generate a unique session
            // name
            self::session()->start();

            // validate session data
            if (
                   self::session()->get('IPaddress') != $_SERVER['REMOTE_ADDR']
                || self::session()->get('userAgent') != $_SERVER['HTTP_USER_AGENT']
            ) {
                self::log()->addDebug('check of IP and/or userAgent failed!');
                self::log()->addDebug(sprintf(
                    'session IP [%s] incoming IP [%s]',
                    self::session()->get('IPaddress'),
                    $_SERVER['REMOTE_ADDR']
                ));
                self::session()->clear(1);
                return false;
            }
            self::$curruser = new \CAT\Objects\User(self::session()->get('user_id'));
            return true;
        }   // end function isAuthenticated()

        /**
         *
         * @access public
         * @return
         **/
        public function isOwner($page_id)
        {
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->isOwner($page_id);
        }   // end function isOwner()

        /**
         *
         * @access public
         * @return
         **/
        public function isRoot()
        {
            if (!isset(self::$curruser) || !self::$curruser instanceof \CAT\Objects\User) {
                return false;
            }
            return self::$curruser->isRoot();
        }   // end function isRoot()

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
            return (self::db()->isError() ? self::db()->getError() : true);
        }   // end function deleteUser()

        /**
         *
         * @access public
         * @return
         **/
        public static function exists($user_id)
        {
            $data = self::getUsers(array('user_id'=>$user_id), true);
            if ($data && is_array($data) && count($data)) {
                return true;
            }
            return false;
        }   // end function exists()

        /**
         *
         * @access public
         * @return
         **/
        public static function get($key)
        {
            if (!self::$curruser) {
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
            $data = self::getUsers(array('user_id'=>$user_id), true);
            if ($data && is_array($data) && count($data)) {
                return $data[0];
            }
            return array();
        }   // end function getDetails()

        /**
         *
         * @access public
         * @return
         **/
        public static function getUserNames()
        {
            $stmt = self::db()->query('SELECT `user_id`, `username`, `display_name` FROM `:prefix:rbac_users`');
            $data = $stmt->fetchAll();
            $list = array();

            if (is_array($data) && count($data)>0) {
                foreach ($data as $i => $item) {
                    $list[$item['user_id']] = $item;
                }
            }

            return $list;
        }   // end function getUserNames()


        /**
         * get users from DB; has several options to define what is requested
         *
         * @access public
         * @param  array    $opt
         * @param  boolean  $extended (default: false)
         * @return array
         **/
        public static function getUsers($opt=null, $extended=false)
        {
            $q    = 'SELECT `t1`.* FROM `:prefix:rbac_users` AS `t1` ';
            $p    = array();
            if (is_array($opt)) {
                if (isset($opt['group_id'])) {
                    $q .= 'LEFT OUTER JOIN `:prefix:rbac_usergroups` AS `t2` '
                       .  'ON `t1`.`user_id`=`t2`.`user_id` '
                       .  'WHERE ((`t2`.`group_id`'
                       .  (isset($opt['not_in_group']) ? '!' : '')
                       .  '=:id'
                       ;
                    $p['id'] = $opt['group_id'];
                    if (isset($opt['not_in_group'])) {
                        // skip users in admin group and protected users
                        $q .= ' AND `t2`.`group_id`  != 1'
                           .  ' AND `t1`.`protected` != "Y")'
                           .  ' OR `t2`.`group_id` IS NULL )';
                    } else {
                        $q .= '))';
                    }
                }
                if (isset($opt['user_id'])) {
                    $q .= ' WHERE `t1`.`user_id`=:uid';
                    $p['uid'] = $opt['user_id'];
                }
            }
            $sth  = self::db()->query($q, $p);
            $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($data as $i => $user) {
                if (strlen($user['wysiwyg'])) { // resolve wysiwyg editor
                    $data[$i]['wysiwyg'] = Addons::getDetails($user['wysiwyg'], 'name');
                }
                if ($extended) {
                    $sth = self::db()->query(
                        'SELECT * FROM `:prefix:rbac_user_extend` WHERE `user_id`=?',
                        array($user['user_id'])
                    );
                    $ext = $sth->fetchAll(\PDO::FETCH_ASSOC);
                    $data[$i]['extended'] = array();
                    foreach ($ext as $item) {
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
            $sth = self::db()->query($q, array('id'=>$id));
            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        }   // end function getUserGroups()

        /**
         * handle user authentication
         *
         * @access public
         * @return mixed
         **/
        public static function authenticate()
        {
            if (!isset($_REQUEST['acc']) || $_REQUEST['acc'] != 'true') {
                self::printFatalError('Authentication failed! Please accept the session cookie to proceed.');
            }
            $auth_result = self::user()->login();
            if (false!==$auth_result) {
                // session
                if (!self::session()->started()===true) {
                    self::session()->start();
                }
                self::session()->set('user_id', self::user()->get('user_id'));
                self::session()->set('IPaddress', $_SERVER['REMOTE_ADDR']);
                self::session()->set('userAgent', $_SERVER['HTTP_USER_AGENT']);
                // debugging
                self::log()->addDebug(sprintf(
                    'Authentication succeeded, username [%s], id [%s]',
                    self::user()->get('username'),
                    self::user()->get('user_id')
                ));
                // forward
                if (self::asJSON()) {
                    self::log()->addDebug(sprintf(
                        'sending json result, forward to URL [%s]',
                        CAT_ADMIN_URL.'/'.self::user()->getDefaultPage()
                    ));
                    Json::printData(array(
                        'success' => true,
                        'url'     => CAT_ADMIN_URL.'/'.self::user()->getDefaultPage(),
                    ));
                } else {
                    self::log()->addDebug(sprintf(
                        'forwarding to URL [%s]',
                        CAT_ADMIN_URL.'/'.self::user()->getDefaultPage()
                    ));
                    header('Location: '.CAT_ADMIN_URL.'/'.self::user()->getDefaultPage());
                }
            } else {
                self::log()->addDebug('Authentication failed!');
                if (self::asJSON()) {
                    Json::printError('Authentication failed!');
                } else {
                    self::printFatalError('Authentication failed!');
                }
            }
            exit;
        }   // end function authenticate()

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

                // Get the TFA token
                $field = Validate::sanitizePost('token_fieldname');
                $tfaToken = htmlspecialchars(Validate::sanitizePost($field), ENT_QUOTES);

                // check whether the password is correct
                $success = Authenticate::authenticate($uid, $passwd, $tfaToken);
                if ($success) {
                    self::db()->query(
                        'UPDATE `:prefix:rbac_users` SET `login_when`=?, `login_ip`=? WHERE `user_id`=?',
                        array(time(), $_SERVER['REMOTE_ADDR'], $uid)
                    );
                    self::$curruser = new \CAT\Objects\User($uid);
                    return true;
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

            // redirect to admin login
            if (!self::asJSON()) {
                $redirect = str_ireplace('/logout/', '/login/', $_SERVER['SCRIPT_NAME']);
                header('Location: '.CAT_ADMIN_URL.'/login');
            } else {
                \CAT\Helper\Json::printData(array(
                    'success' => true,
                    'message' => 'ok'
                ));
            }
        }   // end function logout()
    }
}
