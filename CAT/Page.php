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
use \CAT\Helper\Page as HPage;

if (!class_exists('\CAT\Page', false))
{
    class Page extends Base
    {
        // ID of last instantiated page
        private   static $curr_page  = NULL;
        // singleton, but one instance per page_id!
        private   static $instances  = array();
        // loglevel
        protected static $loglevel   = \Monolog\Logger::EMERGENCY;
        //
        protected        $page_id    = NULL;

        /**
         * get instance for page with ID $page_id
         *
         * @access public
         * @param  integer $page_id
         * @return object
         **/
        public static function getInstance($page_id=NULL)
        {
            if($page_id)
            {
                self::log()->addDebug(sprintf('\CAT\Page::getInstance(%s)',$page_id));
                if(!isset(self::$instances[$page_id]))
                {
                    self::log()->addDebug('creating new instance');
                    self::$instances[$page_id] = new self($page_id);
                    self::$instances[$page_id]->page_id = $page_id;
                }
                return self::$instances[$page_id];
            }
            else
            {
                return new self(0);
            }
        }   // end function getInstance()

        /**
         * get current page
         *
         * @access public
         * @return
         **/
        public static function getID()
        {
            if(!self::$curr_page)
            {
                if(!\CAT\Backend::isBackend())
                {
                    // check if the system is in maintenance mode
                    if(\CAT\Frontend::isMaintenance())
                    {
                        $result = self::db()->query(
                            'SELECT `value` FROM `:prefix:settings` WHERE `name`="maintenance_page"'
                        );
                        $value = $result->fetch();
                        self::$curr_page = $value['value'];
                    }
                    else
                    {
                        $route = self::router()->getRoute();
                        // no route -> get default page
                        if($route == '' || $route == 'index')
                        {
                            self::$curr_page = \CAT\Helper\Page::getDefaultPage();
                        }
                        else // find page by route
                        {
                            self::$curr_page = \CAT\Helper\Page::getPageForRoute($route);
                        }
                    }
                    define('CAT_PAGE_ID',self::$curr_page);
                } else {
                    return \CAT\Backend\Page::getPageID();
                }
            }
            return self::$curr_page;
        }   // end function getID()

        /**
         * get page sections for given block
         *
         * @access public
         * @param  integer $block
         * @return void (direct print to STDOUT)
         **/
        public static function getPageContent($block=1)
        {
            $page_id = self::getID();

            self::log()->addDebug(sprintf(
                'getPageContent called for block [%s], page [%s]',
                $block, $page_id
            ));

            // check if the page exists, is not marked as deleted, and has
            // some content at all
            if(
                   !$page_id                              // no page id
                || !\CAT\Helper\Page::exists($page_id)    // page does not exist
                || !\CAT\Helper\Page::isActive($page_id)  // page not active
            ) {
                return self::print404();
                exit;
            }

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Maintenance page
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

            // check if user is allowed to see this page
            if(!self::user()->is_root())
            {
                // global perm
                if(!self::user()->hasPagePerm($page_id,'pages_view'))
                {
                    self::log()->addError(sprintf(
                        'User with ID [%s] tried to view page [%d], but does not have the pages_view permission',
                        self::user()->getID(), $page_id
                    ));
                    self::printFatalError('You are not allowed to view this page!');
                }
            }

            // get active sections
            $sections = \CAT\Sections::getSections($page_id,$block,true);

            // in fact, this should never happen, als isActive() does the same
            if(!is_array($sections) || !count($sections)) // no content for this block
                return false;

            $output = array();

            #foreach($sections as $block => $items)
            foreach($sections as $index => $section)
            {
                #foreach($items as $section)
                #{
                    if(!$section['active'] || $section['expired']) continue;

                    // spare some typing
                    $section_id = $section['section_id'];
                    $module     = $section['module'];

                    // special case
                    if($module=='wysiwyg')
                    {
                        $output[] = \CAT\Addon\WYSIWYG::view($section);
                    }
                    else
                    {
                        // get the module class
                        $name    = \CAT\Helper\Addons::getDetails($module,'name');
                        $handler = NULL;
                        foreach(array_values(array(str_replace(' ','',$name),$module)) as $classname) {
                            $filename = \CAT\Helper\Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$module.'/inc/class.'.$classname.'.php');
                            if(file_exists($filename)) {
                                 $handler = $filename;
                            }
                        }

                        if($handler)
                        {
                            self::log()->addDebug(sprintf('found class file [%s]',$handler));
                            Base::addLangFile(CAT_ENGINE_PATH.'/modules/'.$module.'/languages/');
                            self::setTemplatePaths($module);
                            include_once $handler;
                            $classname::initialize();
                            $content = $classname::view($section_id);
                        }
                        else
                        {
                            self::log()->addError(
                                sprintf('non existing module [%s] or missing handler [%s], called on page [%d], block [%d]',
                                $module,'class.'.$module.'.php',$page_id,$block)
                            );
                        }
                    }
                #}
            }
            echo implode("\n", $output);
        }   // end function getPageContent()

        /**
         *
         * @access public
         * @return
         **/
        public static function print404()
        {
            if(\CAT\Registry::get('err_page_404')!==0)
            {
                $err_page_id = \CAT\Registry::get('err_page_404');
                header($_SERVER['SERVER_PROTOCOL'].' 404 Not found');
                header('Location: '.\CAT\Helper\Page::getLink($err_page_id));
            }
            else
            {
                header($_SERVER['SERVER_PROTOCOL'].' 404 Not found');
            }
            exit;
        }   // end function print404()

        /**
         * Figure out which template to use
         *
         * @access public
         * @return void   sets globals
         **/
        public function setTemplate()
        {
            if(!defined('TEMPLATE'))
            {
                define('TEMPLATE',HPage::getPageTemplate($this->page_id));
            }
            $dir = '/templates/'.TEMPLATE;
            // Set the template url
            \CAT\Registry::register('CAT_TEMPLATE_URL', CAT_URL.$dir);
            // This is the REAL dir
            \CAT\Registry::register('CAT_TEMPLATE_DIR', CAT_ENGINE_PATH.$dir);
        }   // end function setTemplate()

        /**
         * shows the current page
         *
         * @access public
         * @return void
         **/
        public function show()
        {
            // send appropriate header
            if(\CAT\Frontend::isMaintenance() || \CAT\Registry::get('maintenance_page') == $this->page_id)
            {
                $this->log()->addDebug('Maintenance mode is enabled');
                header('HTTP/1.1 503 Service Temporarily Unavailable');
                header('Status: 503 Service Temporarily Unavailable');
                header('Retry-After: 7200'); // in seconds
                return;
            }

            $this->setTemplate();

            self::tpl()->setGlobals('page_id',$this->page_id);

            self::track($this->page_id);

            // including the template; it may calls different functions
            // like page_content() etc.
            $this->log()->addDebug('including template');

            ob_start();
                require \CAT\Registry::get('CAT_TEMPLATE_DIR').'/index.php';
                $output = ob_get_contents();
            ob_clean();

            echo $output;
        }   // end function show()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function track($pageID)
        {
            // get the IP to create 'unique' identifier; it is not stored!
            $ip = NULL;
            if (isset($_SERVER['HTTP_CLIENT_IP']))
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            elseif (isset($_SERVER['REMOTE_ADDR']))
                $ip = $_SERVER['REMOTE_ADDR'];

            // remove outdated entries (1 Minute)
            $ts = time() - 60; //(60*60);
            self::db()->query(
               'DELETE FROM `:prefix:mod_stats_reload` WHERE `page_id`=? && `timestamp`<?',
                array($pageID, $ts)
            );

            // don't track localhost
            #if($ip && !( $ip == '127.0.0.1' || substr($ip,0,2) == '0::' ) )
            #{
                // create identifier
                $ident  = ( isset($_SERVER['HTTP_USER_AGENT']) )      ? $_SERVER['HTTP_USER_AGENT']      : 'xc';
    			$ident .= ( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'x9';
    			$ident .= ( isset($_SERVER['HTTP_ACCEPT_CHARSET']) )  ? $_SERVER['HTTP_ACCEPT_CHARSET']  : 'xB';
                $ident .= $ip;
                $hash  = sha1($ident);

                $stmt = self::db()->query(
                    'SELECT `page_id` FROM `:prefix:mod_stats_reload` WHERE `page_ID`=? AND `hash`=?',
                    array($pageID,$hash)
                );
                // do not count visits on the same page
                if(!$stmt->rowCount()) {
                    self::db()->query(
                        'INSERT INTO `:prefix:mod_stats_reload` VALUES (?,?,?)',
                        array($pageID,$hash,time())
                    );
                    self::db()->query(
                          'INSERT INTO `:prefix:pages_visits` (`page_id`,`last`) '
                        . 'VALUES(?,?) '
                        . 'ON DUPLICATE KEY UPDATE `visits`=`visits`+1;',
                        array($pageID,time())
                    );
                }
            #}
        }   // end function track()
        

    } // end class \CAT\Page

}
