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

namespace CAT\Backend;
use \CAT\Base as Base;
use \CAT\Helper\Json as Json;

if (!class_exists('\CAT\Backend\Groups'))
{
    class Groups extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance = NULL;

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
        public static function addmember()
        {
print_r($_REQUEST);
        }   // end function addmember()

        public static function add()
        {
            if(!Base::user()->hasPerm('groups_add'))
                self::printError('You are not allowed for the requested action!');

            $val   = \CAT\Helper\Validate::getInstance();
            $name  = $val->sanitizePost('group_name');
            $desc  = $val->sanitizePost('group_description');
            if(\CAT\Helper\Groups::exists($name))
                Json::printError('A group with the same name already exists!');

            $result = \CAT\Helper\Groups::addGroup($name,$desc);

            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo Json::printSuccess('Success');
                return;
            }

            self::router()->reroute(CAT_BACKEND_PATH.'/groups');
        }

        /**
         * delete a group; requires the group id as route param
         *    example: /groups/delete/99
         * prints JSON result (success or error) to STDOUT
         *
         * @access public
         * @return void
         **/
        public static function delete()
        {
            if(!self::user()->hasPerm('groups_delete'))
                Json::printError('You are not allowed for the requested action!');
            $val   = \CAT\Helper\Validate::getInstance();
            $id    = $val->sanitizePost('id');
            if(!\CAT\Helper\Groups::exists($id))
                Json::printError('No such group!');
            $group = \CAT\Groups::getInstance()->getGroup($id);
            if($group['builtin']=='Y')
                Json::printError('Built-in elements cannot be removed!');
            $res   = \CAT\Helper\Groups::removeGroup($id);
            Base::json_result($res,($res?'':'Failed!'),($res?true:false));
        }   // end function delete()

        /**
         *
         * @access public
         * @return
         **/
        public static function deleteuser()
        {
            if(!self::user()->hasPerm('groups_users'))
                Json::printError('You are not allowed for the requested action!');
            $id   = self::router()->getParam();
            if(self::user()->hasGroup($id))
            {
            }
        }   // end function deleteuser()
        

        /**
         * edit group attribute set by param 'name'
         *
         * @access public
         * @return void
         **/
        public static function edit()
        {
            if(!self::user()->hasPerm('groups_edit'))
                Json::printError('You are not allowed for the requested action!');
            $val = \CAT\Helper\Validate::getInstance();
            $field = $val->sanitizePost('name');
            $id    = $val->sanitizePost('pk');
            $value = $val->sanitizePost('value');
            \CAT\Groups::set($field,$value,$id);
        }   // end function edit()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function index($id=NULL)
        {
            $params = self::router()->getParams();
            if(is_array($params) && count($params))
            {
                switch($params[0])
                {
                    case 'deleteuser':
                        $user = new CAT_User($params[1]);
                        if($user->hasGroup($id))
                        {
                         #   self::db()->query(
                         #       'DELETE FROM `:prefix:rbac_usergroups` WHERE `user_id`=? AND `group_id`=?',
                         #       array($params[1],$id)
                         #   );
                        }
                        break;
                }
                if(self::asJSON())
                {
                    echo header('Content-Type: application/json');
                    echo Json::printSuccess('Success');
                    return;
                }
            }

            $tpl_data = array(
                'groups' => \CAT\Helper\Groups::getGroups(),
            );

            foreach($tpl_data['groups'] as $i => $g)
            {
                $members = \CAT\Helper\Groups::getMembers($g['group_id']);
                $roles   = \CAT\Roles::getRoles(array('for'=>'group','id'=>$g['group_id']));
                $tpl_data['groups'][$i]['member_count'] = count($members);
                $tpl_data['groups'][$i]['role_count']   = count($roles);
            }
            \CAT\Backend::printHeader();
            self::tpl()->output('backend_groups', $tpl_data);
            \CAT\Backend::printFooter();
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function users()
        {
            if(!Base::user()->hasPerm('groups_users'))
                Json::printError('You are not allowed for the requested action!');
            $id    = self::router()->getParam();
            $users = \CAT\Groups::getMembers($id);
            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode($users,true);
                return;
            }

            $tpl_data = array(
                'members' => $users
            );
            \CAT\Backend::printHeader();
            self::tpl()->output('backend_groups_members', $tpl_data);
            \CAT\Backend::printFooter();
        }   // end function users()

    } // class \CAT\Helper\Groups

} // if class_exists()