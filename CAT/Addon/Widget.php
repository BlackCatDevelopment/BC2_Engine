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

use \CAT\Base as Base;


class Widget extends Module implements IAddon
{

	/**
	 * @var void
	 */
	protected static $type = 'widget';

	/**
	 * @inheritDoc
	 */
	public static function save(int $section_id)
	{
		// TODO: implement here
	}

	/**
	 * @inheritDoc
	 */
	public static function modify(array $section)
	{
		// TODO: implement here
	}

    /**
	 * @inheritDoc
	 */
    public static function upgrade()
    {
        // TODO: implement here
    }

    /**
	 * @inheritDoc
	 */
    public static function view($widget_id,$dashboard_id)
    {
		// TODO: implement here
	}

}
