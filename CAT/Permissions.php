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

declare(strict_types=1);

namespace CAT;

use CAT\Base as Base;
use CAT\Helper\DB        as DB;

if(!class_exists('\CAT\Permissions',false))
{
    class Permissions extends Base
    {
        protected        $perms         = null;
        protected        $perms_by_role = null;
        protected static $instance      = null;
        protected static $loglevel      = \Monolog\Logger::EMERGENCY;

        /**
         * create a new permissions object
         *
         * @access public
         * @return object
         **/
        public function __construct()
        {
            parent::__construct();
            if(!$this->perms)
                $this->initPerms();
        }   // end function __construct()

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }

        /**
         * returns the permissions for the given role
         *
         * @access public
         * @param  integer  $role_id
         * @return array
         **/
        public function getPerms(int $role_id=0)
        {
            if(!$this->perms) $this->initPerms();

            if($role_id)
            {
                if(!isset($this->perms_by_role[$role_id]))
                {
                    $sth = self::db()->query(
                          'SELECT * FROM `:prefix:rbac_permissions` AS `t1` '
                        . 'JOIN `:prefix:rbac_rolepermissions` AS `t2` '
                        . 'ON `t1`.`perm_id`=`t2`.`perm_id` '
                        . 'WHERE `t2`.`role_id`=? '
                        . 'ORDER BY `group`,`requires`,`position`',
                        array($role_id)
                    );
                    $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
                    $this->perms_by_role[$role_id] = $data;
                    return $data;
                }
            }

            return $this->perms;
        }   // end function getPerms()

        /**
         * init permissions; loads the permissions assigned to each role and
         * stores the assignments into protected 'perms' array
         *
         * @access protected
         * @return
         **/
        protected function initPerms()
        {
            // do not use $self here, it will lead to infinite loop!
            $roles = \CAT\Roles::getInstance()->getRoles();
            if(!is_array($roles) || !count($roles)) return array();

            $q   = 'SELECT * FROM `:prefix:rbac_permissions` ORDER BY `group`,`requires`,`position`';
            $sth = self::db()->query($q);
            $this->perms = $sth->fetchAll(\PDO::FETCH_ASSOC);

            // translate description
            foreach($this->perms as $i => $item)
            {
                $this->perms[$i]['description'] = self::lang()->t($item['description']);
            }
        }   // end function initPerms()

    } // class Permissions

} // if class_exists()