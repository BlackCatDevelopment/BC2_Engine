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

namespace CAT\Helper;
use \CAT\Base as Base;
use \CAT\Registry as Registry;
use \CAT\Helper\Directory as Directory;

if (!class_exists('\CAT\Helper\Media'))
{
    class Media extends Base
    {
        private static $instance;
        private static $tag_map = array(
            'basedata' => array(
                'mime_type',
                'filesize',
                'bits_per_sample',
                'resolution_x',
                'resolution_y',
                'encoding',
                'error',
                'warning',
            ),
            'EXIF' => array(
#                'ExposureTime',
#                'ISOSpeedRatings',
#                'ShutterSpeedValue',
#                'FocalLength',
                'ExifImageWidth',
                'ExifImageLength',
                'DateTimeOriginal',
            ),
            'IFD0' => array(
                'Make',
                'Model',
                'Orientation',
                'XResolution',
                'YResolution',
            ),
            'FILE' => array(
                'FileDateTime',
            ),
        );

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public static function getAttributes($id)
        {
            $attr = array();
            $sth = self::db()->query(
                  'SELECT * FROM `:prefix:media_files` AS `t1` '
                . 'JOIN `:prefix:media_data` AS `t2` '
                . 'ON `t1`.`media_id`=`t2`.`media_id` '
                . 'WHERE `t1`.`media_id`=?',
                array($id)
            );
            $data = $sth->fetchAll();

            if(is_array($data) && count($data))
            {
                foreach($data as $item)
                {
                    $attr[$item['attribute']] = $item['value'];
                }
                $attr['hfilesize'] = Directory::humanize($attr['filesize']);
                $attr['filename']  = $data[0]['filename'];
                $attr['is_image']  = (substr($attr['mime_type'],0,6) == 'image/')
                                   ? true
                                   : false;
                #$attr['url']       = \CAT\Helper\Validate::path2uri($attr['path'].'/'.$attr['filename']);
            }
            return $attr;
        }   // end function getAttributes()

        /**
         *
         * @access public
         * @return
         **/
        public static function getDirname(string $dir)
        {
            return str_ireplace(
                self::user()->getHomeFolder().'/',
                '',
                $dir
            );
        }   // end function getDirname()

        /**
         *
         * @access public
         * @return
         **/
        public static function getFiles($dir,$skip_deleted=false)
        {
            $id  = self::getFolderID($dir);
            if($id) {
                $sth = self::db()->query(
                      'SELECT * FROM `:prefix:media_files` AS `t1` '
                    . 'WHERE `t1`.`site_id`=? AND `t1`.`dir_id`=? '
                    . ($skip_deleted ? 'AND (`t1`.`deleted` IS NULL OR `t1`.`deleted`=0) ' : '')
                    . 'ORDER BY `filename` ASC',
                    array(CAT_SITE_ID,$id)
                );
                $files = $sth->fetchAll();
                foreach($files as $i => $f) {
                    $attr      = self::getAttributes($f['media_id']);
                    $files[$i] = array_merge($files[$i],$attr);
                    $files[$i]['filename'] = Directory::getName($f['filename']);
                    $files[$i]['isImage']  = substr_count($attr['mime_type'],'image/');
                }
                return $files;
            }
            return false;
        }   // end function getFiles()

        /**
         *
         * @access public
         * @return
         **/
        public static function getFolderByID(int $id) : string
        {
            $sth  = self::db()->query(
                  'SELECT `path` FROM `:prefix:media_dirs` AS `t1` '
                . 'WHERE `dir_id`=? AND `site_id`=?',
                array($id,CAT_SITE_ID)
            );
            $data = $sth->fetch();
            if(is_array($data) && isset($data['path'])) return $data['path'];
            return '';
        }   // end function getFolderByID()

        /**
         *
         * @access public
         * @return
         **/
        public static function getFolderID($dir)
        {
            $path = str_ireplace(CAT_PATH.'/'.\CAT\Registry::get('media_directory').'/','',$dir);
            #if(empty($path)) $path = '[root]';
            $sth  = self::db()->query(
                  'SELECT `dir_id` FROM `:prefix:media_dirs` AS `t1` '
                . 'WHERE `path`=? AND `site_id`=?',
                array(ltrim($path,'/'),CAT_SITE_ID)
            );
            $data = $sth->fetch();
            if(is_array($data) && isset($data['dir_id'])) return $data['dir_id'];
            return null;
        }   // end function getFolderID()

        /**
         *
         * @access public
         * @return
         **/
        public static function getFolders($skip_deleted=false)
        {
            $sth = self::db()->query(
                  'SELECT * FROM `:prefix:media_dirs` AS `t1` '
                . 'WHERE `t1`.`site_id`=? '
                . ($skip_deleted ? 'AND (`t1`.`deleted` IS NULL OR `t1`.`deleted`=0) ' : '')
                . 'ORDER BY `path` ASC',
                array(CAT_SITE_ID)
            );
            $dbfolders = $sth->fetchAll();
            return $dbfolders;
        }   // end function getFolders()

        /**
         *
         * @access public
         * @return
         **/
        public static function getMediaFromDir($dir,$filter=NULL)
        {
            self::updateFiles($dir,$filter); // will call updateFolderData()
        }   // end function getMediaFromDir()

        /**
         *
         * @access public
         * @return
         **/
        public static function removeFolder(int $id, string $dir) : bool
        {
            $fulldir = CAT_PATH.'/'.Registry::get('media_directory').'/'.$dir;
            // the folder may no longer exists, just the entry in the db
            if(is_dir($fulldir)) {
// !!!!! TODO: Derzeit wird das Ergebnis der Loeschen-Operation ignoriert !!!!!!
                $result = Directory::removeDirectory($fulldir);
            } else {
                $result = true;
            }
            if($id && $result) {
                $sth = self::db()->query(
                    'DELETE FROM `:prefix:media_dirs` WHERE `dir_id`=?',
                    array($id)
                );
            }
            return self::db()->isError() ? false : true;
        }   // end function removeFolder()

        /**
         *
         * @access public
         * @return
         **/
        public static function updateFiles($dir,$filter=NULL,$recurse=false)
        {
            // first, make sure the directory exists, and is already present in
            // the database
            if(!is_dir($dir)) return false;

            self::updateFolderData($dir);

            // get the ID
            $id       = self::getFolderID($dir);

            // no ID?
            if(!$id) return false;

            $data     = array();
            $suffixes = array();

            if($filter)
            {
                $suffixes = \CAT\Helper\Mime::getAllowedFileSuffixes($filter);
                if(!count($suffixes))
                    return false;
            }
            $files = Directory::findFiles(
                $dir,array('extension'=>$suffixes,'recurse'=>$recurse)
            );

            // load file data from database
            $sth = self::db()->query(
                  'SELECT * FROM `:prefix:media_files` AS `t1` '
                . 'WHERE `t1`.`dir_id`=?',
                array($id)
            );
            $dbdata  = $sth->fetchAll();
            $dbfiles = array();
            if(is_array($dbdata) && count($dbdata))
            {
                foreach($dbdata as $index => $item)
                {
                    $dbfiles[$item['filename']] = $item;
                }
            }

            // add missing files
            if(is_array($files) && count($files)>0)
            {
                foreach($files as $file)
                {
                    $decoded_filename = Directory::getName(pathinfo($file,PATHINFO_BASENAME));
                    if(!array_key_exists($decoded_filename,$dbfiles))
                    {
                        self::db()->query(
                              'INSERT INTO `:prefix:media_files` ( `site_id`, `dir_id`, `filename`, `checksum` ) '
                            . 'VALUES (?, ?, ?, ? )',
                            array(CAT_SITE_ID, $id, $decoded_filename, sha1_file($file))
                        );
                        $fid = self::db()->lastInsertId();
                        self::analyzeFile($fid,$file);
                    }
                }
            }

            // mark missing files
            if(is_array($dbfiles) && count($dbfiles)>0)
            {
                foreach($dbfiles as $item)
                {
                    if(!in_array($dir.$item['filename'],$files))
                    {
                        self::db()->query(
                              'UPDATE `:prefix:media_files` '
                            . 'SET `deleted`=1 WHERE `filename`=?',
                            array($item['filename'])
                        );
                    }
                }
            }

        }   // end function updateFiles()
        
        /**
         * scan for new folders and mark removed folders as "deleted"
         *
         * @access public
         * @return
         **/
        public static function updateFolderData($dir)
        {
            // under some circumstances, the 'media' folder is added twice
            $dir = str_ireplace(
                \CAT\Registry::get('media_directory').'/'.\CAT\Registry::get('media_directory'),
                \CAT\Registry::get('media_directory'),
                $dir
            );
            // get subfolders
            $subfolders = Directory::findDirectories(
                $dir, array('recurse'=>true,'remove_prefix'=>CAT_PATH.'/'.Registry::get('media_directory').'/')
            );
            // add the dir itself to the folders to check
            array_unshift($subfolders,self::getDirname($dir));
            // get folders already in DB
            $dbfolders = self::getFolders();
            // check real folders against DB
            if(is_array($subfolders) && count($subfolders)>0)
            {
                $lookup1 = array_flip($subfolders);
                $lookup2 = array();
                if(is_array($dbfolders) && count($dbfolders)>0) {
                    foreach($dbfolders as $item) {
                        $lookup2[$item['path']] = 1;
                    }
                }
                // add folders to db
                foreach($subfolders as $folder)
                {
                    if(!array_key_exists(Directory::getName(ltrim($folder,'/')),$lookup2))
                    {
                        self::db()->query(
                            'INSERT INTO `:prefix:media_dirs` (`site_id`,`path`) VALUES (?,?)',
                            array(CAT_SITE_ID,$folder)
                        );
                    }
                }
                // mark deleted folders
                if(is_array($dbfolders) && count($dbfolders)>0) {
                    foreach($dbfolders as $item) {
                        if($item['path']!='' && !array_key_exists($item['path'],$lookup1)) {
                            self::db()->query(
                                'UPDATE `:prefix:media_dirs` SET `deleted`=1 WHERE `path`=?',
                                array($item['path'])
                            );
                        }
                    }
                }
            }
        }   // end function updateFolderData()
        

        /**
         *
         * @access protected
         * @return
         **/
        protected static function analyzeFile($id,$filename)
        {
            $self = self::getInstance();
            $info = $self->fileinfo()->analyze($filename);
            $data = array();

            // base data
            foreach(array_values(self::$tag_map['basedata']) as $attr)
            {
                $data[$attr]
                    = isset($info[$attr]) ? $info[$attr] : NULL;

                if(!$data[$attr])
                {
                    foreach(array_values(array('video')) as $key)
                    {
                        if(isset($info[$key][$attr]))
                            $data[$attr] = $info[$key][$attr];
                    }
                }

                if($attr!='warning' && $data[$attr] && isset($data[$attr]) && $data[$attr]!='?')
                {
                    self::db()->query(
                          'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                        . 'VALUES(?, ?, ?)',
                        array($id, $attr, $data[$attr])
                    );
                }
            }

            // file size
            if(isset($data['filesize']) && $data['filesize'] != 'n/a')
            {
                $data['hfilesize'] = Directory::humanize($data['filesize']);
                self::db()->query(
                      'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                    . 'VALUES(?, ?, ?)',
                    array($id, 'filesize', $data['filesize'])
                );
            }

            // modification time
            $data['moddate'] = \CAT\Helper\DateTime::getDateTime(Directory::getModdate($filename));
            self::db()->query(
                  'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                . 'VALUES(?, ?, ?)',
                array($id, 'moddate', $data['moddate'])
            );

            if(isset($info['mime_type']))
            {
                $tmp = array();
                list($group,$type) = explode('/',$info['mime_type']);
                switch($group)
                {
                    case 'video':
                        $data['video']    = true;
                        break;
                    case 'image':
                        if($type == 'jpeg') $type = 'jpg';
                        $data['image']    = true;
                        $data['url']      = \CAT\Helper\Validate::path2uri($filename);
                        break;
                }

                if(isset($info[$type]) && isset($info[$type]['exif']))
                {
                    foreach(self::$tag_map as $key => $attrs)
                    {
                        if(isset($info[$type]['exif'][$key]))
                        {
                            $arr = $info[$type]['exif'][$key];
                            foreach($attrs as $attr)
                            {
                                $tmp[$attr] = ( isset($arr[$attr]) ? $arr[$attr] : '?' );
                                self::db()->query(
                                      'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                                    . 'VALUES(?, ?, ?)',
                                    array($id, $attr, $tmp[$attr])
                                );
                            }
                        }
                    }
                    $data['exif'] = $tmp;
                }

                if(
                       isset($info['tags'])
                    && isset($info['tags']['iptc'])
                    && isset($info['tags']['iptc']['IPTCApplication'])
                    && isset($info['tags']['iptc']['IPTCApplication']['CopyrightNotice'])
                ) {
                    $data['copyright']
                        = is_array($info['tags']['iptc']['IPTCApplication']['CopyrightNotice'])
                        ? $info['tags']['iptc']['IPTCApplication']['CopyrightNotice'][0]
                        : $info['tags']['iptc']['IPTCApplication']['CopyrightNotice'];
                    self::db()->query(
                          'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                        . 'VALUES(?, ?, ?)',
                        array($id, 'copyright', $data['copyright'])
                    );
                }
            }

            return $data;
        }   // end function analyzeFile()
        
        

    }   // ----- class \CAT\Helper\Media -----
}