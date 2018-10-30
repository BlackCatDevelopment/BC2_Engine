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

if (!class_exists('\CAT\Helper\DateTime'))
{
    class DateTime extends Base
    {
        private static $instance;

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }

        public function __call($method, $args)
        {
            if ( ! isset($this) || ! is_object($this) )
                return false;
            if ( method_exists( $this, $method ) )
                return call_user_func_array(array($this, $method), $args);
        }

        /**
         * check given date format
         *
         * @access public
         * @param  string $df
         * @return boolean
         **/
        public static function checkDateformat($date_format)
        {
            $date_format_key	= str_replace(' ', '|', $date_format);
            $date_formats       = \CAT\Helper\DateTime::getDateFormats();
            return array_key_exists( $date_format_key, $date_formats );
        }   // end function checkDateformat()

        public static function checkTimeformat($time_format)
        {
            $time_format_key	= str_replace(' ', '|', $time_format);
            $time_formats       = \CAT\Helper\DateTime::getTimeFormats();
            return array_key_exists($time_format_key, $time_formats);
        }   // end function checkTimeformat()

        /**
         * check given timezone
         * the timezone string must match a value in the table
         *
         * @access public
         * @param  string  $tz
         * @return boolean
         **/
        public static function checkTZ($tz)
        {
            $timezone_table     = \CAT\Helper\DateTime::getTimezones();
            if ( in_array($tz, $timezone_table) )
            	return true;
            return false;
        }   // end function checkTZ()

        /**
         * returns formatted date
         *
         * @access public
         * @param  string  $t    - optional timestamp
         * @param  boolean $long - get long format (default:false)
         * @return string
         **/
        public static function getDate($t=NULL,$long=false)
        {
            $format = ( $long == true )
                    ? self::getDefaultDateFormatLong()
                    : self::getDefaultDateFormatShort();
            return strftime($format,($t?$t:time()));
        }   // end function getDate()

        /**
         * returns formatted time
         *
         * @access public
         * @param  string  $t   - optional timestamp
         * @return string
         **/
        public static function getTime($t=NULL)
        {
            $format = self::getDefaultTimeFormat();
            return strftime($format,($t?$t:time()));
        }   // end function getTime()

        /**
         * returns formatted date and time
         *
         * @access public
         * @param  string  $t   - optional timestamp
         * @return string
         **/
        public static function getDateTime($t=NULL)
        {
            return strftime(
                sprintf(
                    '%s %s',
                    self::getDefaultDateFormatShort(),
                    self::getDefaultTimeFormat()
                ),
                ($t?$t:time())
            );
        }   // end function getDateTime()

        /**
         * get currently used timezone string
         **/
        public static function getTimezone()
        {
            $tz = \CAT\Helper\Validate::fromSession('TIMEZONE_STRING');
            return
                isset($tz)
                ? $tz
                : DEFAULT_TIMEZONE_STRING;
        }

        /**
         * returns a list of known timezones, using DateTimeZone::listIdentifiers()
         **/
        public static function getTimezones()
        {
            return DateTimeZone::listIdentifiers();
        }   // end function getTimezones()

        /**
         * returns a list of known time formats
         **/
        public static function getTimeFormats()
        {
            global $user_time,$language_time;
            $actual_time = time();
            $TIME_FORMATS = array(
                '%I:%M|%p' => strftime('%I:%M %p', $actual_time),
                '%H:%M:%S' => strftime('%H:%M:%S', $actual_time),
                '%H:%M'    => strftime('%H:%M'   , $actual_time),
            );
            if(isset($user_time) AND $user_time == true) {
           		$TIME_FORMATS['system_default'] = date(DEFAULT_TIME_FORMAT, $actual_time).' (System Default)';
                $TIME_FORMATS = array_reverse($TIME_FORMATS, true);
            }
            if(isset($language_time) && !array_key_exists($language_time,$TIME_FORMATS))
            {
                $TIME_FORMATS[$language_time] = date($language_time,$actual_time);
            }
            return $TIME_FORMATS;
        }   // end function getTimeFormats()

        /**
         * returns a list of known date formats
         **/
        public static function getDateFormats()
        {
            global $user_time, $language_date_long, $language_date_short;
            $actual_time = time();
            $locale      = setlocale(LC_ALL, 0);
            $ord         = date('S', $actual_time);
            $ord_long    = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
                         ? '%#d #O# %B, %Y'
                         : '%e #O# %B, %Y';
            $j_short     = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
                         ? '%#d.%-m.%Y'
                         : '%e.%-m.%Y';
            $long        = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
                         ? '%A, %#d %B, %Y'
                         : '%A, %e %B, %Y';
            if ( defined('LANGUAGE') ) setlocale(LC_ALL, LANGUAGE);
            $DATE_FORMATS = array(
                '%A,|%e|%B,|%Y' => utf8_encode(strftime($long, $actual_time)),
                '%e|%B,|%Y'     => utf8_encode(strftime(str_replace(' #O#', $ord, $ord_long), $actual_time)).' (jS F, Y)',
                '%d|%m|%Y'      => utf8_encode(strftime('%d %m %Y',      $actual_time)).' (d M Y)',
                '%b|%d|%Y'      => utf8_encode(strftime('%b %d %Y',      $actual_time)).' (M d Y)',
                '%a|%b|%d,|%Y'  => utf8_encode(strftime('%a %b %d, %Y',  $actual_time)).' (D M d, Y)',
                '%d-%m-%Y'      => utf8_encode(strftime('%d-%m-%Y',      $actual_time)).' (D-M-Y)',
                '%m-%d-%Y'      => utf8_encode(strftime('%m-%d-%Y',      $actual_time)).' (M-D-Y)',
                '%d.%m.%Y'      => utf8_encode(strftime('%d.%m.%Y',      $actual_time)).' (D.M.Y)',
                '%m.%d.%Y'      => utf8_encode(strftime('%m.%d.%Y',      $actual_time)).' (M.D.Y)',
                '%d/%m/%Y'      => utf8_encode(strftime('%d/%m/%Y',      $actual_time)).' (D/M/Y)',
                '%m/%d/%Y'      => utf8_encode(strftime('%m/%d/%Y',      $actual_time)).' (M/D/Y)',
                #'%e.%-m.%Y'     => utf8_encode(strftime($j_short,        $actual_time)).' (j.n.Y)',
                '%a, %d %b %Y %H:%M:%S %z' => utf8_encode(strftime('%a, %d %b %Y %H:%M:%S %z',      $actual_time)).' (r)',
                '%A,|%d.|%B|%Y' => utf8_encode(strftime('%A, %d. %B %Y',  $actual_time)),        // German date
            );
            if(isset($user_time) && $user_time == true)
            {
		        $DATE_FORMATS['system_default'] = date(DEFAULT_DATE_FORMAT, $actual_time).' (System Default)';
                $DATE_FORMATS = array_reverse($DATE_FORMATS, true);
	        }
            if(isset($language_date_long) && !array_key_exists($language_date_long,$DATE_FORMATS))
            {
                $DATE_FORMATS[$language_date_long] = date($language_date_long,$actual_time);
            }
            if(isset($language_date_short) && !array_key_exists($language_date_short,$DATE_FORMATS))
            {
                $DATE_FORMATS[$language_date_short] = date($language_date_short,$actual_time);
            }
            if ( defined('LANGUAGE') ) setlocale(LC_ALL, $locale);
            return $DATE_FORMATS;
        }   // enc function getDateFormats()

        /**
         * returns the default time format:
         *   - checks $_SESSION['TIME_FORMAT'] first;
         *   - returns global default if not set
         **/
        public static function getDefaultTimeFormat()
        {
            global $language_time;
            // user defined format
            if ( isset ($_SESSION['TIME_FORMAT']) ) return $_SESSION['TIME_FORMAT'];
            return Registry::get('time_format');
        }   // end function getDefaultTimeFormat()

        /**
         * returns the default date format (short)
         *   - checks $_SESSION['DATE_FORMAT'] first;
         *   - returns global default if not set
         **/
        public static function getDefaultDateFormatShort()
        {
            global $language_date_short;
            // user defined format
            if(isset($_SESSION['DATE_FORMAT'])) return $_SESSION['DATE_FORMAT'];
            return Registry::get('date_format');
        }   // end function getDefaultDateFormatShort()

        /**
         * combines DATE_FORMAT with TIME_FORMAT
         **/
        public static function getDefaultDateFormatLong()
        {
            global $language_date_long;
            $format = Registry::get('date_format');
            $format .= ' ' . self::getDefaultTimeFormat();
            return $format;
        }   // end function getDefaultDateFormatLong()
    }
}
