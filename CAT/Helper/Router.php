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

if(!class_exists('Router',false))
{
    class Router extends Base
    {
        // log level
        #public    static $loglevel   = \Monolog\Logger::EMERGENCY;
        public    static $loglevel   = \Monolog\Logger::DEBUG;
        // instance
        private   static $instance   = NULL;
        // full route
        private          $route      = NULL;
        // query string
        private          $query      = NULL;
        // the route split into parts
        private          $parts      = NULL;
        // flag
        private          $backend    = false;

        private          $func       = NULL;

//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// Spaeter konfigurierbar machen!
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        private   static $asset_paths = array(
            'css','js','images','eot','fonts'
        );
        private   static $assets = array(
            'css','js','eot','svg','ttf','woff','woff2',
        );

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
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
            if(!$this->route) {
                $this->route = 'index';
            }

            // ----- serve asset files -----------------------------------------
            $suffix = pathinfo($this->route,PATHINFO_EXTENSION);
            if(
                ($type=$this->match('~^('.implode('|',self::$asset_paths).')~i'))!==false
                ||
                (strlen($suffix) && in_array($suffix,self::$assets))
            ) {
                if(strlen($suffix) && in_array($suffix,self::$assets))
                {
                    \CAT\Helper\Assets::serve($suffix,array($this->route),true);
                } else {
                    parse_str($this->getQuery(),$files);
                    // remove leading / from all files
                    foreach($files as $i => $f) $files[$i] = preg_replace('~^/~','',$f,1);
                    \CAT\Helper\Assets::serve($type,$files);
                }
                return;
            }

            $this->controller = "\\CAT\\".($this->backend ? 'Backend' : 'Frontend'); // \CAT\Backend || \CAT\Frontend
            $this->function   = ( (is_array($this->parts) && count($this->parts)>0 ) ? $this->parts[0] : 'index' );

            // ----- load template language files ------------------------------
            if(self::isBackend()) {
                Backend::initialize();
                $lang_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.\CAT\Registry::get('DEFAULT_THEME').'/languages');
            } else {
                $lang_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.\CAT\Registry::get('DEFAULT_TEMPLATE').'/languages');
            }
            if(is_dir($lang_path)) {
                self::addLangFile($lang_path);
            }

            self::log()->addDebug(sprintf(
                'controller [%s] function [%s]',
                $this->controller,
                $this->function
            ));

            // ----- frontend page ---------------------------------------------
            if(!$this->backend)
            {
                $page = HPage::getPageForRoute($this->route);
                if($page && is_int($page))
                {
                    $pg = \CAT\Page::getInstance($page);
                    $pg->show();
                    exit;
                }
            }

            // ----- forward to modules ----------------------------------------
            // Note: This may be dangerous, but for now, we do not have a
            //       whitelist for allowed file names
            // -----------------------------------------------------------------
            if(self::router()->match('~^modules/~i') && $suffix=='php')
            {
                require CAT_ENGINE_PATH.'/'.self::router()->getRoute();
                return;
            }

#echo "is callable controller[", $this->controller, "] function [", $this->function,"] result [", is_callable(array($this->controller,$this->function)), "]<br />";
            // ----- internal handler? ex \CAT\Backend::index() ----------------
            if(!is_callable(array($this->controller,$this->function)))
            {
                self::log()->addDebug(sprintf('is_callable() failed for function [%s], trying to find something in route parts',$this->function));
                // find controller
                if(class_exists($this->controller.'\\'.ucfirst($this->function)))
                {
                    $this->controller = $this->controller.'\\'.ucfirst($this->function);
                    $this->function   = ( count($this->parts)>1 ? $this->parts[1] : 'index' );
#echo sprintf("controller [%s] func [%s]<br />", $this->controller, $this->function);
                    if($this->function=='index' && count($this->params)>0)
                    {
                        $this->function = array_shift($this->params);
                    }
                }
            }

            $handler = $this->controller.'::'.$this->function;
            self::log()->addDebug(sprintf(
                'handler [%s]', $handler
            ));
#echo sprintf("is_callable controller [%s] func [%s]<br />", $this->controller, $this->function);
            if(is_callable(array($this->controller,$this->function)))
            {
                self::log()->addDebug('is_callable() succeeded');
                if(is_callable(array($this->controller,'getPublicRoutes')))
                {
                    self::log()->addDebug('found getPublicRoutes() method in controller');
                    $public_routes = $this->controller::getPublicRoutes();
                    if(is_array($public_routes) && in_array($this->route,$public_routes))
                    {
                        self::log()->addDebug('found current route in public routes, unprotecting it');
                        $this->protected = false;
                    }
                }

                // check for protected route
                if($this->protected && !self::user()->is_authenticated()) {
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
            if($this->controller) return $this->controller;
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
            if($this->function) return $this->function;
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
            if(!$name || !strlen($name))
            {
                $caller = debug_backtrace();
                $dbg    = '';
                foreach(array('file','function','class','line',) as $key)
                {
                    if(isset($caller[0][$key]))
                    {
                        $dbg .= "$key => ".$caller[0][$key]." | ";
                    }
                    else
                    {
                        $dbg .= "$key => not set | ";
                    }
                }
                $this->log()->addError(sprintf(
                    'Router error: setFunction called with empty function name, caller [%s]',
                    $dbg
                ));
                return;
            }
            if(is_numeric($name))
            {
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
            if(!$this->handler)
            try {
                $class = $this->controller;
                $this->handler = $class::getInstance();
            }
            catch( Exception $e )
            {
                echo $e->getMessage();
                return false;
            }
            return $this->handler;
        }   // end function getHandler()

        /**
         *
         * @access public
         * @return
         **/
        public function getParam($index=-1,$shift=false)
        {
            if(!is_array($this->params)) return NULL;
            if($index == -1) { // last param
                end($this->params);
                $index = key($this->params);
            }
            if(!isset($this->params[$index])) return NULL;
            $value = $this->params[$index];
            if($shift)
                array_splice($this->params,$index,1);
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
            if($this->params && is_array($this->params)) return $this->params;
            return false;
        }   // end function getParams()

        /**
         *
         * @access public
         * @return
         **/
        public function getParts() : array
        {
            if($this->parts) return $this->parts;
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
            if($this->route) return $this->route;
            return false;
        }   // end function getRoute()

        /**
         *
         * @access public
         * @return
         **/
        public function getRoutePart($index)
        {
            if($this->route)
            {
                $parts = explode('/',$this->route);
                if(is_array($parts) && count($parts))
                {
                    if($index == -1) { // last param
                        end($parts);
                        $index = key($parts);
                    }
                    if(isset($parts[$index])) return $parts[$index];
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
            if($this->query) {
                parse_str($this->query,$query);
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
        public function initRoute($remove_prefix=NULL)
        {
            self::log()->addDebug('initializing route');

            $this->route     = NULL;
            $this->query     = NULL;
            $this->params    = array();
            $this->protected = false;
            $this->backend   = false;

            foreach(array_values(array('REQUEST_URI','REDIRECT_SCRIPT_URL','SCRIPT_URL','ORIG_PATH_INFO','PATH_INFO')) as $key)
            {
                if(isset($_SERVER[$key]))
                {
                    self::log()->addDebug(sprintf(
                        'found key [%s] in $_SERVER', $key
                    ));
                    $route = parse_url($_SERVER[$key],PHP_URL_PATH);
                    self::log()->addDebug(sprintf(
                        'route [%s]', $route
                    ));
                    break;
                }
            }
            if(!$route) { $route = '/'; }

            if(isset($_SERVER['QUERY_STRING']))
            {
                $this->query = $_SERVER['QUERY_STRING'];
                self::log()->addDebug(sprintf(
                        'query string [%s]', $this->query
                    ));
            }

            // remove params
            if(stripos($route,'?')) {
                list($route,$ignore) = explode('?',$route,2);
            }

            // remove site subfolder
            $route = preg_replace('~^\/'.self::site()['site_folder'].'\/?~i','',$route);

            // remove index.php
            $route = str_ireplace('index.php','',$route);

            // remove document root
            $path_prefix = str_ireplace(
                Directory::sanitizePath($_SERVER['DOCUMENT_ROOT']),
                '',
                Directory::sanitizePath(CAT_PATH)
            );

            // remove leading /
            if(substr($route,0,1)=='/')
                $route = substr($route,1,strlen($route));

            // remove trailing /
            if(substr($route,-1,1)=='/')
                $route = substr($route,0,strlen($route)-1);

            // if there's a prefix to remove (needed for backend paths)
            if($remove_prefix)
            {
                $route = str_replace($remove_prefix,'',$route);
                $route = substr($route,1,strlen($route));
            }

            if($route)
            {
                $this->parts = explode('/',str_replace('\\','/',$route));
                $this->route = $route;
                $backend_route = defined('BACKEND_PATH')
                    ? BACKEND_PATH
                    : 'backend';
                if(preg_match('~^/?'.$backend_route.'/?~i', $route))
                {
                    $this->backend   = true;
                    $this->protected = true;
                    array_shift($this->parts); // remove backend/ from route
                    $this->route     = implode("/",$this->parts);
                    // pages list
                    if(!self::asJSON() && self::user()->hasPerm('pages_list'))
                    {
                        self::tpl()->setGlobals('pages',BPage::tree());
                        self::tpl()->setGlobals('pagelist',HPage::getPages(1));
                        self::tpl()->setGlobals('sections',Sections::getSections());
                    }
                }
            }

            self::log()->addDebug(sprintf(
                'initRoute() returning result: route [%s] query [%s]', $route, $this->query
            ));
            
        }   // end function initRoute()

        /**
         *
         * @access public
         * @return mixed
         **/
        public function match($pattern)
        {
            // if the pattern has brackets, we return the first match
            // if not, we return boolean true
            if(preg_match($pattern,$this->getRoute(),$m))
            {
                if(count($m) && strlen($m[0]))
                    return $m[0];
                return true;
            }
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
                $this->getRoute(), $needed_perm
            ));
            $this->protected = true;
            $this->perm      = $needed_perm;
        }   // end function protect()
        
        /**
         *
         **/
        public function reroute($newroute)
        {
            $_SERVER['REQUEST_URI'] = $newroute;
            $this->initRoute();
            $this->dispatch();
        }

    }
}
