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

if(!class_exists('\CAT\Addon\WYSIWYG\CKEditor4',false))
{
    class CKEditor4
    {
        public static function getJS()
        {
            // if the module is installed...
            if(\CAT\Helper\Addons::isInstalled('ckeditor4',null,'WYSIWYG'))
                return '/modules/ckeditor4/js/wysiwyg.js';
            // ...else use CDN
            return 'https://cdn.ckeditor.com/4.7.3/full/ckeditor.js';
        }
        public static function getHeight()
        {
            return '350px';
        }
        public static function getWidth()
        {
            return '100%';
        }
        public function getEditorJS()
        {
            return '';
        }   // end function getEditorJS()

        public function showEditor()
        {
            return "
            <form name=\"cat_wysiwyg_editor_{\$section_id}\" action=\"{\$action}\" method=\"post\">
                <input type=\"hidden\" name=\"section_id\" value=\"{\$section_id}\" />
                <input type=\"hidden\" name=\"content_id\" value=\"{\$id}\" />
                <textarea class=\"cat_wysiwyg_editor\" id=\"{\$id}\" name=\"{\$id}\" style=\"width:{\$width};height:{\$height}\">{\$content}</textarea><br />
            	<input type=\"submit\" value=\"{translate('Save')}\" />
            </form>\n";
        }
    }
}

/*

,
            //contentsCss: \"{\$CAT_URL}/modules/ckeditor4/ckeditor/contents.css\",


            //customConfig: \"{\$CAT_URL}/modules/ckeditor4/ckeditor/custom/config.js\",
            //extraPlugins: \"divarea,xml,ajax,cmsplink,droplets{if isset(\$plugins)},{\$plugins}{/if}\",
{if \$filemanager}            {\$filemanager}{/if}
{if \$css}            contentsCss: [ \"{\$css}\" ],{/if}
{if \$toolbar}            toolbar: \"{\$toolbar}\",{/if}
{if \$editor_config}{foreach \$editor_config cfg}
            {\$cfg.option}: {if \$cfg.value != 'true' && \$cfg.value != 'false' }'{/if}{\$cfg.value}{if \$cfg.value != 'true' && \$cfg.value != 'false' }'{/if}{if ! \$.foreach.default.last},{/if}
{/foreach}{/if}

*/