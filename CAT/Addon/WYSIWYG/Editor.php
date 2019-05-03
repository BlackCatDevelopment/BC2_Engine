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

namespace CAT\Addon\WYSIWYG;

use \CAT\Base as Base;
use \CAT\Registry as Registry;

if(!class_exists('\CAT\Addon\WYSIWYG\Editor',false))
{
    class Editor implements IEditor
    {
        protected static $width  = '100%';
        protected static $height = '350px';
        protected static $class  = 'wysiwyg';
        protected static $tpl    = '<textarea class="%class%" id="%id%" name="%name%" style="width:%width%;height:%height%">%content%</textarea>';

        public function showEditor($id,$content='',$width='',$height='')
        {
            return str_ireplace(
                array(
                    '%class%','%id%','%name%','%width%','%height%','%content%'
                ),
                array(
                    self::getClass(),
                    $id,
                    $id,
                    ( strlen($width)>0  ? $width  : self::getWidth() ),
                    ( strlen($height)>0 ? $height : self::getHeight() ),
                    $content
                ),
                self::$tpl
            );
        }

        public static function addJS() {
            // to be implemented by the editor class
        }
        public static function getClass()
        {
            return self::$class;
        }
        public static function getEditorJS()
        {
            return '';
            // to be implemented by the editor class
        }
        public static function getHeight()
        {
            return self::$height;
        }
        public static function getWidth()
        {
            return self::$width;
        }
        public static function setHeight($height) {
            self::$height = $height;
        }
        public static function setWidth($width) {
            self::$width = $width;
        }
        public static function setClass($class) {
            self::$class = $class;
        }
    }
}