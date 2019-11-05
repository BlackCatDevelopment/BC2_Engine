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
use \CAT\Helper\FormBuilder as FormBuilder;
use \CAT\Helper\JSON as JSON;

if (!class_exists('\CAT\Backend\Users')) {
    class Users extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance = null;
        protected static $avail_settings = null;
        protected static $debug    = false;

        /**
         * Singleton
         *
         * @access public
         * @return object
         **/
        public static function getInstance()
        {
            if (!is_object(self::$instance)) {
                self::$instance = new self();
            }
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
            if (!self::user()->hasPerm('users_membership')) {
                JSON::printError('You are not allowed for the requested action!');
            }
            $id   = self::getItem('bygroup');
            $data = \CAT\Helper\Groups::getMembers($id);
            if (self::asJSON()) {
                echo header('Content-Type: application/json');
                echo json_encode($data, true);
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
            if (!self::user()->hasPerm('user_delete')) {
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            }
            $userID = self::getItem('user_id','is_numeric');
            $userData = \CAT\Helper\Users::getDetails($userID);

            $form = FormBuilder::generateForm('be_edit_user');
            $form->setAttribute('_auto_buttons',false);
            $form->setAttribute('action', CAT_ADMIN_URL.'/users/edit');
            $form->addElement(new \wblib\wbForms\Element\Hidden('user_id',array('value'=>$userID)));
            
            $form->setData($userData);
#echo "is sent? [", $form->isSent(), "]<br />";
            // form already sent?
            if ($form->isSent()) {
                // check data
                if ($form->isValid()) {
                    // save data
                    $data = $form->getData();
                    $query = self::db()->qb();
                    $query->update(self::db()->prefix().'rbac_users')
                          ->where($query->expr()->eq('user_id', $userData['user_id']));

                    foreach($form->getElements() as $e) {
                        $fieldname = $e->getName();
                        if($fieldname=='user_id') {
                            continue;
                        }

                        if(isset($data[$fieldname])) {
                            // checkbox
                            if(is_array($data[$fieldname])) {
                                $data[$fieldname] = $data[$fieldname][0];
                            }
                            $query->set($fieldname, $query->expr()->literal($data[$fieldname]));
                        }
                    }
                    $sth   = $query->execute();
                    if (self::db()->isError()) {
                        if (self::asJSON()) {
                            JSON::printError(self::db()->getError());
                        }
                    } else {
                        if (self::asJSON()) {
                            JSON::printSuccess('success');
                        }
                    }
                    return;
                }
            }

            if (self::asJSON()) {
                echo header('Content-Type: application/json');
                echo json_encode(array(
                    'form' => $form->render(true),
                ), true);
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
            if (!self::user()->hasPerm('user_delete')) {
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            }
            $id   = self::router()->getParam();
            if (\CAT\Helper\Users::deleteUser($id)!==true) {
                if (self::asJSON()) {
                    echo \CAT\Helper\Json::printError('Unable to delete the user');
                } else {
                    self::printFatalError('Unable to delete the user');
                }
            } else {
                if (self::asJSON()) {
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
            if (count($data)) {
                foreach ($data as $i => $user) {
                    $data[$i]['groups'] = \CAT\Helper\Users::getUserGroups($user['user_id']);
                }
            }
            if (self::asJSON()) {
                echo header('Content-Type: application/json');
                echo json_encode($data, true);
                return;
            }
            $tpl_data = array(
                'users' => $data,
                'userform' => self::renderForm($data),
            );
            Backend::show('backend_users', $tpl_data);
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function notingroup()
        {
            if (!self::user()->hasPerm('users_membership')) {
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            }
            $id    = self::router()->getParam();
            $users = \CAT\Helper\Users::getUsers(array('group_id'=>$id,'not_in_group'=>true));
            if (self::asJSON()) {
                echo header('Content-Type: application/json');
                echo json_encode($users, true);
                return;
            }
        }   // end function notingroup()

        /**
         *
         * @access public
         * @return
         **/
        public static function profile()
        {
            if (!self::user()->hasPerm('my_profile')) {
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            }

            $userID = self::session()->get('userID');
            $userData = \CAT\Helper\Users::getDetails($userID);

            $form = FormBuilder::generateForm('my_profile');
            $form->setData($userData);

// !!!!! TODO: Die erlaubten Routen eines Benutzers auslesen und als
// !!!!!       default_page anbieten
            $pages_list = array();
            foreach(array_values(array(
                'profile',        // edit own profile
                'dashboard',      // see dashboard
                'administration', // see admin area
                'content',        // see content area
            )) as $perm) {
                if(self::user()->hasPerm($perm)) {
                    $pages_list[$perm] = self::lang()->t($perm);
                }
            }
            $form->getElement('default_page')->setData($pages_list);
            #$form->getElement('default_page')->setValue($userData['default_page']);

            \CAT\Backend::show('profile',array(
                'form' => $form->render(true),
            ));

        }   // end function profile()

        /**
         *
         * @access public
         * @return
         **/
        public static function save()
        {
            $userID = self::getItemID('user_id', '\CAT\Helper\Users::exists');
            if(empty($userID)) {
                Base::printFatalError('Invalid data!');
            }

            if (!self::user()->hasPerm('users_edit')) {
                self::printFatalError('You are not allowed for the requested action!');
            }


        }   // end function save()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function tfa()
        {
            if (!self::user()->hasPerm('users_edit')) {
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            }
            $id   = self::router()->getParam();
            $user = new CAT_User($id);
            $tfa  = $user->get('tfa_enabled');
            $new  = ($tfa == 'Y' ? 'N' : 'Y');
            self::db()->query(
                'UPDATE `:prefix:rbac_users` SET `tfa_enabled`=? WHERE `user_id`=?',
                array($new,$id)
            );
            if (self::db()->isError()) {
                echo \CAT\Helper\Json::printError('Unable to save');
            } else {
                echo \CAT\Helper\Json::printSuccess('Success');
            }
        }   // end function tfa()

        /**
         * get available settings
         **/
        protected static function getSettings()
        {
            if (!self::$avail_settings) {
                $data = self::db()->query(
                    'SELECT * FROM `:prefix:rbac_user_settings` AS `t1` '
                    . 'JOIN `:prefix:forms_fieldtypes` AS `t2` '
                    . 'ON `t1`.`fieldtype`=`t2`.`type_id` '
                    . 'WHERE `is_editable`=? '
                    . 'ORDER BY `fieldset` ASC, `position` ASC',
                    array('Y')
                );
                if ($data) {
                    self::$avail_settings = $data->fetchAll();
                }
            }
            return self::$avail_settings;
        }   // end function getSettings()

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
