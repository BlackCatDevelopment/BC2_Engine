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

if (!class_exists('Page'))
{
    class Page extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;
        protected static $instance    = NULL;
        protected static $javascripts = NULL;
        protected static $debug       = false;

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance()
        {
            if(!is_object(self::$instance))
                self::$instance = new self();
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
            if(!self::user()->hasPerm('pages_add'))
                self::printFatalError('You are not allowed for the requested action!');

            $pageID   = NULL;

            $add_form = FormBuilder::generateForm('be_page_add');
            $add_form->setAttribute('action',CAT_ADMIN_URL.'/page/add');

            if($add_form->isSent() && $add_form->isValid())
            {
                $data   = $add_form->getData();
                $errors = array();

                // use query builder for easier handling
                $query  = self::db()->qb();
                $query->insert(self::db()->prefix().'pages');

                $query->setValue('site_id',$query->createNamedParameter(CAT_SITE_ID));

                $i      = 0;
                $parent = 0;

                // expected data
                $title  = isset($data['page_title'])  ? htmlspecialchars($data['page_title']) : '*please add a title*';
                $parent = isset($data['page_parent']) ? intval($data['page_parent']) : 0;
                $lang   = isset($data['page_language']) ? $data['page_language'] : Registry::get('default_language');

                // set menu title = page title for now
                $query->setValue('page_title',$query->createNamedParameter($title));
                $query->setValue('menu_title',$query->createNamedParameter($title));
                $query->setValue('parent',$query->createNamedParameter($parent));
                $query->setValue('language',$query->createNamedParameter($lang));
                $query->setValue('modified_when',$query->createNamedParameter(time()));
                $query->setValue('modified_by',$query->createNamedParameter(self::user()->getID()));

                if($parent>0)
                {
                    // get details for parent page
                    $parent_page = HPage::properties($parent);

                    // set level
                    $query->setValue('level',$query->createNamedParameter($parent_page['level']+1));

                    // set link
                    $query->setValue('route',$query->createNamedParameter($parent_page['link'].'/'.$title));
                }
                else
                {
                    // set link
                    $query->setValue('route',$query->createNamedParameter('/'.$title));
                }

                // save page
                $sth   = $query->execute();

                if(self::db()->isError()) {
                    $errors[] = self::db()->getError();
                }

                // get the ID of the newly created page
                $pageID = self::db()->lastInsertId();

                if(!$pageID) {
                    self::printFatalError(
                        'Unable to create the page: '.implode("<br />",$errors)
                    );
                } 

                $tpl_data = array(
                    'success' => true,
                    'page_id' => $pageID,
                    'message' => self::lang()->t('The page was created successfully')
                );
            }

            $tpl_data['form'] = $add_form->render(true);

            if(self::asJSON())
            {
                echo Json::printData($tpl_data);
                exit;
            }

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            Backend::printHeader();
            self::tpl()->output('backend_page_add', $tpl_data);
            Backend::printFooter();
        }   // end function add()

        /**
         * returns the pages list, sorted by parent -> children -> position
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            // note: the access rights for index() are checked by the Backend
            // class, so there's no need to do it here
            return self::router()->reroute('/pages/index');
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            $pageID  = self::getPageID();

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID,'pages_edit'))
                self::printFatalError('You are not allowed for the requested action!');

            // addable addons
            $addable  = Addons::getAddons('page','name',false);

            // sections
            $sections = array();

            // blocks (one block may contain several sections)
            $blocks   = array();

            // available template blocks
            $tpl_blocks = Template::getBlocks();

            // default template data
            $tpl_data = array(
                'blocks'  => NULL,
                'addable' => $addable,
                'langs'   => self::getLanguages(1), // available languages
                'pages'   => HPage::getPages(1),
                'avail_blocks' => Template::getBlocks(),
            );

            // catch errors on wrong pageID
            if($pageID && is_numeric($pageID) && HPage::exists($pageID))
            {
                self::tpl()->setGlobals('page_id',$pageID);
                $tpl_data['page']    = HPage::properties($pageID);
                $tpl_data['linked']  = HPage::getLinkedByLanguage($pageID);
                // get sections; format: $sections[array_of_blocks[array_of_sections]]
                $sections = \CAT\Sections::getSections($pageID,NULL,false);
            }

            /** sections array:
            Array
            (
                [39] => Array                   pageID
                        [1] => Array            block #
                                [0] => Array    section index
            **/
            if(is_array($sections) && count($sections)>0)
            {
                // for hybrid modules
                global $page_id;
                $page_id = $pageID;

                foreach($sections as $block => $items)
                {
                    foreach($items as $section)
                    {
                        $section_content = null;
                        // spare some typing
                        $section_id      = intval($section['section_id']);
                        $module          = $section['module'];
                        $directory       = Addons::getDetails($module,'directory');
                        $module_path     = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$module);
                        $options_file    = null;
                        $options_form    = null;
                        $variants        = null;
                        $variant         = null;
                        $infofiles       = array();

                        if($section['active'])
                        {
                            Base::addLangFile($module_path.'/languages/');

                            $variants    = Addons::getVariants($directory);
                            $variant     = \CAT\Sections::getVariant($section_id);

                            // check if there's an options.tpl inside the variants folder
                            if(file_exists($module_path.'/templates/'.$variant.'/options.tpl'))
                                $options_file = $module_path.'/templates/'.$variant.'/options.tpl';

                            // there may also be a forms.inc.php
                            if(file_exists($module_path.'/templates/'.$variant.'/inc.forms.php'))
                            {
                                $form = \wblib\wbForms\Form::loadFromFile('options','inc.forms.php',$module_path.'/templates/'.$variant);
                                $form->setAttribute('lang_path',$module_path.'/languages/');
                                $form->setAttribute('action',CAT_ADMIN_URL.'/section/save/'.$section_id);
                                if(is_dir($module_path.'/templates/'.$variant.'/languages/'))
                                {
                                    $form->lang()->addPath($module_path.'/templates/'.$variant.'/languages/');
                                }
                                $form->getElement('section_id')->setValue($section_id);
                                if(isset($section['options']))
                                    $form->setData($section['options']);
                                $options_form = $form->render(1);
                            }

                            // if there are variants, collect info.tpl files
                            if(count($variants))
                            {
                                $files = Directory::findFiles(
                                    $module_path.'/templates/',
                                    array(
                                        'filename'      => 'info',
                                        'max_depth'     => 2,
                                        'recurse'       => true,
                                        'remove_prefix' => true,
                                    )
                                );
                                if(count($files))
                                {
                                    $map = array();
                                    foreach($files as $i => $f)
                                        $map[str_replace('/','',pathinfo($f,PATHINFO_DIRNAME))] = $i;
                                    foreach($variants as $v) {
                                        if(array_key_exists($v, $map)) {
                                            $infofiles[$v] = $module_path.'/templates/'.$files[$map[$v]];
                                        }
                                        if(is_dir($module_path.'/templates/'.$v.'/languages')) {
                                            Base::addLangFile($module_path.'/templates/'.$v.'/languages');
                                        }
                                    }
                                }
                            }

                            // special case
                            if($module=='wysiwyg')
                            {
                                \CAT\Addon\WYSIWYG::initialize($section);
                                $section_content = \CAT\Addon\WYSIWYG::modify($section);
                                // Time until wysiwyg is rendered: 0.031202 Seconds
                            }
                            else
                            {
                                // get the module class
                                $handler = NULL;
                                foreach(array_values(array(str_replace(' ','',$directory),$module)) as $classname) {
                                    $filename = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$module.'/inc/class.'.$classname.'.php');
                                    if(file_exists($filename)) {
                                         $handler = $filename;
                                    }
                                }
                                // execute the module's modify() function
                                if ($handler)
                                {
                                    self::log()->addDebug(sprintf('found class file [%s]',$handler));
                                    include_once $handler;
                                    $classname::initialize($section_id);
                                    self::setTemplatePaths($module,$variant);
                                    $section_content = $classname::modify($section_id);
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

            if(self::asJSON())
            {
                echo json_encode($tpl_data,1);
                exit;
            }

            HPage::setTitle(sprintf(
                'BlackCat CMS Backend / %s / %s',
                self::lang()->translate('Page'),
                self::lang()->translate('Edit')
            ));

            Backend::printHeader();
            self::tpl()->output('backend_page_modify', $tpl_data);
            Backend::printFooter();
        }   // end function edit()

        /**
         * tries to retrieve 'page_id' by checking (in this order):
         *
         *    - $_POST['page_id']
         *    - $_GET['page_id']
         *    - Route param['page_id']
         *
         * also checks for numeric value
         *
         * access is 'public' as it is used by the Assets helper
         *
         * @access public
         * @return integer
         **/
        public static function getPageID()
        {
            $pageID  = Validate::sanitizePost('page_id','numeric');

            if(!$pageID)
                $pageID  = Validate::sanitizeGet('page_id','numeric');

            if(!$pageID)
                $pageID = self::router()->getParam(-1);

            if(!$pageID)
                $pageID = self::router()->getRoutePart(-1);

            if(!$pageID || !is_numeric($pageID) || !HPage::exists($pageID))
                $pageID = NULL;

            return intval($pageID);
        }   // end function getPageID()

        /**
         * get header files
         *
         * @access public
         * @return
         **/
        public static function headerfiles()
        {
            $pageID  = self::getPageID();

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID,'pages_edit'))
                Base::printFatalError('You are not allowed for the requested action!');

            // get current files
            $headerfiles = HPage::getExtraHeaderFiles($pageID);

            // get registered javascripts
            $plugins     = Addons::getAddons('javascript');

            // find javascripts in template directory
            $tpljs       = Directory::findFiles(
                CAT_ENGINE_PATH.'/templates/'.HPage::getPageTemplate($pageID),
                array(
                    'extension' => 'js',
                    'recurse' => true
                )
            );

/*
Array
(
    [css] => Array
        (
            [screen,projection] => Array
                (
                    [0] => /modules/lib_bootstrap/vendor/css/font-awesome.min.css
                    [1] => /modules/lib_bootstrap/vendor/v4/css/cerulean/bootstrap.min.css
                    [2] => /modules/lib_javascript/plugins/tippy/1.4.1/tippy.css
                    [3] => /modules/lib_javascript/jquery-ui/themes/base/jquery-ui.css
                    [4] => /templates/backstrap/js/datetimepicker/jquery.datetimepicker.min.css
                    [5] => /modules/lib_javascript/plugins/jquery.datatables/css/dataTables.bootstrap.min.css
                    [6] => /templates/backstrap/css/default/theme.css
                )

        )

    [js] => Array
        (
            [0] => /modules/lib_javascript/jquery-core/jquery-core.min.js
            [1] => /modules/lib_javascript/jquery-ui/ui/i18n/jquery-ui-i18n.min.js
            [2] => /modules/lib_javascript/jquery-ui/ui/jquery-ui.min.js
            [3] => P:/BlackCat2/cat_engine/modules/lib_javascript/plugins/jquery.cattranslate/jquery.cattranslate.js
            [4] => P:/BlackCat2/cat_engine/modules/lib_javascript/plugins/jquery.mark/jquery.mark.min.js
            [5] => /modules/lib_javascript/plugins/jquery.columns/jquery.columns.js
            [6] => /modules/lib_javascript/plugins/jquery.datatables/js/jquery.dataTables.min.js
            [7] => /modules/lib_javascript/plugins/jquery.datatables/js/dataTables.mark.min.js
            [8] => /modules/lib_javascript/plugins/jquery.datatables/js/dataTables.bootstrap.min.js
            [9] => /modules/lib_javascript/plugins/jquery.fieldset_to_tabs/jquery.fieldset_to_tabs.js
            [10] => /CAT/Backend/js/session.js
            [11] => /templates/backstrap/js/datetimepicker/jquery.datetimepicker.full.js
            [12] => CONDITIONAL lt IE 9 START
            [13] => https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js
            [14] => https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js
            [15] => CONDITIONAL lt IE 9 END
            [16] => /templates/backstrap/js/backend.js
        )

)

*/
            // find css files in template directory
            $tplcss = Directory::findFiles(
                CAT_ENGINE_PATH.'/templates/'.HPage::getPageTemplate($pageID),
                array(
                    'extension' => 'css',
                    'recurse' => true,
                    'remove_prefix' => true,
                )
            );

            // already assigned
            $headerfiles = Assets::getAssets('header',$pageID,false,true);
            $footerfiles = Assets::getAssets('footer',$pageID,false,true);
            $files       = array('js'=>array(),'css'=>array());

            if(isset($headerfiles['js']) && count($headerfiles['js'])) {
                foreach($headerfiles['js'] as $file) {
                    $files['js'][] = array('file'=>$file,'pos'=>'header');
                }
            }
            if(isset($footerfiles['js']) && count($footerfiles['js'])) {
                foreach($footerfiles['js'] as $file) {
                    $files['js'][] = array('file'=>$file,'pos'=>'footer');
                }
            }

            if(self::asJSON())
            {
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
                Backend::printHeader();
                self::tpl()->output('backend_page_headerfiles', array(
                    'files'  => $files,
                    'tplcss' => $tplcss,
                ));
                Backend::printFooter();
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

            if(($plugin = Validate::sanitizePost('jquery_plugin')) !== false)
            {
                $success = true;
                // find JS files
                $js  = self::getJQueryFiles('js',$plugin);
                // find CSS files
                $css = self::getJQueryFiles('css',$plugin);
                foreach($js as $file)
                {
                    if(($result=self::addHeaderComponent('js',$plugin.'/'.$file,$pageID)) !== true)
                    {
                        echo Json::printError($result);
                        exit;
                    }
                }
                foreach($css as $file)
                {
                    if(($result=self::addHeaderComponent('css',$plugin.'/'.$file,$pageID)) !== true)
                    {
                        Json::printError($result);
                    }
                }
                $ajax    = array(
                    'message'    => $success ? 'ok' : 'error',
                    'success'    => $success
                );
                print json_encode( $ajax );
                exit();
            }
        }   // end function header()

        /**
         *
         *
         *
         *
         **/
        public static function list($as_array=false)
        {
            if(!self::user()->hasPerm('pages_list'))
                Json::printError('You are not allowed for the requested action!');

            $pages = HPage::getPages(true);

/*
            $lang  = self::router()->getRoutePart(-1);
            if($lang && !in_array($lang,array('page','index','list')))
            {
                $addon = Addons::getDetails($lang);
                if(!$addon || !is_array($addon) || !isset($addon['type']) || !$addon['type'] == 'language')
                {
                    self::printFatalError('Invalid data! (Page::list())');
                }
                $pages = HPage::getPagesForLanguage($lang);
            }
*/

            if(!$as_array && self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode($pages,true);
                return;
            }

            return $pages;
        }   // end function list()

        /**
         *
         * @access public
         * @return
         **/
        public static function reorder()
        {
            $pageID  = self::getPageID();

            // the user needs to have the global pages_settings permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_settings') || !self::user()->hasPagePerm($pageID,'pages_settings'))
                Base::printFatalError('You are not allowed for the requested action!');

            $parent = Validate::get('_REQUEST','parent');
            $pos    = Validate::get('_REQUEST','position');
            $page   = HPage::properties($pageID);

            // new parent
            if($parent != $page['parent'])
            {
                self::db()->query(
                    'UPDATE `:prefix:pages` SET `parent`=?, `position`=0 WHERE `page_id`=?',
                    array($parent,$pageID)
                );
            }

            if(true===self::db()->reorder('pages',(int)$pageID,(int)$pos,'position','page_id'))
            {
                if(self::asJSON())
                {
                    echo Json::printSuccess('Success');
                } else {
                    echo Json::printError('Failed');
                }
            }
        }   // end function reorder()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function save()
        {
            $pageID  = self::getPageID();

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID,'pages_edit'))
                self::printFatalError('You are not allowed for the requested action!');

            // page relation by language
            if(
                   ($lang=Validate::get('_REQUEST','relation_lang'))!==''
                && ($linkto=Validate::get('_REQUEST','linked_page'))!==''
                && HPage::exists($pageID) && HPage::exists($linkto)
            ) {
                // already linked?
                if(!HPage::isLinkedTo($pageID,$linkto,$lang))
                {
                    self::db()->query(
                        'INSERT INTO `:prefix:pages_langs` (`page_id`,`lang`,`link_page_id`) '
                        .'VALUES(?,?,?)',
                        array($pageID,$lang,$linkto)
                    );
                } else {
                    echo Json::printResult(false,self::lang()->t('The pages are already linked together'));
                }
            }

            if(self::asJSON())
            {
                echo Json::printResult(
                    ( self::db()->isError() ? false : true ),
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
            $pageID  = self::getPageID();

            // the user needs to have the global pages_settings permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_settings') || !self::user()->hasPagePerm($pageID,'pages_settings'))
                Base::printFatalError('You are not allowed for the requested action!');

            $page       = HPage::properties($pageID);
            $form       = FormBuilder::generateForm('be_page_settings',$page);
            $form->setAttribute('action',CAT_ADMIN_URL.'/page/settings/'.$pageID);

            // fill template select
            $templates = array(''=>self::lang()->translate('System default'));
            if(is_array(($tpls=Addons::getAddons('template'))))
                foreach(array_values($tpls) as $dir => $name)
                    $templates[$dir] = $name;
            $form->getElement('page_template')->setValue($templates);

            // set current value for template select
            $curr_tpl   = HPage::getPageTemplate($pageID);
            $form->getElement('page_template')->setValue($curr_tpl);

            // remove variant select if no variants are available
            $variants   = Template::getVariants($curr_tpl);
            if(!$variants) $form->removeElement('template_variant');
            else           $form->getElement('template_variant')->setData($variants);

            // remove menu select if there's only one menu block
            $menus      = Template::get_template_menus($curr_tpl);
            if(!$menus) $form->removeElement('page_menu');
            else {
                $form->getElement('page_menu')->setData($menus);
                $form->getElement('page_menu')->setValue($page['menu']);
            }

            // form already sent?
            if($form->isSent())
            {
                // check data
                if($form->isValid())
                {
                    // save data
                    $data = $form->getData();
/*
---form data---
Array
(
    [page_id] => 30
    [page_parent] => 30
    [page_visibility] => 1
    [page_menu] => 1
    [page_template] =>
    [template_variant] =>
    [page_title] => Homepage
    [menu_title] => Homepage
    [page_description] => asdfasdf
    [page_language] => DE
)

CREATE TABLE `cat_pages` (
	`page_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`vis_id` INT(2) UNSIGNED NOT NULL DEFAULT '7',
	`parent` INT(11) UNSIGNED NOT NULL DEFAULT '0',
	`root_parent` INT(11) UNSIGNED NOT NULL DEFAULT '0',
	`level` INT(11) UNSIGNED NOT NULL DEFAULT '0',
	`link` TEXT NOT NULL,
	`page_title` VARCHAR(255) NOT NULL DEFAULT '',
	`menu_title` VARCHAR(255) NOT NULL DEFAULT '',
	`description` TEXT NOT NULL,
	`page_trail` TEXT NOT NULL,
	`template` VARCHAR(255) NOT NULL DEFAULT '',
	`position` INT(11) NOT NULL DEFAULT '1',
	`menu` INT(11) NOT NULL DEFAULT '1',
	`language` VARCHAR(5) NOT NULL DEFAULT '',
	`searching` INT(11) NOT NULL DEFAULT '1',
	`created_by` INT(11) UNSIGNED NOT NULL DEFAULT '1',
	`modified_by` INT(11) UNSIGNED NOT NULL DEFAULT '0',
	`modified_when` INT(11) NOT NULL DEFAULT '0',
	PRIMARY KEY (`page_id`),
	INDEX `FK_cat_pages_cat_visibility` (`vis_id`),
	INDEX `FK_cat_pages_cat_rbac_users` (`created_by`),
	INDEX `FK_cat_pages_cat_rbac_users_2` (`modified_by`),
	CONSTRAINT `FK_cat_pages_cat_rbac_users` FOREIGN KEY (`created_by`) REFERENCES `cat_rbac_users` (`user_id`),
	CONSTRAINT `FK_cat_pages_cat_rbac_users_2` FOREIGN KEY (`modified_by`) REFERENCES `cat_rbac_users` (`user_id`),
	CONSTRAINT `FK_cat_pages_cat_visibility` FOREIGN KEY (`vis_id`) REFERENCES `cat_visibility` (`vis_id`) ON UPDATE NO ACTION ON DELETE NO ACTION
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
AUTO_INCREMENT=47
;

*/
echo "FUNC ",__FUNCTION__," LINE ",__LINE__,"<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
print_r($page);
echo "</textarea>";
                    if(is_array($data) && count($data))
                    {
                        // get old data
                        $old_parent       = intval($page['parent']);
                        $old_position     = intval($page['position']);
                        $old_link         = $page['link'];

                        // new parent?
                        if(isset($data['page_parent']) && $old_parent!=intval($data['page_parent']))
                        {
                            // new position (add to end)
                            $page['position'] = self::db()->getNext(
                                'pages',
                                intval($data['page_parent'])
                            );
                            $page['parent'] = intval($data['page_parent']);
                        }
                        // Work out level and root parent
                        if(intval($data['page_parent'])!='0')
                        {
                            $page['level'] = HPage::properties(intval($data['page_parent']),'level') + 1;
                            $page['root_parent']
                                = ($page['level'] == 1)
                                ? $page['parent']
                                : HPage::getRootParent($page['parent'])
                                ;
                        }
/*
    echo "FUNC ",__FUNCTION__," LINE ",__LINE__,"<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
print_r($page);
echo "</textarea>";
*/

                    }
                }
            }


// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Die aktuellen Einstellungen als JSON zurueckliefern, nicht nur als
// fertiges HTML-Formular
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if(self::asJSON())
            {
                Json::printSuccess($form->render(true));
            } else {
                Backend::printHeader();
                self::tpl()->output('backend_page_settings', array(
                    'form' => $form->render(true),
                    'page' => HPage::properties($pageID),
                ));
                Backend::printFooter();
            }
        }   // end function settings()

        /**
         *
         * @access public
         * @return
         **/
        public static function sections()
        {
            $pageID  = self::getPageID();
            if(self::asJSON())
            {
                Json::printSuccess($form->getForm());
            } else {
                Backend::printHeader();
                self::tpl()->output('backend_page_sections', array(
                    'page'     => HPage::properties($pageID),
                    'sections' => \CAT\Sections::getSections($pageID,NULL,false),
                    'blocks'   => Template::getBlocks(),
                    'addable'  => Addons::getAddons('page','name',false),
                ));
                Backend::printFooter();
            }
        }   // end function sections()

        /**
         *
         * @access public
         * @return
         **/
        public static function tree()
        {
            if(!self::user()->hasPerm('pages_list'))
                Json::printError('You are not allowed for the requested action!');

            $pages = HPage::getPages(true);
            $pages = self::lb()->buildRecursion($pages);

            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo json_encode($pages,true);
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
            $pageID   = self::getPageID();
            $unlinkID = Validate::sanitizePost('unlink');

            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID,'pages_edit'))
                Base::printFatalError('You are not allowed for the requested action!');

            // check data
            if(!HPage::exists($pageID) || !HPage::exists($unlinkID))
                Base::printFatalError('Invalid data!');

            self::db()->query(
                'DELETE FROM `:prefix:pages_langs` WHERE `page_id`=? AND `link_page_id`=?',
                array($pageID,$unlinkID)
            );

            if(self::asJSON())
            {
                echo Base::json_result(
                    ( self::db()->isError() ? false : true ),
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
            if(!self::user()->hasPerm('pages_edit'))
                Json::printError('You are not allowed for the requested action!');
            $params  = self::router()->getParams();
            $page_id = $params[0];
            $newval  = $params[1];
            if(!is_numeric($page_id)) {
                Json::printError('Invalid value');
            }
            if(!in_array($newval,array('public','private','hidden','none','deleted','registered')))
            {
                Json::printError('Invalid value');
            }
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// MUSS ANGEPASST WERDEN! Neue Spalte vis_id (FK)
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            self::db()->query(
                'UPDATE `:prefix:pages` SET `visibility`=? WHERE `page_id`=?',
                array($newval,$page_id)
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
        protected static function addHeaderComponent($type,$file,$page_id=NULL)
        {
            $headerfiles = HPage::getExtraHeaderFiles($page_id);

            if(!is_array($headerfiles) || !count($headerfiles))
            {
                $headerfiles = array(array());
            }

            foreach(array_values($headerfiles) as $data)
            {
                if(isset($data[$type]) && is_array($data[$type]) && count($data[$type]) && in_array($file,$data[$type]))
                {
                    return Base::lang()->translate('The file is already listed');
                }
                else
                {
                    $paths = array(
                        self::$javascripts
                    );

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Dateien des WYSIWYG-Editors, evtl. des Templates?
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

                    $db = self::db(); // spare some typing...

                    foreach($paths as $path)
                    {
                        $filename = Directory::sanitizePath($path.'/'.$file);
                        if(file_exists($filename))
                        {
                            $new    = ( isset($data[$type]) && is_array($data[$type]) && count($data[$type]) )
                                    ? $data[$type]
                                    : array();
                            array_push($new,Validate::path2uri($filename));
                            $new = array_unique($new);
                            $params = array(
                                'field'   => 'page_'.$type.'_files',
                                'value'   => serialize($new),
                                'page_id' => $page_id,
                            );

                            if(count($data))
                            {
                                $q = 'UPDATE `:prefix:pages_headers` SET :field:=:value WHERE `page_id`=:page_id';
                            }
                            else
                            {
                                $q = 'INSERT INTO `:prefix:pages_headers` ( `page_id`, :field: ) VALUES ( :page_id, :value )';
                            }
                            $db->query($q,$params);
                            if($db->isError())
                                return $db->getError();
                        }
                    }
                }
            }
            return true;
        }   // end function addHeaderComponent()

        /**
         * remove header file from the database
         **/
        protected static function delHeaderComponent($type,$file,$page_id=NULL)
        {
            $headerfiles = HPage::getExtraHeaderFiles($page_id);

echo "remove file $file\n";
            if(is_array($headerfiles) && count($headerfiles))
            {
                foreach(array_values($headerfiles) as $item)
                {
print_r($item[$type]);
                    if(!(is_array($item[$type]) && count($item[$type]) && in_array($file,$item[$type])))
                        return true; // silently fail
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