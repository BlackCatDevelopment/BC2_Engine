
$mod_{$position} = array(
    '{$for}' => array(
{if $for=='frontend' && $position=='headers'}
    {if $addon_type=='template'}
        'meta' => array(
            array( 'charset' => (defined('DEFAULT_CHARSET') ? DEFAULT_CHARSET : "utf-8") ),
            array( 'http-equiv' => 'X-UA-Compatible', 'content' => 'IE=edge' ),
            array( 'name' => 'viewport', 'content' => 'width=device-width, initial-scale=1' ),
            array( 'name' => 'description', 'content' => 'BlackCat CMS' ),
        ),
    {/if}
        'css' => array(
{if $addon_bootstrap.0=='Yes'}
            array('file'=>'CAT/vendor/twbs/bootstrap/dist/css/bootstrap.min.css',),
{/if}{if $addon_jqueryui.0=='Yes'}
            array('file'=>'CAT/vendor/components/jqueryui/themes/base/jquery-ui.min.css',),
{/if}
        ),
        'jquery' => array(
            'core'    => {if $addon_jquery.0=='Yes'}true{else}false{/if},
            'ui'      => {if $addon_jqueryui.0=='Yes'}true{else}false{/if},
{if $addon_jquery.0=='Yes'}
            'plugins' => array(),
{/if}
        ),
{/if}
        'js' => array(
{if $position=='footers' && $addon_bootstrap.0=='Yes'}
            'CAT/vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js',
{/if}
{if $position=='footers' && $addon_type=='tool'}
            'CAT/Backend/js/session.js',
{/if}
        )
    )
);

