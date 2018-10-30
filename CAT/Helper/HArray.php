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

if(!class_exists('\CAT\Helper\HArray'))
{
	class HArray extends Base
	{

        protected static $loglevel  = \Monolog\Logger::EMERGENCY;
        private   static $instance  = NULL;
        private   static $filtered  = null;

        public function __call($method, $args)
        {
            if ( ! isset($this) || ! is_object($this) )
                return false;
            if ( method_exists( $this, $method ) )
                return call_user_func_array(array($this, $method), $args);
        }

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         * allows to reorder the $_FILES array if the 'multiple' attribute
         * was set on the file upload field; see
         * http://de1.php.net/manual/de/reserved.variables.files.php#109958
         * for details
         *
         * @access public
         * @param  array  $vector
         * @return array
         **/
        public static function diverse($vector) {
            $result = array();
            foreach($vector as $key1 => $value1)
                foreach($value1 as $key2 => $value2)
                    $result[$key2][$key1] = $value2;
            return $result;
        }   // end function diverse()

        /**
         * encode all entries of an multidimensional array into utf8
         * http://de1.php.net/manual/de/function.json-encode.php#100492
         *
         * @access public
         * @param  array  $dat
         * @return array
         **/
        public static function encodeUTF8($dat)
        {
            if (is_string($dat)) return utf8_encode($dat);
            if (!is_array($dat)) return $dat;
            $ret = array();
            foreach($dat as $i=>$d) $ret[$i] = self::encodeUTF8($d);
            return $ret;
        }   // end function encodeUTF8()

        /**
         *
         * @access public
         * @return
         **/
        public static function extract()
        {
            call_user_func_array(array('self','filter'), func_get_args());
            return json_decode(json_encode(self::$filtered), true);
        }   // end function extract()
        

        /**
         * extracts the values of a given key from an array, returning just a
         * list of values
         *
         * @access public
         * @param  array   $array
         * @param  string  $key
         * @return array
         **/
        public static function extractList($array,$key,$index_by=null)
        {
            if(!is_array($array) || !count($array)) return array();
            $result = array();
            foreach($array as $i => $item)
            {
                if(isset($item[$key]) && is_array($item[$key]))
                    $result[] = self::extract($item[$key],$key);
                if(array_key_exists($key,$item))
                    if($index_by && array_key_exists($index_by,$item))
                        $result[$item[$index_by]] = $item[$key];
                    else
                        $result[] = $item[$key];

            }
            return $result;
        }   // end function extractList()

        /**
         * filter array; this means the items that match the filter are
         * removed
         *
         * Examples:
         *    Filter by key-value-pair
         *        $filtered = filter($array,array($key=>$value));
         *
         *    Filter by value
         *        $filtered = filter($array,null,<Value to find>);
         *
         * @access  public
         * @param   array    input array
         * @param   mixed    filter options
         * @param   array    filtered array (items that do not match)
         **/
        public static function filter()
        {
            $arguments = func_get_args();
            $origarray = array_shift($arguments); // first arg must be the input array
            $array     = $origarray;

            if(!is_array($array) || !count($array)) return array();

            $filterby = array_shift($arguments);
            $result   = new \stdClass();

            if(!$filterby)
            {
                $byvalue = array_shift($arguments);
                foreach($array as $k => $v)
                {
                    if(is_array($v))
                    {
                        $subresult = self::filter($v,null,$byvalue);
                        if(count($subresult)) {
                            $result->{$k} = $subresult;
                        }
                    }
                    else
                    {
                        if($v===$byvalue) {
                            $result->{$k} = $v;
                        }
                    }
                }
                return json_decode(json_encode($result), true);
            }

            // filter by key/value
            if(!is_callable($filterby) && is_scalar($filterby))
            {
                $key  = $filterby;
                $cond = null;
                $val  = array_shift($arguments);
                if(count($arguments)) $cond = array_shift($arguments);
                foreach($array as $k => $v)
                {
                    if(is_array($v))
                    {
                        if(array_key_exists($key,$v)) {
                            switch($cond) {
                                case 'matching':
                                    if($v[$key] == $val) {
                                        $result->{$k} = $v;
                                    }
                                    break;
                                default:
                                    if($v[$key] !== $val) {
                                        $result->{$k} = $v;
                                    }
                                    break;
                            }
                        } else {
                            $result->{$k} = self::filter($v,$key,$val);
                        }
                    }
                    else
                    {
echo "not an array<br />";
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// TODO: not implemented yet
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                        $result->{$k} = '?';
                    }
                }
            }

            // store filtered items for later use (extract() method)
            // Compare all values by a json_encode
            $diff = array_diff(array_map('json_encode', $origarray),array_map('json_encode', (array)$result));

            // Json decode the result
            self::$filtered = array_map('json_decode', $diff);

            return json_decode(json_encode($result), true);
        }   // end function filter()

        /**
         * recursive function to check if a given array key exists
         *
         * @access public
         * @param  string    $key
         * @param  reference $array
         * @return boolean
         **/
        public static function keyExists($key,&$array)
        {
            if(!is_array($array))   return false;
            if(isset($array[$key])) return true;
            foreach($array as $elem)
            {
                if(is_array($elem))
                {
                    return self::keyExists($elem,$key);
                }
            }
            return false;
        }   // end function keyExists()

        /**
         * search multidimensional array for $Needle
         *
         * @access public
         * @param  string  $Needle
         * @param  array   $Haystack
         * @param  string  $NeedleKey - optional
         * @param  boolean $Strict    - optional, default: false
         * @param  array   $Path      - needed for recursion
         * @return mixed   array (path) or false (not found)
         **/
        public static function search($Needle, $Haystack, $NeedleKey="", $Strict=false, $Path=array())
        {
            if(!is_array($Haystack)) {
                return false;
            }
            reset($Haystack);
            foreach($Haystack as $Key => $Val) {
                if (
                    is_array($Val)
                    &&
                    $SubPath = self::search($Needle,$Val,$NeedleKey,$Strict,$Path)
                ) {
                    $Path = array_merge($Path,Array($Key),$SubPath);
                    return $Path;
                }
                elseif (
                    (!$Strict && $Val  == $Needle && $Key == (strlen($NeedleKey) > 0 ? $NeedleKey : $Key))
                    ||
                    ( $Strict && $Val === $Needle && $Key == (strlen($NeedleKey) > 0 ? $NeedleKey : $Key))
                ) {
                    $Path[]=$Key;
                    return $Path;
                }
            }
            return false;
        }   // end function search()

        /**
         * sort an array
         *
         * @access public
         * @param  array   $array          - array to sort
         * @param  mixed   $index          - key to sort by
         * @param  string  $order          - 'asc' (default) || 'desc'
         * @param  boolean $natsort        - default: false
         * @param  boolean $case_sensitive - sort case sensitive; default: false
         *
         **/
        public static function sort($array,$index,$order='asc',$natsort=false,$case_sensitive=false)
        {
            if(is_array($array) && count($array))
            {
                 foreach(array_keys($array) as $key)
                     $temp[$key] = $array[$key][$index];
                 if(!$natsort)
                 {
                     ($order=='asc') ? asort($temp) : arsort($temp);
                 }
                 else
                 {
                     ($case_sensitive) ? natsort($temp) : natcasesort($temp);
                     if($order != 'asc')
                         $temp = array_reverse($temp,TRUE);
                 }
                 foreach(array_keys($temp) as $key)
                     (is_numeric($key)) ? $sorted[]=$array[$key] : $sorted[$key]=$array[$key];
                 return $sorted;
            }
            return $array;
        }   // end function sort()

        /**
         * make multidimensional array unique
         *
         * @access public
         * @param  array
         * @return array
         **/
        public static function unique($array)
        {
    		$set = array();
    		$out = array();
    		foreach($array as $key => $val)
            {
                if(is_array($val))
                {
                    $out[$key] = self::unique($val);
                }
                elseif(!isset($set[$val]))
                {
                    $out[$key] = $val;
                    $set[$val] = true;
                }
                else
                {
                    $out[$key] = $val;
                }
    		}
    		return $out;
   		}   // end function unique()
    }
}