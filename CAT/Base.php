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

declare(strict_types=1);

namespace CAT;

use CAT\Helper\DB        as DB;
use CAT\Helper\Directory as Directory;
use CAT\Helper\Template  as Template;
use CAT\Helper\Router    as Router;
use CAT\Helper\Addons    as Addons;
use \CAT\Helper\Json     as Json;

if(!class_exists('Base',false))
{
    class Base
    {
        /**
         * log level
         **/
        private   static $loglevel   = \Monolog\Logger::EMERGENCY;
        /**
         * adds function info to error messages; for debugging only!
         **/
        protected static $debug      = false;
        /**
         * array to store class/object handlers (for accessor functions)
         */
        protected static $objects    = array();
        /**
         * current error state; default 500 (Internal server error)
         **/
        protected static $errorstate = 500;
        /**
         * current site
         **/
        protected static $site       = null;
        /**
         * known HTTP status
         **/
        protected static $state      = array(
            '200' => 'Success',
            '201' => 'Created',
            '202' => 'Accepted',
            '301' => 'Moved permanently',
            '400' => 'Bad request',
            '401' => 'Access denied',
            '403' => 'Forbidden',
            '404' => 'Not found',
            '409' => 'Conflict',
            '429' => 'Too many requests',
            '500' => 'Internal Server Error',
        );
        /**
         * current settings (data from settings DB table(s))
         **/
        protected static $settings   = NULL;
        /**
         * global settings (fallback if no data found in $settings)
         **/
        protected static $globals    = NULL;

        /**
         * inheritable constructor; allows to set object variables
         **/
        public function __construct($options=array())
        {
            if(is_array($options) && count($options)>0)
                $this->config($options);
        }   // end function __construct()

        /**
         * inheritable __destruct
         **/
        public function __destruct() {}

        /**
         * inheritable __call
         **/
        public function __call($method,$args)
        {
            if(!isset($this) || !is_object($this))
                return false;
            if(method_exists($this,$method))
                return call_user_func_array(array($this,$method),$args);
        }   // end function __call()

// =============================================================================
//   Accessor functions
// =============================================================================

        /**
         * returns database connection handle; creates an instance of
         * \CAT\Helper\DB if no instance was created yet
         *
         * @access public
         * @return object - instanceof \CAT\Helper\DB
         **/
        public static function db()
        {
            if(
                   !isset(Base::$objects['db'])
                || !is_object(Base::$objects['db'])
                || !Base::$objects['db'] instanceof \CAT\Helper\DB
            ) {
                if(!DB::connectionFailed()) {
                    self::storeObject('db',DB::getInstance());
                }
            }
            return Base::$objects['db'];
        }   // end function db()

        /**
         * returns an instance of getID3
         *
         * @access public
         * @return object - instanceof \getID3
         **/
        public static function fileinfo()
        {
            if(
                   !isset(Base::$objects['getid3'])
                || !is_object(Base::$objects['getid3'])
                || !Base::$objects['getid3'] instanceof \getID3
            ) {
                require_once CAT_ENGINE_PATH.'/modules/lib_getid3/getid3/getid3.php';
        	    Base::$objects['getid3'] = new \getID3;
            }
            return Base::$objects['getid3'];
        }   // end function fileinfo()

        /**
         * creates a global wbForms handler
         *
         * @access public
         * @return object - instanceof \wblib\wbForms\Form
         **/
        public static function form() : object
        {
            if(
                   !isset(Base::$objects['formbuilder'])
                || !is_object(Base::$objects['formbuilder'])
                || !Base::$objects['formbuilder'] instanceof \wblib\wbForms\Form
            ) {
                //\wblib\wbForms\Form::$wblang = self::lang();
                Base::$objects['formbuilder'] = new \wblib\wbForms\Form();
                $init = Directory::sanitizePath(
                    CAT_ENGINE_PATH.'/templates/'.Registry::get(
                        (Backend::isBackend() ? 'DEFAULT_THEME' : 'DEFAULT_TEMPLATE')
                    ).'/forms.init.php'
                );
                if(file_exists($init))
                    require $init;
                Base::$objects['formbuilder']->setAttribute('lang_path',CAT_ENGINE_PATH.'/languages');
                if(Backend::isBackend())
                {
                    Base::$objects['formbuilder']->setAttribute('lang_path',CAT_ENGINE_PATH.'/'.CAT_BACKEND_PATH.'/languages');
                }
            }
            return Base::$objects['formbuilder'];
        }   // end function form()
        
        /**
         * accessor to I18n helper
         *
         * @access public
         * @return object - instanceof \wblib\wbLang
         **/
        public static function lang() : object
        {
            if(
                   !isset(Base::$objects['lang'])
                || !is_object(Base::$objects['lang'])
                || !Base::$objects['lang'] instanceof \wblib\wbLang
            ) {
                \wblib\wbLang::addPath(CAT_ENGINE_PATH.'/languages');
                \wblib\wbLang::addPath(CAT_ENGINE_PATH.'/CAT/Backend/languages');
                self::storeObject('lang',\wblib\wbLang::getInstance(Registry::get('LANGUAGE',NULL,NULL)));
            }
            return Base::$objects['lang'];
        }   // end function lang()

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// !!! TODO: Refactor to new List Builder
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

        /**
         * initializes wbList for use with pages
         *
         * @access public
         * @return object
         **/
        public static function lb()
        {
            if(
                   !isset(Base::$objects['list'])
                || !is_object(Base::$objects['list'])
                || !Base::$objects['list'] instanceof \wblib\wbList
            )
                self::storeObject('list', new \wblib\wbList(array(
                    'id'    => 'page_id',
                    'title' => 'menu_title',
                    // for page selects
                    'value' => 'page_id',
                )));
            return Base::$objects['list'];
        }   // end function list()

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

        /**
         * accessor to Monolog logger
         *
         * @access public
         * @param  boolean $reset - delete logfile and start over
         * @return object - instanceof \Monolog\Logger
         **/
        public static function log($reset=false)
        {
            // global logger
            if(
                   !isset(Base::$objects['logger'])
                || !is_object(Base::$objects['logger'])
                || !Base::$objects['logger'] instanceof \Monolog\Logger
            ) {
                // default logger; will set the log level to the global default
                // set in Base
                $logger = new Base_LoggerDecorator(new \Monolog\Logger('CAT'));

                $bubble = false;
                $errorStreamHandler = new \Monolog\Handler\StreamHandler(
                    CAT_ENGINE_PATH.'/temp/logs/core_error.log', \Monolog\Logger::ERROR, $bubble
                );
                $emergStreamHandler = new \Monolog\Handler\StreamHandler(
                    CAT_ENGINE_PATH.'/temp/logs/core_critical.log', \Monolog\Logger::CRITICAL, $bubble
                );

                $logger->pushHandler($errorStreamHandler);
                $logger->pushHandler($emergStreamHandler);

                $logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());

                self::storeObject('logger',$logger);

                Registry::set('CAT.logger.Base',$logger);
            }

            // specific logger
            $class    = get_called_class();
            $loglevel = self::getLogLevel();

            if($loglevel != Base::$loglevel || $loglevel == \Monolog\Logger::DEBUG)
            {
                $logger  = Registry::get('CAT.logger.'.$class);
                $logfile = 'core_'.$class.'_'.date('m-d-Y').'.log';
                if($reset && file_exists(CAT_ENGINE_PATH.'/temp/logs/'.$logfile))
                    unlink(CAT_ENGINE_PATH.'/temp/logs/'.$logfile);
                if(!$logger)
                {
                    $logger = new Base_LoggerDecorator(new \Monolog\Logger('CAT.'.$class));
                    $stream = new \Monolog\Handler\StreamHandler(
                        CAT_ENGINE_PATH.'/temp/logs/'.$logfile,
                        $class::$loglevel,
                        false
                    );
                    $stream->setFormatter(new \Monolog\Formatter\LineFormatter(
                        "[%datetime%] [%channel%.%level_name%]  %message%  %context% %extra%\n"
                    ));
                    $logger->pushHandler($stream);
                    $logger->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
                    Registry::set('CAT.logger.'.$class,$logger);
                }
                return $logger;
            }
            else {
                return Base::$objects['logger'];
            }
        }   // end function log ()

        /**
         * accessor to permissions
         *
         * @access public
         * @return object - instanceof \CAT\Permissions
         **/
        public static function perms()
        {
            if(
                   !isset(Base::$objects['perms'])
                || !is_object(Base::$objects['perms'])
                || !Base::$objects['perms'] instanceof \CAT\Permissions
            ) {
                self::storeObject('perms',\CAT\Permissions::getInstance());
            }
            return Base::$objects['perms'];
        }   // end function perms()

        /**
         * accessor to current role object
         *
         * @access public
         * @return object - instanceof \CAT\Roles
         **/
        public static function role()
        {
            if(
                   !isset(Base::$objects['roles'])
                || !is_object(Base::$objects['roles'])
                || !Base::$objects['roles'] instanceof \CAT\Roles
            ) {
                self::storeObject('roles',\CAT\Roles::getInstance());
            }
            return Base::$objects['roles'];
        }   // end function role()

        /**
         * accessor to router
         *
         * @access public
         * @return object - instanceof \CAT\Router
         **/
        public static function router()
        {
            if(
                   !isset(Base::$objects['router'])
                || !is_object(Base::$objects['router'])
                || !Base::$objects['router'] instanceof \CAT\Router
            ) {
                self::storeObject('router',Router::getInstance());
            }
            return Base::$objects['router'];
        }   // end function router()

        /**
         * gets the data of the currently used Site from the DB and caches them
         *
         * @access public
         * @return array
         **/
        public static function site()
        {
            if(!Base::$site || !is_array(Base::$site) || !count(Base::$site)>0)
            {
                $stmt = self::db()->query(
                    'SELECT * FROM `:prefix:sites` WHERE `site_id`=?',
                    array(CAT_SITE_ID)
                );
                Base::$site = $stmt->fetch();
            }
            return Base::$site;
        }   // end function site()

        /**
         * accessor to current template engine object
         *
         * @access public
         * @return object - instanceof \CAT\Helper\Template
         **/
        public static function tpl()
        {
            if(
                   !isset(Base::$objects['tpl'])
                || !is_object(Base::$objects['tpl'])
                || !Base::$objects['tpl'] instanceof \CAT\Helper\Template
            ) {
                Base::$objects['tpl'] = Template::getInstance('Dwoo');
                Base::$objects['tpl']->setGlobals(array(
                    'WEBSITE_DESCRIPTION' => Registry::get('WEBSITE_DESCRIPTION'),
                    'WEBSITE_TITLE'       => Registry::get('WEBSITE_TITLE'),
                    'CAT_VERSION'         => Registry::get('CAT_VERSION'),
                    'CAT_SITE_URL'        => CAT_SITE_URL,
#                    'CAT_CORE'            => 'BlackCat CMS',
#                    'CAT_BUILD'           => Registry::get('CAT_BUILD'),
#                    'CAT_DATE_FORMAT'     => Registry::get('CAT_DATE_FORMAT'),
                    'LANGUAGE'            => Registry::get('LANGUAGE'),
                ));
            }
            return Base::$objects['tpl'];
        }   // end function tpl()

        /**
         * accessor to current user object
         *
         * @access public
         * @return object - instanceof \CAT\User
         **/
        public static function user()
        {
            if(
                   !isset(Base::$objects['user'])
                || !is_object(Base::$objects['user'])
                || !Base::$objects['user'] instanceof \CAT\User
            ) {
                self::storeObject('user',User::getInstance());
            }
            return Base::$objects['user'];
        }   // end function user()

// =============================================================================
// various helper functions
// =============================================================================

        /**
         * add language file for current language (if any)
         *
         * @access public
         * @return
         **/
        public static function addLangFile($path)
        {
            $lang     = Registry::get('LANGUAGE');
            foreach(array_values(array($lang, strtoupper($lang), strtolower($lang))) as $l) {
                $langfile = Directory::sanitizePath($path.'/'.$l.'.php');
                // load language file (if exists and is valid)
                if(file_exists($langfile) && self::lang()->checkFile($langfile,'LANG',true))
                {
                    self::lang()->addFile($l.'.php', $path);
                }
            }
        }   // end function addLangFile()
        
        /**
         * create a guid; used by the backend, but can also be used by modules
         *
         * @access public
         * @param  string  $prefix - optional prefix
         * @return string
         **/
        public static function createGUID(string $prefix='')
        {
            if(!$prefix||$prefix='') $prefix=rand();
            $s = strtoupper(md5(uniqid($prefix,true)));
            $guidText =
                substr($s,0,8) . '-' .
                substr($s,8,4) . '-' .
                substr($s,12,4). '-' .
                substr($s,16,4). '-' .
                substr($s,20);
            return $guidText;
        }   // end function createGUID()

        /**
         *
         * @access public
         * @return
         **/
        public static function getEncodings(bool $with_labels=false)
        {
            $result = array();
            $sth = self::db()->query(
                'SELECT ' . ($with_labels?'*':'`name`').' FROM `:prefix:charsets` ORDER BY `name` ASC'
            );
            $data = $sth->fetchAll();
            foreach($data as $item) {
                if($with_labels) {
                    $result[$item['name']] = $item['labels'];
                } else {
                    $result[] = $item['name'];
                }
            }
            return $result;
        }   // end function getEncodings()

        /**
         *
         * @access public
         * @return
         **/
        public static function getItemID(string $key) : int
        {
            $itemID  = \CAT\Helper\Validate::sanitizePost($key,'numeric');

            if(!$itemID)
                $itemID  = \CAT\Helper\Validate::sanitizeGet($key,'numeric');

            if(!$itemID)
                $itemID = self::router()->getParam(-1);

            if(!$itemID)
                $itemID = self::router()->getRoutePart(-1);

            if(!$itemID || !is_numeric($itemID))
                $itemID = 0;

            return (int)$itemID;
        }   // end function getItemID()
        
        
        /**
         * returns a list of installed languages
         *
         * if $langs_only is true (default), only the list of available langs
         * will be returned; if set to false, the complete result of
         * Addons::getAddons will be returned
         *
         * @access public
         * @param  boolean  $langs_only
         * @return array
         **/
        public static function getLanguages(bool $langs_only=true)
        {
            if($langs_only)
                return Addons::getAddons('language');
            return Addons::getAddons('language','name',false,true);
        }   // end function getLanguages()

        /**
         * get value for setting $name
         *
         * @access public
         * @param  string   setting name (example: wysiwyg_editor)
         * @return mixed    setting value or false
         **/
        public static function getSetting(string $name)
        {
            if(!self::$settings || !is_array(self::$settings))
                self::loadSettings();
            if(isset(self::$settings[$name]))
                return self::$settings[$name];
            return false;
        }   // end function getSetting()

        /**
         *
         * @access public
         * @return
         **/
        public static function getStateID(string $name)
        {
            $sth = self::db()->query(
                'SELECT `state_id` FROM `:prefix:item_states` WHERE `state_name`=?',
                array($name)
            );
            $data = $sth->fetch();
            if(isset($data['state_id'])) return $data['state_id'];
            return false;
        }   // end function getStateID()

        /**
         * converts variable names like "default_template_variant" into human
         * readable labels like "Default template variant"
         *
         * @access public
         * @return
         **/
        public static function humanize(string $string)
        {
            return ucfirst(str_replace('_',' ',$string));
        }   // end function humanize()

        /**
         * get the settings from the DB
         * 
         * @access public
         * @return
         **/
        public static function loadSettings()
        {
            if(!self::$settings || !is_array(self::$settings))
            {
                self::$settings = array();
                self::$globals  = array();

                // populate self::$settings
                $sql = 'SELECT `t1`.`name`, '
                     . 'IFNULL(`t2`.`value`, `t1`.`value`) AS `value` '
                     . 'FROM `:prefix:settings_global` AS `t1` '
                     . 'LEFT JOIN `:prefix:settings_site` AS `t2` '
                     . 'ON `t1`.`name`=`t2`.`name` AND `t2`.`site_id`=? '
                     . 'ORDER BY `t1`.`name`';

                if(null!==($stmt=self::db()->query($sql,array(CAT_SITE_ID))))
                {
                    $rows = $stmt->fetchAll();
                    foreach($rows as $row)
                    {
                        if (preg_match('/^[0-7]{1,4}$/', $row['value']) == true)
                            $value = $row['value'];
                        elseif (preg_match('/^[0-9]+$/S', $row['value']) == true)
                            $value = intval($row['value']);
                        elseif ($row['value'] == 'false')
                            $value = false;
                        elseif ($row['value'] == 'true')
                            $value = true;
                        else
                            $value = $row['value'];
                        $temp_name = strtoupper($row['name']);
                        Registry::register($temp_name, $value);
                        self::$settings[$row['name']] = $value;
                    }
                    unset($row);
                }
                else
                {
                    Base::printFatalError("No settings found in the database, please check your installation!");
                }
            }

            return self::$settings;
        }   // end function loadSettings()

        /**
         *
         * @access public
         * @return
         **/
        public static function setTemplatePaths(string $name,string $variant='default',string $type='module')
        {
            $base = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.$type.'s/'.$name.'/templates');
            $paths = array(
                $base.'/'.$variant,
                $base.'/default',
                $base
            );
            foreach($paths as $path)
            {
                if(file_exists($path))
                {
                    self::tpl()->setPath($path);
                    self::tpl()->setFallbackPath($base.'/default');
                    return;
                }
            }
        }   // end function setTemplatePaths()
        
        

// =============================================================================
//   JSON output helper functions
// =============================================================================

        /**
         * checks for 'ACCEPT' request header; returns true if exists and
         * value is 'application/json'
         *
         * @access public
         * @return boolean
         **/
        public static function asJSON()
        {
            $headers = self::getallheaders();
            if(isset($headers['Accept']) && preg_match('~application/json~i',$headers['Accept']))
                return true;
            else
                return false;
        }   // end function asJSON()

// =============================================================================
//  LOGGING / DEBUGGING
// =============================================================================

        /**
         * enable or disable debugging at runtime
         *
         * @access public
         * @param  boolean  enable (TRUE) / disable (FALSE)
         *
         **/
        public function debug(bool $bool)
        {
            $class = get_called_class();
            if ($bool === true)
            {
                self::log()->addDebug('enable debugging for class {class}',array('class'=>$class));
                $class::$loglevel = \Monolog\Logger::DEBUG;
            }
            else
            {
                self::log()->addDebug('resetting loglevel to default for class {class}',array('class'=>$class));
                $class::$loglevel = Base::$loglevel;
            }
        }   // end function debug()

        /**
         *
         * @access public
         * @return
         **/
        public static function getLogLevel()
        {
            $class = get_called_class();
            return $class::$loglevel;
        }   // end function getLogLevel()

        /**
         *
         * @access public
         * @return
         **/
        public static function setLogLevel(string $level='EMERGENCY')
        {
#echo "setLogLevel()<br />";
echo "<pre>";
print_r(debug_backtrace());
echo "</pre>";
            // map old KLogger levels
            if(is_numeric($level))
            {
                switch($level) {
                    case 8:
                        $level = 'EMERGENCY';
                        break;
                    default:
                        $level = 'DEBUG';
                        break;
                }
            }
            $class = get_called_class();
echo "setLogLevel called for class $class, old level ", $class::getLogLevel(), ", new level $level<br />";
            $class::$loglevel = constant('\Monolog\Logger::'.$level);
echo "level now: ", $class::$loglevel, "<br />";
        }   // end function setLogLevel()
        

// =============================================================================
//  ERROR HANDLING
// =============================================================================

        public static function errorstate(int $id=NULL)
        {
            if($id)
                Base::$errorstate = $id;
            return Base::$errorstate;
        }   // end function errorstate()

        /**
         * print an error message; this will set the HTTP status code to 500
         *
         * the error message will be translated (if translation is available)
         *
         * @access public
         * @param  string   $message
         * @param  string   $link         - URL for forward to
         * @param  boolean  $print_header - wether to print the page header
         * @param  array    $args
         * @return void
         **/
        public static function printError(string $message='',string $link='index.php',bool $print_header=true,array $args=array())
        {

            if(empty($message)) {
                $message = 'unknown error';
            }
            self::log()->addError($message);
            self::errorstate(500);

            if(self::asJSON())
            {
                echo Json::printError($message,true);
                exit; // should never be reached
            }

            $message = Base::lang()->t($message);
            $errinfo = Base::lang()->t(self::$state[self::errorstate()]);

            $print_footer = false;
            if(!headers_sent() && $print_header)
            {
                $print_footer = true; // print header also means print footer
                if (
                       !isset(Base::$objects['tpl'])
                    || !is_object(Base::$objects['tpl'])
#                    || ( !self::router()->isBackend() && !defined('CAT_PAGE_CONTENT_DONE'))
                ) {
                    // template helper not available
                    self::err_page_header();
                } else {
                    if(self::router()->isBackend()) {
                        \CAT\Backend::printHeader();
                    }
                }
            }

            if (
                   !isset(Base::$objects['tpl'])
                || !is_object(Base::$objects['tpl'])
                || self::router()->isBackend()
            )
            //if (!is_object(Base::$objects['tpl']) || ( !Backend::isBackend() && !defined('CAT_PAGE_CONTENT_DONE')) )
            {
                require dirname(__FILE__).'/templates/error_content.php';
            }

            if($print_footer)
            {
                if (!isset(Base::$objects['tpl']) || !is_object(Base::$objects['tpl']))
                {
                    self::err_page_footer();
                }
                if(self::router()->isBackend()) {
                    \CAT\Backend::printFooter();
                }
            }

        }   // end function printError()

        /**
         * wrapper to printError(); print error message and exit
         *
         * see printError() for @params
         *
         * @access public
         * @return void
         **/
        public static function printFatalError(string $message=NULL,string $link='index.php',bool $print_header=true,array $args=array())
        {
            Base::printError($message, $link, $print_header, $args);
            exit;
        }   // end function printFatalError()

        /**
         *  Print a message and redirect the user to another page
         *
         *  @access public
         *  @param  mixed   $message     - message string or an array with a couple of messages
         *  @param  string  $redirect    - redirect url; default is "index.php"
         *  @param  boolean $auto_footer - optional flag to 'print' the footer. Default is true.
         *  @param  boolean $auto_exit   - optional flag to call exit() (default) or not
         *  @return void    exit()s
         */
    	public static function printMsg($message,string $redirect='index.php',bool $auto_footer=true,bool $auto_exit=true)
    	{
    		if (true === is_array($message))
    			$message = implode("<br />", $message);

    		self::tpl()->setPath(CAT_THEME_PATH.'/templates');
    		self::tpl()->setFallbackPath(CAT_THEME_PATH.'/templates');

    		self::tpl()->output('success',array(
                'MESSAGE'        => Base::lang()->translate($message),
                'REDIRECT'       => $redirect,
                'REDIRECT_TIMER' => Registry::get('REDIRECT_TIMER'),
            ));

    		if ($auto_footer == true)
    		{
                $caller       = debug_backtrace();
                // remove first item (it's the printMsg() method itself)
                array_shift($caller);
                $caller_class
                    = isset( $caller[0]['class'] )
                    ? $caller[0]['class']
                    : NULL;
    			if ($caller_class && method_exists($caller_class, "print_footer"))
    			{
                    if( is_object($caller_class) )
    				    $caller_class->print_footer();
                    else
                        $caller_class::print_footer();
    			}
                else {
                    self::log()->error("unable to print footer - no such method $caller_class -> print_footer()");
                }
                if($auto_exit)
                    exit();
    		}
        }   // end function printMsg()

        /**
         *
         * @access public
         * @return
         **/
        public static function storeObject(string $name,$obj)
        {
            Base::$objects[$name] = $obj;
        }   // end function storeObject()
        

        /**
         * prints (requires) error_footer.php
         *
         * @access private
         * @return void
         **/
        private static function err_page_footer()
        {
            require dirname(__FILE__).'/templates/error_footer.php';
            return;
        }   // end function err_page_footer()

        /**
         * prints (requires) error_header.php; also sets HTTP status header
         * and $_SERVER['REDIRECT_STATUS']
         *
         * @access private
         * @return void
         **/
        private static function err_page_header()
        {
            header('HTTP/1.1 '.self::$errorstate.' '.self::$state[self::$errorstate]);
		    header('Status: '.self::$errorstate.' '.self::$state[self::$errorstate]);
		    $_SERVER['REDIRECT_STATUS'] = self::$errorstate;
            require dirname(__FILE__).'/templates/error_header.php';
            return;
        }   // end function err_page_header()
        
		/**
		 * Get all HTTP header key/values as an associative array for the current request.
		 * 
		 * https://github.com/ralouphie/getallheaders
		 * 
		 * @return string[string] The HTTP header key/value pairs.
		 **/
		private static function getallheaders()
		{
			$headers = array();
			
			$copy_server = array(
			    'CONTENT_TYPE'   => 'Content-Type',
			    'CONTENT_LENGTH' => 'Content-Length',
			    'CONTENT_MD5'    => 'Content-Md5',
			);
			
			foreach ($_SERVER as $key => $value) {
			    if (substr($key, 0, 5) === 'HTTP_') {
			        $key = substr($key, 5);
			        if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
			            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
			            $headers[$key] = $value;
			        }
			    } elseif (isset($copy_server[$key])) {
			        $headers[$copy_server[$key]] = $value;
			    }
			}
			
			if (!isset($headers['Authorization'])) {
			    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
			        $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
			    } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
			        $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
			        $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
			    } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
			        $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
			    }
			}
			return $headers;
		}   // end function getallheaders()
    }
}


/**
 * This class adds the old logging method names to the new Monolog logger
 * used since BlackCat version 2.0
 **/
if(!class_exists('Base_LoggerDecorator',false))
{
    class Base_LoggerDecorator extends \Monolog\Logger
    {
        private $logger = NULL;
        public function __construct(\Monolog\Logger $logger) {
            parent::__construct($logger->getName());
            $this->logger = $logger;
        }
        public function logDebug (string $msg,array $args=array()) {
            if(!is_array($args)) $args = array($args);
            return $this->logger->addDebug($msg,$args);
        }
        public function logInfo  () {
        }
        public function logNotice() {
        }
        public function logWarn  () {
        }
        public function logError () {}
        public function logFatal () {}
        public function logAlert () {}
        public function logCrit  () {}
        public function logEmerg () {}
    }
}