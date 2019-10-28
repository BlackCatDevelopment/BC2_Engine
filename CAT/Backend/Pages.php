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
use \CAT\Backend as Backend;
use \CAT\Registry as Registry;
use \CAT\Helper\Addons as Addons;
use \CAT\Helper\Directory as Directory;
use \CAT\Helper\Page as HPage;
use \CAT\Helper\FormBuilder as FormBuilder;
use \CAT\Helper\Json as Json;
use \CAT\Helper\Validate as Validate;
use \CAT\Helper\Template as Template;
use \CAT\Helper\Assets as Assets;

if (!class_exists('Pages')) {
    class Pages extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance    = null;
        protected static $javascripts = null;
        protected static $debug       = false;
        /**
         * will be saved 'as is'
         **/
        protected static $basics      = array(
            'page_visibility',
            'page_title',
            'menu_title',
            'description',
            'language',
        );

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance()
        {
            if (!is_object(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public static function add()
        {
            // check permissions
            if (!self::user()->hasPerm('pages_add')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            $pageID   = null;

            $add_form = FormBuilder::generateForm('be_page_add');
            $add_form->setAttribute('action', CAT_ADMIN_URL.'/pages/add');

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Pruefen ob gleichnamige Seite an selber Stelle schon vorhanden
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

            if ($add_form->isSent() && $add_form->isValid()) {
                $data   = $add_form->getData();
                $errors = array();

                // use query builder for easier handling
                $query  = self::db()->qb();
                $query->insert(self::db()->prefix().'pages');
                $query->setValue('site_id', $query->createNamedParameter(CAT_SITE_ID));

                // routing table
                $route_qb = self::db()->qb();
                $route_qb->insert(self::db()->prefix().'pages_routes');

                $i      = 0;
                $parent = 0;

                // expected data
                $title  = isset($data['page_title'])  ? htmlspecialchars($data['page_title']) : '*please add a title*';
                $parent = isset($data['page_parent']) ? intval($data['page_parent']) : 0;
                $lang   = isset($data['page_language']) ? $data['page_language'] : Registry::get('default_language');

                // set menu title = page title for now
                $query->setValue('page_title', $query->createNamedParameter($title));
                $query->setValue('menu_title', $query->createNamedParameter($title));
                $query->setValue('parent', $query->createNamedParameter($parent));
                $query->setValue('language', $query->createNamedParameter($lang));
                $query->setValue('modified_when', $query->createNamedParameter(time()));
                $query->setValue('modified_by', $query->createNamedParameter(self::user()->getID()));

                if ($parent>0) {
                    // get details for parent page
                    $parent_page = HPage::properties($parent);
                    // set level
                    $query->setValue('level', $query->createNamedParameter($parent_page['level']+1));
                    // set link
                    $route_qb->setValue('route', $route_qb->createNamedParameter($parent_page['link'].'/'.$title));
                } else {
                    // set link
                    $route_qb->setValue('route', $route_qb->createNamedParameter('/'.$title));
                }

                // save page
                $sth   = $query->execute();

                if (self::db()->isError()) {
                    $errors[] = self::db()->getError();
                }

                // get the ID of the newly created page
                $pageID = self::db()->lastInsertId();

                if (!$pageID) {
                    self::printFatalError(
                        'Unable to create the page: '.implode("<br />", $errors)
                    );
                } else {
                    $route_qb->setValue('page_id', $route_qb->createNamedParameter($pageID));
                    $route_qb->execute();
                }

                $tpl_data = array(
                    'success' => true,
                    'page_id' => $pageID,
                    'message' => self::lang()->t('The page was created successfully')
                );
            }

            $tpl_data['form'] = $add_form->render(true);

            if (self::asJSON()) {
                echo Json::printData($tpl_data);
                exit;
            }

            Backend::show('backend_page_add', $tpl_data);
        }   // end function add()

        /**
         *
         * @access public
         * @return
         **/
        public static function delete()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            if(empty($pageID)) {
                Base::printFatalError('Invalid data!');
            }

            if (!self::user()->hasPerm('pages_delete')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            if (self::getSetting('trash_enabled')!==true) {
                self::db()->query(
                    'DELETE FROM `:prefix:pages` WHERE `page_id`=?',
                    array($pageID)
                );
            } else {
                self::db()->query(
                    'UPDATE `:prefix:pages` SET `page_visibility`=? WHERE `page_id`=?',
                    array(HPage::getVisibilityID('deleted'), $pageID)
                );
            }

            if (self::asJSON()) {
                echo Json::printSuccess('Page deleted');
                exit;
            }

            return self::router()->reroute(CAT_BACKEND_PATH.'/pages');

        }   // end function delete()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            if(empty($pageID)) {
                Base::printFatalError('Invalid data!');
            }

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID, 'pages_edit')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            // addable addons
            $addable  = Addons::getAddons('page', 'name', false);

            // sections
            $sections = array();

            // blocks (one block may contain several sections)
            $blocks   = array();

            // available template blocks
            $tpl_blocks = Template::getBlocks();

            // default template data
            $tpl_data = array(
                'blocks'       => null,
                'addable'      => $addable,
                'langs'        => self::getLanguages(1), // available languages
                'pages'        => HPage::getPages(1),
                'current'      => 'content',
                'avail_blocks' => Template::getBlocks(),
            );

            // catch errors on wrong pageID
            if ($pageID && is_numeric($pageID) && HPage::exists($pageID)) {
                self::tpl()->setGlobals('page_id', $pageID);
                $tpl_data['page']    = HPage::properties($pageID);
                $tpl_data['linked']  = HPage::getLinkedByLanguage($pageID);
                // get sections; format: $sections[array_of_blocks[array_of_sections]]
                $sections = \CAT\Sections::getSections($pageID, null, false);
            }

            /** sections array:
            Array
            (
                [39] => Array                   pageID
                        [1] => Array            block #
                                [0] => Array    section index
            **/
            if (is_array($sections) && count($sections)>0) {
                // for hybrid modules
                global $page_id;
                $page_id = $pageID;

                foreach ($sections as $block => $items) {
                    foreach ($items as $section) {
                        $section_content = null;
                        // spare some typing
                        $section_id      = intval($section['section_id']);
                        $module          = $section['module'];
                        $directory       = Addons::getDetails($module, 'directory');
                        $module_path     = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$module);
                        $options_file    = null;
                        $options_form    = null;
                        $variants        = null;
                        $variant         = null;
                        $infofiles       = array();

                        if ($section['active']) {
                            Base::addLangFile($module_path.'/languages/');

                            $variants    = Addons::getVariants($directory);
                            $variant     = \CAT\Sections::getVariant($section_id);

                            // check if there's an options.tpl inside the variants folder
                            if (file_exists($module_path.'/templates/'.$variant.'/options.tpl')) {
                                $options_file = $module_path.'/templates/'.$variant.'/options.tpl';
                            }

                            // there may also be a forms.inc.php
                            if (file_exists($module_path.'/templates/'.$variant.'/inc.forms.php')) {
                                $form = \wblib\wbForms\Form::loadFromFile('options', 'inc.forms.php', $module_path.'/templates/'.$variant);
                                if($form != false) {
                                    $form->setAttribute('lang_path', $module_path.'/languages/');
                                    $form->setAttribute('action', CAT_ADMIN_URL.'/section/save/'.$section_id);
                                    if (is_dir($module_path.'/templates/'.$variant.'/languages/')) {
                                        $form->lang()->addPath($module_path.'/templates/'.$variant.'/languages/');
                                    }
                                    $form->getElement('section_id')->setValue($section_id);
                                    $form->getElement('page_id')->setValue($page_id);
                                    if (isset($section['options'])) {
                                        $form->setData($section['options']);
                                    }
                                    $options_form = $form->render(1);
                                }
                            }

                            // if there are variants, collect info.tpl files
                            if (count($variants)) {
                                $files = Directory::findFiles(
                                    $module_path.'/templates/',
                                    array(
                                        'filename'      => 'info',
                                        'max_depth'     => 2,
                                        'recurse'       => true,
                                        'remove_prefix' => true,
                                    )
                                );
                                if (count($files)) {
                                    $map = array();
                                    foreach ($files as $i => $f) {
                                        $map[str_replace('/', '', pathinfo($f, PATHINFO_DIRNAME))] = $i;
                                    }
                                    foreach ($variants as $v) {
                                        if (array_key_exists($v, $map)) {
                                            $infofiles[$v] = $module_path.'/templates/'.$files[$map[$v]];
                                        }
                                        if (is_dir($module_path.'/templates/'.$v.'/languages')) {
                                            Base::addLangFile($module_path.'/templates/'.$v.'/languages');
                                        }
                                    }
                                }
                            }

                            // special case
                            if ($module=='wysiwyg') {
                                \CAT\Addon\WYSIWYG::initialize($section);
                                $section_content = \CAT\Addon\WYSIWYG::modify($section);
                            // Time until wysiwyg is rendered: 0.031202 Seconds
                            } else {
                                // get the module class
                                $handler = null;
                                foreach (array_values(array(str_replace(' ', '', $directory),$module)) as $classname) {
                                    $filename = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$module.'/inc/class.'.$classname.'.php');
                                    if (file_exists($filename)) {
                                        $handler = $filename;
                                    }
                                }

                                // execute the module's modify() function
                                if ($handler) {
                                    self::tpl()->setGlobals(array(
                                        'section_id' => $section_id,
                                        'page_id'    => $pageID,
                                    ));
                                    self::log()->addDebug(sprintf('found class file [%s]', $handler));
                                    include_once $handler;
                                    $classname = '\CAT\Addon\\'.$classname;
                                    $classname::initialize($section);
                                    self::setTemplatePaths($module, $variant);
                                    $section_content = $classname::modify($section);
                                    // make sure to reset the template search paths
                                    Backend::initPaths();
                                }
                            }
                        }

                        $blocks[] = array_merge(
                            $section,
                            array(
                                'section_content'    => $section_content,
                                'available_variants' => $variants,
                                'options_file'       => $options_file,
                                'options_form'       => $options_form,
                                'infofiles'          => $infofiles,
                            )
                        );
                    }
                }

                $tpl_data['blocks'] = $blocks;
            }

            if (self::asJSON()) {
                echo json_encode($tpl_data, 1);
                exit;
            }

            HPage::setTitle(sprintf(
                'BlackCat CMS Backend / %s / %s',
                self::lang()->translate('Page'),
                self::lang()->translate('Edit')
            ));

            Backend::show('backend_page_modify', $tpl_data);
        }   // end function edit()

        /**
         * get header files
         *
         * @access public
         * @return
         **/
        public static function headerfiles()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID, 'pages_edit')) {
                Base::printFatalError('You are not allowed for the requested action!');
            }

            // get current files
            $headerfiles = HPage::getExtraHeaderFiles($pageID);

            // get registered javascripts
            $plugins     = Addons::getAddons('javascript');

            // find javascripts in template directory
            $tpljs       = Directory::findFiles(
                CAT_ENGINE_PATH.'/templates/'.\CAT\Helper\Template::getPageTemplate($pageID),
                array(
                    'extension' => 'js',
                    'recurse' => true
                )
            );

            // find css files in template directory
            $tplcss = Directory::findFiles(
                CAT_ENGINE_PATH.'/templates/'.\CAT\Helper\Template::getPageTemplate($pageID),
                array(
                    'extension' => 'css',
                    'recurse' => true,
                    'remove_prefix' => true,
                )
            );

            // already assigned
            $headerfiles = Assets::getAssets('header', $pageID, false, true);
            $footerfiles = Assets::getAssets('footer', $pageID, false, true);
            $files       = array('js'=>array(),'css'=>array());

            if (isset($headerfiles['js']) && count($headerfiles['js'])) {
                foreach ($headerfiles['js'] as $file) {
                    $files['js'][] = array('file'=>$file,'pos'=>'header');
                }
            }
            if (isset($footerfiles['js']) && count($footerfiles['js'])) {
                foreach ($footerfiles['js'] as $file) {
                    $files['js'][] = array('file'=>$file,'pos'=>'footer');
                }
            }

            if (self::asJSON()) {
                Backend::initPaths();
                Json::printData(array(
                    'success' => true,
                    'files'   => $files,
                    'content' => self::tpl()->get('backend_page_headerfiles', array(
                        'files'   => $files,
                        'tplcss'  => $tplcss,
                    ))
                ));
            } else {
                Backend::show('backend_page_headerfiles', array(
                    'files'  => $files,
                    'tplcss' => $tplcss,
                    'page'    => HPage::properties($pageID),
                    'current' => 'headerfiles',
                ));
            }
        }   // end function headerfiles()

        /**
         *
         * @access public
         * @return
         **/
        public static function header()
        {
            $pageID  = Validate::sanitizePost('page_id');

            if (($plugin = Validate::sanitizePost('jquery_plugin')) !== false) {
                $success = true;
                // find JS files
                $js  = self::getJQueryFiles('js', $plugin);
                // find CSS files
                $css = self::getJQueryFiles('css', $plugin);
                foreach ($js as $file) {
                    if (($result=self::addHeaderComponent('js', $plugin.'/'.$file, $pageID)) !== true) {
                        echo Json::printError($result);
                        exit;
                    }
                }
                foreach ($css as $file) {
                    if (($result=self::addHeaderComponent('css', $plugin.'/'.$file, $pageID)) !== true) {
                        Json::printError($result);
                    }
                }
                $ajax    = array(
                    'message'    => $success ? 'ok' : 'error',
                    'success'    => $success
                );
                print json_encode($ajax);
                exit();
            }
        }   // end function header()

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

            \CAT\Backend::show('backend_pages',array('pages'=>$pages_list));

        }   // end function index()

        /**
         *
         *
         *
         *
         **/
        public static function list($as_array=false,$flattened=false)
        {
            if (!self::user()->hasPerm('pages_list')) {
                Json::printError('You are not allowed for the requested action!');
            }

            $pages = HPage::getPages(true);
            $flattened = self::router()->getQueryParam('flattened');

            if($flattened) {
                $options = array('id'=>'page_id','value'=>'menu_title','linkKey'=>'href','sort'=>true);
                $tree = new \wblib\wbList\Tree($pages,$options);
                $temp = $tree->flattened();
                foreach($temp as $i => $item) {
                    $temp[$i]['visibility'] = \CAT\Helper\Page::properties($item['id'],'visibility');
                }
                $pages = $temp;
            }

            if (!$as_array && self::asJSON()) {
                echo header('Content-Type: application/json');
                echo json_encode($pages, true);
                return;
            }

            return $pages;
        }   // end function list()

        /**
         * recover a page marked as "deleted"
         *
         * @access public
         * @return
         **/
        public static function recover()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');

            if (!self::user()->hasPerm('pages_recover')) {
                Json::printError('You are not allowed for the requested action!');
            }

            self::db()->query(
                'UPDATE `:prefix:pages` SET `page_visibility`=? WHERE `page_id`=?',
                array(HPage::getVisibilityID('hidden'), $pageID)
            );

            if (self::asJSON()) {
                echo Json::printSuccess('Page recovered');
                exit;
            }

            HPage::reload();
            return self::router()->reroute(CAT_BACKEND_PATH.'/pages');
        }   // end function recover()

        /**
         *
         * @access public
         * @return
         **/
        public static function relations()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');

            // the user needs to have the global pages_settings permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_settings') || !self::user()->hasPagePerm($pageID, 'pages_settings')) {
                Base::printFatalError('You are not allowed for the requested action!');
            }

            if (self::asJSON()) {
                Json::printData(array(
                    'success' => true,
                    
                ));
            } else {
                Backend::show('backend_page_modify_relations', array(
                    'page'    => HPage::properties($pageID),
                    'pages'   => HPage::getPages(1),
                    'linked'  => HPage::getLinkedByLanguage($pageID),
                    'langs'   => self::getLanguages(1), // available languages
                    'current' => 'relations',
                ));
            }
        }   // end function relations()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function reorder()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');

            // the user needs to have the global pages_settings permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_settings') || !self::user()->hasPagePerm($pageID, 'pages_settings')) {
                Base::printFatalError('You are not allowed for the requested action!');
            }

            $parent = Validate::get('parent');
            $pos    = Validate::get('position');
            $page   = HPage::properties($pageID);

            // new parent
            if ($parent != $page['parent']) {
                echo "NEW PARENT! pos = $pos\n";
                if (!empty($parent)) {
                    $parent_properties = HPage::properties($parent);
                } else {
                    // empty parent = root level
                    $parent_properties = array(
                        'level' => 0,
                        'route' => '',
                        'ordering' => (($pos*10)+5)
                    );
                    $parent = 0;
                }
                self::db()->query(
                      'UPDATE `:prefix:pages` '
                    . 'SET `parent`=?, `level`=?, `route`=?, `ordering`=? '
                    . 'WHERE `page_id`=?',
                array(
                        $parent,
                        $parent_properties['level']+1,
                        $parent_properties['route'].'/'.$page['menu_title'],
                        $parent_properties['ordering']+5,
                        $pageID
                )
            );
    }

            // reorder
            self::db()->query(
                  'SET @new_ordering = 0; '
                . 'SET @ordering_inc = 10; '
                . 'UPDATE `:prefix:pages` '
                . 'SET `ordering` = (@new_ordering := @new_ordering + @ordering_inc) '
                . 'WHERE `parent`=? '
                . 'ORDER BY `ordering` ASC',
                array($parent)
            );
            print_r(self::db()->getLastStatement());

            #            if(true===self::db()->reorder('pages',(int)$pageID,(int)$pos,'ordering','page_id'))
#            {
#                if(self::asJSON())
#                {
#                    echo Json::printSuccess('Success');
#                } else {
#                    echo Json::printError('Failed');
#                }
#            }
        }   // end function reorder()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function save()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            if(empty($pageID)) {
                Base::printFatalError('Invalid data!');
            }

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID, 'pages_edit')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            // page relation by language
            if (
                   ($lang=Validate::get('_REQUEST', 'relation_lang'))!==''
                && ($linkto=Validate::get('_REQUEST', 'linked_page'))!==''
                && HPage::exists($pageID) && HPage::exists($linkto)
            ) {
                // already linked?
                if (!HPage::isLinkedTo($pageID, $linkto, $lang)) {
                    self::db()->query(
                        'INSERT INTO `:prefix:pages_langs` (`page_id`,`lang`,`link_page_id`) '
                        .'VALUES(?,?,?)',
                        array($pageID,$lang,$linkto)
                    );
                } else {
                    echo Json::printResult(false, self::lang()->t('The pages are already linked together'));
                }
            }

            if (self::asJSON()) {
                echo Json::printResult(
                    (self::db()->isError() ? false : true),
                    'Success'
                );
                return;
            }

            self::edit();
        }   // end function save()

        /**
         *
         * @access public
         * @return
         **/
        public static function settings()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            if(empty($pageID)) {
                Base::printFatalError('Invalid data!');
            }

            // the user needs to have the global pages_settings permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_settings') || !self::user()->hasPagePerm($pageID, 'pages_settings')) {
                Base::printFatalError('You are not allowed for the requested action!');
            }

            $page      = HPage::properties($pageID);

            // default template data
            $tpl_data = array(
                'current' => 'settings',
            );

            $form      = FormBuilder::generateForm('be_page_settings', $page);
            $form->setAttribute('action', CAT_ADMIN_URL.'/pages/settings/'.$pageID);

            // fill template select
            $templates = array(''=>self::lang()->translate('System default'));
            if (is_array(($tpls=Addons::getAddons('template')))) {
                foreach (array_values($tpls) as $dir => $name) {
                    $templates[$dir] = $name;
                }
            }
            $form->getElement('page_template')->setValue($templates);

            // set current value for template select
            $curr_tpl   = \CAT\Helper\Template::getPageTemplate($pageID);
            $form->getElement('page_template')->setValue($curr_tpl);

            // remove variant select if no variants are available
            $variants   = Template::getVariants($curr_tpl);
            if (!$variants) {
                $form->removeElement('template_variant');
            } else {
                $form->getElement('template_variant')->setData($variants);
            }

            // variant
            $variant    = \CAT\Helper\Template::getVariant($pageID);
            $form->getElement('template_variant')->setValue($variant);

            // remove menu select if there's only one menu block
            $menus      = Template::get_template_menus($curr_tpl);
            if (!$menus) {
                $form->removeElement('page_menu');
            } else {
                $form->getElement('page_menu')->setData($menus);
                $form->getElement('page_menu')->setValue($page['menu']);
            }

            $form2 = \CAT\Helper\Template::getOptionsForm($pageID);
            if (is_object($form2)) {
                $form->addElement(new \wblib\wbForms\Element\Fieldset(
                    'template_options',
                    array(
                        'label' => self::lang()->translate('Template options')
                    )
                ));
                foreach ($form2->getElements() as $e) {
                    //$form->addElement($e);
                    $form->addElement($e->copyAs('template_option_'.$e->getName()));
                }
            }

            // form already sent?
            if ($form->isSent()) {
                // check data
                if ($form->isValid()) {

                    // save data
                    $data = $form->getData();
                    if (is_array($data) && count($data)) {

                        // extract optional template settings
                        $tpl_opt = array();
                        foreach(array_keys($data) as $key) {
                            if(substr_compare($key, 'template_option_', 0, 16)==0) {
                                $tpl_opt[$key] = $data[$key];
                                unset($data[$key]);
                            }
                        }

                        // get old data
                        $old_parent       = intval($page['parent']);
                        $old_position     = intval($page['ordering']);
                        $old_link         = $page['link'];

                        // new parent?
                        if (isset($data['page_parent']) && $old_parent!=intval($data['page_parent'])) {
                            // new position (add to end)
                            $page['ordering'] = self::db()->getNext(
                                'pages',
                                intval($data['page_parent'])
                            );
                            $page['parent'] = intval($data['page_parent']);
                        }
                        // Work out level and root parent
                        if (intval($data['page_parent'])!='0') {
                            $page['level'] = HPage::properties(intval($data['page_parent']), 'level') + 1;
                            $page['root_parent']
                                = ($page['level'] == 1)
                                ? $page['parent']
                                : HPage::getRootParent($page['parent'])
                                ;
                        }

                        $changes = 0;

                        // use query builder for easier handling
                        $query   = self::db()->qb();
                        $query->update(self::db()->prefix().'pages')
                              ->where($query->expr()->eq('page_id', $pageID));

                        // basics
                        foreach(array_values(self::$basics) as $key) {
                            $fieldname = $key;
                            // alias
                            if(isset($data['page_'.$key])) {
                                $key = 'page_'.$key;
                            }
                            if(isset($data[$key]) && $data[$key] != $page[$fieldname]) {
                                $query->set($fieldname, $query->expr()->literal($data[$key]));
                                $changes++;
                            }
                            
                        }

                        // template options
                        if(isset($tpl_opt) && is_array($tpl_opt) && count($tpl_opt)>0) {
                            \CAT\Helper\Template::saveOptions($pageID, $tpl_opt);
                        }

                        if($changes>0) {
                            $query->set('modified_by',self::user()->getID());
                            $query->set('modified_when',time());
                            $sth   = $query->execute();
                            if (self::db()->isError()) {
                                self::printFatalError(self::db()->getError());
                            }
                        }
                    }
                }
            }

            HPage::reload();

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Die aktuellen Einstellungen als JSON zurueckliefern, nicht nur als
// fertiges HTML-Formular
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if (self::asJSON()) {
                Json::printSuccess($form->render(true));
            } else {
                Backend::show('backend_page_settings', array(
                    'form' => $form->render(true),
                    'page' => HPage::properties($pageID),
                    'current' => 'settings',
                ));
            }
        }   // end function settings()

        /**
         *
         * @access public
         * @return
         **/
        public static function sections()
        {
            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            if (self::asJSON()) {
                Json::printSuccess($form->getForm());
            } else {
                Backend::show('backend_page_sections', array(
                    'page'     => HPage::properties($pageID),
                    'sections' => \CAT\Sections::getSections($pageID, null, false),
                    'blocks'   => Template::getBlocks(),
                    'addable'  => Addons::getAddons('page', 'name', false),
                ));
            }
        }   // end function sections()

        /**
         *
         * @access public
         * @return
         **/
        public static function tree()
        {
            if (!self::user()->hasPerm('pages_list')) {
                Json::printError('You are not allowed for the requested action!');
            }

            $pages = HPage::getPages(true);
            $pages = self::lb()->buildRecursion($pages);

            if (self::asJSON()) {
                echo header('Content-Type: application/json');
                echo json_encode($pages, true);
                return;
            }

            return $pages;
        }   // end function tree()

        /**
         * remove a page relation
         *
         * note: if the relation does not exist, there will be no error!
         *
         * @access public
         * @return
         **/
        public static function unlink()
        {
            $pageID   = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            $unlinkID = Validate::sanitizePost('unlink');

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if (!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID, 'pages_edit')) {
                Base::printFatalError('You are not allowed for the requested action!');
            }

            // check data
            if (!HPage::exists($pageID) || !HPage::exists($unlinkID)) {
                Base::printFatalError('Invalid data!');
            }

            self::db()->query(
                'DELETE FROM `:prefix:pages_langs` WHERE `page_id`=? AND `link_page_id`=?',
                array($pageID,$unlinkID)
            );

            if (self::asJSON()) {
                echo Base::json_result(
                    (self::db()->isError() ? false : true),
                    ''
                );
                return;
            }

            self::edit();
        }   // end function unlink()

        /**
         *
         * @access public
         * @return
         **/
        public static function visibility()
        {
            if (!self::user()->hasPerm('pages_edit')) {
                Json::printError('You are not allowed for the requested action!');
            }

            $pageID = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            $newval = self::router()->getParam();

            if (!is_numeric($pageID) || $pageID==0) {
                // if "Editable" jQuery Plugin is used
                $pageID = \CAT\Helper\Validate::sanitizePost('pk');
            }
            if(empty($newval)) {
                // if "Editable" jQuery Plugin is used
                $newval = \CAT\Helper\Validate::sanitizePost('value');
            }

            if (!is_numeric($pageID) || empty($newval)) {
                Json::printError('Invalid value');
            }

            // map $newval to id
            $visid = HPage::getVisibilityID($newval);
            self::db()->query(
                'UPDATE `:prefix:pages` SET `page_visibility`=? WHERE `page_id`=?',
                array($visid,$pageID)
            );
            echo Base::json_result(
                self::db()->isError(),
                '',
                true
            );
        }   // end function visibility()
        
        /**
         * add header file to the database; returns an array with keys
         *     'success' (boolean)
         *         and
         *     'message' (some error text or 'ok')
         *
         * @access public
         * @param  string  $type
         * @param  string  $file
         * @param  integer $page_id
         * @return array
         **/
        protected static function addHeaderComponent($type, $file, $page_id=null)
        {
            $headerfiles = HPage::getExtraHeaderFiles($page_id);

            if (!is_array($headerfiles) || !count($headerfiles)) {
                $headerfiles = array(array());
            }

            foreach (array_values($headerfiles) as $data) {
                if (isset($data[$type]) && is_array($data[$type]) && count($data[$type]) && in_array($file, $data[$type])) {
                    return Base::lang()->translate('The file is already listed');
                } else {
                    $paths = array(
                        self::$javascripts
                    );

                    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                    // TODO: Dateien des WYSIWYG-Editors, evtl. des Templates?
                    // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

                    $db = self::db(); // spare some typing...

                    foreach ($paths as $path) {
                        $filename = Directory::sanitizePath($path.'/'.$file);
                        if (file_exists($filename)) {
                            $new    = (isset($data[$type]) && is_array($data[$type]) && count($data[$type]))
                                    ? $data[$type]
                                    : array();
                            array_push($new, Validate::path2uri($filename));
                            $new = array_unique($new);
                            $params = array(
                                'field'   => 'page_'.$type.'_files',
                                'value'   => serialize($new),
                                'page_id' => $page_id,
                            );

                            if (count($data)) {
                                $q = 'UPDATE `:prefix:pages_headers` SET :field:=:value WHERE `page_id`=:page_id';
                            } else {
                                $q = 'INSERT INTO `:prefix:pages_headers` ( `page_id`, :field: ) VALUES ( :page_id, :value )';
                            }
                            $db->query($q, $params);
                            if ($db->isError()) {
                                return $db->getError();
                            }
                        }
                    }
                }
            }
            return true;
        }   // end function addHeaderComponent()

        /**
         * remove header file from the database
         **/
        protected static function delHeaderComponent($type, $file, $page_id=null)
        {
            $headerfiles = HPage::getExtraHeaderFiles($page_id);

            echo "remove file $file\n";
            if (is_array($headerfiles) && count($headerfiles)) {
                foreach (array_values($headerfiles) as $item) {
                    print_r($item[$type]);
                    if (!(is_array($item[$type]) && count($item[$type]) && in_array($file, $item[$type]))) {
                        return true;
                    } // silently fail
                }
            }

            /*
                        if(($key = array_search($file, $data[$type])) !== false) {
                            unset($data[$type][$key]);
                        }
                        $q = count($data)
                           ? sprintf(
                                 'UPDATE `:prefix:pages_headers` SET `page_%s_files`=\'%s\' WHERE `page_id`="%d"',
                                 $type, serialize($data[$type]), $page_id
                             )
                           : sprintf(
                                 'REPLACE INTO `:prefix:pages_headers` ( `page_id`, `page_%s_files` ) VALUES ( "%d", \'%s\' )',
                                 $type, $page_id, serialize($data[$type])
                             )
                           ;
                        self::getInstance(1)->db()->query($q);
                        return array(
                            'success' => ( self::getInstance(1)->isError() ? false                            : true ),
                            'message' => ( self::getInstance(1)->isError() ? self::getInstance(1)->getError() : 'ok' )
                        );
            */
        }   // end function delHeaderComponent()
    } // class Page
} // if class_exists()
