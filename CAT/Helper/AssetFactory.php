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
use \CAT\Backend as Backend;
use \CAT\Registry as Registry;
use \CAT\Helper\Assets as Assets;
use \CAT\Helper\Page as HPage;
use \CAT\Helper\Validate as Validate;

if(!class_exists('AssetFactory'))
{
    class AssetFactory
	{
        private static $ids = array();
        private $id   = null;
        private $js   = array('header'=>array(),'footer'=>array());
        private $css  = array();
        private $meta = array();
        private $cond = array();
        private $code = array('header'=>array(),'footer'=>array());
        private $jq   = false;
        private $ui   = false;

        /**
         * output template for external stylesheets
         **/
        private   static $css_tpl  = '%%condition_open%%<link rel="stylesheet" href="%%file%%" media="%%media%%" />%%condition_close%%';
        /**
         * output template for external javascripts
         **/
        private   static $js_tpl   = '%%condition_open%%<script type="text/javascript" src="%%file%%">%%code%%</script>%%condition_close%%';
        /**
         * output template for meta tags
         **/
        private   static $meta_tpl = '<meta %%content%% />';

        /**
         * Constructor
         *
         * $id may be a page_id or a combination of "backend_" and BE area
         * Example: backend_media
         **/
        private function __construct($id)
        {
            $this->id = $id;
            self::$ids[$id] = $this;
        }   // end function __construct()

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance($id=null)
        {
            if(isset(self::$ids[$id]))
                return self::$ids[$id];
            else
                return new self($id);
        }   // end function getInstance()
        

        /**
         *
         * @access public
         * @return
         **/
        public function addAsset($file,$parm)
        {
            $type = pathinfo($file,PATHINFO_EXTENSION);
            if($type=='css') $this->addCSS(Validate::path2uri($file));
            if($type=='js')  $this->addJS(Validate::path2uri($file),$parm);
            if($type=='php') Assets::addInclude($file,$parm);
        }   // end function addAsset()

        /**
         *
         * @access public
         * @return
         **/
        public function addCode($code,$pos='header',$after=null)
        {
            $index = null;
            if(!strlen($pos))
                $pos = 'footer';
            if(!isset($this->code[$pos]))
                $this->code[$pos] = array();
/*
            if($after)
            {
                $index = array_search($after,$this->js[$pos]);
#echo "INDEX: $index<br />";
                if($index) {
                    array_splice($this->js[$pos],$index,0,$file);
                    return;
                }
            }
*/
            $this->code[$pos][] = $code;
        }   // end function addCode()


        /**
         *
         * @access public
         * @return
         **/
        public function addCondition($file, $condition)
        {
            $this->cond[$file] = $condition;
        }   // end function addCondition()
        
        /**
         *
         * @access public
         * @return
         **/
        public function addCSS($url,$media='screen')
        {
            if(!strlen($media)) $media = 'screen';
            if(!isset($this->css[$media]))
                $this->css[$media] = array();
            if(!in_array($url,$this->css[$media]))
                $this->css[$media][] = $url;
        }   // end function addCSS()

        /**
         *
         * @access public
         * @return
         **/
        public function addMeta($meta)
        {
            $this->meta[] = $meta;
        }   // end function addMeta()

        /**
         *
         * @access public
         * @return
         **/
        public function addJS($file,$pos='header',$after=null)
        {
            $index = null;
            if(!strlen($pos))
                $pos = 'footer';
            if(!isset($this->js[$pos]))
                $this->js[$pos] = array();
            if(!in_array($file,$this->js[$pos]))
            {
                if($after)
                {
                    $index = array_search($after,$this->js[$pos]);
#echo "INDEX: $index<br />";
                    if($index) {
                        array_splice($this->js[$pos],$index,0,$file);
                        return;
                    }
                }
                $this->js[$pos][] = $file;
            }
        }   // end function addJS()

        /**
         *
         * @access public
         * @return
         **/
        public function enableJQuery()
        {
            $this->jq = true;
        }   // end function enableJQuery()


        /**
         *
         * @access public
         * @return
         **/
        public function enableJQueryUI()
        {
            $this->ui = true;
        }   // end function enableJQueryUI()

        /**
         *
         * @access public
         * @return
         **/
        public function getCSS($media=NULL)
        {
            if($media)
            {
                return (
                    isset($this->css[$media])
                    ? $this->css[$media]
                    : array()
                );
            }
            return $this->css;
        }   // end function getCSS()
        
        /**
         * returns the items of array $css as HTML link markups
         *
         * @access public
         * @return HTML
         **/
        public function renderCSS()
        {
            $output = array();
            if(count($this->css))
            {
                foreach(array_keys($this->css) as $media)
                {
                    $files = $this->css[$media];
                    $files_with_conditions = array();
                    for($i=count($files)-1;$i>=0;$i--)
                    {
                        $file = $files[$i];
                        $files[$i] = preg_replace('~^/~','',$file); // remove leading /
                        if(isset($this->cond[$file])) {
                            $files_with_conditions[$this->cond[$file]][] = $file;
                            unset($files[$i]);
                        }
                    }
                    $line = str_replace(
                        array('%%condition_open%%','%%file%%','%%media%%','%%condition_close%%'),
                        array('',Assets::serve('css',$files),$media,''),
                        self::$css_tpl
                    );
                    if(isset($this->cond[$file]))
                    {
                        $line = '<!--[if '.$this->cond[$file].']>'."\n"
                              . $line
                              . '<![endif]-->'."\n"
                              ;
                    }
                    $output[] = $line;

                    foreach($files_with_conditions as $cond => $files)
                    {
                        $line = str_replace(
                            array('%%condition_open%%','%%file%%','%%media%%','%%condition_close%%'),
                            array(
                                '<!--[if '.$cond.']>',
                                Assets::serve('css',$files),
                                $media,
                                '<![endif]-->'
                            ),
                            self::$css_tpl
                        );
                        $output[] = $line;
                    }
                }
            }
            return implode("\n",$output);
        }   // end function renderCSS()

        /**
         *
         * @access public
         * @return
         **/
        public function renderJS($pos='header')
        {
            $output = array();
            if($this->ui)
                array_unshift($this->js['header'],'modules/lib_javascript/jquery-ui/ui/jquery-ui.min.js');
            if($this->ui || $this->jq)
                array_unshift($this->js['header'],'modules/lib_javascript/jquery-core/jquery-core.min.js');

            if($pos=='header')
            {
                // add static js
                $header_js = array('var CAT_URL = "'.CAT_SITE_URL.'";');
                if(Backend::isBackend())
                {
                    array_push(
                        $header_js,
	                    'var CAT_ADMIN_URL = "'.CAT_ADMIN_URL. '";'
                    );
                }
                $output[] = str_replace(
                    array('%%condition_open%%',' src="%%file%%"','%%code%%','%%condition_close%%'),
                    array('','',implode("\n",$header_js),''),
                    self::$js_tpl
                );
            }

            if(count($this->js) && isset($this->js[$pos]) && count($this->js[$pos]))
            {
                $files = $this->js[$pos];
                for($i=count($files)-1;$i>=0;$i--)
                {
                    $file = $files[$i];
                    $files[$i] = preg_replace('~^/~','',$file); // remove leading /
                }
                $line = str_replace(
                    array('%%condition_open%%','%%file%%','%%code%%','%%condition_close%%'),
                    array(
                        ( isset($this->cond[$file]) ? '<!--[if '.$this->cond[$file].']>' : '' ),
                        Assets::serve('js',$files),
                        '',
                        ( isset($this->cond[$file]) ? '<![endif]-->' : '' ),
                    ),
                    self::$js_tpl
                );
                $output[] = $line;
            }

            if(count($this->code) && isset($this->code[$pos]) && count($this->code[$pos]))
            {
                $output[] = "<script type=\"text/javascript\">\n"
                          . implode("\n",$this->code[$pos])
                          . "</script>\n";
            }

            return implode("\n",$output);
        }   // end function renderJS()

        /**
         *
         * @access public
         * @return
         **/
        public function renderMeta()
        {
            $output = array();
            $title  = null;

            if(count($this->meta))
            {
                foreach($this->meta as $el)
                {
                    if(!is_array($el) || !count($el)) continue;
                    $str = '<meta ';
                    foreach($el as $key => $val)
                        $str .= $key.'="'.$val.'" ';
                    $str .= '/>';
                    $output[] = $str;
                }
            }
            $output = array_unique($output);

            // Frontend only: get page properties
            if(is_numeric($this->id))
            {
                $properties = HPage::properties($this->id);

                // droplets may override page title and description and/or
                // add meta tags

                // check page title
                if(isset($droplets_config['page_title']))
                    $title = $droplets_config['page_title'];
                elseif(null!=($t=HPage::getTitle()))
                    $title = $t;
                elseif(defined('WEBSITE_TITLE'))
                    $title = WEBSITE_TITLE . (isset($properties['page_title']) ? ' - ' . $properties['page_title'] : '' );
                elseif(isset($properties['page_title']))
                    $title = $properties['page_title'];
                else
                    $title = '-';

                // check description
                if(isset($droplets_config['description']))
                    $description = $droplets_config['description'];
                elseif(isset($properties['description']) && $properties['description'] != '' )
                    $description = $properties['description'];
                else
                    $description = Registry::get('WEBSITE_DESCRIPTION');

                // check other meta tags set by droplets
                if(isset($droplets_config['meta']))
                    $output[] = $droplets_config['meta'];

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: SEO
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            }
            else {
                $description = Registry::get('WEBSITE_DESCRIPTION');
                if(null!=($t=HPage::getTitle()))
                    $title = $t;
            }

            if($title)
                $output[] = '<title>' . $title . '</title>';
            if ($description!='')
                $output[] = '<meta name="description" content="' . $description . '" />';
            return implode("\n",$output);
        }   // end function renderMeta()
    }
}