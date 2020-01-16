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

namespace CAT\Objects;

use CAT\Base as Base;
use CAT\Roles as Roles;
use CAT\Helper\Validate as Validate;
use CAT\Helper\Directory as Directory;

if (!class_exists('\CAT\Objects\User'))
{
    class User extends Base
    {
        // log level
        protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        #protected static $loglevel  = \Monolog\Logger::DEBUG;

        /**
         * array to hold the user data
         **/
        protected $user      = array();
        // user ID
        protected $id        = null;
        // array to hold the user roles
        protected $roles     = array();
        // array to hold the permissions
        protected $perms     = array();
        // array to hold the module permissions
        protected $modules   = array();
        // array to hold the user groups
        protected $groups    = array();
        // array to hold the list of pages a user owns
        protected $pages     = array();
        // last user error
        protected $lasterror = null;

        /**
         * create a new user object
         *
         * @access public
         * @param  integer  $id - user id
         * @return object
         **/
        public function __construct($id=null)
        {
            self::log()->addDebug(sprintf(
                'constructor called with param [%s]',
                $id
            ));
            parent::__construct();
            $this->reset();       // make sure there is no old data
            $this->initUser($id); // load user
        }   // end function __construct()

        /**
         *
         * @access public
         * @return
         **/
        public function checkPermission($perm)
        {
            $perms_to_check = array();
            if(is_array($perm)) {
                $perms_to_check = $perm;
            } elseif (is_string($perm)) {
                $perms_to_check[] = $perm;
            }
            if(count($perms_to_check)>0) {
                foreach($perms_to_check as $i => $p) {
                    if (!$his->hasPerm($p)) {
                        // fatal error always stops execution
                        self::printFatalError('You are not allowed for the requested action!');
                    }
                }
            }
        }   // end function checkPermission()

        /**
         * get user attribute; returns NULL if the given attribute is not set
         *
         * @access public
         * @param  string  $attr - attribute name
         * @return mixed   value of $attr or NULL if not set
         **/
        public function get($attr=null)
        {
            if (isset($this->user)) {
                if ($attr) {
                    if (isset($this->user[$attr])) {
                        return $this->user[$attr];
                    }
                    return null;
                }
                return (array)$this->user;
            } else {
                return null;
            }
        }   // end function get()

        /**
         * TODO: Aus den Benutzereinstellungen auslesen
         *
         * @access public
         * @return
         **/
        public function getDefaultPage()
        {
            $path = $this->get('default_page');
            if(empty($path)) {
                $path = "dashboard";
            }
            return $path;
        }   // end function getDefaultPage()
        

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
            if ($ids_only && is_array($this->groups) && count($this->groups)) {
                $ids = array();
                foreach (array_values($this->groups) as $item) {
                    $ids[] = $item['group_id'];
                }
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
            if ($this->isRoot()) {
                return ($relative ? self::getSetting('media_directory') : $default);
            }
            $home = $this->get('home_folder');
            if (strlen($home)) {
                return (
                      $relative
                    ? $home
                    : Directory::sanitizePath(CAT_PATH.'/'.$home)
                );
            }
            return ($relative ? self::getSetting('media_directory') : $default);
        }   // end function getHomeFolder()

        /**
         *
         * @access public
         * @return
         **/
        public function getID()
        {
            return ($this->id ? $this->id : 2);
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
         * checks if the user is member of the given group
         *
         * @access public
         * @param  integer  $group
         * @return boolean
         * @return
         **/
        public function hasGroup($group)
        {
            foreach (array_values($this->groups) as $gr) {
                if ($gr['group_id'] == $group) {
                    return true;
                }
            }
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
            if ($this->isRoot()) {
                return true;
            }
            if (!is_numeric($module)) {
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
        public function hasPagePerm($page_id, $perm='pages_view')
        {
            if ($this->isRoot()) {
                return true;
            }
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // TODO: Rechte im Frontend
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            return ($this->hasPerm($perm) || $this->isOwner($page_id));
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
         * @param  string  $perm      - required permission
         **/
        public function hasPerm($perm)
        {
            if ($this->isRoot()) {
                return true;
            }
            // user permission
            if (is_array($this->perms) && array_key_exists($perm, $this->perms)) {
                self::log()->addDebug('permission granted');
                return true;
            }
            return false;
        }   // end function hasPerm()

        /**
         * Check if current user is superuser (the one who installed the CMS)
         *
         * @access public
         * @return boolean
         **/
        public function isRoot()
        {
#print_r($this->user);
            if (isset($this->user) && isset($this->user['user_id']) && $this->user['user_id'] == 1) {
                return true;
            } elseif // member of admin group
                ($this->hasGroup(1)) {
                return true;
            } else {
                return false;
            }
        }   // end function isRoot()

        /**
         *
         * @access public
         * @return
         **/
        public function isOwner($page_id)
        {
            if (isset($this->user) && $this->isRoot()) {
                return true;
            }
            if (count($this->pages) && in_array($page_id, $this->pages)) {
                return true;
            }
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
            $this->user   = array();
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
        public function tfa_enabled()
        {
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // TODO: check global setting and group settings
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if ($this->getID() == 2 || $this->get('tfa_enabled') == 'Y') {
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
            $fieldname = (is_numeric($id) ? 'user_id' : 'username');

            $this->log()->addDebug(sprintf('init user with id: [%d]', $id));

            // read user from DB
            $get_user = self::db()->query(
                'SELECT * '.
                'FROM `:prefix:rbac_users` WHERE `'.$fieldname.'`=:id',
                array('id'=>$id)
            );

            // load data into object
            if ($get_user->rowCount() != 0) {
                $this->user = $get_user->fetch(\PDO::FETCH_ASSOC);
                $this->id   = $id = $this->user['user_id'];
                #$this->log()->addDebug('user data:'.print_r($this->user,1));
                $this->initGroups();
                #$this->log()->addDebug('user groups:'.print_r($this->groups,1));
                $this->initRoles();
                #$this->log()->addDebug('user roles:'.print_r($this->roles,1));
                $this->initPerms();
                #$this->log()->addDebug('user permissions:'.print_r($this->perms,1));
                $this->initPages();
            }

            return $this->user;
        }   // end function initUser()

        /**
         * get user roles (roles directly assigned to the user)
         *
         * @access protected
         * @return void
         **/
        protected function initRoles()
        {
            $this->roles = Roles::getRoles(
                array('for'=>'user','user'=>$this->user['user_id'])
            );
            if(isset($this->groups) && count($this->groups)>0) {
                foreach($this->groups as $group) {
                    $roles = Roles::getRoles(
                        array('for'=>'group','group'=>$group['group_id'])
                    );
                    if(is_array($roles) && count($roles)>0) {
                        $this->roles = array_merge(
                            $this->roles,
                            $roles
                        );
                    }
                }
            }
        }   // end function initRoles()

        /**
         *
         * @access protected
         * @return
         **/
        protected function initPerms()
        {
            if (!$this->isRoot()) {
                if (is_array($this->roles) && count($this->roles)>0) {
                    // use the QueryBuilder
                    $query  = $this->db()->qb();
                    $query2 = $this->db()->qb();
                    $params = $params2 = array();

                    // query for role permissions
                    $query->select('*')
                          ->from($this->db()->prefix().'rbac_rolepermissions', 't1')
                          ->join('t1', $this->db()->prefix().'rbac_permissions', 't2', 't1.perm_id=t2.perm_id')
                          ;

                    // query for module permissions
                    $query2->select('*')
                           ->from($this->db()->prefix().'rbac_role_has_modules', 't1')
                           ->join('t1', $this->db()->prefix().'addons', 't2', 't1.addon_id=t2.addon_id')
                           ;

                    // add the roles
                    foreach ($this->roles as $role) {
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
                    foreach (array_values($perms) as $perm) {
                        $this->perms[$perm['title']] = $perm['role_id'];
                    }
                    foreach (array_values($mods) as $mod) {
                        $this->modules[$mod['addon_id']] = true;
                    }
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
            if (!$this->isRoot()) {
                $q     = 'SELECT * FROM `:prefix:rbac_pageowner` AS t1 '
                       . 'WHERE `t1`.`owner_id`=?';
                $sth   = $this->db()->query($q, array($this->user['user_id']));
                $pages = $sth->fetchAll(\PDO::FETCH_ASSOC);
                $this->log()->addDebug(
                    sprintf(
                        'found [%d] pages for owner [%d]',
                    count($pages),
                        $this->user['user_id']
                    )
                );
                foreach (array_values($pages) as $pg) {
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
