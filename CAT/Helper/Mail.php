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

if (!class_exists('\CAT\Helper\Mail'))
{
    class Mail extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;
        /**
         * @var
         **/
        protected static $instance    = null;
        protected static $drivers     = array('Swift','PHPMailer');
        protected static $settings = array(
            'routine'            => 'smtp',
            'smtp_auth'          => '',
            'smtp_host'          => '',
            'smtp_password'      => '',
            'smtp_username'      => '',
            'default_sendername' => 'Black Cat CMS Mailer',
        );

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance(string $driver='Swift')
        {
            if(empty(self::$instance) || !is_object(self::$instance)) {
                $driver_path = realpath(__DIR__).'/Mail/'.$driver.'Driver.php';
                if(!file_exists($driver_path)) {
                    self::printFatalError('Unable to send mail');
                }
                require $driver_path;
                $driver = '\CAT\Helper\Mail\\'.$driver.'Driver';
                self::$settings['smtp_host']     = self::getSetting('mail_server');
                self::$settings['smtp_password'] = self::getSetting('mail_password');
                self::$settings['smtp_username'] = self::getSetting('mail_username');
                self::$instance = $driver::getInstance(self::$settings);
            }
            return self::$instance;
        }   // end function getInstance()
        

    } // class Mail
} // if class_exists()