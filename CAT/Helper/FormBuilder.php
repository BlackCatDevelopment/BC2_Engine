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
use \CAT\Helper\Page as HPage;

use \wblib\wbForms\Form;
use \wblib\wbForms\Element;

#require CAT_ENGINE_PATH.'/modules/lib_wblib/wblib/wbForms/autoload.php';

if (!class_exists('FormBuilder'))
{
    class FormBuilder extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $forms    = array();

        public static function generate(string $name,$items,array $formdata=array(),string $legend_key='fieldset',bool $skip_filehandler=false) : \wblib\wbForms\Form
        {
            \wblib\wbForms\Base::$lang_path = CAT_ENGINE_PATH.'/CAT/Backend/languages';
            $form = new Form($name);

            if(is_array($items) && count($items))
            {
                $lastlabel = null;
                foreach($items as $item)
                {
                    if(isset($item['fieldset']) && $lastlabel != $item['fieldset'])
                    {
                        $form->addElement(new \wblib\wbForms\Element\Fieldset(
                            self::lang()->translate(self::humanize($item['fieldset']))
                        ));
                    }

                    $type = 'wblib\wbForms\Element\\'.ucfirst($item['fieldtype']);

                    // if no label is given, use the field name as label
                    $label = strlen($item['label'])
                            ? self::lang()->translate($item['label'])
                            : self::lang()->translate(self::humanize($item['name']));

                    // create the element
                    $element = array_merge(
                        $item,
                        array(
                            'required' => (
                                  (isset($item['required']) && strlen($item['required']))
                                ? true
                                : false
                            ),
                            'helptext' => $item['helptext'],
                            'pattern'  => ( isset($item['pattern']) ? $item['pattern'] : false ),
                            'label'    => $label,
                        )
                    );
                    $e = $form->addElement(new $type($item['name'],$element));

                    // list of values for checkbox and radio
                    if(in_array($item['fieldtype'],array('checkbox','radio','select')))
                    {
                        if(isset($item['data']) && strlen($item['data']) && substr_count($item['data'],","))
                        {
                            $e->setData(explode(",",$item['data']));
                        }
                    }

                    // add values from fieldhandler
                    if(strlen($item['fieldhandler'])) {
                        if($item['fieldtype'] != 'button') {
                            $params = ( substr_count($item['params'], ',') ? explode(', ',$item['params']) : array($item['params']) );
                            $data = call_user_func_array($item['fieldhandler'], $params);
                            if($data) {
                                $e->setData($data);
                            }
                        } else {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Inline JavaScript vermeiden; man koennte z.B. alle onclicks sammeln
// und an den Asset Manager als Scriptblock uebergeben
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                            $e->setAttribute('onclick','window.location.href=\''.CAT_SITE_URL.$item['route'].'\';');
                        }
                    }


                    if(isset($formdata[$item['name']])) {
                        $e->setValue($formdata[$item['name']]);
                    } elseif(isset($item['mapto']) && isset($formdata[$item['mapto']])) {
                        $e->setValue($formdata[$item['mapto']]);
                    }
                }

                // buttons
                $form->addElement(new \wblib\wbForms\Element\Submit(
                    self::lang()->translate('Save')
                ));
                $form->addElement(new \wblib\wbForms\Element\Submit(
                    self::lang()->translate('Cancel')
                ));
            }

            return $form;
        }   // end function generate()

        /**
         *
         * @access public
         * @return
         **/
        public static function generateForm($name,$data=array())
        {
            // get form from DB
            $stmt = self::db()->query(
                'SELECT `t1`.`action`, `t2`.*, '
                . '     `t3`.`name`, `t3`.`mapto`, `t3`.label, `t3`.`helptext`, `t3`.`pattern`, '
                . '     `t4`.`fieldtype` '
                . 'FROM `:prefix:forms` as `t1` '
                . 'JOIN `:prefix:forms_has_fields` AS `t2` ON `t1`.`form_id`=`t2`.`form_id` '
                . 'JOIN `:prefix:forms_fielddefinitions` AS `t3` ON `t2`.`field_id`=`t3`.`field_id` '
                . 'JOIN `:prefix:forms_fieldtypes` AS `t4` ON `t2`.type_id=`t4`.`type_id` '
                . 'WHERE `t1`.`form_name`=? ORDER BY `fieldset`, `t2`.`position` ',
                array($name)
            );
            $fields = $stmt->fetchAll();
            if(!$fields) return new \wblib\wbForms\Form();
            return self::generate($name,$fields,$data);
        }   // end function generateForm()
    } // class FormBuilder
} // if class_exists()