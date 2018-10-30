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

use \CAT\Helper\Directory as Directory;

class Page extends Module implements IAddon, IPage
{
    /**
     * @var void
     */
    protected static $type     = 'page';
    protected static $addonID  = 0;
    protected static $template = '';

    public function __construct()
    {
        parent::__construct();
    }
    public function __destruct()
    {
        parent::__destruct();
    }

    /**
     * default add function; override to add your own actions
     **/
    public static function add() : integer
    {
        self::setIDs();
        // Add a new section
        if (self::db()->query(
                'INSERT INTO `:prefix:mod_' . static::$directory . '`
					( `page_id`, `section_id` ) VALUES
					( :page_id, :section_id )',
                array(
                    'page_id'		=> self::$page_id,
                    'section_id'	=> self::$section_id
                )
            )
        ) {
            self::$addonID = (int)self::db()->lastInsertId();
            return self::$addonID;
        } else {
            return 0;
        }
    }

    /**
     * default view function
     *
     * @access public
     * @param  array  $section - section settings
     * @return string
     **/
    public static function view(array $section)
    {
        self::$template	= 'view';
    }

    /**
     * default remove function
     **/
    public static function remove()
    {
        // Remove from database
        if (self::db()->query(
            'DELETE FROM `:prefix:mod_' . static::$directory . '` ' .
                'WHERE `page_id` =:page_id ' .
                'AND `section_id` =:section_id',
            array(
                'page_id'		=> self::$page_id,
                'section_id'	=> self::$section_id
            )
        )) {
            return true;
        } else {
            return false;
        }
    }
}
