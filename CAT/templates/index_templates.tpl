/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          {$addon_author}
   @copyright       {$year} {$addon_author}
   @category        CAT_Addon
   @package         {$addon_name}

*/

$tpldata	= array(); // if you need to set some additional template vars, add them here
global $page_id;
$variant  = \CAT\Helper\Page::getPageSettings($page_id,'internal','template_variant');

if(!$variant) {
    $variant = ( defined('DEFAULT_TEMPLATE_VARIANT') && DEFAULT_TEMPLATE_VARIANT != '' )
             ? DEFAULT_TEMPLATE_VARIANT
             : 'default';
}

\CAT\Base::tpl()->setPath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/templates/'.$variant);
\CAT\Base::tpl()->setFallbackPath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/templates/default');
\CAT\Base::tpl()->output('index.tpl',$tpldata);
\CAT\Base::tpl()->resetPath();
