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

if (!class_exists('\CAT\Backend\Users'))
{
    class Users extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance = NULL;
        protected static $avail_settings = NULL;
        protected static $debug    = false;

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
         * get the list of users that are members of the given group
         *
         * @access public
         * @return
         **/
        public static function bygroup()
        {
            if(!self::user()->hasPerm('users_membership'))
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            $id   = self::router()->getParam();
            $data = \CAT\Groups::getInstance()->getMembers($id);
            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode($data,true);
                return;
            }
        }   // end function bygroup()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            if(!self::user()->hasPerm('user_delete'))
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            $userID = self::getUserID();
            $form   = self::renderForm(\CAT\Helper\Users::getDetails($userID));
            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode(array(
                    'form' => $form,
                ),true);
                return;
            }
        }   // end function edit()

        /**
         *
         * @access public
         * @return
         **/
        public static function delete()
        {
            if(!self::user()->hasPerm('user_delete'))
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            $id   = self::router()->getParam();
            if(\CAT\Helper\Users::deleteUser($id)!==true)
            {
                if(self::asJSON())
                {
                    echo \CAT\Helper\Json::printError('Unable to delete the user');
                } else {
                    self::printFatalError('Unable to delete the user');
                }
            }
            else
            {
                if(self::asJSON())
                {
                    echo \CAT\Helper\Json::printSuccess('User successfully deleted');
                } else {
                    self::printMsg('User successfully deleted');
                }
            }
        }   // end function delete()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            $data  = \CAT\Helper\Users::getUsers();
            if(count($data))
            {
                foreach($data as $i => $user)
                {
                    $data[$i]['groups'] = \CAT\Helper\Users::getUserGroups($user['user_id']);
                }
            }
            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode($data,true);
                return;
            }
            $tpl_data = array(
                'users' => $data,
                'userform' => self::renderForm($data),
            );
            \CAT\Backend::printHeader();
            self::tpl()->output('backend_users', $tpl_data);
            \CAT\Backend::printFooter();
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function notingroup()
        {
            if(!self::user()->hasPerm('users_membership'))
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            $id    = self::router()->getParam();
            $users = \CAT\Helper\Users::getUsers(array('group_id'=>$id,'not_in_group'=>true));
            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode($users,true);
                return;
            }
        }   // end function notingroup()

        /**
         *
         * @access public
         * @return
         **/
        public static function tfa()
        {
            if(!self::user()->hasPerm('users_edit'))
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            $id   = self::router()->getParam();
            $user = new CAT_User($id);
            $tfa  = $user->get('tfa_enabled');
            $new  = ( $tfa == 'Y' ? 'N' : 'Y' );
            self::db()->query(
                'UPDATE `:prefix:rbac_users` SET `tfa_enabled`=? WHERE `user_id`=?',
                array($new,$id)
            );
            if(self::db()->isError())
            {
                echo \CAT\Helper\Json::printError('Unable to save');
            }
            else
            {
                echo \CAT\Helper\Json::printSuccess('Success');
            }
        }   // end function tfa()

        /**
         * get available settings
         **/
        protected static function getSettings()
        {
            if(!self::$avail_settings)
            {
                $data = self::db()->query(
                    'SELECT * FROM `:prefix:rbac_user_settings` AS `t1` '
                    . 'JOIN `:prefix:forms_fieldtypes` AS `t2` '
                    . 'ON `t1`.`fieldtype`=`t2`.`type_id` '
                    . 'WHERE `is_editable`=? '
                    . 'ORDER BY `fieldset` ASC, `position` ASC',
                    array('Y')
                );
                if($data)
                {
                    self::$avail_settings = $data->fetchAll();
                }
            }
            return self::$avail_settings;
        }   // end function getSettings()

        /**
         * tries to retrieve 'user_id' by checking (in this order):
         *
         *    - $_POST['user_id']
         *    - $_GET['user_id']
         *    - Route param['user_id']
         *
         * also checks for numeric value
         *
         * @access private
         * @return integer
         **/
        protected static function getUserID()
        {
            $userID  = \CAT\Helper\Validate::sanitizePost('user_id','numeric');

            if(!$userID)
                $userID  = \CAT\Helper\Validate::sanitizeGet('user_id','numeric');

            if(!$userID)
                $userID = self::router()->getParam(-1);

            if(!$userID || !is_numeric($userID) || !\CAT\Helper\Users::exists($userID))
                Base::printFatalError('Invalid data')
                . (self::$debug ? '(\CAT\Backend\Users::getUserID())' : '');;

            return $userID;
        }   // end function getUserID()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function renderForm($data)
        {
            return \CAT\Helper\FormBuilder::generate(
                'edit_user',
                self::getSettings(),
                $data
            )->render(1);
        }   // end function renderForm()

    } // class \CAT\Helper\Users

} // if class_exists()