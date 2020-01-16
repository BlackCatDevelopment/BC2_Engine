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
use \CAT\Sections as Sections;

use \Ds\Set as Set;
use \Ds\Map as Map;

if (!class_exists('\CAT\Helper\Assets'))
{
    class Assets extends Base
    {
        // set debug level
        protected static $loglevel      = \Monolog\Logger::EMERGENCY;
        #protected static $loglevel     = \Monolog\Logger::DEBUG;

        public    static $sourcemaps   = array();
        public    static $defaultmedia = 'screen,projection';

        protected static $isBackend    = false;
        protected static $backendArea  = null;

        protected static $autoload     = array(
            'jq' => false,
            'ui' => false,
        );
        protected static $seen         = array(
            'jq' => false,
            'ui' => false,
        );
        // map type to content-type
        protected static $mime_map     = array(
            'css'   => 'text/css',
            'js'    => 'text/javascript',
            #'png'   => 'image/png',
            #'jpg'   => 'image/jpeg',
            'svg'   => 'image/svg+xml',
            'map'   => 'text/plain',
            'html'  => 'text/html',
        );

        // default CSP Rules
        protected static $csp_rules = array(
            'default-src' => array( '\'self\'' ),
            'style-src'   => array( '\'self\'', '\'unsafe-inline\'' ),
            'script-src'  => array( '\'self\'', '\'unsafe-inline\'', '\'unsafe-eval\'' ),
            'img-src'     => array( '\'self\'' ),
            'object-src'  => array( '\'none\'' ),
            'frame-src'   => array( '\'self\'' ),
        );

        // collections to fill
        protected static $JSSet        = array('header'=>null,'footer'=>null);
        protected static $JSCond       = array('header'=>null,'footer'=>null);
        protected static $code         = array('header'=>null,'footer'=>null);
        protected static $CSSMap;
        protected static $CSSCond;
        protected static $Meta;

        protected static $factory;
        protected static $fm;

        /**
         * output template for external stylesheets
         **/
        private static $css_tpl  = '%%condition_open%%<link rel="stylesheet" href="%%file%%" media="%%media%%" />%%condition_close%%';
        /**
         * output template for external javascripts
         **/
        private static $js_tpl   = '%%condition_open%%<script type="text/javascript" src="%%file%%">%%code%%</script>%%condition_close%%';
        /**
         * output template for meta tags
         **/
        private static $meta_tpl = '<meta %%content%% />';

        /**
         *
         * @access public
         * @return
         **/
        public static function addCode(string $code, string $pos)
        {
            if(empty($code)) {
                return;
            }
            self::init();
            self::$code[$pos]->add($code);
        }   // end function addCode()

        /**
         *
         * @access public
         * @return
         **/
        public static function addCSPRule(string $rule_name, string $value)
        {
# !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
# TODO: Auf gueltige Eintraege pruefen
# !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if(!array_key_exists($rule_name, self::$csp_rules)) {
                self::$csp_rules[$rule_name] = array();
            }
            self::$csp_rules[$rule_name][] = $value;
        }   // end function addCSPRule()

        /**
         *
         * @access public
         * @return
         **/
        public static function addCSS(string $file, string $media='')
        {
            if(empty($file)) {
                return;
            }
            self::init();
            self::log()->addDebug(sprintf('adding [%s] to $CSSMap, media [%s]',$file,$media));
            self::$CSSMap->put($file,($media!=''?$media:self::$defaultmedia));
        }   // end function addCSS()

        /**
         *
         * @access public
         * @return
         **/
        public static function addJQuery()
        {
            self::$autoload['jq'] = true;
        }   // end function addJQuery()
        

        /**
         *
         * @access public
         * @return
         **/
        public static function addJS(string $file, string $pos)
        {
            if(empty($file)) {
                return;
            }
            self::init();
            self::log()->addDebug(sprintf('adding [%s] to $JSSet, position [%s]',$file,$pos));
            self::$JSSet[$pos]->add($file);
        }   // end function addJS()

        /**
         *
         * @access public
         * @return
         **/
        public function addMeta($meta)
        {
            if(empty($meta)) {
                return;
            }
            self::$Meta->add($meta);
        }   // end function addMeta()

        /**
         *
         * @access public
         * @return
         **/
        public static function compile(string $type, array $files)
        {
            self::log()->addDebug('>>>>> compile() <<<<<');

            if(!count($files)>0) { // nothing to do
                return;
            }

            // make sure the array is indexed correctly
            $files = array_values($files);

            // fix path and remove invalid
            for($i=count($files)-1; $i>=0; $i--) {
                if(empty($files[$i])) {
                    unset($files[$i]);
                    continue;
                }
                // external
                if(substr_compare($files[$i],'http',0,4)==0) {
                    self::log()->addDebug('External URL');
                    #unset($files[$i]);
                    continue;
                }
                $path = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.$files[$i]);
                if(!file_exists($path)) {
                    self::log()->addDebug(sprintf(
                        'file not found: [%s]', $path
                    ));
                    unset($files[$i]);
                } else {
                    $files[$i] = $path;
                }
            }

            // create asset factory and pass engine path as basedir
            $factory    = self::getFactory();
            $filters    = array();
            $filterlist = array();

            if ($type=='css') {
                $filterlist = array('CssImportFilter','CATCssRewriteFilter','CATSourcemapFilter','MinifyCssCompressorFilter','CATDebugAddPathInfoFilter','CssCacheBustingFilter');
            } elseif ($type=='js') {
                $filterlist = array('CATSourcemapFilter','JSMinFilter');
            }
            foreach ($filterlist as $filter) {
                $filterclass = '\Assetic\Filter\\'.$filter;
                self::$fm->set($filter, new $filterclass());
                $filters[] = $filter;
            }

            self::log()->addDebug(sprintf(
                'type [%s], number of files [%d]',
                $type, count($files)
            ));
            self::log()->addDebug('files: '.print_r($files, 1));

            // add assets
            $assets = $factory->createAsset(
                $files,
                $filters
            );

            if(isset(self::$seen[$assets->getTargetPath()])) {
                self::log()->addDebug(sprintf(
                    '>>> already compiled: [%s]',
                    $assets->getTargetPath()
                ));
                return;
            }

            $am = new \Assetic\AssetManager();
            $am->set('assets', $assets);

            // create the asset manager instance
            try {
                // create the writer to save the combined file
                $writer = new \Assetic\AssetWriter(Directory::sanitizePath(CAT_PATH));
                $writer->writeManagerAssets($am);
                #self::$seen[$assets->getTargetPath()] = 1;
            } catch ( \Exception $e ) {
                self::log()->addDebug(sprintf(
                    '>>>>> Exception in compile: %s',
                    $e->getMessage()
                ));
            }

            self::log()->addDebug('>>>>> compile() DONE <<<<<');
            return CAT_SITE_URL.'/'.$assets->getTargetPath();
        }   // end function compile()

        /**
         * collects all the assets (JS, CSS, jQuery Core & UI) for the given
         * page; $id may be a pageID or a backend area like 'backend_media'
         *
         * @access public
         * @param  string  $pos - 'header' / 'footer'
         * @param  string  $id  - pageID or 'backend_<area>'
         * @param  boolean $ignore_inc - wether to load inc files or not
         * @return AssetFactory object
         **/
        public static function getAssets($pos, $id=null, $ignore_inc=false, $as_array=false)
        {
            self::log()->addDebug('getAssets()');

            self::init();

            list($id, $for) = self::analyzeID($id);

            self::log()->addDebug(sprintf(
                '[%s] pos [%s] id [%s] for [%s] ignore includes [%s]',
                __FUNCTION__, $pos, $id, $for, $ignore_inc
            ));

            // paths to scan; $paths and $incpaths will be \Ds\Set objects
            list($paths, $incpaths, $filter) = self::getPaths($id, $pos);

            // figure out page_id
            $page_id = false;
            if (is_numeric($id) && $id>0) {
                $page_id = $id;
            }
            if(self::$isBackend && self::$backendArea=='page') {
                $page_id = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            }

            // if it's a frontend page, add scan paths for modules
            if (is_numeric($page_id) && $page_id>0) {
                $sections = Sections::getSections($page_id);
                if (is_array($sections) && count($sections)>0) {
                    foreach ($sections as $block => $items) {
                        foreach ($items as $item) {
                            $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$item['module'].'/css'));
                            $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$item['module'].'/js'));
                            $incpaths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$item['module']));
                            if (strtolower($item['module'])=='wysiwyg') {
                                $wysiwyg = true;
                            }
                            if ($item['variant']!='') {
                                $variant_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$item['module'].'/'.CAT_TEMPLATES_FOLDER.'/'.$item['variant']);
                                if(is_dir($variant_path.'/css')) {
                                    $paths->add($variant_path.'/css');
                                }
                                if(is_dir($variant_path.'/js')) {
                                    $paths->add($variant_path.'/js');
                                }
                            }
                        }
                    }
                }
            }

            // add area specific assets
            if (self::$isBackend) {
                self::log()->addDebug(sprintf(
                    '>>> looking for area specific js/css, current area: [%s]',
                    self::$backendArea
                ));
                if(self::$backendArea=='login') {
                    $filter = self::$backendArea;
                }
                else {
                    $filter .= '|'.self::$backendArea;
                    if ($pos=='footer') {
                        $filter .= '_body';
                    }
                }
            } else {
                if ($pos=='footer') {
                    $filter .= '_body';
                }
            }
            self::log()->addDebug(sprintf('>>> filter: [%s]', $filter));

            if (!$ignore_inc) {
                self::analyzeIncFiles($pos,$for,$incpaths->toArray());
            }

            self::getDefaultFiles($paths->toArray(),$pos,$filter);

        }   // end function getAssets()

        /**
         *
         * @access public
         * @return
         **/
        public static function getCode(string $pos)
        {
            $output = self::$code[$pos]->toArray();
            if(is_array($output) && count($output)>0) {
                return implode("\n",$output);
            }
        }   // end function getCode()

        /**
         *
         * @access public
         * @return
         **/
        public static function renderAssets($pos, $id=null, $ignore_inc=false, $print=true)
        {
            list($id, $for) = self::analyzeID($id);
            self::log()->addDebug(sprintf(
                'renderAssets() pos [%s] ID [%s] ignore inc [%s] print [%s] (by analyzeID for [%s])',
                $pos, $id, $ignore_inc, $print, $for
            ));
            // get assets for current ID
            $maps = self::getAssets($pos, $id, $ignore_inc);

            // render
            switch ($pos) {
                case 'header':
                    $output = self::renderMeta()
                            . self::renderCSS()
                            . self::renderJS($pos);
                    break;
                case 'footer':
                    $output = self::renderJS($pos)
                            . self::getCode($pos);
                    break;
            }

            if (is_array(self::$sourcemaps) && count(self::$sourcemaps)>0) {
                foreach (self::$sourcemaps as $file) {
                    $file = CAT_ENGINE_PATH.'/'.$file;
                    if (file_exists($file)) {
                        copy($file, CAT_PATH.'/assets/'.basename($file));
                    }
                }
            }

            if($print) {
                echo $output;
            } else {
                return $output;
            }
        }   // end function renderAssets()

        /**
         * returns the items of array $css as HTML link markups
         *
         * @access public
         * @return HTML
         **/
        public static function renderCSS()
        {
            self::log()->addDebug('>>>>> renderCSS() <<<<<');

            $output = array();

            if(self::$CSSMap->count()>0) {
                // number of media types to support
                try {
                    $media   = array_unique(self::$CSSMap->values()->toArray());
                } catch ( Exception $e ) {
                    $media   = array(self::$defaultmedia);
                }
                $files   = self::$CSSMap->keys()->toArray();

self::log()->addDebug('media: '.implode(' | ',$media));
self::log()->addDebug(print_r($files,1));

                // extract files with conditions
                $files_with_conditions = array();
                for ($i=count($files)-1;$i>=0;$i--) {
                    $file = $files[$i];
                    if (self::$CSSCond->hasKey($file)) {
                        $files_with_conditions[self::$CSSCond->get($file)][] = $file;
                        unset($files[$i]);
                    }
                }

                // it's just that simple...
                foreach($media as $m) {
                    $line  = str_replace(
                        array('%%condition_open%%','%%file%%','%%media%%','%%condition_close%%'),
                        array('',self::compile('css', $files), $m),
                        self::$css_tpl
                    );
                    $line  = str_replace('media="" ','',$line); // should never happen
                    $output[] = $line;

                    foreach ($files_with_conditions as $cond => $files) {
                        $line = str_replace(
                            array('%%condition_open%%','%%file%%','%%media%%','%%condition_close%%'),
                            array(
                                '<!--[if '.$cond.']>'."\n",
                                self::compile('css', $files),
                                $m,
                                "\n".'<![endif]-->'
                            ),
                            self::$css_tpl
                        );
                        $output[] = $line;
                    }
                }
            } else {
                self::log()->addDebug('no CSS files found');
            }

            self::log()->addDebug('>>>>> renderCSS() ENDE <<<<<');

            return implode("\n", $output)."\n";
        }   // end function renderCSS()

        /**
         *
         * @access public
         * @return
         **/
        public static function renderJS(string $pos='header')
        {
            $output = array();

            if ($pos=='header') {
                // add static js
                $header_js = array(
                    'var CAT_URL ="'.CAT_URL.'";',
                    'var CAT_SITE_URL = "'.CAT_SITE_URL.'";'
                );
                if (Backend::isBackend()) {
                    array_push(
                        $header_js,
                        'var CAT_ADMIN_URL = "'.CAT_ADMIN_URL. '";'
                    );
                }
                self::addCode(implode("\n",$header_js),$pos);
            }

            if(self::$JSSet[$pos]->count()>0) {

                $files = self::$JSSet[$pos]->toArray();

                // extract files with conditions
                $files_with_conditions = array();
                for ($i=count($files)-1;$i>=0;$i--) {
                    $file = $files[$i];
                    if (self::$JSCond[$pos]->hasKey($file)) {
                        $files_with_conditions[self::$JSCond[$pos]->get($file)][] = $file;
                        unset($files[$i]);
                    }
                }
                //  make sure jQuery and UI are loaded if needed
                if(self::$autoload['ui'] && !self::$seen['ui']) {
                    array_unshift($files, 'CAT/vendor/components/jqueryui/jquery-ui.min.js');
                    self::$seen['ui'] = true;
                }
                if((self::$autoload['jq'] || self::$autoload['ui']) && !self::$seen['jq']) {
                    array_unshift($files, 'CAT/vendor/components/jquery/jquery.min.js');
                    self::$seen['jq'] = true;
                }

                if(count($files)>0) {
                    $line = str_replace(
                        array('%%condition_open%%','%%file%%','%%code%%','%%condition_close%%'),
                        array(
                            '',
                            Assets::compile('js', $files),
                            '',
                            ''
                        ),
                        self::$js_tpl
                    );
                    $output[] = $line;
                }


                foreach ($files_with_conditions as $cond => $files) {
                    // check if files are external
                    $has_externals = false;
                    foreach($files as $file) {
                        if(substr_compare($file,'http',0,4)==0) {
                            $has_externals = true;
                        }
                    }
                    if(!$has_externals) {
                        $line = str_replace(
                            array('%%condition_open%%','%%file%%','%%code%%','%%condition_close%%'),
                            array(
                                '<!--[if '.$cond.']>'."\n",
                                self::compile('js', $files),
                                '',
                                "\n".'<![endif]-->'
                            ),
                            self::$js_tpl
                        );
                    } else {
// !!!!! TODO !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// Hier können auch wieder lokale Dateien dabei sein
                        $line = '<!--[if '.$cond.']>'."\n";
                        foreach($files as $file) {
                            $line .= '<script type="text/javascript" src="'.$file.'"></script>';
                        }
// !!!!! TODO !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                        $line .= "\n".'<![endif]-->';
                    }
                    $output[] = $line;
                }
            }

            // add inline code
            $code = self::getCode($pos);
            if(strlen($code)) {
                $factory = self::getFactory();
                $asset   = new \Assetic\Asset\StringAsset($code);
                $asset->setTargetPath(substr(sha1($code), 0, 7).'.js');
                $writer  = new \Assetic\AssetWriter(Directory::sanitizePath(CAT_PATH.'/assets'));
                $writer->writeAsset($asset);
                $line = str_replace(
                    array('%%condition_open%%','%%file%%','%%code%%','%%condition_close%%'),
                    array(
                        ''."\n",
                        CAT_SITE_URL.'/assets/'.$asset->getTargetPath(),
                        '',
                        "\n".''
                    ),
                    self::$js_tpl
                );
                $output[] = $line;
            }

            return implode("\n",$output)."\n";
        }   // end function renderJS()

        /**
         *
         * @access public
         * @return
         **/
        public static function renderMeta()
        {
            $output = array();
            $title  = null;
            $host   = $_SERVER['SERVER_NAME'];

            // CSP Rules
            /*
            $output[] = '<meta http-equiv="Content-Security-Policy" content="'
                      . 'default-src \'self\' ' . $host . '; '
                      . 'style-src \'self\' \'unsafe-inline\' ' . $host . '; '
                      . 'script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' ' . $host . '; '
                      . 'img-src \'self\' data: ; '
                      . 'object-src \'none\' " />';
            */

            $rule = '<meta http-equiv="Content-Security-Policy" content="';
            foreach(self::$csp_rules as $rulename => $values) {
                $rule .= $rulename . ' ' . implode(' ',$values) . '; ';
            }
            $rule .= ' " />';
            $output[] = $rule;

            $meta = self::$Meta->toArray();

            if (count($meta)) {
                foreach ($meta as $el) {
                    if (!is_array($el) || !count($el)) {
                        continue;
                    }
                    $str = '<meta ';
                    foreach ($el as $key => $val) {
                        $str .= $key.'="'.$val.'" ';
                    }
                    $str .= '/>';
                    $output[] = $str;
                }
            }

            // frontend only
            if(!Backend::isBackend()) {
                $pageID = \CAT\Page::getID();
                $properties = Page::properties($pageID);

                // droplets may override page title and description and/or
                // add meta tags
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Droplets are not yet implemented!
#$droplets_config = \CAT\Helper\Droplet::getDropletsForHeader($page_id);
# temporarily:
$droplets_config = array();
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

                // check page title
                if (isset($droplets_config['page_title'])) {
                    $title = $droplets_config['page_title'];
                } elseif (null!=($t=Page::getTitle())) {
                    $title = $t;
                } elseif (defined('WEBSITE_TITLE')) {
                    $title = WEBSITE_TITLE . (isset($properties['page_title']) ? ' - ' . $properties['page_title'] : '');
                } elseif (isset($properties['page_title'])) {
                    $title = $properties['page_title'];
                } else {
                    $title = '-';
                }

                // check description
                if (isset($droplets_config['description'])) {
                    $description = $droplets_config['description'];
                } elseif (isset($properties['description']) && $properties['description'] != '') {
                    $description = $properties['description'];
                } else {
                    $description = Registry::get('WEBSITE_DESCRIPTION');
                }

                // check other meta tags set by droplets
                if (isset($droplets_config['meta'])) {
                    $output[] = $droplets_config['meta'];
                }
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: SEO
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            } else {
                $description = Registry::get('WEBSITE_DESCRIPTION');
                if (null!=($t=Page::getTitle())) {
                    $title = $t;
                }
            }

            if ($title) {
                $output[] = '<title>' . $title . '</title>';
            }
            if ($description!='') {
                $output[] = '<meta name="description" content="' . $description . '" />';
            }

            return implode("\n", $output);
        }   // end function renderMeta()

        /**
         *
         * @access public
         * @return
         **/
        public static function serve(string $type, string $file, bool $return_url = false)
        {
            self::log()->addDebug(sprintf(
                'serve() type [%s], file [%s]',
                $type, $file
            ));

            if(!file_exists(CAT_ENGINE_PATH.'/'.$file) && !file_exists(CAT_PATH.'/'.$file)) {
                self::log()->addError(sprintf(
                    'no such file: [%s]',$file
                ));
                return;
            }

            // images
            if(\CAT\Helper\Media::isImage($file)) {
                self::log()->addDebug(sprintf('serving image (isImage()) [%s]',$file));
                copy(CAT_ENGINE_PATH.'/'.$file, CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
                if(!$return_url) {
	                // the content-type defaults to 'application/octet-stream' if the suffix is not present in the mime table
	                header('Content-Type: '.Media::getContentType(pathinfo($file, PATHINFO_EXTENSION)));
	                readfile(CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
	                return;
                } else {
                    return \CAT\Helper\Validate::path2uri(CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
                }
            }

            if ($type=='images'||$type=='svg') {
                self::log()->addDebug(sprintf('serving image (by type) [%s]',$file));
                if (file_exists(CAT_ENGINE_PATH.'/'.$file)) {
                    self::log()->addDebug(sprintf(
                        'copying file [%s] to path [%s]',
                        CAT_ENGINE_PATH.'/'.$file,
                        CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME)
                    ));
                    copy(CAT_ENGINE_PATH.'/'.$file, CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
                    if (isset(self::$mime_map[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
                        header('Content-Type: '.self::$mime_map[strtolower(pathinfo($file, PATHINFO_EXTENSION))]);
                        readfile(CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
                        return;
                    }
                    echo CAT_SITE_URL.'/assets/'.pathinfo($file, PATHINFO_BASENAME);
                }
                return;
            }

            // not im $mime_map == not allowed
            if(!isset(self::$mime_map[$type])) {
                self::log()->addError(sprintf(
                    'not allowed: [%s]',$file
                ));
                return;
            }

            if(file_exists(CAT_ENGINE_PATH.'/'.$file)) {
                $file_to_serve = \CAT\Helper\Directory::sanitizePath(CAT_ENGINE_PATH.'/'.$file);
            } else {
                $file_to_serve = \CAT\Helper\Directory::sanitizePath(CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
            }
            self::log()->addDebug(sprintf(
                'serving [%s] as [%s]',
                $file_to_serve,
                self::$mime_map[$type]
            ));

            if(!$return_url) {
                header('Content-Type: '.self::$mime_map[$type]);
                readfile($file_to_serve);
            } else {
                return \CAT\Helper\Validate::path2uri($file_to_serve);
            }

            return;
        }   // end function serve()


        /**
         *
         * @access public
         * @return
         **/
        protected static function analyzeID($id)
        {
            self::log()->addDebug(sprintf(
                'analyzeID [%s]',
                $id
            ));

            self::init();

            $for    = 'frontend';
            $filter = 'frontend';

            if (empty($id)) {
                self::log()->addDebug('empty id');
                if (Backend::isBackend()) {
                    $id = 'backend_'.self::$backendArea;
                    $filter = $for = 'backend';
                } else {
                    $id = \CAT\Page::getID();
                }
                self::log()->addDebug(sprintf('id now [%s] filter [%s] for [%s]',$id,$filter,$for));
                return array($id,$for);
            }

            if (Backend::isBackend()) {
                $for    = 'backend';
            }

            #$for = (substr($id, 0, 7)=='backend' ? 'backend' : 'frontend');
            self::log()->addDebug(sprintf('id now [%s] filter [%s] for [%s]',$id,$filter,$for));

            return array($id,$for);
        }   // end function analyzeID()

        /**
         * analyze headers/footers.inc
         *
         * @access protected
         * @return
         **/
        protected static function analyzeIncFiles(string $pos, string $for, array $incpaths)
        {
            $filename = $pos.'s.inc';

            // temp. collection for inc files to read
            $coll = new Set();

            // make sure collections are initialized
            self::init();

            foreach ($incpaths as $path) {
                $temp = Directory::findFiles(
                    $path,
                    array(
                        'filename'   => $filename,
                        'extension'  => 'php',
                        'recurse'    => true,
                        'max_depth'  => 3
                    )
                );
                if (is_array($temp) && count($temp)>0) {
                    foreach($temp as $item) {
                        $coll->add($item);
                    }
                }
            }

            // if we have any files to read...
            if ($coll->count()>0) {
                self::log()->addDebug(sprintf(
                    'Found [%d] include files for position [%s]',
                    $coll->count(), $pos
                ));
                $incfiles = $coll->toArray();
                foreach ($incfiles as $file) {
                    try {
                        self::log()->addDebug(sprintf(
                            'reading file [%s]',
                            $file
                        ));
                        require $file;
                        $array =& ${'mod_'.$pos.'s'};
                        // CSS
                        if (isset($array[$for]) && array_key_exists('css', $array[$for]) && count($array[$for]['css'])>0) {
                            foreach ($array[$for]['css'] as $item) {
                                $files = ( isset($item['files']) && is_array($item['files']) )
                                       ? $item['files']
                                       : ( isset($item['file']) ? array($item['file']) : '' );
                                foreach($files as $f) {
self::log()->addDebug(sprintf('adding [%s] to $CSSMap',$f));
                                    self::$CSSMap->put($f,(isset($item['media']) ? $item['media'] : self::$defaultmedia));
                                    if(isset($item['condition'])) {
self::log()->addDebug(sprintf('adding condition for [%s] to $CSSCond',$f));
                                        self::$CSSCond->put($f,$item['condition']);
                                    }
                                }
                            }
                        }
                        // JS
                        if (isset($array[$for]) && array_key_exists('js', $array[$for]) && count($array[$for]['js'])>0) {
                            foreach ($array[$for]['js'] as $item) {
                                if (is_array($item)) {
                                    // if it's an array there _must_ be a conditional
                                    if (!isset($item['condition'])) {
                                        continue;
                                    }
                                    foreach ($item['files'] as $f) {
                                        self::$JSSet[$pos]->add($f);
                                        self::$JSCond[$pos]->put($f,$item['condition']);
                                    }
                                } else {
                                    self::$JSSet[$pos]->add($item);
                                }
                            }
                        }
                        // jQuery
                        if (isset($array[$for]) && array_key_exists('jquery', $array[$for])) {
                            if (isset($array[$for]['jquery']['core']) && $array[$for]['jquery']['core']) {
                                self::addJQuery();
                            }
                            if (isset($array[$for]['jquery']['ui']) && $array[$for]['jquery']['ui']) {
                                self::$autoload['ui'] = true;
                            }
                            if (isset($array[$for]['jquery']['plugins']) && $array[$for]['jquery']['plugins']) {
                                foreach ($array[$for]['jquery']['plugins'] as $item) {
                                    if (false!==($file=self::findJQueryPlugin($item))) {
                                        self::$JSSet[$pos]->add($file);
                                    }
                                }
                            }
                        }
                        // META
                        if (isset($array[$for]) && array_key_exists('meta', $array[$for])) {
                            foreach ($array[$for]['meta'] as $item) {
                                self::$Meta->add($item);
                            }
                        }
                        // Javascript code
                        if (isset($array[$for]) && array_key_exists('javascript', $array[$for])) {
                            foreach ($array[$for]['javascript'] as $item) {
                                self::$code[$pos]->add($item);
                            }
                        }
                    } catch (\Exception $e) {
                        self::log()->addError(sprintf(
                            '>>> exception: [%s]',
                            $e->getMessage()
                        ));
                    }
                }
            }
        }   // end function analyzeIncFiles()

        /**
         * evaluate correct item path; this resolves
         *    ./plugins/<name>.min.js
         *    ./plugins/<name>.js
         *    ./plugins/<name>/<name>.min.js
         *    ./plugins/<name>/<name>.js
         *
         * @access private
         * @param  string  $item
         * @return mixed
         **/
        protected static function findJQueryPlugin($item)
        {
            $plugin_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_JS_PATH.'/plugins/'.$item);

            // check suffix
            if (pathinfo($item, PATHINFO_EXTENSION) != 'js') {
                $item .= '.js';
            }

            // prefer minimized
            $minitem = pathinfo($item, PATHINFO_FILENAME).'.min.js';
            $file    = Directory::sanitizePath($plugin_path.'/'.$minitem);

            // just there? --> minimized
            if (!file_exists($file)) {
                // without .min.
                $file = Directory::sanitizePath($plugin_path.'/'.$item);
                if (!file_exists($file)) {
                    $dir = pathinfo($item, PATHINFO_FILENAME);
                    // prefer minimized
                    $minitem = pathinfo($item, PATHINFO_FILENAME).'.min.js';
                    $file    = Directory::sanitizePath($plugin_path.'/'.$dir.'/'.$minitem);
                    if (!file_exists($file)) {
                        $file = Directory::sanitizePath($plugin_path.'/'.$dir.'/'.$item);
                        if (!file_exists($file)) {
                            // give up
                            return false;
                        }
                    }
                }
            }

            return str_ireplace(Directory::sanitizePath(CAT_ENGINE_PATH), '', $file);
        }   // end function findJQueryPlugin()

        /**
         * find default files (frontend[_body].css/js, ...)
         *
         * @access protected
         * @return
         **/
        protected static function getDefaultFiles(array $paths, string $pos, string $filter)
        {
            self::log()->addDebug('>>>>> getDefaultFiles() <<<<<');
            self::log()->addDebug(sprintf(
                'position [%s] filter [%s]', $pos, $filter
            ));
            $ext      = array('css','js');
            foreach ($paths as $path) {
                $files = Directory::findFiles(
                    $path,
                    array(
                        'remove_prefix' => Directory::sanitizePath(CAT_ENGINE_PATH).'/',
                        'extensions'    => $ext,
                        'recurse'       => true,
                        'max_depth'     => 1,
                        'filter'        => "($filter)"
                    )
                );
                if(count($files)>0) {
                    foreach($files as $file) {
                        if(pathinfo($file,PATHINFO_EXTENSION)=='css') {
                            self::addCSS($file,'');
                        } else {
                            self::addJS($file,$pos);
                        }
                    }
                }
            }
            self::log()->addDebug('>>>>> getDefaultFiles() ENDE <<<<<');
        }   // end function getDefaultFiles()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function getFactory()
        {
            if(!is_object(self::$factory)) {
                self::$factory = new \Assetic\Factory\AssetFactory(Directory::sanitizePath(CAT_ENGINE_PATH));
                self::$fm      = new \Assetic\FilterManager();
                self::$factory->setFilterManager(self::$fm);
                self::$factory->setDefaultOutput('assets/*');
                self::$factory->setProxy(Registry::get('proxy'), Registry::get('proxy_port'));
            }
            return self::$factory;
        }   // end function getFactory()
        
        /**
         *
         * @access public
         * @return
         **/
        protected static function getPaths($id, $pos)
        {
            self::log()->addDebug('getPaths()');

            list($id, $for) = self::analyzeID($id); // sanitize ID

            $paths    = new \Ds\Set();
            $incpaths = new \Ds\Set();
            $filter   = null;

            switch ($for) {
            // -----------------------------------------------------------------
            // ----- FRONTEND --------------------------------------------------
            // -----------------------------------------------------------------
                case 'frontend':
                    $filter = 'frontend';
                    $page_id = \CAT\Page::getID();
                    $tplpath = \CAT\Helper\Template::getPath($page_id,false);
                    // CSS
                    $paths->add(Directory::sanitizePath($tplpath.'/css/'.Registry::get('default_template_variant')));
                    $paths->add(Directory::sanitizePath($tplpath.'/css'));
                    // JS
                    $paths->add(Directory::sanitizePath($tplpath.'/js/'.Registry::get('default_template_variant')));
                    $paths->add(Directory::sanitizePath($tplpath.'/js'));
                    // *.inc.php - fallback sorting; search will stop on first occurance
                    $incpaths->add(Directory::sanitizePath($tplpath.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_template_variant')));
                    $incpaths->add(Directory::sanitizePath($tplpath.'/'.CAT_TEMPLATES_FOLDER.'/default'));
                    $incpaths->add(Directory::sanitizePath($tplpath.'/templates'));
                    $incpaths->add(Directory::sanitizePath($tplpath));
                    break;
            // -----------------------------------------------------------------
            // ----- BACKEND ---------------------------------------------------
            // -----------------------------------------------------------------
                case 'backend':
                    $filter = 'backend|theme';
                    if ($pos=='footer') {
                        $filter = 'backend_body|theme_body';
                    }
                    // CSS
                    $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/css/'.Registry::get('default_theme_variant')));
                    $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/css/default'));
                    $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/css'));
                    // JS
                    $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme_variant')));
                    $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/js/'.Registry::get('default_theme_variant')));
                    $paths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/js'));
                    // admin tool
                    if (self::router()->match('~\/tool\/~i')) {
                        $tool = \CAT\Backend\Admintools::getTool();
                        foreach (
                            array_values(array(
                                Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$tool.'/css'),
                                Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_MODULES_FOLDER.'/'.$tool.'/js')
                            )) as $p
                        ) {
                            if (is_dir($p)) {
                                $paths->add($p);
                                $incpaths->add($p);
                            }
                        }
                    }
                    // fallback sorting; search will stop on first occurance
                    $incpaths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme_variant')));
                    $incpaths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme').'/templates'));
                    $incpaths->add(Directory::sanitizePath(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER.'/'.Registry::get('default_theme')));
                    break;
            }

            return array($paths,$incpaths,$filter);
        }   // end function getPaths()

        /**
         * create collectors for JS and CSS
         *
         * @access protected
         * @return
         **/
        protected static function init()
        {
            if(!defined('CAT_HELPER_ASSETS_INIT')) {
            // header and/or footer
            foreach(array_values(array('header','footer')) as $pos) {
                if(!self::$JSSet[$pos] instanceof \Ds\Set) {
                    self::$JSSet[$pos]      = new Set(); // Javascript files
                }
                if(!self::$JSCond[$pos] instanceof \Ds\Map) {
                    self::$JSCond[$pos]     = new Map(); // JS conditionals
                }
                if(!self::$code[$pos] instanceof \Ds\Set) {
                    self::$code[$pos]       = new Set(); // Javascript code
                }
            }

            // header only
            if(!self::$CSSMap instanceof \Ds\Map) {
                self::$CSSMap  = new Map(); // CSS files
            }
            if(!self::$CSSCond instanceof \Ds\Map) {
                self::$CSSCond = new Map(); // CSS conditionals
            }
            if(!self::$Meta instanceof \Ds\Set) {
                self::$Meta    = new Set(); // META
                }

                // Backend
                if(Backend::isBackend()) {
                    self::$isBackend = true;
                    self::$backendArea = Backend::getArea();
                }

                define('CAT_HELPER_ASSETS_INIT',1);
            }
        }   // end function init()
        
    }
}