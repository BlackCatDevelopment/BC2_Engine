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

if (!class_exists('\CAT\Backend\Settings')) {
    class Settings extends Base
    {
        // log level
        protected static $loglevel       = \Monolog\Logger::EMERGENCY;
        //protected static $loglevel  = \Monolog\Logger::DEBUG;
        protected static $instance       = null;
        protected static $perm_prefix    = 'settings_';
        private static $regions        = null;
        private static $avail_settings = null;

        public static function __callstatic($name, $arguments)
        {
            $stmt = self::db()->query(
                'SELECT `region` FROM `:prefix:settings` AS `t1` GROUP BY `region`'
            );
            $data = $stmt->fetchAll();
            for ($i=0;$i<count($data);$i++) {
                if ($name==$data[$i]['region']) {
                    call_user_func([__CLASS__, 'index'], $name);
                }
            }
        }   // end function __callstatic()

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance()
        {
            if (!is_object(self::$instance)) {
                self::$instance = new self();
                self::addLangFile(__dir__.'/languages');
            }
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public static function get($name)
        {
            if (!self::$avail_settings) {
                self::getSettings();
            }
            return (
                  isset(self::$avail_settings[$name])
                ? self::$avail_settings[$name]
                : null
            );
        }   // end function get()

        /**
         * get available settings
         **/
        public static function getSettings()
        {
            if (!self::$avail_settings) {
                $data = self::db()->query(
                    'SELECT * FROM `:prefix:settings` AS `t1` '
                    . 'JOIN `:prefix:forms_fieldtypes` AS `t2` '
                    . 'ON `t1`.`fieldtype`=`t2`.`type_id` '
                    . 'WHERE `is_editable`=? '
                    . 'ORDER BY `region`,`name`',
                    array('Y')
                );
                if ($data) {
                    self::$avail_settings = $data->fetchAll();
                }
            }
            return self::$avail_settings;
        }   // end function getSettings()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if (!self::user()->hasPerm('settings_list')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            $settings = self::getSettings();
            if (!is_array($settings) || !count($settings)) {
                self::printFatalError('missing settings!');
            }

            // there *may* be a region name
            $region = self::getRegion();

            // filter settings by region
            if ($region && $region != 'index') {
                $settings = \CAT\Helper\HArray::filter($settings, 'region', $region, 'matching');
            }

            // get the form
            $form = \CAT\Helper\FormBuilder::generate(
                'settings',          // form name
                $settings,           // form items
                self::loadSettings() // form data
            );
            $form->setAttribute('action', CAT_ADMIN_URL.'/settings/'.$region);

            // add template options (if any)
            if(!$region || $region=='frontend') {
                $form2 = \CAT\Helper\Template::getOptionsForm();
                if(is_object($form2)) {
                    foreach($form2->getElements() as $e) {
                        $form->addElement($e,'default_template_variant');
                    }
                }
            }

            if($form->isSent()) {
                if($form->isValid()) {
                    $data = $form->getData();
                    // known settings
                    $known = array();
                    foreach($settings as $i => $s) {
                        $known[$s['name']] = $i;
                    }
                    // there may be template options
                    $tpl_options = array();
                    if(isset($data['options'])) {
                        $opt = explode(',',$data['options']);
                        foreach(array_values($opt) as $o) {
                            if(isset($data[$o])) {
                                $tpl_options[$o] = $data[$o];
                            }
                        }
                    }
                    // save settings
                    foreach($data as $key => $value) {
                        // check if that setting exists
                        if(isset($known[$key])) {
                            // check if value != default
                            if(empty($value) || $value==$settings[$known[$key]]['default_value']) {
                                self::db()->query(
                                    'DELETE FROM `:prefix:settings_site` WHERE `site_id`=? AND `name`=?',
                                    array(CAT_SITE_ID, $key)
                                );
                            }
                            elseif($value!=$settings[$known[$key]]['default_value']) {
                                // dirty hack for checkboxes
                                if(is_array($value) && $value[0]=='Yes') {
                                    $value = true;
                                }
                                self::db()->query(
                                    'REPLACE INTO `:prefix:settings_site` VALUES ( ?, ?, ? )',
                                    array(CAT_SITE_ID, $key, $value)
                                );
                            }
                        }
                    }
                    if(count($tpl_options)>0) {
                        $addon_id = \CAT\Helper\Addons::getDetails(
                            \CAT\Registry::get('default_template'),
                            'addon_id'
                        );
                        foreach($tpl_options as $opt => $val) {
                            self::db()->query(
                                'REPLACE INTO `:prefix:templates` VALUES ( ?, ?, ? )',
                                array($addon_id, $opt, $val)
                            );
                        }
                    }
                }
            }

            if (!self::asJSON()) {
                Backend::printHeader();
                self::tpl()->output(
                    'backend_settings',
                    array(
                        'form'   => $form->render(true),
                    )
                );
                Backend::printFooter();
            }
        }   // end function index()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function getRegion()
        {
            $region     = self::router()->getParam();
            if (!$region) {
                $region = self::router()->getFunction();
            }
            if (!$region) {
                $region = self::router()->getRoutePart(1);
            }
            return $region;
        }   // end function getRegion()
    } // class CAT_Backend_Settings
} // if class_exists()
