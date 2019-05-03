<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       2018 Black Cat Development
   @link            https://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

namespace CAT\Backend;
use \CAT\Base as Base;
use \CAT\Helper\Directory as Directory;
use \CAT\Helper\Media as FM;

if (!class_exists('\CAT\Backend\Media'))
{
    class Media extends Base
    {
        /**
         * log level
         **/
        protected static $loglevel   = \Monolog\Logger::EMERGENCY;
        /**
         * current instance (singleton)
         **/
        protected static $instance = null;
        /**
         * current base dir, this is the user's home folder
         **/
        protected static $basedir  = null;

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            // global list permission
            if(!self::user()->hasPerm('media_list')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            self::init();
            $folder = self::getFolder();
            $type   = \CAT\Helper\Validate::get('type');

            $currentFolder
                = self::$basedir
                . ( (empty($folder) || $folder=='media') ? '' : '/'.$folder );

            // folder permission
            if(!Directory::checkPath($currentFolder,'MEDIA')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            $isRoot = ( (empty($folder) || $folder=='media') ? true : false );
            $parent = ( (empty($folder) || $folder=='media') ? null : pathinfo($folder,PATHINFO_DIRNAME) );

            $tpl_data = array(
                'isRoot'        => $isRoot,
                'parent'        => $parent,
                'folders'       => FM::getFolders($currentFolder,false,true),
                'subfolders'    => FM::getFolders($currentFolder,true),
                'files'         => FM::getFiles($currentFolder,$type,true),
                'currentFolder' => $folder,
                'currentPath'   => explode('/',$folder),
                'current'       => 'folders',
            );

            if(self::asJSON())
            {
                if(isset($_POST['ashtml'])) {
                    \CAT\Backend::initPaths();
                    echo \CAT\Helper\Json::printSuccess(
                        self::tpl()->get('filemanager.tpl', $tpl_data)
                    );
                } else {
                    echo \CAT\Helper\Json::printData($tpl_data);
                }
            }
            else
            {
                // this function may be used by the WYSIWYG module; if this is
                // the case, we do not print the backend header/footer
                if(!isset($_POST['FM'])) {
                    \CAT\Backend::printHeader();
                }
                self::tpl()->output('filemanager.tpl', $tpl_data);
                if(!isset($_POST['FM'])) {
                    \CAT\Backend::printFooter();
                }
            }
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        protected static function getFolder()
        {
            // get folder from params
            $parts = self::router()->getParts();
            $parts = array_slice($parts,2);
            $folder = implode('/',$parts);
            return urldecode($folder);
        }   // end function folder()

        /**
         * initialize
         *
         * @access protected
         * @return
         **/
        protected static function init()
        {
            self::$basedir = self::user()->getHomeFolder();
        }   // end function init()

    } // class \CAT\Helper\Media

} // if class_exists()