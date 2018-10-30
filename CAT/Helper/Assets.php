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
use \CAT\Helper\AssetFactory as AssetFactory;
use \CAT\Helper\Directory as Directory;

if(!class_exists('Assets'))
{
	class Assets extends Base
	{
        // set debug level
        protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        #protected static $loglevel  = \Monolog\Logger::DEBUG;
        protected static $instance  = NULL;
        // map type to content-type
        protected static $mime_map  = array(
            'css'   => 'text/css',
            'js'    => 'text/javascript',
            'png'   => 'image/png',
            'svg'   => 'image/svg+xml',
        );
        //
        protected static $includes  = array();

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         * allows to add a headers.inc.php or footers.inc.php at runtime;
         * used by WYSIWYG for example to include the editor's inc files
         *
         * if $position is omitted, the method will try to get it from the
         * $file name (example: headers.inc.php -> header); defaults to
         * header on failure
         *
         * @access public
         * @param  string  $file      file path
         * @param  string  $position  header|footer (optional)
         * @return void
         **/
        public static function addInclude($file,$pos=NULL)
        {
            if(!$pos)
            {
                preg_match('~^(.*)s\.inc\.php$~i',pathinfo($file,PATHINFO_BASENAME),$m);
                $pos = ( isset($m[1]) ? $m[1] : 'header' );
            }
            self::log()->addDebug(sprintf(
                'addInclude file [%s] position [%s]', $file, $pos
            ));
            if(!isset(self::$includes[$pos])) self::$includes[$pos] = array();
            self::$includes[$pos][] = Directory::sanitizePath($file);
        }   // end function addInclude()

        /**
         *
         * @access public
         * @return
         **/
        public static function analyzeID($id)
        {
            self::log()->addDebug(sprintf(
                'analyzeID [%s]', $id
            ));

            if(!$id)
            {
                if(Backend::isBackend())
                {
                    $id     = 'backend_'.Backend::getArea();
                    $filter = 'backend';
                    $for    = 'backend';
                } else {
                    $id = \CAT\Page::getID();
                    $for = 'frontend';
                }
                return array($id,$for);
            }
            return array(
                $id,
                ( substr($id,0,7)=='backend' ? 'backend' : 'frontend' )
            );
        }   // end function analyzeID()

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

            list($id,$for) = self::analyzeID($id);

            self::log()->addDebug(sprintf(
                '[%s] pos [%s] id [%s] for [%s] ignore includes [%s]',
                __FUNCTION__, $pos, $id, $for, $ignore_inc
            ));

            $am = AssetFactory::getInstance($id);

// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Das muss anders gehen!
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if($id=='backend_login')
                return $am;

            // paths to scan
            list($paths,$incpaths,$filter) = self::getPaths($id,$pos);

            $page_id = false;
            if(is_numeric($id) && $id>0)
                $page_id = $id;
            if(Backend::isBackend() && Backend::getArea()=='page')
                $page_id = \CAT\Backend\Page::getPageID();

            // if it's a frontend page, add scan paths for modules
            if(is_numeric($page_id) && $page_id>0)
            {
                $sections = Sections::getSections($page_id);
                if(is_array($sections) && count($sections)>0)
                {
                    foreach($sections as $block => $items)
                    {
                        foreach($items as $item)
                        {
                            array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module'].'/css'));
                            array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module'].'/js'));
                            array_push($incpaths,Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module']));
                            if(strtolower($item['module'])=='wysiwyg') $wysiwyg = true;

                            if($item['variant']!='')
                            {
                                $variant_path = Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$item['module'].'/templates/'.$item['variant'].'/css');
                                if(is_dir($variant_path))
                                    array_push($paths,$variant_path);
                            }
                        }
                    }
                    if(isset($wysiwyg) && $wysiwyg) $am->addJS(\CAT\Addon\WYSIWYG::getJS());
                }
            }

            if(Backend::isBackend())
            {
                $area = Backend::getArea();
                self::log()->addDebug(sprintf(
                    'looking for area specific js/css, current area: [%s]',
                    $area
                ));
                $filter .= '|'.$area;
                if($pos=='footer') $filter .= '_body';
                self::log()->addDebug(sprintf('filter: [%s]',$filter));
            }

            self::log()->addDebug('>>> scan paths');
            self::log()->addDebug('    $paths    : ' . var_export($paths,1));
            self::log()->addDebug(' $incpaths    : ' . var_export($incpaths,1));
            self::log()->addDebug('   filter     : ' . $filter);

            // -----------------------------------------------------------------
            // analyze headers/footers.inc
            // -----------------------------------------------------------------
            if(!$ignore_inc)
            {
                $filename = $pos.'s.inc';
                $incfiles = array();
                foreach($incpaths as $path)
                {
                    $temp = Directory::findFiles(
                        $path,
                        array(
                            'filename'   => $filename,
                            'extension'  => 'php',
                            'recurse'    => true,
                            'max_depth'  => 3
                        )
                    );
                    if(is_array($temp) && count($temp)>0)
                    {
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: Wenn mehrere Dateien vorhanden sind, darf pro Stammverzeichnis
//       (Modul oder Template) nur eine davon geladen werden
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                        $incfiles[] = Directory::sanitizePath($temp[0]);
                        //break;
                    }
                }

                if(isset(self::$includes[$pos]) && is_array(self::$includes[$pos]))
                    $incfiles = array_merge($incfiles,self::$includes[$pos]);

                if(is_array($incfiles) && count($incfiles)>0) {
                    self::log()->addDebug(sprintf(
                        'Found [%d] include files for position [%s]',
                        count($incfiles),$pos
                    ));
                    foreach($incfiles as $file) {
                        if(file_exists($file)) {
                            try {
                                require $file;
                                $array =& ${'mod_'.$pos.'s'};
                                // CSS
                                if(array_key_exists('css',$array[$for]) && count($array[$for]['css'])>0)
                                {
                                    foreach($array[$for]['css'] as $item)
                                    {
                                        if(isset($item['condition']))
                                        {
                                            foreach($item['files'] as $f)
                                            {
                                                $am->addCSS($f,(isset($item['media']) ? $item['media'] : NULL));
                                                $am->addCondition($f,$item['condition']);
                                            }
                                        } else {
                                            $am->addCSS(
                                                $item['file'],
                                                (isset($item['media']) ? $item['media'] : NULL)
                                            );
                                        }
                                    }
                                }
                                // JS
                                if(array_key_exists('js',$array[$for]) && count($array[$for]['js'])>0)
                                {
                                    foreach($array[$for]['js'] as $item)
                                    {
                                        if(is_array($item))
                                        {
                                            // if it's an array there _must_ be a conditional
                                            if(!isset($item['condition'])) continue;
                                            foreach($item['files'] as $f)
                                            {
                                                $am->addJS($f,$pos);
                                                $am->addCondition($f,$item['condition']);
                                            }
                                        } else {
                                            $am->addJS($item,$pos);
                                        }
                                    }
                                }
                                // jQuery
                                if(array_key_exists('jquery',$array[$for]))
                                {
                                    if(isset($array[$for]['jquery']['core']) && $array[$for]['jquery']['core'])
                                        $am->enableJQuery();
                                    if(isset($array[$for]['jquery']['ui']) && $array[$for]['jquery']['ui'])
                                        $am->enableJQueryUI();
                                    if(isset($array[$for]['jquery']['plugins']) && $array[$for]['jquery']['plugins'])
                                    {
                                        foreach($array[$for]['jquery']['plugins'] as $item)
                                        {
                                            if(false!==($file=self::findJQueryPlugin($item)))
                                            {
                                                $am->addJS($file,$pos);
                                            }
                                        }
                                    }
                                }
                                // META
                                if(array_key_exists('meta',$array[$for]))
                                {
                                    foreach($array[$for]['meta'] as $item)
                                    {
                                        $am->addMeta($item);
                                    }
                                }
                            } catch (\Exception $e) {
                            }
                        }
                    }
                }
            }

            // -----------------------------------------------------------------
            // find default files (frontend[_body].css/js, ...)
            // -----------------------------------------------------------------
            $files    = array();
            $ext      = array('css','js');
            foreach($paths as $path)
            {
                $temp = Directory::findFiles(
                    $path,
                    array(
                        'extensions' => $ext,
                        'recurse'    => true,
                        'max_depth'  => 1,
                        'filter'     => "($filter)"
                    )
                );
                $files = array_merge($files,$temp);
            }

            self::log()->addDebug(sprintf(
                'Found [%d] files for filter [%s]',
                count($files),$filter
            ));

            if($as_array) return $files;

            if(is_array($files) && count($files)>0) {
                foreach($files as $file) {
                    self::log()->addDebug(sprintf(
                        ' --- adding file [%s] to pos [%s]',$file,$pos
                    ));
                    $am->addAsset($file,$pos); // default CSS have always media 'all'
                }
            }

            return $am;

        }   // end function getAssets()

        /**
         *
         * @access public
         * @return
         **/
        public static function getPaths($id,$pos)
        {
            self::log()->addDebug('getPaths()');

            list($id,$for) = self::analyzeID($id); // sanitize ID

            $paths    = array();
            $incpaths = array();
            $filter   = null;

            switch($for)
            {
            // -----------------------------------------------------------------
            // ----- FRONTEND --------------------------------------------------
            // -----------------------------------------------------------------
                case 'frontend':
                    $filter = 'frontend';
                    // CSS
                    array_push($paths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/css/'.Registry::get('default_template_variant')));
                    array_push($paths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/css'));
                    // JS
                    array_push($paths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/js/'.Registry::get('default_template_variant')));
                    array_push($paths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/js'));
                    // *.inc.php - fallback sorting; search will stop on first occurance
                    array_push($incpaths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/templates/'.Registry::get('default_template_variant')));
                    array_push($incpaths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/templates/default'));
                    array_push($incpaths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR').'/templates'));
                    array_push($incpaths,Directory::sanitizePath(\CAT\Registry::get('CAT_TEMPLATE_DIR')));
                    break;
                case 'backend':
                    $filter = 'backend|theme';
                    if($pos=='footer') $filter = 'backend_body|theme_body';

                    array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/css/'.Registry::get('default_theme_variant')));
                    array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/css/default'));
                    array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/css'));

                    array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/templates/'.Registry::get('default_theme_variant')));
                    array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/js/'.Registry::get('default_theme_variant')));
                    array_push($paths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/js'));

                    #$area = CAT_Backend::getArea();
                    #self::log()->addDebug(sprintf(
                    #    'looking for area specific js/css, current area: [%s]',
                    #    $area
                    #));

                    // admin tool
                    if(self::router()->match('~\/tool\/~i'))
                    {
                        $tool = \CAT\Backend\Admintools::getTool();
                        foreach(
                            array_values(array(
                                Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$tool.'/css'),
                                Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$tool.'/js')
                            )) as $p
                        ) {
                            if(is_dir($p)) {
                                array_push($paths,$p);
                                array_push($incpaths,$p);
                            }
                        }
                    }

                    // fallback sorting; search will stop on first occurance
                    array_push($incpaths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/templates/'.Registry::get('default_theme_variant')));
                    array_push($incpaths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme').'/templates'));
                    array_push($incpaths,Directory::sanitizePath(CAT_ENGINE_PATH.'/templates/'.Registry::get('default_theme')));
                    break;
            }

            return array(array_unique($paths),array_unique($incpaths),$filter);
        }   // end function getPaths()

        /**
         *
         * @access public
         * @return
         **/
        public static function isValid($file)
        {
            self::log()->addDebug(sprintf('isValid file [%s]',$file));
            $type = pathinfo($file,PATHINFO_EXTENSION);
            if(!strlen($type) || !array_key_exists($type,self::$mime_map))
                return false;
            return true;
        }   // end function isValid()

        /**
         *
         * @access public
         * @return
         **/
        public static function renderAssets($pos, $id=null, $ignore_inc=false, $print=true)
        {
            self::log()->addDebug('renderAssets()');
            $am = self::getAssets($pos,$id,$ignore_inc);
            $output = null;
            switch($pos)
            {
                case 'header':
                    $output = $am->renderMeta()
                            . $am->renderCSS()
                            . $am->renderJS('header');
                    break;
                case 'footer':
                    $output = $am->renderJS('footer');
                    break;
            }

            return $output;
        }   // end function renderAssets
        
        /**
         *
         * @access public
         * @return
         **/
        public static function serve($type,$files,$print=false)
        {
            self::log()->addDebug('serve()');
            if(!is_array($files) || !count($files)>0) return false;
            if($type=='images'||$type=='svg')
            {
                self::log()->addDebug('serving image');
                foreach($files as $file)
                {
                    if(file_exists(CAT_ENGINE_PATH.'/'.$file))
                    {
                        self::log()->addDebug(sprintf(
                            'copying file [%s] to path [%s]',
                            CAT_ENGINE_PATH.'/'.$file,
                            CAT_PATH.'/assets/'.pathinfo($file,PATHINFO_BASENAME)
                        ));
                        copy(CAT_ENGINE_PATH.'/'.$file,CAT_PATH.'/assets/'.pathinfo($file,PATHINFO_BASENAME));
                        if(isset(self::$mime_map[strtolower(pathinfo($file,PATHINFO_EXTENSION))]))
                        {
                            header('Content-Type: '.self::$mime_map[strtolower(pathinfo($file,PATHINFO_EXTENSION))]);
                            readfile(CAT_PATH.'/assets/'.pathinfo($file,PATHINFO_BASENAME));
                            return;
                        }
                        echo CAT_SITE_URL.'/assets/'.pathinfo($file,PATHINFO_BASENAME);
                    }
                }
            }

            // create asset factory and pass engine path as basedir
            $factory = new \Assetic\Factory\AssetFactory(Directory::sanitizePath(CAT_ENGINE_PATH));
            $fm      = new \Assetic\FilterManager();
            $factory->setFilterManager($fm);
            $factory->setDefaultOutput('assets/*');
            $factory->setProxy(Registry::get('PROXY'),Registry::get('PROXY_PORT'));

            $filters = array();
            if($type=='css')
            {
                foreach(array('CssImportFilter','CATCssRewriteFilter','MinifyCssCompressorFilter','CssCacheBustingFilter') as $filter)
                {
                    $filterclass = '\Assetic\Filter\\'.$filter;
                    $fm->set($filter,new $filterclass());
                    $filters[] = $filter;
                }
            }

            self::log()->addDebug(sprintf('type [%s], number of files [%d]', $type, count($files)).print_r($files,1));

            // add assets
            $assets  = $factory->createAsset(
                $files,
                $filters
            );

            // create the asset manager instance
            $am = new \Assetic\AssetManager();
            $am->set('assets', $assets);
            // create the writer to save the combined file
            $writer = new \Assetic\AssetWriter(Directory::sanitizePath(CAT_PATH));
            $writer->writeManagerAssets($am);

            if($print) {
                return self::printAsset($assets->getTargetPath(),$assets);
            }

            return CAT_SITE_URL.'/'.$assets->getTargetPath();
        }   // end function serve()

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
        private static function findJQueryPlugin($item)
        {
            $plugin_path = CAT_JQUERY_PATH.'/plugins';
            // check suffix
            if(pathinfo($item,PATHINFO_EXTENSION) != 'js')
                $item .= '.js';

            // prefer minimized
            $minitem = pathinfo($item,PATHINFO_FILENAME).'.min.js';
            $file    = Directory::sanitizePath($plugin_path.'/'.$minitem);

            // just there?
            if (!file_exists($file))
            {
                $file = Directory::sanitizePath($plugin_path.'/'.$item);
                if (!file_exists($file))
                {
                    $dir = pathinfo($item,PATHINFO_FILENAME);
                    // prefer minimized
                    $minitem = pathinfo($item,PATHINFO_FILENAME).'.min.js';
                    $file    = Directory::sanitizePath($plugin_path.'/'.$dir.'/'.$minitem);
                    if(!file_exists($file))
                    {
                        $file = Directory::sanitizePath($plugin_path.'/'.$dir.'/'.$item);
                        if(!file_exists($file))
                        {
                            // give up
                            return false;
                        }
                    }
                }
            }

            return $file;
        }   // end function findJQueryPlugin()

        /**
         *
         * @access public
         * @return
         **/
        protected static function printAsset($file,$asset)
        {
            if(!self::isValid($file)) exit;
            $type = pathinfo($file,PATHINFO_EXTENSION);
            header('Content-Type: '.self::$mime_map[$type]);
            echo $asset->dump();
        }   // end function printAsset()
    }
}