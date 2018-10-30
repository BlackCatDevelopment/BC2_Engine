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

if (!class_exists('\CAT\Backend\Addons'))
{
    class Addons extends Base
    {
        protected static $instance = NULL;
        protected static $known_types = array(
            'Page','Language','Library','Template','Tool','WYSIWYG'
        );

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
        public static function index()
        {
            if(!self::user()->hasPerm('addons_list'))
                self::printFatalError('You are not allowed for the requested action!');

            // some backend themes may show all addons on one page, while
            // others (like Backstrap) use tabs. So we pick them both here.
            $data = HAddons::getAddons(NULL,'name',false,true); // all
            $ftp  = HAddons::getAddons(NULL,'name',false,true,true);

            foreach($data as $i => $item)
            {
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
            Backend::printHeader();
            self::tpl()->output('backend_addons', $tpl_data);
            Backend::printFooter();
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function catalog()
        {
            if(!file_exists(CAT_ENGINE_PATH."/temp/catalog.json"))
            {
                self::updateCatalog();
            }
            $catalog = self::getCatalog();
            // get installed
            $modules = HAddons::getAddons(NULL,'name',false); // all
            // map installed
            $installed = array();
            foreach($modules as $i => $m) {
                if(isset($m['version'])) {
                    $installed[$m['directory']] = $m['version'];
                }
            }
            // find installed in catalog
            foreach($catalog['modules'] as $i => $m)
            {
                $catalog['modules'][$i]['upgradable']   = false;
                $catalog['modules'][$i]['is_installed'] = false;

                if(isset($installed[$m['directory']]))
                {
                    $catalog['modules'][$i]['is_installed'] = true;
                    $catalog['modules'][$i]['installed_version'] = $installed[$m['directory']];
                    if(version_compare($m['version'],$installed[$m['directory']],'>'))
                    {
                        $catalog['modules'][$i]['upgradable'] = true;
                    }
                }
                if(!isset($catalog['modules'][$i]['type']))
                {
                    $catalog['modules'][$i]['type'] = 'module';
                }
                // get description for current language
                if(isset($m['description'])) {
                    if(isset($m['description'][LANGUAGE]['title'])) {
                        $catalog['modules'][$i]['description'] = $m['description'][LANGUAGE]['title'];
                    } elseif(isset($m['description']['en']['title'])) {
                        $catalog['modules'][$i]['description'] = $m['description']['en']['title'];
                    } else {
                        $catalog['modules'][$i]['description'] = 'n/a';
                    }
                }
                // check requirements
                if(isset($m['require']['core']['release'])) {
                    // CMS version too low
                    if(HAddons::versionCompare(CAT_VERSION,$m['require']['core']['release'],'<')) {
                        $catalog['modules'][$i]['warn'] = self::lang()->t('This module requires BlackCat CMS v').$m['require']['core']['release'];
                    }
                    // old module (CMS version too high)
                    if(HAddons::versionCompare(CAT_VERSION,$m['require']['core']['release'],'>')) {
                        $catalog['modules'][$i]['warn'] = self::lang()->t('This module requires BlackCat CMS v').$m['require']['core']['release'];
                    }
                }
            }

            if(self::asJSON())
            {
                print json_encode(array('success'=>true,'modules'=>$catalog['modules']));
                exit();
            }

            $tpl_data = array(
                'modules'      => $catalog['modules'],
                'current'      => 'catalog',
            );

            Backend::printHeader();
            self::tpl()->output('backend_addons', $tpl_data);
            Backend::printFooter();
        }   // end function catalog()

        /**
         *
         * @access public
         * @return
         **/
        public static function create()
        {
            if(!self::user()->hasPerm('addons_create'))
                self::printFatalError('You are not allowed for the requested action!');

            $form = FormBuilder::generateForm('be_addon_create');
            $form->setAttribute('action',CAT_ADMIN_URL.'/addons/create');
            $form->getElement('addon_type')->setData(self::$known_types);

            // form already sent?
            if($form->isSent())
            {
                // check data
                if($form->isValid())
                {
                    // save data
                    $data = $form->getData();
                    // create database entry
                    self::db()->query(
                          'INSERT INTO `:prefix:addons` '
                        . '(`type`,`directory`,`name`,`installed`,`upgraded`,`removable`,`bundled`) '
                        . 'VALUES (?,?,?,?,?,"Y","N")',
                        array(
                            strtolower(self::$known_types[$data['addon_type']]),
                            $data['addon_directory'],
                            $data['addon_name'],
                            time(),
                            time(),
                        )
                    );
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Create folder and default files
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                    self::router()->reroute(CAT_BACKEND_PATH.'/addons');
                }
            }

            Backend::printHeader();
                self::tpl()->output('backend_addons_create', array(
                    'form'    => $form->render(true),
                    'current' => 'create',
                ));
                Backend::printFooter();
        }   // end function create()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function install()
        {
            if(!self::user()->hasPerm('addons_install'))
                self::printFatalError('You are not allowed for the requested action!');

            $addon = self::getAddonName();
            $path  = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$addon);
            $handler = null;
            $classname = null;

            // already there? (uploaded via FTP)
            if(is_dir($path)) {
                $info = HAddons::getInfo($addon);
                $names = array($addon);
                if(isset($info['name']) && $info['name']!=$addon) {
                    $names[] = $info['name'];
                }
                if(isset($info['directory']) && $info['directory']!=$addon) {
                    $names[] = $info['directory'];
                }
                foreach(array_values($names) as $name) {
                    $filename = \CAT\Helper\Directory::sanitizePath($path.'/inc/class.'.$name.'.php');
                    if(file_exists($filename)) {
                         $handler = $filename;
                         $classname = '\CAT\Addon\\'.$name;
                         break;
                    }
                }
                if($handler)
                {
                    include_once $handler;
                    $errors = $classname::install();
                    if(!count($errors)) {
                        self::router()->reroute(CAT_BACKEND_PATH.'/addons');
                    } else {
                        $tpl_data = array(
                            'modules'      => array(),
                            'current'      => 'notinstalled',
                            'errors'       => $errors,
                        );

                        Backend::printHeader();
                        self::tpl()->output('backend_addons', $tpl_data);
                        Backend::printFooter();
                    }
                }
            }
        }   // end function install()

        /**
         *
         * @access public
         * @return
         **/
        public static function notinstalled()
        {
            if(!self::user()->hasPerm('addons_install'))
                self::printFatalError('You are not allowed for the requested action!');

            $data  = HAddons::getAddons(NULL,'name',false,true,true);

            if(self::asJSON())
            {
                print json_encode(array('success'=>true,'modules'=>$data));
                exit();
            }

            $tpl_data = array(
                'modules'      => $data,
                'current'      => 'notinstalled',
            );

            Backend::printHeader();
            self::tpl()->output('backend_addons', $tpl_data);
            Backend::printFooter();
        }   // end function notinstalled()

        /**
         *
         **/
        public static function getAddonName() : string
        {
            $name  = Validate::sanitizePost('addon','string');

            if(!$name)
                $name  = Validate::sanitizeGet('addon','string');

            if(!$name)
                $name = self::router()->getParam(-1);

            if(!$name)
                $name = self::router()->getRoutePart(-1);

            return $name;
        }   // end function getAddonName()
        
        /**
         * get the catalog contents from catalog.json
         *
         * @access private
         * @return array
         **/
        private static function getCatalog()
        {
            $string    = file_get_contents(CAT_ENGINE_PATH."/temp/catalog.json");
            $catalog   = json_decode($string,true);
            if(is_array($catalog)) {
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
            $ch   = GitHub::init_curl(GITHUB_CATALOG_LOCATION);
            $data = curl_exec($ch);
            if(curl_error($ch))
            {
                Json::printError(trim(curl_error($ch)));
            }
            $fh = fopen(CAT_ENGINE_PATH.'/temp/catalog.json','w');
            if($fh)
            {
                fwrite($fh,$data);
                fclose($fh);
            }
            else
            {
                Json::printError('Unable to save file');
            }
        }
        

    } // class Addons

} // if class_exists()