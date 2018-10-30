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

use CAT\Base as Base;
use CAT\Roles as Roles;
use CAT\Authenticate as Authenticate;
use CAT\Helper\Validate as Validate;
use CAT\Helper\Directory as Directory;

if (!class_exists('User'))
{
    class User extends Base
    {
        // log level
        protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        #protected static $loglevel  = \Monolog\Logger::DEBUG;
        /**
         * instances
         **/
        protected static $instances = array();
        /**
         * array to hold the user data
         **/
        protected        $user      = array();
        // user ID
        protected        $id        = NULL;
        // array to hold the user roles
        protected        $roles     = array();
        // array to hold the permissions
        protected        $perms     = array();
        // array to hold the module permissions
        protected        $modules   = array();
        // array to hold the user groups
        protected        $groups    = array();
        // array to hold the list of pages a user owns
        protected        $pages     = array();
        // last user error
        protected        $lasterror = NULL;
        // cache already loaded users
        protected static $users     = array();

        /**
         * create a new user object
         * @access public
         * @param  integer  $id - user id
         * @return object
         **/
        public function __construct($id=NULL)
        {
            self::log()->addDebug(sprintf(
                'constructor called with param [%s]', $id
            ));
            parent::__construct();
            $this->reset(); // make sure there is no old data
            $this->initUser($id); // load user
        }   // end function __construct()

        public static function getInstance($id=NULL)
        {
            if(!$id)
                $id = Validate::fromSession('USER_ID','numeric');
            if(!$id) $id = 2; // guest user
            if(!isset(self::$instances[$id]))
                self::$instances[$id] = new self($id);
            return self::$instances[$id];
        }   // end function getInstance()


        /**
         * get user attribute; returns NULL if the given attribute is not set
         *
         * @access public
         * @param  string  $attr - attribute name
         * @return mixed   value of $attr or NULL if not set
         **/
        public function get($attr=NULL)
        {
            if(isset($this->user))
            {
                if($attr)
                {
                    if(isset($this->user[$attr]))
                    {
                        return $this->user[$attr];
                    }
                    return NULL;
                }
                return (array)$this->user;
            }
            else
            {
                return NULL;
            }
        }   // end function get()

        /**
         *
         * @access public
         * @return
         **/
        public function getError()
        {
            return $this->lasterror;
        }   // end function getError()

        /**
         *
         * @access public
         * @return
         **/
        public function getGroups($ids_only=false)
        {
            if($ids_only && is_array($this->groups) && count($this->groups))
            {
                $ids = array();
                foreach(array_values($this->groups) as $item)
                    $ids[] = $item['group_id'];
                return $ids;
            }
            return $this->groups;
        }   // end function getGroups()

        /**
         * get the user's home folder (subfolder of /media)
         *
         * @access public
         * @param  boolean  $relative (default:false) - do not prepend CAT_PATH
         * @return
         **/
        public function getHomeFolder($relative=false)
        {
            $default = Directory::sanitizePath(CAT_PATH.'/'.self::getSetting('media_directory'));
            if($this->is_root())
                return ($relative ? self::getSetting('media_directory') : $default);
            $home = $this->get('home_folder');
            if(strlen($home))
                return (
                      $relative
                    ? $home
                    : Directory::sanitizePath(CAT_PATH.'/'.$home)
                );
            return ($relative ? self::getSetting('media_directory') : $default);
        }   // end function getHomeFolder()

        /**
         *
         * @access public
         * @return
         **/
        public function getID()
        {
            return ( $this->id ? $this->id : 2 );
        }   // end function getID()
        
        /**
         *
         * @access public
         * @return
         **/
        public function getPerms()
        {
            return $this->perms;
        }   // end function getPerms()

        /**
         *
         * @access public
         * @return
         **/
        public function setError($msg)
        {
            $this->log()->debug($msg);
            $this->lasterror = $msg;
        }   // end function setError()

        /**
         *
         * @access public
         * @return
         **/
        public static function get_initial_page($user_id=NULL,$as_array=false)
        {
            return CAT_ADMIN_URL.'/start/index.php?initial=true';
        }   // end function get_initial_page()

        // =====================================================================
        //    OOP
        // =====================================================================

        /**
         * authenticate user
         *
         * @access public
         * @return boolean
         **/
        public function login()
        {
			$field	= Validate::sanitizePost('username_fieldname');
			$user	= htmlspecialchars(Validate::sanitizePost($field),ENT_QUOTES);
			$name	= preg_match('/[\;\=\&\|\<\> ]/',$user) ? '' : $user;

			// If no name was given or not allowed chars were sent
			if ($name == '')
            {
                return false;
            }
			else
            {
				$uid = $this->db()->query(
					'SELECT `user_id` FROM `:prefix:rbac_users` WHERE `username`=:username',
					array( 'username' => $name )
				)->fetchColumn();

				// Get fieldname of password and the password itself
				$field	= Validate::sanitizePost('password_fieldname');
				$passwd	= Validate::sanitizePost($field);

				// Get the token
				$field = Validate::sanitizePost('token_fieldname');
				$token = htmlspecialchars(Validate::sanitizePost($field),ENT_QUOTES);

				// check whether the password is correct
				if(Authenticate::authenticate($uid, $passwd, $token))
                {
                    $this->db()->query(
                        'UPDATE `:prefix:rbac_users` SET `login_when`=?, `login_ip`=? WHERE `user_id`=?',
                        array(time(), $_SERVER['REMOTE_ADDR'], $uid)
                    );
                    self::initUser($uid);
                    return true;
                }
				
				else $this->setError('No such user, user not active, or invalid password!');
				return false;
			}
        }   // end function login()

        /**
         * handle user login
         **/
        public function logout()
        {
            \CAT\Session::stop_session();

            $this->db()->query(
                'UPDATE `:prefix:rbac_users` SET `login_when`=?, `login_ip`=? WHERE `user_id`=?',
                array(0, 0, $this->user()->getID())
            );

            // redirect to admin login
            if(!isset($_POST['_cat_ajax']))
            {
                $redirect = str_ireplace('/logout/','/login/',$_SERVER['SCRIPT_NAME']);
                die(header('Location: '.CAT_ADMIN_URL.'/login'));
            }
            else {
                header('Content-type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'message' => 'ok'
                ));
            }
        }   // end function logout()

        /**
         * checks if the user is member of the given group
         *
         * @access public
         * @param  integer  $group
         * @return boolean
         * @return
         **/
        public function hasGroup($group)
        {
            foreach(array_values($this->groups) as $gr)
                if($gr['group_id'] == $group)
                    return true;
            return false;
        }   // end function hasGroup()

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
            if($this->is_root()) return true;
            if(!is_numeric($module))
            {
                $module_data = Addons::getDetails($module);
                $module      = $module_data['addon_id'];
            }
            return isset($this->modules[$module]);
        }   // end function hasModulePerm()

        /**
         *
         * @access public
         * @return
         **/
        public function hasPagePerm($page_id,$perm='pages_view')
        {
            if($this->is_root()) return true;
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Rechte im Frontend
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            return ( $this->hasPerm($perm) || $this->isOwner($page_id) );
        }   // end function hasPagePerm()

        /**
         *
         * @access public
         * @return
         **/
        public function hasPathPerm($path)
        {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Wie speichern wir das???
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            return true;
        }   // end function hasPathPerm()
        

        /**
         * checks if the current user has the given permission
         *
         * @access public
         * @param  string  $group     - permission group
         * @param  string  $perm      - required permission
         **/
        public function hasPerm($perm)
        {
            if($this->is_root())        return true;
            if(!is_array($this->perms)) return false;
            return array_key_exists($perm,$this->perms);
        }   // end function hasPerm()

        /**
         * Check if the user is authenticated
         *
         * @access public
         * @return boolean
         **/
        public function is_authenticated()
        {
            if(!isset($this->user) || $this->user['user_id'] == 2)
                self::getInstance();
            if(isset($this->user) && $this->user['user_id'] != 2)
                return true;
            else
                return false;
        }   // end function is_authenticated()

        /**
         * Check if current user is superuser (the one who installed the CMS)
         *
         * @access public
         * @return boolean
         **/
        public function is_root()
        {
            if(isset($this->user) && $this->user['user_id'] == 1)
                return true;
            else
                // member of admin group
                if($this->hasGroup(1))
                    return true;
                else
                    return false;
        }   // end function is_root()

        /**
         *
         * @access public
         * @return
         **/
        public function isOwner($page_id)
        {
            if(isset($this->user) && $this->is_root())
                return true;
            if(count($this->pages) && in_array($page_id,$this->pages))
                return true;
            return false;
        }   // end function isOwner()
        

        /**
         * reset the user object (to guest user)
         *
         * @access public
         * @return void
         **/
        public function reset()
        {
            self::log()->addDebug('reset()');
            $this->user   = array('user_id'=>2,'display_name'=>'unknown','username'=>'unknown');
            $this->roles  = array();
            $this->perms  = array();
            $this->groups = array();
            $this->pages  = array();
        }   // end function reset()

        /**
         *
         * @access public
         * @return
         **/
        public function tfa_enabled()
        {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: check global setting and group settings
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if($this->getID() == 2 || $this->get('tfa_enabled') == 'Y')
            {
                return true;
            }
            return false;
        }   // end function tfa_enabled()
        

        /**
         *
         * @access protected
         * @return
         **/
        protected function initUser($id)
        {
            $fieldname = ( is_numeric($id) ? 'user_id' : 'username' );

            if(!isset(self::$users[$id]))
            {
                $this->log()->addDebug(sprintf('init user with id: [%d]',$id));
                // read user from DB
                $get_user = self::db()->query(
                    'SELECT `user_id`, `username`, `display_name`, `email`, `language`, `home_folder`, `tfa_enabled`, `tfa_secret` '.
                    'FROM `:prefix:rbac_users` WHERE `'.$fieldname.'`=:id',
                    array('id'=>$id)
                );

                // load data into object
        		if($get_user->rowCount() != 0)
                {
        			$this->user = $get_user->fetch(\PDO::FETCH_ASSOC);
                    $this->id   = $id = $this->user['user_id'];
                    #$this->log()->addDebug('user data:'.print_r($this->user,1));
                    $this->initRoles();
                    #$this->log()->addDebug('user roles:'.print_r($this->roles,1));
                    $this->initGroups();
                    #$this->log()->addDebug('user groups:'.print_r($this->groups,1));
                    $this->initPerms();
                    #$this->log()->addDebug('user permissions:'.print_r($this->perms,1));
                    $this->initPages();

                    // cache
                    self::$users[$id] = $this->user;
                }
            }
            return ( isset(self::$users[$id]) ? self::$users[$id] : false );
        }   // end function initUser()

        /**
         * get user roles
         *
         * @access protected
         * @return void
         **/
        protected function initRoles()
        {
            $this->roles = Roles::getInstance()->getRoles(
                array('for'=>'user','user'=>$this->user['user_id'])
            );
        }   // end function initRoles()

        /**
         *
         * @access protected
         * @return
         **/
        protected function initPerms()
        {
            if(!$this->is_root())
            {
                if(is_array($this->roles))
                {
                    // use the QueryBuilder
                    $query  = $this->db()->qb();
                    $query2 = $this->db()->qb();
                    $params = $params2 = array();

                    // query for role permissions
                    $query->select('*')
                          ->from($this->db()->prefix().'rbac_rolepermissions','t1')
                          ->join('t1',$this->db()->prefix().'rbac_permissions','t2','t1.perm_id=t2.perm_id')
                          ;

                    // query for module permissions
                    $query2->select('*')
                           ->from($this->db()->prefix().'rbac_role_has_modules','t1')
                           ->join('t1',$this->db()->prefix().'addons','t2','t1.addon_id=t2.addon_id')
                           ;

                    // add the roles
                    foreach($this->roles as $role)
                    {
                        $query->orWhere('t1.role_id=?');
                        $params[] = $role['role_id'];
                        $query2->orWhere('t1.role_id=?');
                        $params2[] = $role['role_id'];
                    }

                    $query->setParameters($params);
                    $query2->setParameters($params2);

                    $sth   = $query->execute();
                    $perms = $sth->fetchAll();
                    $sth2  = $query2->execute();
                    $mods  = $sth2->fetchAll();

/*
$query
(
            [role_id] => 1
            [perm_id] => 2
            [AssignmentDate] =>
            [area] => pages
            [title] => pages_add_l0
            [description] => Create root pages (level 0)
            [position] => 1
            [requires] => 7
        )
$query2
(
            [role_id] => 1
            [addon_id] => 27
            [type] => module
            [directory] => ckeditor4
            [name] => CKEditor 4
            [description] => CKEditor 4
            [function] => wysiwyg
            [version] =>
            [guid] =>
            [platform] =>
            [author] =>
            [license] =>
            [installed] =>
            [upgraded] =>
            [removable] => Y
            [bundled] => N
        )

*/
                    foreach(array_values($perms) as $perm)
                        $this->perms[$perm['title']] = $perm['role_id'];
                    foreach(array_values($mods) as $mod)
                        $this->modules[$mod['addon_id']] = true;

                }
            }
        }   // end function initPerms()

        /**
         * get the list of pages a user owns
         *
         * @access protected
         * @return
         **/
        protected function initPages()
        {
            if(!$this->is_root())
            {
                $q     = 'SELECT * FROM `:prefix:rbac_pageowner` AS t1 '
                       . 'WHERE `t1`.`owner_id`=?';
                $sth   = $this->db()->query($q,array($this->user['user_id']));
                $pages = $sth->fetchAll(\PDO::FETCH_ASSOC);
                $this->log()->addDebug(
                    sprintf('found [%d] pages for owner [%d]',
                    count($pages),$this->user['user_id'])
                );
                foreach(array_values($pages) as $pg)
                {
                    $this->pages[] = $pg['page_id'];
                }
            }
        }   // end function initPages()
        
        /**
         *
         * @access protected
         * @return
         **/
        protected function initGroups()
        {
            $this->groups = \CAT\Helper\Users::getUserGroups($this->user['user_id']);
        }   // end function initGroups()

    } // class User

} // if class_exists()