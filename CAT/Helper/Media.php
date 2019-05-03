<?php

/**
 *   @author          Black Cat Development
 *   @copyright       2013 - 2018 Black Cat Development
 *   @link            https://blackcat-cms.org
 *   @license         http://www.gnu.org/licenses/gpl.html
 *   @category        CAT_Core
 *   @package         CAT_Core
 **/

namespace CAT\Helper;
use \CAT\Base as Base;

if (!class_exists('\CAT\Helper\Media'))
{
    class Media extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;

        /**
         * @var
         **/
        protected static $mimetypes;
        /**
         * @var
         **/
        protected static $suffixes;

        /**
         * ID3 attributes to retrieve
         **/
        protected static $tag_map = array(
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

        /**
         * retrieve a list of suffixes
         *
         * @access public
         * @param  string  $filter - optional filter, for example, 'image/*'
         * @return array
         **/
        public static function getAllowedFileSuffixes($filter=NULL)
        {
            if(!is_array(self::$mimetypes) || !count(self::$mimetypes)) {
                self::getMimeTypes();
            }
            if($filter) {
                self::log()->addDebug(sprintf(
                    'using filter (preg_match) [~^%s~]',$filter
                ));
                $temp = new \Ds\Set();;
                foreach(self::$mimetypes as $suffix => $types) {
                    foreach($types as $type) {
                        if(preg_match('~^'.$filter.'~', $type)) {
                            $temp->add($suffix);
                        }
                    }
                }
                return $temp->toArray();
            }
            return self::$suffixes;
        }   // end function getAllowedFileSuffixes()

        /**
         * retrieve appropriate content-type for given suffix
         *
         * @access public
         * @param  string  $suffix
         * @return
         **/
        public static function getContentType(string $suffix) : string
        {
            if(!is_array(self::$mimetypes)) {
                self::getMimeTypes();
            }
            if(!isset(self::$mimetypes[$suffix]) || ! is_array(self::$mimetypes[$suffix]) || !count(self::$mimetypes[$suffix])>0) {
                return 'application/octet-stream';
            } else {
                return self::$mimetypes[$suffix][0];
            }
        }   // end function getContentType()

        /**
         * get files of given $currentFolder
         *
         * @access public
         * @param  string  $currentFolder
         * @return array
         **/
        public static function getFiles(string $currentFolder, string $type, $details=false) : array
        {
            $typelist = array();
            // filter by type
            if(!empty($type)) {
                $typelist = self::getAllowedFileSuffixes($type);
            }

            $aFiles   = Directory::findFiles(
                $currentFolder,
                array(
                    'remove_prefix' => $currentFolder.'/',
                    'extensions'    => $typelist,
                )
            );
            if(is_array($aFiles) && count($aFiles)>0) {
                natcasesort($aFiles);
                if($details==true) {
                    $data = array();
                    for($i=0; $i<count($aFiles); $i++) {
                        $details = self::analyzeFile($currentFolder.'/'.$aFiles[$i]);
                        $data[$aFiles[$i]] = $details;
                    }
                    return $data;
                }
                return $aFiles;
            }
            return array();
        }   // end function getFiles()

        /**
         * get subfolders of given $currentFolder
         *
         * @access public
         * @param  string  $currentFolder
         * @return array
         **/
        public static function getFolders(string $currentFolder, $details=false, $recurse=false) : array
        {
            // find subdirs
            $aFolders   = Directory::findDirectories(
                $currentFolder,
                array(
                    'remove_prefix' => false,
                    'recurse'       => $recurse,
                )
            );

            if(is_array($aFolders) && count($aFolders)) {
                $data = array();
                for($i=0; $i<count($aFolders); $i++) {
                    $subfolder = str_ireplace(self::user()->getHomeFolder().'/','',$aFolders[$i]);
                    $data[$aFolders[$i]] = $subfolder;
                    if($details==true) {
                        $details = Directory::getDirectoryItemCount($currentFolder.'/'.$aFolders[$i]);
                        $size    = Directory::getDirectorySize($currentFolder.'/'.$aFolders[$i],true);
                        $data[$aFolders[$i]] = array_merge(
                            $details,
                            array(
                                'size'   => $size,
                                'folder' => $subfolder,
                                'name'   => pathinfo($subfolder,PATHINFO_FILENAME)
                            )
                        );
                    }
                }
                return $data;
            }
            return array();
        }   // end function getFolders()

        /**
         * retrieve known Mime types from the DB; only entries with registered
         * suffixes and labels are considered
         *
         * @access public
         * @return array
         **/
        public static function getMimeTypes()
        {
            if (!is_array(self::$mimetypes)) {
                self::log()->logDebug('getting known mimetypes from DB');
                $res = self::db()->query(
                    'SELECT * FROM `:prefix:mimetypes` WHERE `mime_suffixes` IS NOT NULL AND `mime_label` IS NOT NULL'
                );
                if ($res) {
                    while (false!==($row=$res->fetch())) {
                        $suffixes = explode('|', $row['mime_suffixes']);
                        foreach ($suffixes as $suffix) {
                            if ($suffix == '') {
                                continue;
                            }
                            if (! isset(self::$mimetypes[$suffix])) {
                                self::$mimetypes[$suffix] = array();
                            }
                            self::$mimetypes[$suffix][] = $row['mime_type'];
                        }
                    }
                }
                self::log()->addDebug('registered mime types: '.print_r(self::$mimetypes,1));
            }
            self::$suffixes = array_keys(self::$mimetypes);
            return self::$mimetypes;
        }   // end function getMimeTypes()

        /**
         *
         * @access public
         * @return
         **/
        public static function isImage(string $filename) : bool
        {
            if(!is_array(self::$mimetypes)) {
                self::getMimeTypes();
            }
            // unknown extension
            if(!isset(self::$mimetypes[pathinfo($filename,PATHINFO_EXTENSION)])) {
                return false;
            }
            $type  = self::$mimetypes[pathinfo($filename,PATHINFO_EXTENSION)];
            if(!is_array($type)) {
                $type = array($type);
            }
            foreach($type as $t) {
                if(substr_compare($t,'image/',0,6)==0) {
                    return true;
                }
            }
            return false;
        }   // end function isImage()


/*******************************************************************************
 * protected functions
 ******************************************************************************/

        /**
         *
         * @access protected
         * @return
         **/
        protected static function analyzeFile($filename)
        {
            $info = self::fileinfo()->analyze($filename);
            $data = array();

            // base data
            foreach (array_values(self::$tag_map['basedata']) as $attr) {
                $data[$attr]
                    = isset($info[$attr]) ? $info[$attr] : null;

                if (!$data[$attr]) {
                    foreach (array_values(array('video')) as $key) {
                        if (isset($info[$key][$attr])) {
                            $data[$attr] = $info[$key][$attr];
                        }
                    }
                }
/*
                if ($attr!='warning' && $data[$attr] && isset($data[$attr]) && $data[$attr]!='?') {
                    self::db()->query(
                          'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                        . 'VALUES(?, ?, ?)',
                        array($id, $attr, $data[$attr])
                    );
                }
*/
            }

            // suffix; may be used by template css
            $data['filetype'] = pathinfo($filename,PATHINFO_EXTENSION);

            // file size
            if (isset($data['filesize']) && $data['filesize'] != 'n/a') {
                $data['hfilesize'] = Directory::humanize($data['filesize']);
/*
                self::db()->query(
                      'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                    . 'VALUES(?, ?, ?)',
                    array($id, 'filesize', $data['filesize'])
                );
*/
            }

            // modification time
            $data['moddate'] = \CAT\Helper\DateTime::getDateTime(Directory::getModdate($filename));
/*
            self::db()->query(
                  'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                . 'VALUES(?, ?, ?)',
                array($id, 'moddate', $data['moddate'])
            );
*/
            if (isset($info['mime_type'])) {
                $tmp = array();
                list($group, $type) = explode('/', $info['mime_type']);
                switch ($group) {
                    case 'video':
                        $data['video']    = true;
                        break;
                    case 'image':
                        if ($type == 'jpeg') {
                            $type = 'jpg';
                        }
                        $data['image']    = true;
                        $data['url']      = Validate::path2uri($filename);
                        break;
                }

                if (isset($info[$type]) && isset($info[$type]['exif'])) {
                    foreach (self::$tag_map as $key => $attrs) {
                        if (isset($info[$type]['exif'][$key])) {
                            $arr = $info[$type]['exif'][$key];
                            foreach ($attrs as $attr) {
                                $tmp[$attr] = (isset($arr[$attr]) ? $arr[$attr] : '?');
/*
                                self::db()->query(
                                      'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                                    . 'VALUES(?, ?, ?)',
                                    array($id, $attr, $tmp[$attr])
                                );
*/
                            }
                        }
                    }
                    $data['exif'] = $tmp;
                }

                if (
                       isset($info['tags'])
                    && isset($info['tags']['iptc'])
                    && isset($info['tags']['iptc']['IPTCApplication'])
                    && isset($info['tags']['iptc']['IPTCApplication']['CopyrightNotice'])
                ) {
                    $data['copyright']
                        = is_array($info['tags']['iptc']['IPTCApplication']['CopyrightNotice'])
                        ? $info['tags']['iptc']['IPTCApplication']['CopyrightNotice'][0]
                        : $info['tags']['iptc']['IPTCApplication']['CopyrightNotice'];
/*
                    self::db()->query(
                          'INSERT INTO `:prefix:media_data` ( `media_id`, `attribute`, `value` ) '
                        . 'VALUES(?, ?, ?)',
                        array($id, 'copyright', $data['copyright'])
                    );
*/
                }
            }

            return $data;
        }   // end function analyzeFile()

    } // class Media
} // if class_exists()