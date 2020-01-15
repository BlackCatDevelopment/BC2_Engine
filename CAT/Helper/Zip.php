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

   Usage:

   Example: Zip the templates subfolder
        $z = new \CAT\Helper\Zip(CAT_TEMP_FOLDER.'/templates.zip');
        $z->adapter->zip(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER,CAT_TEMP_FOLDER);

*/

namespace CAT\Helper;

use \CAT\Base as Base;
use \CAT\Helper\Directory as Directory;

if (!class_exists('\CAT\Helper\Zip')) {
    interface ZipInterface
    {
        public function zip(string $sourceFolder, string $folder);
        public function unzip(string $folder);
    }
    class Zip extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;
        public $adapter     = null;
        protected $zipfile     = null;

        /**
         *
         * @access public
         * @return
         **/
        public function __construct(string $file)
        {
            // if we are going to create a zip, the file will not exist yet
            // so we just store the file name for now
            $this->zipfile = Directory::sanitizePath($file);
            if (class_exists('\ZipArchive')) {
                $this->adapter = new ZipArchiveAdapter(new \ZipArchive());
                $this->adapter->setFile($this->zipfile);
            } else {
                // as this is a constant that will be set to an empty default
                // value as soon as PclZip lib is included, we have to define it
                // before creating the PclZip object
                define('PCLZIP_TEMPORARY_DIR', CAT_TEMP_FOLDER);
                // PclZip does not take any action here, so it is safe enough
                // to pass the file name here
                $this->adapter = new PclZipAdapter(new PclZip($this->zipfile));
            }
        }   // end function __construct()
    } // class Zip

    class PclZipAdapter implements ZipInterface
    {
        protected $zip;
        public function __construct(Zip $zip)
        {
            $this->zip = $zip;
        }

        /**
         *
         * @access public
         * @return
         **/
        public function unzip(string $folder)
        {
        }   // end function unzip()

        /**
         *
         * @access public
         * @return
         **/
        public function zip(string $sourceFolder, string $folder)
        {
        }   // end function zip()

        /*
        $list = $archive->extract(
            PCLZIP_OPT_PATH, 'folder',
            PCLZIP_CB_PRE_EXTRACT, 'myPreExtractCallBack'
        );
        */

        /**
         *
         * @access public
         * @return
         **/
        public function preCheckPath($p_event, &$p_header)
        {
            $info = pathinfo(Directory::sanitizePath($p_header['filename']));
            $path = strtolower($info['dirname']);
            // allowed paths:
            // 1. engine
            // 2. site
            // 3. media
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // TODO: weiter einschränken, wenn also z.B. ein Zip in media hochgeladen
//       wird, auch nur media erlauben
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if (substr_compare($path, strtolower(CAT_ENGINE_PATH), 0, strlen(CAT_ENGINE_PATH), true)==0) {
                return 1;
            }
            if (substr_compare($path, strtolower(CAT_PATH), 0, strlen(CAT_PATH), true)==0) {
                return 1;
            }
            // TODO: media
            #if(substr_compare($path),strtolower(CAT_ENGINE_PATH),0,strlen(CAT_ENGINE_PATH),true)==0) {
            #    return 1;
            #}
            return 0;
        }   // end function preCheckPath()
    }

    class ZipArchiveAdapter implements ZipInterface
    {
        protected $zip;
        protected $zipfile     = null;

        public function __construct(\ZipArchive $zip)
        {
            $this->zip = $zip;
        }

        /**
         *
         * @access public
         * @return
         **/
        public function setFile(string $file)
        {
            $this->zipfile = $file;
        }   // end function setFile()
        
        /**
         *
         * @access public
         * @return
         **/
        public function resolveErrCode($code)
        {
            switch ($code) {
                case 0:
                    return 'OK';
                case 1:
                    return 'Multi-disk zip archives not supported';
                case 2:
                    return 'Renaming temporary file failed';
                case 3:
                    return 'Closing zip archive failed';
                case 4:
                    return 'Seek error';
                case 5:
                    return 'Read error';
                case 6:
                    return 'Write error';
                case 7:
                    return 'CRC error';
                case 8:
                    return 'Containing zip archive was closed';
                case 9:
                    return 'No such file';
                case 10:
                    return 'File already exists';
                case 11:
                    return 'Can\'t open file';
                case 12:
                    return 'Failure to create temporary file';
                case 13:
                    return 'Zlib error';
                case 14:
                    return 'Malloc failure';
                case 15:
                    return 'Entry has been changed';
                case 16:
                    return 'Compression method not supported';
                case 17:
                    return 'Premature EOF';
                case 18:
                    return 'Invalid argument';
                case 19:
                    return 'Not a zip archive';
                case 20:
                    return 'Internal error';
                case 21:
                    return 'Zip archive inconsistent';
                case 22:
                    return 'Can\'t remove file';
                case 23:
                    return 'Entry has been deleted';
                default:
                    return 'An unknown error has occurred('.intval($code).')';
            }
        }   // end function resolveErrCode()
        

        /**
         *
         * @access public
         * @return
         **/
        public function unzip(string $folder)
        {
            if (empty($this->zipfile)) {
                throw new \RuntimeException('missing zip file');
            }
            $folder = strtolower(Directory::sanitizePath($folder));
            if (
                   !Directory::checkPath($folder, 'SITE')
                && !Directory::checkPath($folder, 'MEDIA')
                && !Directory::checkPath($folder, 'TEMP')
            ) {
                throw new \RuntimeException('zip path outside allowed folders');
            }
            $open_result = $this->zip->open($this->zipfile);
            if ($open_result === true) {
                if ($this->zip->extractTo($folder) === true) {
                    $status = $this->zip->getStatusString();
                    $this->zip->close();
                    return ( $status == 'No Error' );
                }
            } else {
                throw new \RuntimeException(sprintf(
                    'unzip failed: [%s]',
                    $this->resolveErrCode($open_result)
                ));
            }
        }   // end function unzip()

        /**
         *
         * @access public
         * @return
         **/
        public function zip(string $sourceFolder, string $folder)
        {
            if (empty($this->zipfile)) {
                throw new \RuntimeException('missing zip file');
            }
            // check output path
            $folder = strtolower(Directory::sanitizePath($folder));
            if (
                   !Directory::checkPath($folder, 'SITE')
                && !Directory::checkPath($folder, 'MEDIA')
                && !Directory::checkPath($folder, 'TEMP')
            ) {
                throw new \RuntimeException('zip path outside allowed folders');
            }
            // check path to zip
            $folder = strtolower(Directory::sanitizePath($sourceFolder));
            if (
                   !Directory::checkPath($sourceFolder, 'SITE')
                && !Directory::checkPath($sourceFolder, 'MEDIA')
                && !Directory::checkPath($sourceFolder, 'TEMP')
            ) {
                throw new \RuntimeException('zip path outside allowed folders');
            }

            $open_result = $this->zip->open($this->zipfile,\ZipArchive::CREATE);
            if ($open_result === true) {
                if ($this->zipfolder($sourceFolder,$this->zip,strlen($sourceFolder.'/')) === true) {
                    $status = $this->zip->getStatusString();
                    $this->zip->close();
                    return ( $status == 'No Error' );
                }
            } else {
                throw new \RuntimeException(sprintf(
                    'zip failed: [%s]',
                    $this->resolveErrCode($open_result)
                ));
            }
        }   // end function zip()

        /**
         *
         * @access private
         * @return
         **/
        private function zipFolder(string $folder, &$zipFile, int $removeLength=0)
        {
            $handle = opendir($folder);
            while (false !== $f = readdir($handle)) {
                if ($f != '.' && $f != '..') {
                    $filePath = "$folder/$f";
                    // Remove prefix from file path
                    $localPath = substr($filePath, $removeLength);
                    if (is_file($filePath)) {
                        if (!$zipFile->addFile($filePath, $localPath)) {
                            throw new \RuntimeException(sprintf(
                                'unable to add file [%s]', $filePath
                            ));
                        }
                    }
                    elseif (is_dir($filePath)) {
                        // Add sub-directory.
                        if (!$zipFile->addEmptyDir($localPath)) {
                            throw new \RuntimeException(sprintf(
                                'unable to add directory [%s]',$localPath
                            ));
                        }
                        // recursion
                        $this->zipFolder($filePath, $zipFile, $removeLength);
                    }
                }
            }
            closedir($handle);
        }   // end function zipFolder()
    }
} // if class_exists()
