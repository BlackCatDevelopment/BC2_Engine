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

if (!class_exists('\CAT\Groups')) {
    class Groups extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance = null;

        public static function getInstance()
        {
            if (!is_object(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access protected
         * @return
         **/
        protected function initPerms()
        {
            if (!$this->roles) {
                $this->initRoles();
            }
            if (!is_array($this->roles) || !count($this->roles)) {
                return array();
            }

            $q   = 'SELECT * FROM `:prefix:rbac_rolepermissions` AS t1
                   JOIN `:prefix:rbac_permissions` AS t2
                   ON `t1`.`perm_id`=`t2`.`perm_id`'
                 ;
            $sth   = self::db()->query($q);
            $perms = $sth->fetchAll(\PDO::FETCH_ASSOC);

            foreach (array_values($perms) as $perm) {
                $this->perms[$perm['role_id']][] = $perm;
            }
        }   // end function initPerms()

        /**
         * get group roles
         *
         * @access protected
         * @return void
         **/
        protected function initRoles()
        {
            $this->roles = \CAT\Roles::getRoles(
                array(
                    'for' => 'group'
                )
            );
        }   // end function initRoles()
    }
}
