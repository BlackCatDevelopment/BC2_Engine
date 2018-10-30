<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       2018 Black Cat Development
   @link            https://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

namespace CAT\Backend;
use \CAT\Base as Base;
use \CAT\Helper\Validate as Validate;

if (!class_exists('\CAT\Backend\Roles'))
{
    class Roles extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance = NULL;

        /**
         * Singleton
         *
         * @access public
         * @return object
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
        public static function add()
        {
            // check permissions
            if(!self::user()->hasPerm('roles_add'))
                self::printFatalError('You are not allowed for the requested action!');

            $name  = Validate::sanitizePost('role_name');
            $desc  = Validate::sanitizePost('role_description');

            $sth = self::db()->query(
                  'INSERT INTO `:prefix:rbac_roles` ( `role_title`, `role_description` ) '
                . 'VALUES ( :name, :desc )',
                array('name'=>$name,'desc'=>$desc)
            );

            if(!self::db()->isError()) return true;
            else                       return false;

        }   // end function add()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if(!self::user()->hasPerm('roles_perms'))
                self::printFatalError('You are not allowed for the requested action!');

            // get all roles
            $roles = self::role()->getRoles();

            // perms to nested list
            $renderer  = new \wblib\wbList\Formatter\ListFormatter();
            $list      = $renderer->render(new \wblib\wbList\Tree(
                self::perms()->getPerms(),
                array('id'=>'perm_id','parent'=>'requires','value'=>'description','sort'=>true)
            ));

            // counter
            foreach($roles as $i => $r)
            {
                $users  = self::role()->getUsers($r['role_id']);
                $groups = self::role()->getGroups($r['role_id']);
                $perms  = self::perms()->getPerms($r['role_id']);
                $roles[$i]['user_count']  = ( is_array($users)  ? count($users)  : 0 );
                $roles[$i]['group_count'] = ( is_array($groups) ? count($groups) : 0 );
                $roles[$i]['perm_count']  = ( is_array($perms)  ? count($perms)  : 0 );
            }

            $tpl_data = array(
                'roles' => $roles,
                'perms' => $list,
            );

            \CAT\Backend::printHeader();
            self::tpl()->output('backend_roles', $tpl_data);
            \CAT\Backend::printFooter();
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function savePerms()
        {
            $roleID = self::getRoleID();

            if(!self::user()->hasPerm('roles_perms'))
                self::printFatalError('You are not allowed for the requested action!');

            $perms = \CAT\Helper\Validate::sanitizePost('perms');

            if(is_array($perms) && count($perms)>0) {
                self::db()->query(
                    'DELETE FROM `:prefix:rbac_rolepermissions` WHERE `role_id`=?',
                    array($roleID)
                );
                for($i=0;$i<count($perms);$i++) {
                    self::db()->query(
                          'INSERT INTO `:prefix:rbac_rolepermissions` '
                        . '(`role_id`, `perm_id`,`AssignmentDate`) '
                        . 'VALUES(?, ?, ?)',
                        array($roleID,$perms[$i],time())
                    );
                }
            }

            if(self::asJSON())
            {
                echo Json::printResult(
                    ( self::db()->isError() ? false : true ),
                    'Success'
                );
                return;
            }

        }   // end function savePerms()
        
        /**
         * tries to retrieve 'page_id' by checking (in this order):
         *
         *    - $_POST['page_id']
         *    - $_GET['page_id']
         *    - Route param['page_id']
         *
         * also checks for numeric value
         *
         * @access private
         * @return integer
         **/
        protected static function getRoleID()
        {
            $roleID  = \CAT\Helper\Validate::sanitizePost('role_id','numeric');

            if(!$roleID)
                $roleID  = \CAT\Helper\Validate::sanitizeGet('role_id','numeric');

            if(!$roleID)
                $roleID = self::router()->getParam(-1);

            if(!$roleID)
                $roleID = self::router()->getRoutePart(-1);

            return intval($roleID);
        }   // end function getRoleID()

    } // class \CAT\Helper\Roles

} // if class_exists()