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
use \CAT\Registry as Registry;

if (!class_exists('\CAT\Helper\WYSIWYG'))
{
    class WYSIWYG extends Base
    {
        private static $e_url  = null;
        private static $e_name = null;
        private static $e      = null;

        /**
         *
         * @access public
         * @return
         **/
        public static function editor()
        {
            if(!is_object(self::$e)) {
                self::init();
            }
            return self::$e;
        }   // end function editor()
        
        public static function getJS()
        {
            if(!is_object(self::$e)) self::init();
            return self::$e->getJS();
        }

        public static function init()
        {
            self::$e_name = Registry::get('wysiwyg_editor');     // name
            self::$e_url  = Registry::get('wysiwyg_editor_url'); // url
            $editorclass  = '\CAT\Addon\WYSIWYG\\'.self::$e_name;
            self::$e      = new $editorclass();
            // add the appropriate JS
            $editorclass::addJS();
/*
                // editor has some init code
                $editor_js = self::$e->getEditorJS();
                if(!empty($editor_js))
                {
                    $am->addCode(
                        self::tpl()->get(
                            new \Dwoo\Template\Str(),
                            array(
                                'section_id' => $section['section_id'],
                                'action'     => CAT_ADMIN_URL.'/section/save/'.$section['section_id'],
                                'width'      => \CAT\Helper\WYSIWYG::editor()->getWidth(),
                                'height'     => \CAT\Helper\WYSIWYG::editor()->getHeight(),
                                'id'         => $id,
                                'content'    => $content
                            )
                        ),
                        'footer'
                    );
                }
*/
        }
    }
}