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
        #protected static $loglevel      = \Monolog\Logger::EMERGENCY;
        protected static $loglevel     = \Monolog\Logger::DEBUG;

        public    static $sourcemaps   = array();
        public    static $defaultmedia = 'screen,projection';

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
            'png'   => 'image/png',
            'svg'   => 'image/svg+xml',
            'map'   => 'text/plain',
        );

        // collections to fill
        protected static $JSSet        = array('header'=>null,'footer'=>null);
        protected static $JSCond       = array('header'=>null,'footer'=>null);
        protected static $code         = array('header'=>null,'footer'=>null);
        protected static $CSSMap;
        protected static $CSSCond;
        protected static $Meta;

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
         * output template for Javascript code
         **/
        private static $code_tpl = "<script>\n%%code%%\n</script>\n";

        /**
         *
         * @access public
         * @return
         **/
        public static function addCode(string $code, string $pos)
        {
            self::init();
            self::$code[$pos]->add($code);
        }   // end function addCode()

        /**
         *
         * @access public
         * @return
         **/
        public static function addCSS(string $file, string $media='')
        {
            self::init();
            self::$CSSMap->put($file,($media!=''?$media:self::$defaultmedia));
        }   // end function addCSS()

        /**
         *
         * @access public
         * @return
         **/
        public static function addJS(string $file, string $pos)
        {
            self::init();
            self::$JSSet[$pos]->add($file);
        }   // end function addJS()

        /**
         *
         * @access public
         * @return
         **/
        public function addMeta($meta)
        {
            self::$Meta->add($meta);
        }   // end function addMeta()

        /**
         *
         * @access public
         * @return
         **/
        public static function compile(string $type, array $files)
        {
            // fix path
            foreach ($files as $i => $f) {
                $files[$i] = Directory::sanitizePath(CAT_ENGINE_PATH.'/'.$f);
            }

            // create asset factory and pass engine path as basedir
            $factory = new \Assetic\Factory\AssetFactory(Directory::sanitizePath(CAT_ENGINE_PATH));
            $fm      = new \Assetic\FilterManager();

            $factory->setFilterManager($fm);
            $factory->setDefaultOutput('assets/*');
            $factory->setProxy(Registry::get('PROXY'), Registry::get('PROXY_PORT'));

            $filters    = array();
            $filterlist = array();

            if ($type=='css') {
                $filterlist = array('CssImportFilter','CATCssRewriteFilter','CATSourcemapFilter','MinifyCssCompressorFilter','CssCacheBustingFilter');
            } elseif ($type=='js') {
                $filterlist = array('CATSourcemapFilter','JSMinFilter');
            }
            foreach ($filterlist as $filter) {
                $filterclass = '\Assetic\Filter\\'.$filter;
                $fm->set($filter, new $filterclass());
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

            list($id, $for) = self::analyzeID($id);

            self::log()->addDebug(sprintf(
                '[%s] pos [%s] id [%s] for [%s] ignore includes [%s]',
                __FUNCTION__, $pos, $id, $for, $ignore_inc
            ));

            // paths to scan
            list($paths, $incpaths, $filter) = self::getPaths($id, $pos);

            $page_id = false;
            if (is_numeric($id) && $id>0) {
                $page_id = $id;
            }
            if (Backend::isBackend() && Backend::getArea()=='page') {
                $page_id = self::getItemID('page_id', '\CAT\Helper\Page::exists');
            }

            // if it's a frontend page, add scan paths for modules
            if (is_numeric($page_id) && $page_id>0) {
                $sections = Sections::getSections($page_id);
                if (is_array($sections) && count($sections)>0) {
                    foreach ($sections as $block => $items) {
                        foreach ($items as $item) {
                            array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module'].'/css'));
                            array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module'].'/js'));
                            array_push($incpaths, Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module']));
                            if (strtolower($item['module'])=='wysiwyg') {
                                $wysiwyg = true;
                            }
                            if ($item['variant']!='') {
                                $variant_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module'].'/templates/'.$item['variant'].'/css');
                                if (is_dir($variant_path)) {
                                    array_push($paths, $variant_path);
                                }
                            }
                        }
                    }
                    if (isset($wysiwyg) && $wysiwyg) {
                        self::$JSSet[$pos]->add(\CAT\Addon\WYSIWYG::getJS());
                    }
                }
            }

            // add area specific assets
            if (Backend::isBackend()) {
                $area = Backend::getArea();
                self::log()->addDebug(sprintf(
                    '>>> looking for area specific js/css, current area: [%s]',
                    $area
                ));
                if($area=='login') {
                    $filter = $area;
                }
                else {
                    $filter .= '|'.$area;
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
                self::analyzeIncFiles($pos,$for,$incpaths);
            }

            self::getDefaultFiles($paths,$pos,$filter);

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
                return str_replace(
                    '%%code%%',
                    implode("\n",$output),
                    self::$code_tpl
                );
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
                            . self::renderJS($pos)
                            . self::getCode($pos);
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
            $output = array();

            if(self::$CSSMap->count()>0) {
                // number of media types to support
                try {
                    $media   = array_unique(self::$CSSMap->values()->toArray());
                } catch ( Exception $e ) {
                    $media   = array(self::$defaultmedia);
                }
                $files   = self::$CSSMap->keys()->toArray();

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
            }
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
                $header_js = array('var CAT_URL = "'.CAT_SITE_URL.'";');
                if (Backend::isBackend()) {
                    array_push(
                        $header_js,
                        'var CAT_ADMIN_URL = "'.CAT_ADMIN_URL. '";'
                    );
                }
                $output[] = str_replace(
                    array('%%condition_open%%',' src="%%file%%"','%%code%%','%%condition_close%%'),
                    array('','',implode("\n", $header_js),''),
                    self::$js_tpl
                );
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

                foreach ($files_with_conditions as $cond => $files) {
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
                    $output[] = $line;
                }
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

$host = 'localhost';
            $output[] = '<meta http-equiv="Content-Security-Policy" content="'
                      . 'default-src \'self\' \'unsafe-inline\' \'unsafe-eval\' ' . $host . '; '
                      . 'style-src \'self\' \'unsafe-inline\' ' . $host . '; '
                      . 'script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' ' . $host . '; '
                      . 'object-src \'self\' ' . $host . '" />';

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

            // not im $mime_map == not allowed
            if(!isset(self::$mime_map[$type])) {
                self::log()->addError(sprintf(
                    'not allowed: [%s]',$file
                ));
                return;
            }

            if(!file_exists(CAT_ENGINE_PATH.'/'.$file) && !file_exists(CAT_PATH.'/'.$file)) {
                self::log()->addError(sprintf(
                    'no such file: [%s]',$file
                ));
                return;
            }

            if(\CAT\Helper\Media::isImage($file)) {
                self::log()->addDebug(sprintf('serving image (isImage()) [%s]',$file));
                copy(CAT_ENGINE_PATH.'/'.$file, CAT_PATH.'/assets/'.pathinfo($files[0], PATHINFO_BASENAME));
                // the content-type defaults to 'application/octet-stream' if the suffix is not present in the mime table
                header('Content-Type: '.Media::getContentType(pathinfo($files[0], PATHINFO_EXTENSION)));
                readfile(CAT_PATH.'/assets/'.pathinfo($file, PATHINFO_BASENAME));
                return;
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

            $for    = 'frontend';
            $filter = 'frontend';

            if (empty($id)) {
                self::log()->addDebug('empty id');
                if (Backend::isBackend()) {
                    $id = 'backend_'.Backend::getArea();
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
                                    self::$CSSMap->put($f,(isset($item['media']) ? $item['media'] : self::$defaultmedia));
                                    if(isset($item['condition'])) {
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
                                self::$autoload['jq'] = true;
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
            self::log()->addDebug(sprintf(
                'getDefaultFiles() position [%s] filter [%s]', $pos, $filter
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
                            self::$CSSMap->put($file,self::$defaultmedia);
                        } else {
                            self::$JSSet[$pos]->add($file);
                        }
                    }
                }
            }
        }   // end function getDefaultFiles()

        /**
         *
         * @access public
         * @return
         **/
        protected static function getPaths($id, $pos)
        {
            self::log()->addDebug('getPaths()');

            list($id, $for) = self::analyzeID($id); // sanitize ID

            $paths    = array();
            $incpaths = array();
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
                    array_push($paths, Directory::sanitizePath($tplpath.'/css/'.Registry::get('default_template_variant')));
                    array_push($paths, Directory::sanitizePath($tplpath.'/css'));
                    // JS
                    array_push($paths, Directory::sanitizePath($tplpath.'/js/'.Registry::get('default_template_variant')));
                    array_push($paths, Directory::sanitizePath($tplpath.'/js'));
                    // *.inc.php - fallback sorting; search will stop on first occurance
                    array_push($incpaths, Directory::sanitizePath($tplpath.'/templates/'.Registry::get('default_template_variant')));
                    array_push($incpaths, Directory::sanitizePath($tplpath.'/templates/default'));
                    array_push($incpaths, Directory::sanitizePath($tplpath.'/templates'));
                    array_push($incpaths, Directory::sanitizePath($tplpath));
                    break;
            // -----------------------------------------------------------------
            // ----- BACKEND ---------------------------------------------------
            // -----------------------------------------------------------------
                case 'backend':
                    $filter = 'backend|theme';
                    if ($pos=='footer') {
                        $filter = 'backend_body|theme_body';
                    }

                    array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/css/'.Registry::get('default_theme_variant')));
                    array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/css/default'));
                    array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/css'));

                    array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/templates/'.Registry::get('default_theme_variant')));
                    array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/js/'.Registry::get('default_theme_variant')));
                    array_push($paths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/js'));

                    // admin tool
                    if (self::router()->match('~\/tool\/~i')) {
                        $tool = \CAT\Backend\Admintools::getTool();
                        foreach (
                            array_values(array(
                                Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$tool.'/css'),
                                Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$tool.'/js')
                            )) as $p
                        ) {
                            if (is_dir($p)) {
                                array_push($paths, $p);
                                array_push($incpaths, $p);
                            }
                        }
                    }

                    // fallback sorting; search will stop on first occurance
                    array_push($incpaths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/templates/'.Registry::get('default_theme_variant')));
                    array_push($incpaths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/templates'));
                    array_push($incpaths, Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme')));
                    break;
            }

            return array(array_unique($paths),array_unique($incpaths),$filter);
        }   // end function getPaths()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function init()
        {
            // header and/or footer
            foreach(array_values(array('header','footer')) as $pos) {
                if(!self::$JSSet[$pos] instanceof \Ds\Set) {
                    self::$JSSet[$pos]      = new Set(); // Javascript files
                }
                if(!self::$JSCond[$pos] instanceof \Ds\Map) {
                    self::$JSCond[$pos]  = new Map(); // JS conditionals
                }
                if(!self::$code[$pos] instanceof \Ds\Set) {
                    self::$code[$pos]       = new Set(); // Javascript code
                }
            }

            // header only
            if(!self::$CSSMap instanceof \Ds\Map) {
                self::$CSSMap     = new Map(); // CSS files
            }
            if(!self::$CSSCond instanceof \Ds\Map) {
                self::$CSSCond = new Map(); // CSS conditionals
            }
            if(!self::$Meta instanceof \Ds\Set) {
                self::$Meta    = new Set(); // META
            }
        }   // end function init()
        
    }
}