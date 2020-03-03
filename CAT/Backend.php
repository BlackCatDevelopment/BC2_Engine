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
use \CAT\Helper\Directory as Directory;
use \CAT\Helper\HArray as HArray;
use \CAT\Helper\Validate as Validate;
use \CAT\Helper\FormBuilder as FormBuilder;
use \CAT\Helper\Json as Json;

if (!class_exists('Backend', false)) {
    class Backend extends Base
    {
        #protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $loglevel = \Monolog\Logger::DEBUG;

        private static $instance    = array();
        private static $form        = null;
        private static $route       = null;
        private static $params      = null;
        private static $menu        = array();
        private static $breadcrumb  = null;
        private static $tplpath     = null;
        private static $tplfallback = null;
        private static $scope       = null;

        // public routes (do not check for authentication)
        private static $public   = array(
            'languages','login','authenticate','logout','qr','tfa'
        );

        /**
         * handle the /backend/authenticate path
         **/
        public static function authenticate()
        {
            self::log()->addDebug('handling /backend/authenticate path (forward to \CAT\Helper\Users)');
            return \CAT\Helper\Users::authenticate();
        }

        /**
         * dispatch backend route
         **/
        public static function dispatch()
        {
            self::log()->addDebug(sprintf(
                'dispatch() - route [%s]',
                self::router()->getRoute()
            ));
            return self::router()->dispatch('Backend');
        }   // end function dispatch()

        /**
         * get the id for the current backend area
         *
         * @access public
         * @return string
         **/
        public static function getArea(bool $getID = false)
        {
            $route = self::router()->getRoute();
            self::log()->addDebug(sprintf(
                'getArea() - route [%s]',
                $route
            ));
            // example route: backend/pages/edit/1
            $parts = explode('/', $route);
            if ($parts[0]==CAT_BACKEND_PATH) {
                array_shift($parts);
            }
            if ($getID) {
                $stmt = self::db()->query(
                    'SELECT `id` FROM `:prefix:backend_areas` WHERE `name`=?',
                    array($parts[0])
                );
                $data = $stmt->fetch();
                return $data['id'];
            }
            return $parts[0];
        }   // end function getArea()

        /**
         * get menu items for breadcrumb
         *
         * @access public
         * @return array
         **/
        public static function getBreadcrumb() : array
        {
            self::log()->addDebug('getBreadcrumb()');
            $menu   = \CAT\Backend::getMainMenu();
            $parts  = self::router()->getParts();
            $bread  = array();
            $seen   = array();
            $last   = null;
            $level  = 1;

            foreach (array_values($parts) as $item) {
                for ($i=0;$i<count($menu);$i++) {
                    if ($menu[$i]['name']==$item) {
                        $menu[$i]['id'] = $item;
                        $menu[$i]['parent'] = $last;
                        array_push($bread, $menu[$i]);
                        $seen[$item] = 1;
                        $last = $item;
                        $level = (isset($menu[$i]['level']) ? $menu[$i]['level'] : 1);
                        continue;
                    }
                }
                if (!isset($seen[$item])) {
                    array_push($bread, array(
                        'id'          => $item,
                        'name'        => $item,
                        'parent'      => $last,
                        'title'       => self::lang()->t(self::humanize($item)),
                        'href'        => CAT_ADMIN_URL."/".implode("/", array_slice($parts, 0, ($level+1))),
                        'level'       => ++$level,
                        'is_current' => true,
                    ));
                    $last = $item;
                }
            }

            if (!isset($seen['administration'])) {
                array_unshift($bread, array(
                    'id'          => 'administration',
                    'name'        => 'administration',
                    'parent'      => null,
                    'title'       => self::lang()->t(self::humanize('administration')),
                    'href'        => CAT_ADMIN_URL."/administration",
                    'level'       => 0,
                    'is_current'  => true,
                ));
                $bread[1]['parent'] = 'administration';
            }

            return $bread;
        }   // end function getBreadcrumb()

        /**
        * Print the admin footer
        *
        * @access public
        **/
        public static function getFooter()
        {
            $data = array();
            self::initPaths();

            // regenerate session
            self::session()->migrate();

            $t = self::session()->getMetadataBag()->getLifetime();
            $data['SESSION_TIME'] = sprintf('%02d:%02d:%02d', ($t/3600), ($t/60%60), $t%60);

            // =================================================================
            // ! Try to get the actual version of the backend-theme
            // =================================================================
            $backend_theme_version = '-';
            $theme                 = Registry::get('DEFAULT_THEME');
            if ($theme) {
                $classname = '\CAT\Addon\Template\\'.$theme;
                $filename  = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$theme.'/inc/class.'.$theme.'.php');
                if (file_exists($filename)) {
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
                    'status'    => '~ ' . sprintf('%0.2f', ((memory_get_usage() - $_be_mem) / (1024 * 1024))) . ' MB'
                ),
                array(
                    'name'      => self::lang()->translate('Script run time'),
                    'status'    => '~ ' . sprintf('%0.2f', (microtime(true) - $_be_time)) . ' sec'
                ),
            );

            return self::tpl()->get('footer', $data);
        }   // end function getFooter()

        /**
         *  Print the admin header
         *
         *  @access public
         *  @return void
         */
        public static function getHeader(bool $with_menu=true)
        {
            $tpl_data = array();

            // init template search paths
            self::initPaths();

            if ($with_menu) {
                $menu     = self::getMainMenu();

                if (!self::showPageTree()) {
                    self::tpl()->setGlobals('pageTree', false);
                }

                // the original list, ordered by parent -> children (if the
                // templates renders the HTML output)
                $lb = new \wblib\wblist\Tree(
                    $menu,
                    array('value'=>'title','linkKey'=>'href','root_id'=>0)
                );
                #$tpl_data['MAIN_MENU'] = $lb->flattened();

                // recursive list
                #$tpl_data['MAIN_MENU_RECURSIVE'] = $lb->flattened();
            }

            // set the page title
            $controller = explode('\\', self::router()->getController());
            \CAT\Helper\Page::setTitle(sprintf(
                'BlackCat CMS Backend / %s',
                self::lang()->translate($controller[count($controller)-1])
            ));

            return $tpl_data;
        }   // end function getHeader()

        /**
         * get the main menu (backend sections)
         * checks the user privileges
         *
         * @access public
         * @param  integer  $parent
         * @return array
         **/
        public static function getMainMenu($parent=null) : array
        {
            self::log()->addDebug('getMainMenu');
            // get current scope ID by name
            $scope = self::getScope(self::$scope);
            // make sure the menu is loaded
            self::getMenuItems();
            // sub menu
            if ($parent) {
                $menu = self::$menu[$scope];
                $menu = HArray::filter($menu, 'parent', $parent);
                return $menu;
            }
            if (!isset(self::$menu[$scope])) {
                self::log()->addError(sprintf(
                    'no menu for scope: %s',
                    $scope
                ));
            } else {
                self::log()->addDebug(
                    'remaining menu items: '.print_r(self::$menu[$scope], 1)
                );
                // full menu
                return self::$menu[$scope];
            }
            return array();
        }   // end function getMainMenu()

        /**
         *
         * @access public
         * @return
         **/
        public static function getMenuForScope(string $scope)
        {
            self::log()->addDebug(sprintf('getMenuForScope(%s)', $scope));
            if (!is_numeric($scope)) {
                $scope = self::getScope($scope);
            }
            self::getMenuItems();
            return self::$menu[$scope];
        }   // end function getMenuForScope()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function getMenuItems()
        {
            self::log()->addDebug('getMenuItems()');
            if (!self::$menu) {
                // current area
                $area = self::getArea();
                // get available scopes
                $r = self::db()->query(
                    'SELECT * FROM `:prefix:backend_scopes`'
                );
                $scopes = $r->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($scopes as $s) {
                    $scope = $s['scope_id'];
                    $r = self::db()->query(
                        'SELECT * FROM `:prefix:backend_areas` '.
                        'WHERE `scope_id`=? '.
                        'ORDER BY `level` ASC, `parent` ASC, `position` ASC',
                        array($scope)
                    );
                    self::$menu[$scope] = $r->fetchAll(\PDO::FETCH_ASSOC);
                    self::log()->addDebug('main menu items from DB: '.print_r(self::$menu[$scope], 1));

                    // remove menu items not accessible to current user
                    for ($i=count(self::$menu[$scope])-1;$i>=0;$i--) {
                        if (!self::user()->hasPerm(self::$menu[$scope][$i]['name'])) {
                            self::log()->addDebug(sprintf(
                                'removing item [%s] (missing permission)',
                                self::$menu[$scope][$i]['name']
                            ));
                            unset(self::$menu[$scope][$i]);
                        }
                    }

                    foreach (self::$menu[$scope] as $i => $item) {
                        self::$menu[$scope][$i]['title'] = self::lang()->t(ucfirst($item['name']));
                        self::$menu[$scope][$i]['current']
                            = ($item['name']==$area)
                            ? true
                            : false;
                        if ($item['controller'] != '') { # find controller
                            self::$menu[$scope][$i]['href']
                                = CAT_ADMIN_URL.'/'
                                . (strlen($item['controller']) ? $item['controller'].'/' : '')
                                . $item['name'];
                        } else {
                            self::$menu[$scope][$i]['href'] = CAT_ADMIN_URL.'/'.$item['name'];
                        }
                        self::$menu[$scope][$i]['controller']
                            = !empty($item['controller'])
                            ? $item['controller']
                            : '\CAT\Backend\\'.ucfirst($item['name']);
                    }

                    // get available settings categories / regions
                    $r       = self::db()->query('SELECT `region` FROM `:prefix:settings` GROUP BY `region`');
                    $regions = $r->fetchAll();
                    $path    = HArray::search('settings', self::$menu[$scope], 'name');

                    // if parent is not visible, don't show child
                    if (isset($path[0])) {
                        $id = 1000;
                        $set_parent = self::$menu[$scope][$path[0]];
                        foreach ($regions as $region) {
                            if (self::user()->hasPerm($region['region'])) {
                                self::$menu[$scope][] = array(
                                    'id'          => $id,
                                    'name'        => $region['region'],
                                    'parent'      => $set_parent['id'],
                                    'title'       => self::humanize($region['region']),
                                    'href'        => CAT_ADMIN_URL.'/settings/'.$region['region'],
                                    'level'       => 2,
                                );
                                $id++;
                            }
                        }
                    }
                }
            }
            return self::$menu;
        }   // end function getMenuItems()

        /**
         *
         **/
        public static function getPublicRoutes()
        {
            self::log()->addDebug('getPublicRoutes()');
            return self::$public;
        }    // end function getPublicRoutes()

        /**
         *
         * @access public
         * @return
         **/
        public static function show(string $tpl, array $data=array(), bool $header=true, bool $footer=true)
        {
            self::log()->addDebug(sprintf(
                'show() - tpl [%s] print header [%s] print footer [%s]',
                $tpl,
                $header,
                $footer
            ));

            $data['areas'] = self::getMenuForScope(2);
            $header_data = self::getHeader(false);
            $tpl_data    = array_merge($data, $header_data);
            $output      = ($header===true ? self::tpl()->get('header', $tpl_data) : '')
                         . self::tpl()->get($tpl, $tpl_data)
                         . ($footer===true ? self::getFooter() : '');
            $headers     = \CAT\Helper\Assets::renderAssets('header', null, false, false);
            $output      = str_replace('<!-- pageheader 0 -->', $headers, $output);

            // ======================================
            // ! make sure to flush the output buffer
            // ======================================
            if (ob_get_level()>1) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
            }

            echo $output;
            exit;
        }   // end function show()

        /**
         *
         * @access public
         * @return
         **/
        public static function getScope()
        {
            self::log()->addDebug('getScope()');
            $name = self::getArea();

            // find scope by area name (not all areas are found in the db)
            $r = self::db()->query(
                'SELECT `scope_id` FROM `:prefix:backend_areas` '.
                "WHERE `name`=:area ",
                array('area'=>$name)
            );
            $result = $r->fetchAll(\PDO::FETCH_ASSOC);
            if (isset($result[0]) && isset($result[0]['scope_id'])) {
                return $result[0]['scope_id'];
            }

            // no match, find scope by scope_id
            $r = self::db()->query(
                'SELECT `scope_id` FROM `:prefix:backend_scopes` '.
                "WHERE `scope_name`=:scope ",
                array('scope'=>$name)
            );
            $result = $r->fetchAll(\PDO::FETCH_ASSOC);
            if (isset($result[0]) && isset($result[0]['scope_id'])) {
                return $result[0]['scope_id'];
            }

            // no match, assume admin
            return 2;
        }   // end function getScope()

        /**
         *
         * @access public
         * @return
         **/
        public static function showPageTree()
        {
            self::log()->addDebug('showPageTree()');
            $scope = self::getScope();
            $r = self::db()->query(
                'SELECT `page_tree` FROM `:prefix:backend_scopes` '.
                "WHERE `scope_id`=:id ",
                array('id'=>$scope)
            );
            $result = $r->fetchAll(\PDO::FETCH_ASSOC);

            if (isset($result[0]) && isset($result[0]['page_tree'])) {
                return ($result[0]['page_tree']=='Y')
                    ? true
                    : false;
            }
        }   // end function showPageTree()
        
        
        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            self::log()->addDebug(sprintf('index() - forward to [%s]', self::user()->getDefaultPage()));
            header('Location: '.CAT_ADMIN_URL.'/'.self::user()->getDefaultPage());
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function initialize()
        {
            if (self::user()->isAuthenticated()) {
                $username_fieldname = Validate::createFieldname('username_');
                $add_form   = FormBuilder::generateForm('be_page_add');
                $add_form->getElement('page_type')->setValue("page");
                $add_form->getElement('default_radio')->setLabel('Insert');
                $add_form->getElement('default_radio')->setName('page_insert');
                $add_form->getElement('page_before_after')->setLabel(' ');
                self::tpl()->setGlobals(array(
                    'add_page_form'      => $add_form->render(true),
                    'USERNAME_FIELDNAME' => $username_fieldname,
                    'PASSWORD_FIELDNAME' => Validate::createFieldname('password_'),
                    'area'               => self::getArea(),
                ));
                if (!self::asJSON() && self::user()->hasPerm('pages_list')) {
                    self::tpl()->setGlobals('pages'   , \CAT\Backend\Pages::tree());
                    #self::tpl()->setGlobals('pagelist', \CAT\Helper\Page::getPages(1));
                    #self::tpl()->setGlobals('sections', Sections::getSections());
                    self::tpl()->setGlobals('pageTree', true);
                }
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
        }   // end function initForm()

        /**
         * initializes template search paths for backend
         *
         * @access public
         * @return
         **/
        public static function initPaths()
        {
            if (!self::$tplpath || !file_exists(self::$tplpath)) {
                $theme   = Registry::get('default_theme', null, 'backstrap');
                $variant = Registry::get('default_theme_variant');
                if (!$variant || !strlen($variant)) {
                    $variant = 'default';
                }
                $base  = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.$theme.'/'.CAT_TEMPLATES_FOLDER);
                $default_path = $base.'/default';
                // first try: variant subfolder
                if (file_exists($base.'/'.$variant)) {
                    self::$tplpath = $base.'/'.$variant;
                }
                // second: default subfolder
                if (is_dir($default_path)) {
                    self::$tplfallback = $default_path;
                }
                if (empty(self::$tplpath)) {
                    self::$tplpath = self::$tplfallback;
                }
            }
            self::tpl()->setPath(self::$tplpath, 'backend');
            self::tpl()->setFallbackPath(self::$tplfallback, 'backend');
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
         * switch backend scope
         *
         * @access public
         * @return
         **/
        public static function administration()
        {
            self::$scope = 'administration';
            self::show(
                'backend_administration'
            );
        }   // end function administration()

        public static function content()
        {
            self::$scope = 'content';
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // !!!!! TODO: Passenden Default-Bereich ermitteln, nicht jeder Admin darf auf
//             auf jeden Bereich zugreifen
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            self::router()->reroute(CAT_BACKEND_PATH.'/pages');
        }   // end function administration()

        /**
         *
         * @access public
         * @return
         **/
        public static function languages()
        {
            $self  = self::getInstance();
            $langs = self::getLanguages(1);

            if (($parm = self::router()->getRoutePart(-1)) !== false) {
                switch ($parm) {
                    case 'select':
                        $langselect = array(''=>'[Please select]');
                        foreach (array_values($langs) as $l) {
                            $langselect[$l] = $l;
                        }
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
            self::log()->addDebug(sprintf('login() - msg [%s]', $msg));
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
            return self::show('login', $tpl_data, false, false);
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
         * check if TFA is enabled for current user
         *
         * @access public
         * @return
         **/
        public static function tfa()
        {
            $user = new \CAT\Objects\User(Validate::sanitizePost('user'));
            echo Json::printSuccess($user->tfa_enabled());
        }   // end function tfa()
    }   // end class Backend
}
