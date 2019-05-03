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
use \CAT\Backend as Backend;
use \CAT\Helper\Directory as Directory;
use \CAT\Backend\Page as BPage;
use \CAT\Helper\Page as HPage;
use \CAT\Sections as Sections;

if (!class_exists('Router', false)) {
    class Router extends Base
    {
        // log level
        #public    static $loglevel   = \Monolog\Logger::EMERGENCY;
        public static $loglevel   = \Monolog\Logger::DEBUG;
        // instance
        private static $instance   = null;
        // tables
        private static $routes_table = ':prefix:pages_routes';

        private static $reroutes   = 0;
        // full route
        private $route      = null;
        // query string
        private $query      = null;
        // the route split into parts
        private $parts      = null;
        // flag
        private $backend    = false;

        private $func       = null;

        //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // Spaeter konfigurierbar machen!
        //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        private static $asset_paths = array(
            'css','js','images','eot','fonts'
        );
        private static $assets = array(
            'css','js','eot','svg','ttf','woff','woff2','map','jpg','jpeg','gif','png','html'
        );
        private static $asset_subdirs = array(
            'modules','templates'
        );

        public static function getInstance()
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * create a new route handler
         **/
        public function __construct()
        {
            $this->initRoute();
        }   // end function __construct()

        /**
         *
         * @access public
         * @return
         **/
        public function dispatch()
        {
            self::log()->addDebug('>>>>> dispatch() <<<<<');

            if (!$this->route) {
                $this->route = 'index';
            }

            // ----- serve asset files -----------------------------------------
            $suffix = pathinfo($this->route, PATHINFO_EXTENSION);
            if (
                ($type=$this->match('~^('.implode('|', self::$asset_paths).')~i'))!==false
                ||
                (strlen($suffix) && in_array($suffix, self::$assets))
            ) {
                self::log()->addDebug(sprintf(
                    'serving asset file [%s]',
                    $this->route
                ));
                if (strlen($suffix) && in_array($suffix, self::$assets)) {
                    \CAT\Helper\Assets::serve($suffix, $this->route);
                } else {
                    parse_str($this->getQuery(), $files);
                    \CAT\Helper\Assets::serve($type, $files);
                }
                self::log()->addDebug('>>>>> dispatch() ENDE <<<<<');
                return;
            }

            // ----- forward to modules ----------------------------------------
            // Note: This may be dangerous, but for now, we do not have a
            //       whitelist for allowed file names
            // -----------------------------------------------------------------
            if (self::match('~^modules/~i')) {
                self::log()->addDebug(sprintf('forwarding to module [%s]',$this->route));
                if($suffix=='php') {
                    self::log()->addDebug('>>>>> dispatch() ENDE <<<<<');
                    require CAT_ENGINE_PATH.'/'.self::router()->getRoute();
                    return;
                } else {
                    // get the module class
                    $directory = self::router()->getRoutePart(1);
                    $method    = self::router()->getRoutePart(2);
                    $module    = $directory;
                    list($handler,$classname) = \CAT\Helper\Addons::getHandler($directory,$module);

                    if ($handler) {
                        self::log()->addDebug(sprintf('found class file [%s]', $handler));
                        \CAT\Helper\Addons::executeHandler($handler,$classname,$method);
                    }
                }
            }

            $this->controller = "\\CAT\\".($this->backend ? 'Backend' : 'Frontend'); // \CAT\Backend || \CAT\Frontend
            $this->function   = ((is_array($this->parts) && count($this->parts)>0) ? $this->parts[0] : 'index');

            // ----- load template language files ------------------------------
            if (self::isBackend()) {
                self::log()->addDebug('initializing backend');
                Backend::initialize();
                $lang_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.\CAT\Registry::get('DEFAULT_THEME').'/languages');
            } else {
                $lang_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.\CAT\Registry::get('DEFAULT_TEMPLATE').'/languages');
            }
            if (is_dir($lang_path)) {
                self::log()->addDebug(sprintf('adding lang path [%s]',$lang_path));
                self::addLangFile($lang_path);
            }

            self::log()->addDebug(sprintf(
                'controller [%s] function [%s]',
                $this->controller,
                $this->function
            ));

            $this->params = \CAT\Helper\HArray::filter($this->params,null,$this->function);

            // ----- frontend page ---------------------------------------------
            if (!$this->backend) {
                self::log()->addDebug(sprintf(
                    'serving frontend page',
                    $this->route
                ));
                $page = $this->getPage($this->route);
                if ($page && is_int($page)) {
                    $pg = \CAT\Page::getInstance($page);
                    self::log()->addDebug('>>>>> dispatch() ENDE <<<<<');
                    $pg->show();
                    exit;
                }
            }

            #echo "is callable controller[", $this->controller, "] function [", $this->function,"] result [", is_callable(array($this->controller,$this->function)), "]<br />";
            // ----- internal handler? ex \CAT\Backend::index() ----------------
            if (!is_callable(array($this->controller,$this->function))) {
                self::log()->addDebug(sprintf('is_callable() failed for function [%s], trying to find something in route parts', $this->function));
                // find controller
                if (class_exists($this->controller.'\\'.ucfirst($this->function))) {
                    $this->controller = $this->controller.'\\'.ucfirst($this->function);
                    $this->function   = (count($this->parts)>1 ? $this->parts[1] : 'index');

                    #echo sprintf("controller [%s] func [%s]<br />", $this->controller, $this->function);
                    # ????
                    #if ($this->function=='index' && count($this->params)>0) {
                    #    $this->function = array_shift($this->params);
                    #}

                }
            }

            $handler = $this->controller.'::'.$this->function;
            self::log()->addDebug(sprintf(
                'handler [%s]',
                $handler
            ));

            if (is_callable(array($this->controller,$this->function))) {
                self::log()->addDebug('is_callable() succeeded');
                if (is_callable(array($this->controller,'getPublicRoutes'))) {
                    self::log()->addDebug('found getPublicRoutes() method in controller');
                    $public_routes = $this->controller::getPublicRoutes();
                    if (is_array($public_routes) && in_array($this->route, $public_routes)) {
                        self::log()->addDebug('found current route in public routes, unprotecting it');
                        $this->protected = false;
                    }
                }

                $this->params = \CAT\Helper\HArray::filter($this->params,null,$this->function);

                // check for protected route
                if ($this->protected && !self::user()->isAuthenticated()) {
                    self::log()->addDebug(sprintf(
                        'protected route [%s], forwarding to login page',
                        $this->route
                    ));
                    $this->reroute(CAT_BACKEND_PATH.'/login');
                } else {
                    // forward to route handler
                    self::log()->addDebug('forwarding request to route handler');
                    $handler();
                }
                return;
            }

            self::log()->addDebug('!!!!! Forwarding to 404 !!!!!');
            \CAT\Page::print404();
        }   // end function dispatch()
        

        /**
         * accessor to private controller name
         *
         * @access public
         * @return
         **/
        public function getController()
        {
            if ($this->controller) {
                return $this->controller;
            }
            return false;
        }   // end function getController()

        /**
         * accessor to private controller name
         *
         * @access public
         * @return
         **/
        public function setController($name)
        {
            $this->controller = $name;
        }   // end function setController()

        /**
         * accessor to private function name
         *
         * @access public
         * @return
         **/
        public function getFunction()
        {
            if ($this->function) {
                return $this->function;
            }
            return false;
        }   // end function getFunction()

        /**
         * accessor to private function name
         *
         * @access public
         * @return
         **/
        public function setFunction($name)
        {
            if (!$name || !strlen($name)) {
                $caller = debug_backtrace();
                $dbg    = '';
                foreach (array('file','function','class','line',) as $key) {
                    if (isset($caller[0][$key])) {
                        $dbg .= "$key => ".$caller[0][$key]." | ";
                    } else {
                        $dbg .= "$key => not set | ";
                    }
                }
                $this->log()->addError(sprintf(
                    'Router error: setFunction called with empty function name, caller [%s]',
                    $dbg
                ));
                return;
            }
            if (is_numeric($name)) {
                $this->log()->error(
                    'Router error: setFunction called with numeric function name'
                );
                return;
            }

            $this->func = $name;
        }   // end function setFunction()

        /**
         * accessor to route handler
         *
         * @access public
         * @return
         **/
        public function getHandler()
        {
            if (!$this->handler) {
                try {
                    $class = $this->controller;
                    $this->handler = $class::getInstance();
                } catch (Exception $e) {
                    echo $e->getMessage();
                    return false;
                }
            }
            return $this->handler;
        }   // end function getHandler()

        /**
         *
         * @access public
         * @return
         **/
        public function getPage($route)
        {
            if (\CAT\Backend::isBackend()) {
                return 0;
            }
            $route  = urldecode($route);
            // remove suffix from route
            $route  = str_ireplace(\CAT\Registry::get('PAGE_EXTENSION'), '', $route);
            // remove trailing /
            $route  = rtrim($route, "/");
            // add / to front
            if (substr($route, 0, 1) !== '/') {
                $route = '/'.$route;
            }
            // find page in DB
            $result = self::db()->query(
                'SELECT `page_id` FROM `'.self::$routes_table.'` WHERE `route`=?',
                array($route)
            );
            $data   = $result->fetch();
            if (!$data || !is_array($data) || !count($data)) {
                $result = self::db()->query(
                    'SELECT `page_id` FROM `:prefix:module_routes` WHERE `route`=?',
                    array(ltrim($route,"/"))
                );
                $data   = $result->fetch();
                if (!$data || !is_array($data) || !count($data)) {
                    return false;
                } else {
                    return (int)$data['page_id'];
                }
            } else {
                return (int)$data['page_id'];
            }
        }   // end function getPage()

        /**
         *
         * @access public
         * @return
         **/
        public function getParam($index=-1, $shift=false)
        {
            if (!is_array($this->params)) {
                return null;
            }
            if(!is_numeric($index)) {
                return null;
            }
            if ($index < 0) { // last param
                $reversed = array_reverse($this->params);
                $index    = abs($index)-1;
                $value    = isset($reversed[$index]) ? $reversed[$index] : null;
                return $value;
            }
            if (!isset($this->params[$index])) {
                return null;
            }
            $value = $this->params[$index];
            if ($shift) {
                array_splice($this->params, $index, 1);
            }
            return $value;
        }   // end function getParam()
        

        /**
         * accessor to private route params array
         *
         * @access public
         * @return
         **/
        public function getParams()
        {
            if ($this->params && is_array($this->params)) {
                return $this->params;
            }
            return false;
        }   // end function getParams()

        /**
         *
         * @access public
         * @return
         **/
        public function getParts() : array
        {
            if ($this->parts) {
                return $this->parts;
            }
            return array();
        }   // end function getParts()

        /**
         * accessor to private route (example: 'backend/dashboard')
         *
         * @access public
         * @return string
         **/
        public function getRoute()
        {
            if ($this->route) {
                return $this->route;
            }
            return false;
        }   // end function getRoute()

        /**
         *
         * @access public
         * @return
         **/
        public function getRoutePart($index)
        {
            if ($this->route) {
                $parts = explode('/', $this->route);
                if (is_array($parts) && count($parts)) {
                    if ($index == -1) { // last param
                        end($parts);
                        $index = key($parts);
                    }
                    if (isset($parts[$index])) {
                        return $parts[$index];
                    }
                }
            }
            return false;
        }   // end function getRoutePart()

        /**
         *
         * @access public
         * @return
         **/
        public function getQuery()
        {
            if ($this->query) {
                parse_str($this->query, $query);
                return $query;
            }
            return false;
        }   // end function getQuery()
        

        /**
         * retrieve the route
         *
         * @access public
         * @return
         **/
        public function initRoute($remove_prefix=null)
        {
            self::log()->addDebug('initializing route');

            $this->route     = null;
            $this->query     = null;
            $this->params    = array();
            $this->protected = false;
            $this->backend   = false;

            foreach (array_values(array('REQUEST_URI','REDIRECT_SCRIPT_URL','SCRIPT_URL','ORIG_PATH_INFO','PATH_INFO')) as $key) {
                if (isset($_SERVER[$key])) {
                    self::log()->addDebug(sprintf(
                        'found key [%s] in $_SERVER',
                        $key
                    ));
                    $route = parse_url($_SERVER[$key], PHP_URL_PATH);
                    self::log()->addDebug(sprintf(
                        'route [%s]',
                        $route
                    ));
                    break;
                }
            }
            if (!$route) {
                $route = '/';
            }

            if (isset($_SERVER['QUERY_STRING'])) {
                $this->query = $_SERVER['QUERY_STRING'];
                self::log()->addDebug(sprintf(
                        'query string [%s]',
                    $this->query
                    ));
            }

            // remove params
            if (stripos($route, '?')) {
                list($route, $ignore) = explode('?', $route, 2);
            }

            // remove index.php
            $route = str_ireplace('index.php', '', $route);

            // remove document root
            $path_prefix = str_ireplace(
                Directory::sanitizePath($_SERVER['DOCUMENT_ROOT']),
                '',
                Directory::sanitizePath(CAT_PATH)
            );

            // remove leading /
            if (substr($route, 0, 1)=='/') {
                $route = substr($route, 1, strlen($route));
            }

            // remove trailing /
            if (substr($route, -1, 1)=='/') {
                $route = substr($route, 0, strlen($route)-1);
            }

            // if there's a prefix to remove (needed for backend paths)
            if ($remove_prefix) {
                $route = str_replace($remove_prefix, '', $route);
                $route = substr($route, 1, strlen($route));
            }

            // remove site subfolder; this may not work for asset files as the
            // config.php of the site is not loaded
            $site_folder = self::site()['site_folder'];
            if(strlen($site_folder)==0) {
                // try to extract the site subfolder from the path
                $preg = implode('|',self::$asset_subdirs);
                preg_match('~\/('.$preg.')\/~i', $route, $m);
                if(count($m)>0) {
                    $parts = explode('/',$route);
                    $temp = array();
                    foreach($parts as $part) {
                        if($part != $m[1]) {
                            $temp[] = $part;
                        } else {
                            break;
                        }
                    }
                    if(count($temp)>0) {
                        $site_folder = implode('/',$temp);
                    }
                }
            }
            self::log()->addDebug(sprintf('site folder [%s]',$site_folder));
            $route = preg_replace('~^\/?'.$site_folder.'\/?~i', '', $route);

            if ($route) {
                $this->parts = explode('/', str_replace('\\', '/', $route));
                $this->route = $route;

                $backend_route = defined('BACKEND_PATH')
                    ? BACKEND_PATH
                    : 'backend';

                if (preg_match('~^/?'.$backend_route.'/?~i', $route)) {
                    $this->backend   = true;
                    $this->protected = true;
                    array_shift($this->parts); // remove backend/ from route
                    $this->route     = implode("/", $this->parts);
                }

                $this->params = $this->parts;
            }

            self::log()->addDebug(sprintf(
                'initRoute() returning result: route [%s] query [%s]',
                $route,
                $this->query
            ));
        }   // end function initRoute()

        /**
         *
         * @access public
         * @return mixed
         **/
        public function match($pattern)
        {
            self::log()->addDebug(sprintf(
                'match() route [%s] pattern [%s]',
                $this->getRoute(),
                $pattern
            ));
            // if the pattern has brackets, we return the first match
            // if not, we return boolean true
            if (preg_match($pattern, $this->getRoute(), $m)) {
                if (count($m)>1 && strlen($m[0])) {
                    self::log()->addDebug(sprintf(
                        'returning first match [%s]',$m[0]
                    ));
                    return $m[0];
                }
                self::log()->addDebug('match() returning true');
                return true;
            }
            self::log()->addDebug('match() returning false');
            return false;
        }   // end function match()

        /**
         *
         **/
        public function isBackend()
        {
            return $this->backend;
        }

        /**
         * checks if the route is protected or not
         *
         * @access public
         * @return boolean
         **/
        public function isProtected()
        {
            return $this->protected;
        }   // end function isProtected()

        /**
         *
         * @access public
         * @return
         **/
        public function protect($needed_perm)
        {
            $this->log()->addDebug(sprintf(
                'protecting route [%s] with needed perm [%s]',
                $this->getRoute(),
                $needed_perm
            ));
            $this->protected = true;
            $this->perm      = $needed_perm;
        }   // end function protect()
        
        /**
         *
         * @access public
         * @return
         **/
        public function register(string $route, int $pageID, $addon_id=null, $section_id=null)
        {
        
        }   // end function register()

        /**
         *
         **/
        public function reroute($newroute)
        {
            self::$reroutes++;
            if(self::$reroutes>=3) {
                self::log()->addError(sprintf(
                    'too many reroute attempts, unable to reroute [%s]',
                    $newroute
                ));
                self::printFatalError('unable to serve');
            }
            $_SERVER['REQUEST_URI'] = $newroute;
            $this->initRoute();
            $this->dispatch();
        }
    }
}
