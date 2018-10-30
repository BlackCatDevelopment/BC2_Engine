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
use \CAT\Registry as Registry;

if (!class_exists('\CAT\Helper\GitHub'))
{
    class GitHub extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        private static $ch         = NULL;
        private static $curl_error = NULL;

        /**
         * initializes CUrl
         *
         * @access public
         * @param  string  $url - optional
         * @return object  curl connection
         **/
        public static function init_curl($url=NULL)
        {
            if(self::$ch) return self::$ch;
            self::$ch = curl_init();
            self::reset_curl();
            if($url)
                curl_setopt(self::$ch, CURLOPT_URL, $url);
            return self::$ch;
        }   // end function init_curl()

        /**
         * reset curl options to defaults
         *
         * @access public
         * @return
         **/
        public static function reset_curl()
        {
            if(self::$ch) curl_close(self::$ch);
            self::$ch = curl_init();
            $headers  = array(
                'User-Agent: php-curl'
            );
            curl_setopt(self::$ch, CURLOPT_FOLLOWLOCATION, true    );
            curl_setopt(self::$ch, CURLOPT_RETURNTRANSFER, true    );
            curl_setopt(self::$ch, CURLOPT_SSL_VERIFYHOST, false   );
            curl_setopt(self::$ch, CURLOPT_SSL_VERIFYPEER, false   );
            curl_setopt(self::$ch, CURLOPT_MAXREDIRS     , 2       );
            curl_setopt(self::$ch, CURLOPT_HTTPHEADER    , $headers);
            if(Registry::exists('PROXY'))
                curl_setopt(self::$ch, CURLOPT_PROXY, Registry::get('PROXY'));
            if(Registry::exists('PROXY_PORT'))
                curl_setopt(self::$ch, CURLOPT_PROXYPORT, Registry::get('PROXY_PORT'));
            return self::$ch;
        }   // end function reset_curl()

        /**
         *
         * @access public
         * @return
         **/
        public static function getRelease($org,$repo)
        {
            $releases   = self::retrieve($org,$repo,'releases');
            $latest     = array();
            if(is_array($releases) && count($releases))
            {
                foreach($releases as $r)
                {
                    if($r['prerelease']==1) continue;
                    $latest = $r;
                    break;
                }
                if(is_array($latest)) {
                    return $latest;
                }
            }
            return false;
        }   // end function getRelease()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function getTags($org,$repo)
        {
            $tags   = self::retrieve($org,$repo,'tags');
            $latest = array();
            if(is_array($tags) && count($tags))
            {
                return $tags;
            }
            return false;
        }   // end function getTags()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function getZip($dlurl,$path,$filename)
        {
            $ch   = self::init_curl();
            curl_setopt($ch, CURLOPT_URL, $dlurl);
            $data = curl_exec($ch);
            if(curl_error($ch))
            {
                self::setError(trim(curl_error($ch)));
                return false;
            }
            if(curl_getinfo($ch,CURLINFO_HTTP_CODE)==302) // handle redirect
            {
                preg_match('/Location:(.*?)\n/', $data, $matches);
                $newUrl = trim(array_pop($matches));
                curl_setopt($ch, CURLOPT_URL, $newUrl);
                $data  = curl_exec($ch);
                if(curl_error($ch))
                {
                    self::setError(trim(curl_error($ch)));
                    return false;
                }
            }

            if(!$data || curl_error($ch)) {
                self::setError(trim(curl_error($ch)));
                return false;
            }

            if(!is_dir($path)) mkdir($path,0770);
            $file = $filename.'.zip';
            $fd   = fopen($path.'/'.$file, 'w');
            fwrite($fd, $data);
            fclose($fd);

            if(filesize($path.'/'.$file)) return true;
            else                          self::setError('Filesize '.filesize($path.'/'.$file));

            return false;
        }   // end function getZip()
        

        /**
         * retrieve GitHub info about the given repository;
         * throws Exception on error
         *
         * @access public
         * @param  string  $org  - organisation name
         * @param  string  $repo - repository name
         * @param  string  $url  - sub url
         * @return json
         **/
        public static function retrieve($org,$repo,$url)
        {
            $ch   = self::reset_curl(); // fresh connection
            $url  = sprintf('https://api.github.com/repos/%s/%s/%s',
                    $org, $repo, $url);
            try {
                //echo "retrieve url: $url<br />";
                curl_setopt($ch,CURLOPT_URL,$url);
                $result = json_decode(curl_exec($ch), true);
                if($result)
                {
                    if(isset($result['documentation_url']))
                        self::printError( "GitHub Error: ", $result['message'], "<br />URL: $url<br />" );
                    return $result;
                }
                else
                {
                    self::setError(curl_error($ch));
                    return false;
                }
            } catch ( Exception $e ) {
                self::printError( "CUrl error: ", $e->getMessage(), "<br />" );
            }
        }   // end function retrieve()

        /**
         * get the size of a remote file
         *
         * @access public
         * @param  string  $url
         * @return string
         **/
        public static function retrieve_remote_file_size($url)
        {
             $ch = self::init_curl();
             curl_setopt($ch, CURLOPT_HEADER, TRUE);
             curl_setopt($ch, CURLOPT_NOBODY, TRUE);
             curl_setopt($ch, CURLOPT_URL, $url);
             $data = curl_exec($ch);
             $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
             return $size;
        }

        /**
         *
         * @access public
         * @return
         **/
        public static function getError()
        {
            return self::$curl_error;
        }   // end function getError()

        /**
         *
         * @access public
         * @return
         **/
        public static function resetError()
        {
            self::$curl_error = NULL;
        }   // end function resetError()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function setError($error)
        {
            self::$curl_error = $error;
        }   // end function setError()
        
    } // class GitHub

} // if class_exists()