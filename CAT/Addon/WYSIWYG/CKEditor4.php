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

if(!class_exists('\CAT\Addon\WYSIWYG\ckeditor4',false))
{
    class ckeditor4 extends Editor implements IEditor
    {
        public static function addJS()
        {
            // important: wysiwyg.js loads the ckeditor.js, so do NOT add it here!
            if(file_exists(CAT_ENGINE_PATH.'/CAT/vendor/ckeditor/ckeditor/ckeditor.js')) {
                \CAT\Helper\Assets::addJS('/CAT/Addon/WYSIWYG/ckeditor4/wysiwyg.js','footer');
            }
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