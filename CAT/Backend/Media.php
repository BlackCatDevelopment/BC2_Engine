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
        protected static $instance = NULL;

        /**
         * PHP File Upload error message codes:
         * http://php.net/manual/en/features.file-upload.errors.php
         **/
        protected $error_messages = array(
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload',
            'post_max_size' => 'The uploaded file exceeds the post_max_size directive in php.ini',
            'max_file_size' => 'File is too big',
            'min_file_size' => 'File is too small',
            'accept_file_types' => 'Filetype not allowed',
            'max_number_of_files' => 'Maximum number of files exceeded',
            'max_width' => 'Image exceeds maximum width',
            'min_width' => 'Image requires a minimum width',
            'max_height' => 'Image exceeds maximum height',
            'min_height' => 'Image requires a minimum height',
            'abort' => 'File upload aborted',
            'image_resize' => 'Failed to resize image'
        );

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance()
        {
            if(!is_object(self::$instance))
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public static function delete()
        {
            if(!self::user()->hasPerm('media_delete'))
                self::printFatalError('You are not allowed for the requested action!');
            $mediaID = self::getMediaID();
            $result  = \CAT\Helper\Media::removeFolder($mediaID,\CAT\Helper\Media::getFolderByID($mediaID));

            if(self::asJSON())
            {
                echo Json::printResult(
                    $result,
                    ( $result ? 'Success' : 'Error' )
                );
                return;
            }
        }   // end function delete()
        
        
        /**
         *
         * @access public
         * @return
         **/
        public static function details()
        {
            if(!self::user()->hasPerm('media_list'))
                self::printFatalError('You are not allowed for the requested action!');
            $mediaID = self::getMediaID(); // prints fatal error on fail
            $details = \CAT\Helper\Media::getAttributes($mediaID);
            if(self::asJSON())
            {
                \CAT\Helper\JSON::printData($details);
            } else {
                #\CAT\Backend::printHeader();
                self::tpl()->output('backend_media_details', $details);
                #\CAT\Backend::printFooter();
            }
        }   // end function details()

        /**
         *
         * @access public
         * @return
         **/
        public static function files()
        {
            // global list permission
            if(!self::user()->hasPerm('media_list'))
                self::printFatalError('You are not allowed for the requested action!');
            list($dirID,$path) = self::init();
            // path permission
            if(!self::user()->hasPathPerm($path))
            {
                self::log()->addError(sprintf(
                    'User [%s] requested list access to path [%s], missing path permission',
                    self::user()->get('display_name'), $path
                ));
                self::printFatalError('You are not allowed for the requested action!');
            }

            $files     = \CAT\Helper\Media::getFiles($path);

            $tpl_data = array(
                'baseurl' => CAT_SITE_URL.\CAT\Helper\Validate::path2uri($path),
                'dirs'    => \CAT\Helper\Media::getFolders(),
                'files'   => \CAT\Helper\Media::getFiles($path),
                'current' => 'files',
                'curr_folder' => $dirID,
            );

            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printData($tpl_data);
            }
            else
            {
                \CAT\Backend::printHeader();
                self::tpl()->output('backend_media_files', $tpl_data);
                \CAT\Backend::printFooter();
            }
        }   // end function files()
        
        
        /**
         * by default, the media backend area shows the available folders
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            self::init();
            $dirs   = \CAT\Helper\Media::getFolders();

            // add some info for easier layout
            foreach($dirs as $index => $dir) {
                $dirs[$index]['level'] = count(explode('/',$dir['path']));
                $dirs[$index]['name']  = pathinfo($dir['path'],PATHINFO_FILENAME);
            }

            $tpl_data = array(
                'dirs' => $dirs,
                'current' => 'folders',
            );

            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printData($tpl_data);
            }
            else
            {
                \CAT\Backend::printHeader();
                self::tpl()->output('backend_media_folders', $tpl_data);
                \CAT\Backend::printFooter();
            }
        }   // end function index()

        /**
         *
         * @access public
         * @return
         **/
        public static function protect()
        {
            if(!self::user()->hasPerm('media_folder_protect'))
                self::printFatalError('You are not allowed for the requested action!');

            $path = self::getPath();
            $path = urldecode($path);

            if(!self::user()->hasPathPerm($path))
            {
                self::log()->addError(sprintf(
                    'User [%s] requested upload access to path [%s], missing path permission',
                    self::user()->get('display_name'), $path
                ));
                self::printFatalError('You are not allowed for the requested action!');
            }

            // load templates
            $data = array(
                'password' => self::generatePassword(),
                'filename' => self::user()->getHomeFolder().$path.'/.htpasswd'
            );
            $htpasswd = self::tpl()->get(dirname(__FILE__).'/../templates/htpasswd.tpl',$data);
            $htaccess = self::tpl()->get(dirname(__FILE__).'/../templates/htaccess.tpl',$data);

            // save
            $fh = fopen(self::user()->getHomeFolder().\CAT\Helper\Directory::getName($path).'/.htpasswd','w');
            fwrite($fh,$htpasswd);
            fclose($fh);

            $fh = fopen(self::user()->getHomeFolder().$path.'/.htaccess','w');
            fwrite($fh,$htaccess);
            fclose($fh);

            // update db
            $sth = self::db()->query(
                'UPDATE `:prefix:media_dirs` SET `protected`=1 WHERE `path` like ?',
                array(ltrim($path,'/').'%')
            );

        }   // end function protect()

        /**
         *
         * @access public
         * @return
         **/
        public static function unprotect()
        {
            if(!self::user()->hasPerm('media_folder_protect'))
                self::printFatalError('You are not allowed for the requested action!');

            $path = self::getPath();
            $path = urldecode($path);

            if(!self::user()->hasPathPerm($path))
            {
                self::log()->addError(sprintf(
                    'User [%s] requested upload access to path [%s], missing path permission',
                    self::user()->get('display_name'), $path
                ));
                self::printFatalError('You are not allowed for the requested action!');
            }

            // remove
            unlink(self::user()->getHomeFolder().$path.'/.htpasswd');
            unlink(self::user()->getHomeFolder().$path.'/.htaccess');

            // update db
            $sth = self::db()->query(
                'UPDATE `:prefix:media_dirs` SET `protected`=null WHERE `path` like ?',
                array(ltrim($path,'/').'%')
            );

        }   // end function unprotect()

        /**
         *
         * @access public
         * @return
         **/
        public static function update()
        {
            // global list permission
            if(!self::user()->hasPerm('media_update'))
                self::printFatalError('You are not allowed for the requested action!');
            list($dirID,$path) = self::init();
            // make sure all folders are in the db
            \CAT\Helper\Media::updateFolderData($path);
            \CAT\Helper\Media::updateFiles($path);
            return self::router()->reroute(CAT_BACKEND_PATH.'/media/index');

        }   // end function update()

        /**
         *
         * @access public
         * @return
         **/
        public static function upload()
        {

echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
print_r($_FILES);
echo "</textarea><br />";

            if(!self::user()->hasPerm('media_upload'))
                self::printFatalError('You are not allowed for the requested action!');
            list($dirID,$path) = self::init();
            // path permission
            if(!self::user()->hasPathPerm($path))
            {
                self::log()->addError(sprintf(
                    'User [%s] requested list access to path [%s], missing path permission',
                    self::user()->get('display_name'), $path
                ));
                self::printFatalError('You are not allowed for the requested action!');
            }
            $tpl_data = array();
            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printData($tpl_data);
            }
            else
            {
                \CAT\Backend::printHeader();
                self::tpl()->output('backend_media_upload', $tpl_data);
                \CAT\Backend::printFooter();
            }
        }   // end function upload()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function init() : array
        {
            $itemID = self::getItemID('dir_id');
            $subdir = self::getPath($itemID);
            $home   = self::user()->getHomeFolder();
            return array(
                (int)$itemID,
                (string)$home.'/'.$subdir
            );
        }   // end function init()
        
        
        protected static function generatePassword($length = 12)
        {
            $r    = array_merge(range("a", "z"), range("a", "z"), range("A", "Z"), range(1, 9), range(1, 9));
            $not  = array('i', 'l', 'o', 'I', 'O');
            $r    = array_diff($r, $not);
            shuffle($r);
            $pass = array_slice($r, 0, intval($length));
            return implode("", $pass);
        } // generatePassword()

        /**
         *
         * @access private
         * @return
         **/
        private static function getPath(int $dirID) : string
        {
            $base   = CAT_BACKEND_PATH.'/'.\CAT\Registry::get('media_directory');
            $subdir = '';
            $route  = \CAT\Helper\Media::getFolderByID($dirID);

            if($route != $base)
                $subdir = str_ireplace(array($base.'/index',$base.'/list',$base.'/protect',$base.'/unprotect',$base),'',$route);

            if($subdir=='/') $subdir = '';

            return $subdir;
        }   // end function getPath()
        

    } // class \CAT\Helper\Media

} // if class_exists()