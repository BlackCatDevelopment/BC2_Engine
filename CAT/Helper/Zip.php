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

        #Example: Zip the templates subfolder
        $z = new \CAT\Helper\Zip(CAT_TEMP_FOLDER.'/templates.zip');
        $z->adapter->zip(CAT_ENGINE_PATH.'/'.CAT_TEMPLATES_FOLDER,CAT_TEMP_FOLDER);

*/

namespace CAT\Helper;

use \CAT\Base as Base;
use \CAT\Helper\Directory as Directory;

if (!class_exists('\CAT\Helper\Zip')) {
    interface ZipInterface
    {
        public function listContent() : array;
        public function zip(string $sourceFolder, string $folder);
        public function unzip(string $folder);
    }
    class Zip extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;
        /**
         * @var currently used adapter
         **/
        public $adapter     = null;
        /**
         * @var path to zip file
         **/
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
                $this->adapter = new PclZipAdapter(new \PclZip($this->zipfile));
            }
        }   // end function __construct()
    } // class Zip

    class PclZipAdapter implements ZipInterface
    {
        protected $zip;
        protected $zipfile     = null;

        public function __construct(\PclZip $zip)
        {
            $this->zip = $zip;
            $this->zipfile = $zip->zipname;
        }

        /**
         *
         * @access public
         * @return
         **/
        public function listContent() : array
        {
            return $this->zip->listContent();
        }   // end function listContent()

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
            if (!Directory::checkPath($folder)) {
                throw new \RuntimeException('zip path outside allowed folders');
            }

            $list = $this->zip->extract(
                PCLZIP_OPT_PATH, $folder
            );

            return count($list)>0;
        }   // end function unzip()

        /**
         *
         * @access public
         * @return
         **/
        public function zip(string $sourceFolder, string $folder)
        {
        }   // end function zip()

        /**
         *
         * @access public
         * @return
         **/
        public static function preCheckPath($p_event, &$p_header)
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
        protected $errcodes    = array(
            0 => 'OK',
            1 => 'Multi-disk zip archives not supported',
            2 => 'Renaming temporary file failed',
            3 => 'Closing zip archive failed',
            4 => 'Seek error',
            5 => 'Read error',
            6 => 'Write error',
            7 => 'CRC error',
            8 => 'Containing zip archive was closed',
            9 => 'No such file',
            10 => 'File already exists',
            11 => 'Can\'t open file',
            12 => 'Failure to create temporary file',
            13 => 'Zlib error',
            14 => 'Malloc failure',
            15 => 'Entry has been changed',
            16 => 'Compression method not supported',
            17 => 'Premature EOF',
            18 => 'Invalid argument',
            19 => 'Not a zip archive',
            20 => 'Internal error',
            21 => 'Zip archive inconsistent',
            22 => 'Can\'t remove file',
            23 => 'Entry has been deleted',
        );

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
            return isset($this->errcodes[$code])
                ? $this->errcodes[$code]
                : 'Unknown error code '.intval($code);
        }   // end function resolveErrCode()
        
        /**
         * creates a list of the zip content which is compatible with
         * PclZip listContent()
         *
         * @access public
         * @return array
         **/
        public function listContent() : array
        {
            $open_result = $this->zip->open($this->zipfile);
            $list = array();
            if ($open_result === true) {
                for($i = 0; $i < $this->zip->numFiles; $i++) {
                    $item = $this->zip->statIndex($i);
                    $list[] = array(
                        'filename'        => $item['name'],
                        'stored_filename' => $item['name'],
                        'size'            => $item['size'],
                        'compressed_size' => $item['comp_size'],
                        'mtime'           => $item['mtime'],
                        'comment'         => $this->zip->getCommentIndex($i),
                        // ZipArchive does not have folder entries, so we check
                        // for trailing /
                        'folder'          => (substr($item['name'],-1,1)=='/'),
                        // no status info in ZipArchive
                        'status'          => 'ok',
                        'crc'             => $item['crc'],
                    );
                }
            }
            return $list;
        }   // end function listContent()

        /**
         *
         * @access public
         * @return
         **/
        public function unzip(string $folder)
        {
            if (!Directory::checkPath($folder)) {
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
            if (!Directory::checkPath($folder)) {
                throw new \RuntimeException('target zip path outside allowed folders');
            }
            // check path to zip
            $sourceFolder = strtolower(Directory::sanitizePath($sourceFolder));
            if (!Directory::checkPath($sourceFolder)) {
                throw new \RuntimeException('source zip path outside allowed folders');
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
         * recursive function to iterate the folder to be zipped
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
                    // check path
                    if(Directory::checkPath($filePath)===true) {
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
            }
            closedir($handle);
        }   // end function zipFolder()
    }
} // if class_exists()
