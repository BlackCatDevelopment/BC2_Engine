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
use \CAT\Helper\Assets as Assets;

if (!class_exists('Frontend', false))
{
    class Frontend extends Base
    {
        #protected static $loglevel    = \Monolog\Logger::EMERGENCY;
        protected static $loglevel    = \Monolog\Logger::DEBUG;
        private   static $instance    = array();
        private   static $maintenance = NULL;

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         * dispatch frontend route
         **/
        public static function dispatch()
        {
            // forward to backend router
            if(Backend::isBackend())
                return \CAT\Backend::dispatch();
            return self::router()->dispatch();
        }   // end function dispatch()

        /**
         *
         **/
        public static function index()
        {
            $page_id = \CAT\Page::getID();
            // no page found
            if(!$page_id)
            {
                ob_start();
                    $empty_page_bg = Assets::serve('images',array("CAT/templates/empty_page_bg.jpg"));
                ob_end_clean();
                require dirname(__FILE__).'/templates/empty.php';
                exit;
            }
            // get page handler
            $page   = \CAT\Page::getInstance($page_id);
            // hand over to page handler
            $page->show();
        }

        /**
         * check if system is in maintenance mode
         *
         * @access public
         * @return boolean
         **/
        public static function isMaintenance()
        {
            if(!self::$maintenance)
            {
                self::$maintenance
                    = \CAT\Registry::get('maintenance_mode') == 'true'
                    ? true
                    : false;
            }
            return self::$maintenance;
        }   // end function isMaintenance()

    }   // end class Frontend
}