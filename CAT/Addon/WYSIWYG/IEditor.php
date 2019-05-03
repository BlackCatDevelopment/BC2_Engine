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

interface IEditor
{
    /**
     * use the asset helper to add the editor JS to the header; return void
     **/
    public static function addJS();
    public static function getClass();
    public static function getEditorJS();
    public static function getHeight();
    public static function getWidth();
    public static function setHeight($height);
    public static function setWidth($width);
    public static function setClass($class);
}