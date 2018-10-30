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


namespace CAT\Helper;
use \CAT\Base as Base;

if ( ! class_exists( 'Users', false ) )
{
	class Users extends Base
	{
        // singleton
        private static $instance        = NULL;

        /**
         * get singleton
         **/
        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         * delete a user
         *
         * @access public
         * @param  integer $user_id
         * @return mixed   true on success, db error string otherwise
         **/
        public static function deleteUser($user_id)
        {
       		self::db()->query(
                "DELETE FROM `:prefix:rbac_users` WHERE `user_id`=:id",
                array('id'=>$user_id)
            );
            return ( self::db()->isError() ? self::db()->getError() : true );
        }   // end function deleteUser()

        /**
         *
         * @access public
         * @return
         **/
        public static function exists($user_id)
        {
            $data = self::getUsers(array('user_id'=>$user_id),true);
            if($data && is_array($data) && count($data))
                return true;
            return false;
        }   // end function exists()

        /**
         *
         * @access public
         * @return
         **/
        public static function getDetails($user_id)
        {
            $data = self::getUsers(array('user_id'=>$user_id),true);
            if($data && is_array($data) && count($data))
                return $data[0];
            return array();
        }   // end function getDetails()
        
        /**
         * get users from DB; has several options to define what is requested
         *
         * @access public
         * @param  array    $opt
         * @param  boolean  $extended (default: false)
         * @return array
         **/
        public static function getUsers($opt=NULL,$extended=false)
        {
            $q    = 'SELECT `t1`.* FROM `:prefix:rbac_users` AS `t1` ';
            $p    = array();
            if(is_array($opt))
            {
                if(isset($opt['group_id']))
                {
                    $q .= 'LEFT OUTER JOIN `:prefix:rbac_usergroups` AS `t2` '
                       .  'ON `t1`.`user_id`=`t2`.`user_id` '
                       .  'WHERE ((`t2`.`group_id`'
                       .  ( isset($opt['not_in_group']) ? '!' : '' )
                       .  '=:id'
                       ;
                    $p['id'] = $opt['group_id'];
                    if(isset($opt['not_in_group']))
                    {
                        // skip users in admin group and protected users
                        $q .= ' AND `t2`.`group_id`  != 1'
                           .  ' AND `t1`.`protected` != "Y")'
                           .  ' OR `t2`.`group_id` IS NULL )';
                    }
                    else
                    {
                        $q .= '))';
                    }
                }
                if(isset($opt['user_id']))
                {
                    $q .= ' WHERE `t1`.`user_id`=:uid';
                    $p['uid'] = $opt['user_id'];
                }
            }
            $sth  = self::db()->query($q,$p);
            $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
            foreach($data as $i => $user) {
                if(strlen($user['wysiwyg'])) { // resolve wysiwyg editor
                    $data[$i]['wysiwyg'] = Addons::getDetails($user['wysiwyg'],'name');
                }
                if($extended) {
                    $sth = self::db()->query(
                        'SELECT * FROM `:prefix:rbac_user_extend` WHERE `user_id`=?',
                        array($user['user_id'])
                    );
                    $ext = $sth->fetchAll(\PDO::FETCH_ASSOC);
                    $data[$i]['extended'] = array();
                    foreach($ext as $item) {
                        $data[$i]['extended'][$item['option']] = $item['value'];
                    }
                }
            }
            return $data;
        }   // end function getUsers()

        /**
         *
         * @access public
         * @return
         **/
        public static function getUserGroups($id)
        {
            $q = 'SELECT * '
               . 'FROM `:prefix:rbac_users` AS t1 '
               . 'JOIN `:prefix:rbac_usergroups` AS t2 '
               . 'ON `t1`.`user_id`=`t2`.`user_id` '
               . 'JOIN `:prefix:rbac_groups` AS t3 '
               . 'ON `t2`.`group_id`=`t3`.`group_id` '
               . 'WHERE `t1`.`user_id`=:id'
               ;
            $sth = self::db()->query($q,array('id'=>$id));
            return $sth->fetchAll(\PDO::FETCH_ASSOC);
        }   // end function getUserGroups()
        
        
    }
}

/*******************************************************************************
 * http://aaronsaray.com/blog/2009/02/12/password-complexity-class/
 ******************************************************************************/
class Password
{
	/** constants - are arbritrary numbers - but used for bitwise **/
	const REQUIRE_LOWERCASE       = 4;
	const REQUIRE_UPPERCASE       = 8;
	const REQUIRE_NUMBER	      = 16;
	const REQUIRE_SPECIALCHAR     = 32;
	//const REQUIRE_DIFFPASS	      = 64;
	const REQUIRE_DIFFUSER	      = 128;
	const REQUIRE_UNIQUE	      = 256;
	protected $_passwordDiffLevel = 3;
	protected $_uniqueChrRequired = 4;
	protected $_complexityLevel   = 0;
	protected $_issues            = array();
	/**
	 * returns the standard options
	 * @return integer
	 */
	public function getComplexityStandard()
	{
		return self::REQUIRE_LOWERCASE + self::REQUIRE_UPPERCASE + self::REQUIRE_NUMBER;
	}
	/**
	 *returns all of the options
	 *@return integer
	 */
	public function getComplexityStrict()
	{
		$r = new ReflectionClass($this);
		$complexity = 0;
		foreach ($r->getConstants() as $constant) {
			$complexity += $constant;
		}
		return $complexity;
	}
	public function setComplexity($complexityLevel)
	{
		$this->_complexityLevel=$complexityLevel;
	}
	/**
	 * checks for complexity level. If returns false, it has populated the _issues array
	 */
	public function complexEnough($newPass, $username, $oldPass = NULL)
	{
		$enough = TRUE;
		$r      = new ReflectionClass($this);
		foreach ($r->getConstants() as $name=>$constant) {
			/** means we have to check that type then **/
			if ($this->_complexityLevel & $constant) {
				/** REQUIRE_MIN becomes _requireMin() **/
				$parts    = explode('_', $name, 2);
				$funcName = "_{$parts[0]}" . ucwords($parts[1]);
				$result   = call_user_func_array(array($this, $funcName), array($newPass, $oldPass, $username));
				if ($result !== TRUE) {
					$enough = FALSE;
					$this->_issues[] = $result;
				}
			}
		}
		return $enough;
	}
	public function getPasswordIssues()
	{
		return $this->_issues;
	}
	protected function _requireLowercase($newPass)
	{
		if (!preg_match('/[a-z]/', $newPass)) {
			return 'Password requires a lowercase letter.';
		}
		return true;
	}
	protected function _requireUppercase($newPass)
	{
		if (!preg_match('/[A-Z]/', $newPass)) {
			return 'Password requires an uppercase letter.';
		}
		return true;
	}
	protected function _requireNumber($newPass)
	{
		if (!preg_match('/[0-9]/', $newPass)) {
			return 'Password requires a number.';
		}
		return true;
	}
	protected function _requireSpecialChar($newPass)
	{
		if (!preg_match('/[^a-zA-Z0-9]/', $newPass)) {
			return 'Password requires a special character.';
		}
		return true;
	}
	protected function _requireDiffpass($newPass, $oldPass)
	{
		if (strlen($newPass) - similar_text($oldPass,$newPass) < $this->_passwordDiffLevel || stripos($newPass, $oldPass) !== FALSE) {
			return 'Password must be a bit more different than the last password.';
		}
		return true;
	}
	protected function _requireDiffuser($newPass, $oldPass, $username)
	{
		if (stripos($newPass, $username) !== FALSE) {
			return 'Password should not contain your username.';
		}
		return true;
	}
	protected function _requireUnique($newPass)
	{
		$uniques = array_unique(str_split($newPass));
		if (count($uniques) < $this->_uniqueChrRequired) {
			return 'Password must contain more unique characters.';
		}
		return true;
	}
}




?>