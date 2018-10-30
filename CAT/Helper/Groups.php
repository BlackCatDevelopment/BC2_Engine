<?php

/**
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 3 of the License, or (at
 *   your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful, but
 *   WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *   General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 *   @author          Black Cat Development
 *   @copyright       2013 - Black Cat Development
 *   @link            http://blackcat-cms.org
 *   @license         http://www.gnu.org/licenses/gpl.html
 *   @category        CAT_Core
 *   @package         CAT_Core
 *
 */

namespace CAT\Helper;
use \CAT\Base as Base;

if (!class_exists('\CAT\Helper\Groups'))
{
    class Groups extends Base
    {

        /**
         *
         * @access public
         * @return
         **/
        public static function addGroup($name,$description)
        {
            if(!self::user()->is_authenticated() || !self::user()->hasPerm('groups_add'))
                return false;
            $sth = self::db()->query(
                  'INSERT INTO `:prefix:rbac_groups` ( `group_title`, `group_description` ) '
                . 'VALUES ( :name, :desc )',
                array('name'=>$name,'desc'=>$description)
            );
            if(!self::db()->isError()) return true;
            else                       return false;
        }   // end function addGroup()

        /**
         *
         * @access public
         * @return
         **/
        public static function exists($item)
        {
            $groups = self::getGroups();
            foreach($groups as $group)
            {
                if(is_numeric($item)  && $group['group_id'] == $item) return true;
                if(!is_numeric($item) && !strcasecmp($group['group_title'],$item)) return true;
            }
            return false;
        }   // end function exists()

        /**
         *
         * @access public
         * @return
         **/
        public static function getGroup($id)
        {
            $groups = self::getGroups();
            foreach($groups as $group)
                if($group['group_id'] == $id) return $group;
            return false;
        }   // end function getGroup()

        /**
         * get a list of groups; optional $user_id
         *
         * @access public
         * @param  integer  $user_id - member id
         * @return array
         **/
        public static function getGroups($user_id=NULL)
        {
            $params = array();
            $qb     = self::db()->qb();

            $qb->select('`t2`.*')
               ->from(self::db()->prefix().'rbac_groups','t2')
               ->leftJoin('t2',self::db()->prefix().'rbac_usergroups','t3','`t2`.`group_id`=`t3`.`group_id`');

            if($user_id)
            {
echo "ACHTUNG ACHTUNG ACHTUNG! DAS SQL-STATEMENT STIMMT NOCH NICHT!<br />";
echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br />";
exit;
                $qb->join('t2',self::db()->prefix().'rbac_users','t1','`t1`.`user_id`=`t2`.`user_id`')
                   ->where('`t1`.`user_id`=?')
                   ->setParameter(0,$user_id);
            }

            $sth = $qb->execute();
            $data = $sth->fetchAll();

            return $data;
        }   // end function getGroups()

        /**
         *
         * @access public
         * @return
         **/
        public static function getMembers($group_id)
        {
            $q    = 'SELECT * FROM `:prefix:rbac_groups` AS t1 '
                  . 'JOIN `:prefix:rbac_usergroups` AS t2 '
                  . 'ON `t1`.`group_id`=`t2`.`group_id` '
                  . 'JOIN `:prefix:rbac_users` AS t3 '
                  . 'ON `t2`.`user_id`=`t3`.`user_id` '
                  . 'WHERE `t1`.`group_id` = :id'
                  ;
            $sth  = self::db()->query(
                $q, array('id'=>$group_id)
            );
            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        }   // end function getMembers()

        /**
         *
         * @access public
         * @return
         **/
        public static function removeGroup($id)
        {
            if(!self::user()->is_authenticated() || self::user()->hasPerm('groups_delete'))
                return false;
            $sth = self::db()->query(
                'DELETE FROM `:prefix:rbac_groups` WHERE `group_id`=:id',
                array('id'=>$id)
            );
            if(!self::db()->isError()) return true;
            else                       return false;
        }   // end function removeGroup()

        /**
         *
         * @access public
         * @return
         **/
        public static function set($field,$value,$id)
        {
            if(!self::user()->is_authenticated() || self::user()->hasPerm('groups_modify'))
                return false;
            $sth = self::db()->query(
                'UPDATE `:prefix:rbac_groups` SET `:fieldname:`=:value WHERE `group_id`=:id',
                array('fieldname'=>$field,'value'=>$value,'id'=>$id)
            );
            if(!self::db()->isError()) return true;
            else                       return false;
        }   // end function set()

    } // class Groups

} // if class_exists()