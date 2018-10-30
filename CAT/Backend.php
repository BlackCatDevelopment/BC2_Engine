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
use \CAT\Registry as Registry;
use \CAT\Helper\HArray as HArray;
use \CAT\Helper\Validate as Validate;
use \CAT\Helper\FormBuilder as FormBuilder;
use \CAT\Helper\Json as Json;

if (!class_exists('Backend', false))
{
    class Backend extends Base
    {
        #protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $loglevel = \Monolog\Logger::DEBUG;

        private   static $instance    = array();
        private   static $form        = NULL;
        private   static $route       = NULL;
        private   static $params      = NULL;
        private   static $menu        = NULL;
        private   static $breadcrumb  = null;
        private   static $tplpath     = NULL;
        private   static $tplfallback = NULL;

        // public routes (do not check for authentication)
        private   static $public   = array(
            'languages','login','authenticate','logout','qr','tfa'
        );

        /**
         * dispatch backend route
         **/
        public static function dispatch()
        {
            return self::router()->dispatch('Backend');
        }   // end function dispatch()

        /**
         *
         * @access public
         * @return
         **/
        public static function getArea(bool $getID = false)
        {
            $route = self::router()->getRoute();
            // example route: backend/page/edit/1
            $parts = explode('/',$route);
            if($parts[0]==CAT_BACKEND_PATH) array_shift($parts);
            if($getID) {
                $stmt = self::db()->query(
                    'SELECT `id` FROM `:prefix:backend_areas` WHERE `name`=?',
                    array($parts[0])
                );
                $data = $stmt->fetch();
                return $data['id'];
            }
            return $parts[0];
            return null;
        }   // end function getArea()

        /**
         *
         * @access public
         * @return
         **/
        public static function getBreadcrumb()
        {
            $menu   = \CAT\Backend::getMainMenu();
            $parts  = self::router()->getParts();
            $bread  = array();
            $seen   = array();
            $last   = null;
            $level  = 1;

            foreach(array_values($parts) as $item) {
                for($i=0;$i<count($menu);$i++) {
                    if($menu[$i]['name']==$item) {
                        $menu[$i]['id'] = $item;
                        $menu[$i]['parent'] = $last;
                        array_push($bread,$menu[$i]);
                        $seen[$item] = 1;
                        $last = $item;
                        $level = (isset($menu[$i]['level']) ? $menu[$i]['level'] : 1);
                        continue;
                    }
                }
                if(!isset($seen[$item])) {
                    array_push($bread,array(
                        'id'          => $item,
                        'name'        => $item,
                        'parent'      => $last,
                        'title'       => self::lang()->t(self::humanize($item)),
                        'href'        => CAT_ADMIN_URL."/".implode("/", array_slice($parts,0,($level+1))),
                        'level'       => ++$level,
                        'is_current' => true,
                    ));
                    $last = $item;
                }
            }

            return $bread;
        }   // end function getBreadcrumb()

        /**
         * get the main menu (backend sections)
         * checks the user privileges
         *
         * @access public
         * @return array
         **/
        public static function getMainMenu($parent=NULL)
        {
            if(!self::$menu)
            {
                // get backend areas
                $r = self::db()->query('SELECT * FROM `:prefix:backend_areas` ORDER BY `level` ASC, `parent` ASC, `position` ASC');
                self::$menu = $r->fetchAll(\PDO::FETCH_ASSOC);
                self::log()->addDebug('main menu items from DB: '.print_r(self::$menu,1));

                foreach(self::$menu as $i => $item)
                {
                    self::$menu[$i]['title'] = self::lang()->t(ucfirst($item['name']));
                    if($item['controller'] != '') # find controller
                    {
                        self::$menu[$i]['href']
                            = CAT_ADMIN_URL.'/'
                            . ( strlen($item['controller']) ? $item['controller'].'/' : '' )
                            . $item['name'];
                    }
                    else
                    {
                        self::$menu[$i]['href'] = CAT_ADMIN_URL.'/'.$item['name'];
                    }
                    self::$menu[$i]['controller'] = ( !empty($item['controller']) ? $item['controller'] : '\CAT\Backend\\'.ucfirst($item['name']) );
                }

                // get available settings categories / regions
                $r       = self::db()->query('SELECT `region` FROM `:prefix:settings` GROUP BY `region`');
                $regions = $r->fetchAll();
                $path    = HArray::search('settings',self::$menu,'name');
                $id      = 1000;
                $set_parent = self::$menu[$path[0]];
                foreach($regions as $region)
                {
                    self::$menu[] = array(
                        'id'          => $id,
                        'name'        => $region['region'],
                        'parent'      => $set_parent['id'],
                        'title'       => self::humanize($region['region']),
                        'href'        => CAT_ADMIN_URL.'/settings/'.$region['region'],
                    );
                    $id++;
                }
            }

            if($parent)
            {
                $menu = self::$menu;
                $menu = HArray::filter($menu,'parent',$parent);
                return $menu;
            }

            return self::$menu;
        }   // end function getMainMenu()

        /**
         *
         **/
        public static function getPublicRoutes()
        {
             return self::$public;
        }    // end function getPublicRoutes()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            // forward to dashboard
            header('Location: '.CAT_ADMIN_URL.'/dashboard');
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function initialize()
        {
            if(self::user()->is_authenticated())
            {
                $username_fieldname = Validate::createFieldname('username_');
                $add_form   = FormBuilder::generateForm('be_page_add');
                $add_form->getElement('page_type')->setValue("page");
                $add_form->getElement('default_radio')->setLabel('Insert');
                $add_form->getElement('default_radio')->setName('page_insert');
                $add_form->getElement('page_before_after')->setLabel(' ');
                self::tpl()->setGlobals(array(
                    'add_page_form'      => $add_form->render(true),
                    'USERNAME_FIELDNAME'    => $username_fieldname,
                    'PASSWORD_FIELDNAME'    => Validate::createFieldname('password_'),
                ));
            }
        }   // end function initialize()
        
        
        /**
         * create a global FormBuilder handler
         *
         * @access public
         * @return
         **/
        public static function initForm()
        {
#            \wblib\wbFormsJQuery::set('enabled',false);
#            \wblib\wbFormsJQuery::set('load_ui_theme',false);
#            \wblib\wbFormsJQuery::set('disable_tooltips',true);
        }   // end function initForm()

        /**
         * initializes template search paths for backend
         *
         * @access public
         * @return
         **/
        public static function initPaths()
        {
            if(!self::$tplpath || !file_exists(self::$tplpath))
            {
                $theme   = Registry::get('default_theme',null,'backstrap');
                $variant = Registry::get('default_theme_variant');
                if(!$variant || !strlen($variant)) $variant = 'default';
                $paths = array( // search paths
                    CAT_ENGINE_PATH.'/templates/'.$theme.'/templates/'.$variant,
                    CAT_ENGINE_PATH.'/templates/'.$theme.'/templates/default',
                    CAT_ENGINE_PATH.'/templates/'.$theme.'/templates',
                );
                foreach($paths as $path)
                {
                    if(file_exists($path))
                    {
                        self::$tplpath = $path;
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Check if default subdir exists
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                        self::$tplfallback = CAT_ENGINE_PATH.'/templates/'.$theme.'/templates/default';
                    }
                }
            }
            self::tpl()->setPath(self::$tplpath,'backend');
            self::tpl()->setFallbackPath(self::$tplfallback,'backend');

        }   // end function initPaths()

        /**
         * checks if the current path is inside the backend folder
         *
         * @access public
         * @return boolean
         **/
        public static function isBackend()
        {
            return self::router()->isBackend();
        }   // end function isBackend()

// =============================================================================
//     Route handler
// =============================================================================

        /**
         * handle user authentication
         *
         * @access public
         * @return mixed
         **/
        public static function authenticate()
        {
            if(false!==self::user()->login())
            {
                self::log()->addDebug(sprintf(
                    'Authentication succeeded, username [%s], id [%s]',
                    self::user()->get('username'), self::user()->get('user_id')
                ));

                // forward
                if(self::asJSON())
                {
                    self::log()->addDebug(sprintf(
                        'sending json result, forward to URL [%s]',
                        CAT_ADMIN_URL.'/dashboard'
                    ));
                    Json::printData(array(
                        'success' => true,
                        'url'     => CAT_ADMIN_URL.'/dashboard'
                    ));
                }
                else
                {
                    self::log()->addDebug(sprintf(
                        'forwarding to URL [%s]',
                        CAT_ADMIN_URL.'/dashboard'
                    ));
                    header('Location: '.CAT_ADMIN_URL.'/dashboard');
                }
            }
            else
            {
                self::log()->addDebug('Authentication failed!');
                if(self::asJSON())
                    Json::printError('Authentication failed!');
                else
                    self::printFatalError('Authentication failed!');
            }
            exit;
        }   // end function authenticate()

        /**
         *
         * @access public
         * @return
         **/
        public static function languages()
        {
            $self  = self::getInstance();
            $langs = self::getLanguages(1);

            if(($parm = self::router()->getRoutePart(-1)) !== false)
            {
                switch($parm)
                {
                    case 'select':
                        $langselect = array(''=>'[Please select]');
                        foreach(array_values($langs) as $l)
                            $langselect[$l] = $l;
                        Json::printSuccess($langselect);
                        break;
                    case 'form':
                        $form = new \wblib\wbForms\Element\Select(
                            'language'
                        );
                        $form->setData($langs);
                        Json::printSuccess($form->render());
                        break;
                }
            }
            echo Json::printSuccess();
        }   // end function languages()

        /**
         * show the login page
         *
         * @access public
         * @return
         **/
        public static function login($msg=null)
        {
            self::log()->addDebug('printing login page');
            self::initPaths();
            // we need this twice, so we use a var here
            $username_fieldname = Validate::createFieldname('username_');
			$tpl_data = array(
                'USERNAME_FIELDNAME'    => $username_fieldname,
                'PASSWORD_FIELDNAME'    => Validate::createFieldname('password_'),
                'TOKEN_FIELDNAME'       => Validate::createFieldname('token_'),
                'USERNAME'              => Validate::sanitizePost($username_fieldname),
                'ENABLE_TFA'            => Registry::get('enable_tfa'),
                'error_message'         => ($msg ? self::lang()->translate($msg) : null),
            );
            self::log()->addDebug('printing login page');
            self::tpl()->output('login',$tpl_data);
        }   // end function login()

        /**
         *
         * @access public
         * @return
         **/
        public static function logout()
        {
            self::user()->logout();
        }

        /**
         *  Print the admin header
         *
         *  @access public
         *  @return void
         */
        public static function printHeader()
        {
            $tpl_data = array();
            $menu     = self::getMainMenu();

            // init template search paths
            self::initPaths();

            // the original list, ordered by parent -> children (if the
            // templates renders the HTML output)
            $lb = Base::lb();
            $lb->set('id','id');
            $lb->set('title','title');

            $tpl_data['MAIN_MENU'] = $lb->sort($menu,0);

            // recursive list
            $tpl_data['MAIN_MENU_RECURSIVE'] = $lb->buildRecursion($menu);

            // render list (ul)
            $lb->set(array(
                'top_ul_class'     => 'nav',
                'ul_class'         => 'nav',
                'current_li_class' => 'active',
                'space'            => '',
            ));
            $tpl_data['MAIN_MENU_UL'] = $lb->buildList($menu);

            // set the page title
            $controller = explode('\\',self::router()->getController());
            \CAT\Helper\Page::setTitle(sprintf(
                'BlackCat CMS Backend / %s',
                self::lang()->translate($controller[count($controller)-1])
            ));

            self::log()->addDebug('printing header');
            self::tpl()->output('header', $tpl_data);

            // reset listbuilder
            $lb->set('id','page_id');
            $lb->set('title','menu_title');
        }   // end function printHeader()

        /**
        * Print the admin footer
        *
        * @access public
        **/
        public static function printFooter()
        {
            $data = array();
            self::initPaths();

            $t = ini_get('session.gc_maxlifetime');
            $data['SESSION_TIME'] = sprintf('%02d:%02d:%02d', ($t/3600),($t/60%60), $t%60);

            // =================================================================
            // ! Try to get the actual version of the backend-theme
            // =================================================================
            $backend_theme_version = '-';
            $theme                 = Registry::get('DEFAULT_THEME');
            if($theme)
            {
                $classname = '\CAT\Addon\Template\\'.$theme;
                $filename  = \CAT\Helper\Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.$theme.'/inc/class.'.$theme.'.php');
                if(file_exists($filename)) {
                    $handler = $filename;
                    include_once $handler;
                    $data['THEME_INFO'] = $classname::getInfo();
                }
                
            }
            $data['WEBSITE_TITLE'] = Registry::get('website_title');

            global $_be_mem, $_be_time;
            $data['system_information'] = array(
                array(
                    'name'      => self::lang()->translate('PHP version'),
                    'status'    => phpversion(),
                ),
                array(
                    'name'      => self::lang()->translate('Memory usage'),
                    'status'    => '~ ' . sprintf('%0.2f',( (memory_get_usage() - $_be_mem) / (1024 * 1024) )) . ' MB'
                ),
                array(
                    'name'      => self::lang()->translate('Script run time'),
                    'status'    => '~ ' . sprintf('%0.2f',( microtime(TRUE) - $_be_time )) . ' sec'
                ),
            );

            self::tpl()->output('footer', $data);

            // ======================================
            // ! make sure to flush the output buffer
            // ======================================
            if(ob_get_level()>1)
                while (ob_get_level() > 0)
                    ob_end_flush();

        }   // end function printFooter()

        /**
         * check if TFA is enabled for current user
         *
         * @access public
         * @return
         **/
        public static function tfa()
        {
            $user = new User(Validate::sanitizePost('user'));
            echo Json::printSuccess($user->tfa_enabled());
        }   // end function tfa()

    }   // end class Backend
}