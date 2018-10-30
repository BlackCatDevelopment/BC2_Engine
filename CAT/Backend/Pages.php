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

if (!class_exists('\CAT\Backend\Pages'))
{
    class Pages extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if(!Base::user()->hasPerm('pages_list'))
                self::printFatalError('You are not allowed for the requested action!');

            \CAT\Helper\Page::setTitle('BlackCat CMS Backend / Pages');

            $pages      = \CAT\Helper\Page::getPages(true);
            $pages_list = self::lb()->buildRecursion($pages);

            \CAT\Backend::printHeader();
            self::tpl()->output(
                'backend_pages',
                array(
                    'pages'         => $pages_list,
                )
            );
            \CAT\Backend::printFooter();
        }   // end function index()
    }
}

