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

namespace CAT\Addon;

class Template extends Module implements IAddon
{

	/**
	 * @var void
	 */
	protected static $instance = NULL;
    protected static $blocks   = array();

    /**
	 *
	 */
	public static function getBlocks()
	{
        $class = get_called_class();
		return $class::$blocks;
	}

	/**
	 *
	 */
	public static function getInstance()
	{
		// TODO: implement here
	}

	/**
	 * get form for special areas of the template
	 * login_form, search_form, forgot_form, preferences_form, signup_form, forgotpw_mail_body_html, forgotpw_mail_body, signup_mail_admin_body, signup_mail_body
	 * @param void $$value
	 */
	public static function getForm($value = login)
	{
		// TODO: implement here
	}
}
