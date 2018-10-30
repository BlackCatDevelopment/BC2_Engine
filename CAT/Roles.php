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

if (!class_exists('\CAT\Roles'))
{
    class Roles extends Base
    {
        protected        $roles    = array();
        protected static $instance = NULL;
        protected static $loglevel = \Monolog\Logger::EMERGENCY;

        /**
         * create a new roles object
         *
         * @access public
         * @return object
         **/
        public function __construct($id=NULL)
        {
            parent::__construct();
            $this->initRoles();
        }   // end function __construct()

        /**
         * get singleton
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
        public function addRole($name,$description)
        {
            $sth = self::db()->query(
                   'INSERT INTO `:prefix:rbac_roles` ( `title`, `description` ) '
                 . 'VALUES ( :name, :desc )',
                array('name'=>$name,'desc'=>$description)
            );
            return (is_object($sth)) ? true : false;
        }   // end function addRole()

        /**
         *
         * @access public
         * @return
         **/
        public function exists($item)
        {
            if(!$this->roles) $this->initRoles();
            foreach($this->roles as $role)
            {
                if(is_numeric($item)  && $role['role_id'] == $item) return true;
                if(!is_numeric($item) && strcasecmp($role['title'],$item)===0) return true;
            }
            return false;
        }   // end function exists()

        /**
         *
         * @access public
         * @return
         **/
        public function getGroups($id)
        {
            return $this->getRoles(array('group'=>$id));
        }   // end function getGroups()

        /**
         *
         * @access public
         * @return
         **/
        public function getRole($id)
        {
            if(!$this->roles) $this->initRoles();
            foreach($this->roles as $role)
            {
                if($role['role_id'] == $id) return $role;
            }
            return false;
        }   // end function getRole()

        /**
         * get roles (optionally filtered by given conditions)
         *
         * to get the roles for a specific user, pass:
         *    $opt = array('for'=>'user','user_id'=><user_id>)
         * to get the roles for a specific group, pass:
         *    $opt = array('for'=>'group','group_id'=><group_id>)
         * to get the users for a given role, pass:
         *    $opt = array('for'=>'user','role_id'=><role_id>)
         *
         * to get all roles, don't pass any options
         *
         * @access public
         * @param  array   $opt - optional options array
         * @return array
         **/
        public static function getRoles($opt=NULL)
        {
            if(is_array($opt))
            {
                if(!isset($opt['for']) || !in_array($opt['for'],array('user','group')))
                    return false;

                $table = $opt['for'];
                $query = 'SELECT * '
                       . 'FROM `:prefix:rbac_%sroles` AS t1 '
                       . 'JOIN `:prefix:rbac_roles` AS t2 '
                       . 'ON t1.`role_id`=t2.`role_id` '
                       ;
                $query_options = array();

                if(isset($opt['user_id']) && strlen($opt['user_id']))
                {
                    $query .= 'WHERE t1.`'.$table.'_id`=:id';
                    $query_options = array('id'=>$opt['user_id']);
                }
                if(isset($opt['group_id']) && strlen($opt['group_id']))
                {
                    $query .= 'WHERE t1.`'.$table.'_id`=:id';
                    $query_options = array('id'=>$opt['group_id']);
                }
                if(isset($opt['role_id']) && strlen($opt['role_id']))
                {
                    $query .= 'WHERE t2.`role_id`=:id';
                    $query_options = array('id'=>$opt['role_id']);
                }

                $sth = self::db()->query(
                    sprintf($query,$table),
                    $query_options
                );
                return $sth->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $query = 'SELECT * FROM `:prefix:rbac_roles`';
                $sth = self::db()->query($query);
                return $sth->fetchAll(\PDO::FETCH_ASSOC);
            }
        }   // end function getRoles()

        /**
         * get users for given role
         *
         * @access public
         * @return array
         **/
        public function getUsers($id)
        {
            return $this->getRoles(array('for'=>'user','role_id'=>$id));
        }   // end function getUsers()

        /**
         *
         * @access public
         * @return
         **/
        public function removeRole($id)
        {
            $dbh  = self::db();
            $sth  = $dbh->query(
                'DELETE FROM `:prefix:rbac_roles` WHERE `role_id`=:id',
                array('id'=>$id)
            );
            if(!$dbh->isError()) return true;
            else                 return false;
        }   // end function removeRole()

        /**
         *
         * @access public
         * @return
         **/
        public function set($field,$value,$id)
        {
            $dbh  = self::db();
            $sth  = $dbh->query(
                'UPDATE `:prefix:rbac_roles` SET `:fieldname:`=:value WHERE `role_id`=:id',
                array('fieldname'=>$field,'value'=>$value,'id'=>$id)
            );
            if(!$dbh->isError()) return true;
            else                 return false;
        }   // end function set()

        /**
         * init roles; loads all defined roles from the database and stores
         * the list into protected 'roles' array
         *
         * @access protected
         * @return void
         **/
        protected function initRoles()
        {
            $dbh  = self::db();
            $sth  = $dbh->query(
                'SELECT * FROM `:prefix:rbac_roles`'
            );
            $this->roles = $sth->fetchAll(\PDO::FETCH_ASSOC);
        }   // end function initRoles()

    } // class Roles

} // if class_exists()