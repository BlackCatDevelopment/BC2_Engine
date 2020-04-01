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
use \CAT\Helper\Addons as HAddons;
use \CAT\Helper\DateTime as DateTime;
use \CAT\Helper\Directory as Directory;
use \CAT\Helper\FormBuilder as FormBuilder;
use \CAT\Helper\GitHub as GitHub;
use \CAT\Helper\Json as Json;
use \CAT\Helper\Validate as Validate;

if (!class_exists('\CAT\Backend\Addons')) {
    class Addons extends Base
    {
        protected static $instance = null;
        protected static $known_types = array(
            'Page'      => 'module',
            'Language'  => 'language',
            'Library'   => 'module',
            'Template'  => 'template',
            'Tool'      => 'module',
            'WYSIWYG'   => 'module',
            'Zip'       => '',
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
        public static function index()
        {
            self::user()->checkPermission('addons_list');

            // some backend themes may show all addons on one page, while
            // others (like Backstrap) use tabs. So we pick them both here.
            $data = HAddons::getAddons(null, 'name', false, true); // all
            $ftp  = HAddons::getAddons(null, 'name', false, true, true);

            foreach ($data as $i => $item) {
                $data[$i]['install_date'] = DateTime::getDate($item['installed']);
                $data[$i]['update_date']  = DateTime::getDate($item['upgraded']);
            }
            $tpl_data = array(
                'modules'      => $data,
                'modules_json' => json_encode($data, JSON_NUMERIC_CHECK),
                'notinstalled' => $ftp,
                'notinstalled_json' => json_encode($ftp, JSON_NUMERIC_CHECK),
                'current'      => 'installed',
            );
            
            Backend::show('backend_addons', $tpl_data);
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function catalog()
        {
            self::user()->checkPermission('addons_install');

            if (
                   !file_exists(CAT_ENGINE_PATH."/temp/catalog.json")
                || self::router()->getRoutePart(-1) == 'update'
            ) {
                self::updateCatalog();
            }
            $catalog = self::getCatalog();
            // get installed
            $modules = HAddons::getAddons(null, 'name', false); // all
            // map installed
            $installed = array();
            foreach ($modules as $i => $m) {
                $installed[$m['directory']] = $m;
            }

            // find installed in catalog
            foreach ($catalog['modules'] as $i => $m) {
                $catalog['modules'][$i]['upgradable']   = false;
                $catalog['modules'][$i]['removable']    = false;
                $catalog['modules'][$i]['is_installed'] = false;

                if (isset($installed[$m['directory']])) {
                    $catalog['modules'][$i]['is_installed'] = true;
                    $catalog['modules'][$i]['removable']    =
                        ($installed[$m['directory']]['removable'] == 'N' ? false : true);
                    if (isset($m['version']) && isset($installed[$m['directory']]['version'])) {
                        $catalog['modules'][$i]['installed_version'] = $installed[$m['directory']]['version'];
                        if (version_compare($m['version'], $installed[$m['directory']]['version'], '>')) {
                            $catalog['modules'][$i]['upgradable'] = true;
                        }
                    }
                }
                if (!isset($catalog['modules'][$i]['type'])) {
                    $catalog['modules'][$i]['type'] = 'module';
                }
                // get description for current language
                if (isset($m['description'])) {
                    if (isset($m['description'][strtolower(LANGUAGE)]['title'])) {
                        $catalog['modules'][$i]['description'] = $m['description'][strtolower(LANGUAGE)]['title'];
                    } elseif (isset($m['description']['en']['title'])) {
                        $catalog['modules'][$i]['description'] = $m['description']['en']['title'];
                    } else {
                        $catalog['modules'][$i]['description'] = 'n/a';
                    }
                }
                // check requirements
                if (isset($m['require']['core']['release'])) {
                    // CMS version too low
                    if (HAddons::versionCompare(CAT_VERSION, $m['require']['core']['release'], '<')) {
                        $catalog['modules'][$i]['warn'] = self::lang()->t('This module requires BlackCat CMS v').$m['require']['core']['release'];
                    }
                    // old module (CMS version too high)
                    if (HAddons::versionCompare(CAT_VERSION, $m['require']['core']['release'], '>')) {
                        $catalog['modules'][$i]['warn'] = self::lang()->t('This module requires BlackCat CMS v').$m['require']['core']['release'];
                    }
                }
            }

            if (self::asJSON()) {
                print json_encode(array('success'=>true,'modules'=>$catalog['modules']));
                exit();
            }

            $catalog['modules'] = \CAT\Helper\HArray::sort($catalog['modules'], 'name');
            $tpl_data = array(
                'modules'      => $catalog['modules'],
                'current'      => 'catalog',
                'version'      => $catalog['version'],
            );

            Backend::show('backend_addons', $tpl_data);
        }   // end function catalog()

        /**
         *
         * @access public
         * @return
         **/
        public static function create()
        {
            self::user()->checkPermission('addons_create');

            // !!!! häh??? !!!
            $known_types = array_combine(self::$known_types, self::$known_types);

            $form = FormBuilder::generateForm('be_addon_create');
            $form->setAttribute('action', CAT_ADMIN_URL.'/addons/create');
            $form->getElement('addon_type')->setData($known_types);

            // form already sent?
            if ($form->isSent()) {
                // check data
                if ($form->isValid()) {
                    // save data
                    $data = $form->getData();
                    $sub  = strtolower($data['addon_type']).'s';
                    if (is_dir(CAT_ENGINE_PATH.'/'.$sub.'/'.$data['addon_directory'])) {
                        $form->addError('There already exists a directory with this name');
                    } else {
                        $data['addon_type'] = strtolower($data['addon_type']);
                        $data['year']       = date('Y');
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// !!!!! TODO: sanitize addon_directory (don't allow umlauts, diacritics, ...)
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                        // create database entry
                        self::db()->query(
                              'INSERT INTO `:prefix:addons` '
                            . '(`type`,`directory`,`name`,`installed`,`upgraded`,`removable`,`bundled`) '
                            . 'VALUES (?,?,?,?,?,"Y","N")',
                            array(
                                $data['addon_type'],
                                $data['addon_directory'],
                                $data['addon_name'],
                                time(),
                                time(),
                            )
                        );

                        if (self::db()->isError()===false) {
                            if ($data['addon_type']!='language') {
                                $basedir = CAT_ENGINE_PATH.'/'.$sub.'/'.$data['addon_directory'];
                                Directory::createDirectory($basedir);
                                Directory::createDirectory($basedir.'/inc');
                                if ($data['addon_type']!='library' && $data['addon_type']!='wysiwyg') {
                                    Directory::createDirectory($basedir.'/css');
                                    Directory::createDirectory($basedir.'/js');
                                    Directory::createDirectory($basedir.'/templates');
                                    Directory::createDirectory($basedir.'/'.CAT_TEMPLATES_FOLDER.'/default');
                                    if ($data['addon_type']=='page' || $data['addon_type']=='template') {
                                        $data['for']='frontend';
                                    } elseif ($data['addon_type']=='tool') {
                                        $data['for']='backend';
                                    }
                                    if (isset($data['for'])) {
                                        foreach (array_values(array('headers','footers')) as $pos) {
                                            $tpl = self::tpl()->get(
                                                CAT_ENGINE_PATH.'/CAT/templates/headers_and_footers_inc.tpl',
                                                array_merge(
                                                    array(
                                                        'position' => $pos,
                                                    ),
                                                    $data
                                                )
                                            );
                                            $fh = fopen($basedir.'/'.$pos.'.inc.php', 'w');
                                            fwrite($fh, '<'.'?'.'php'."\n");
                                            fwrite($fh, $tpl);
                                            fclose($fh);
                                        }
                                    }
                                    if($data['addon_type']=='template') {
                                        $tpl = self::tpl()->get(
                                            CAT_ENGINE_PATH.'/CAT/templates/index_templates.tpl',
                                            $data
                                        );
                                        $fh = fopen($basedir.'/index.php', 'w');
                                        fwrite($fh, '<'.'?'.'php'."\n");
                                        fwrite($fh, $tpl);
                                        fclose($fh);
                                    }
                                }
                            } else {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// !!!!! TODO: check language shortcut
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                                $basedir = CAT_ENGINE_PATH.'/languages';
                                $file    = $data['addon_directory'];
                                $fh = fopen($basedir.'/'.$file.'.php', 'w');
                                fwrite($fh, '<'.'?'.'php'."\n");
                                fwrite($fh, '$LANG = array('."\n");
                                fwrite($fh, ');'."\n");
                                fclose($fh);
                            }
                        }
                    }
                    $form->setInfo('The module was created.');
                }
            }

            Backend::show('backend_addons_create', array(
                'form'    => $form->render(true),
                'current' => 'create',
            ));
        }   // end function create()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function install()
        {
            self::user()->checkPermission('addons_install');

            $addon     = self::getItem('addon');
            $type      = self::router()->getRoutePart(-2);

            HAddons::handleInstall($type, $addon);

            return self::index();
        }   // end function install()

        /**
         *
         * @access public
         * @return
         **/
        public static function notinstalled()
        {
            self::user()->checkPermission('addons_install');

            $data = HAddons::getAddons(null, 'name', false, true, true);
            $zips = array();

            // uploaded zip files
            $zipfiles = Directory::findFiles(CAT_TEMP_FOLDER.'/uploaded_addons',array(
                'extension' => 'zip',
                'max_depth' => 0,
                'remove_prefix' => true,
            ));
            if(is_array($zipfiles) && count($zipfiles)>0) {
                // remove leading /
                foreach($zipfiles as $i => $z) {
                    $zips[] = array(
                        'filename' => substr_replace($z,'',0,1),
                        'size'     => Directory::getSize(CAT_TEMP_FOLDER.'/uploaded_addons'.$z,true),
                        'date'     => Directory::getModdate(CAT_TEMP_FOLDER.'/uploaded_addons'.$z,true),
                    );
                }
            }

            if (self::asJSON()) {
                print json_encode(array('success'=>true,'modules'=>$data,'zips'=>$zips));
                exit();
            }

            $tpl_data = array(
                'modules'      => $data,
                'zips'         => $zips,
                'current'      => 'notinstalled',
            );

            Backend::show('backend_addons', $tpl_data);
        }   // end function notinstalled()

        /**
         *
         * @access public
         * @return
         **/
        public static function upload()
        {
            self::user()->checkPermission('addons_install');
            $tpl_data = array('current'=>'upload');
            $result   = null;
            // handle upload?
            if(isset($_FILES['upload_file'])) {
                $handle = new \Verot\Upload\Upload($_FILES['upload_file']);
                $handle->allowed = array(
                    'application/x-zip',
                    'application/x-zip-compressed',
                    'application/zip',
                );
                if ($handle->uploaded) {
                    $handle->process(CAT_TEMP_FOLDER.'/uploaded_addons');
                    if ($handle->processed) {
                        $filename = $handle->file_dst_pathname;
                        $handle->clean();
                        $result = self::handleInstall('zip', $filename);
                        if($result===true) {
                            $tpl_data['success'] = 'Module installed';
                            self::router()->reroute(CAT_BACKEND_PATH.'/addons');
                        }
                    } else {
                        $tpl_data['error'] = $handle->error;
                    }
                }
            }
            Backend::show('backend_addons_upload',$tpl_data);
        }   // end function upload()

        /**
         * get the catalog contents from catalog.json
         *
         * @access private
         * @return array
         **/
        private static function getCatalog()
        {
            $string    = file_get_contents(CAT_ENGINE_PATH."/temp/catalog.json");
            $catalog   = json_decode($string, true);
            if (is_array($catalog)) {
                return $catalog;
            } else {
                return array();
            }
        }   // end function getCatalog()

        /**
         * update the catalog.json from GitHub
         *
         * @access private
         * @return void
         **/
        private static function updateCatalog()
        {
            $ch   = GitHub::init_curl(\CAT\Registry::get('github_catalog_location'));
            $data = curl_exec($ch);
            if (curl_error($ch)) {
                Json::printError(trim(curl_error($ch)));
            }
            $fh = fopen(CAT_ENGINE_PATH.'/temp/catalog.json', 'w');
            if ($fh) {
                fwrite($fh, $data);
                fclose($fh);
            } else {
                Json::printError('Unable to save file');
            }
        }
    } // class Addons
} // if class_exists()
