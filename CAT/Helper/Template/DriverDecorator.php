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

namespace CAT\Helper\Template;

use \CAT\Helper\Template as Template;
use \CAT\Helper\Directory as Directory;
use \CAT\Backend as Backend;

if (!class_exists('DriverDecorator'))
{
    class DriverDecorator extends Template
    {
        protected static $loglevel     = \Monolog\Logger::EMERGENCY;

        public    $template_block;
        protected $last         = NULL;
        private   $te           = NULL;
        private   $seen         = array();
        private   $paths        = array(
            'current'           => NULL,
            'frontend'          => NULL,
            'frontend_fallback' => NULL,
            'backend'           => NULL,
            'backend_fallback'  => NULL,
            'workdir'           => NULL
        );
        private   $search_order = array(
            'current', 'frontend', 'frontend_fallback', 'backend', 'backend_fallback', 'workdir'
        );

        public function __construct( $obj )
        {
            parent::__construct();
            $this->te = $obj;
            // get current working directory
            $callstack = debug_backtrace();
            $this->te->paths['workdir']
                = ( isset( $callstack[0] ) && isset( $callstack[0]['file'] ) )
                ? Directory::sanitizePath(realpath(dirname($callstack[0]['file'])))
                : Directory::sanitizePath(realpath(dirname(__FILE__)));

            if (file_exists( $this->te->paths['workdir'].'/templates' ))
            {
                $this->te->paths['workdir'] .= '/templates';
            }
            $this->te->paths['current'] = $this->te->paths['workdir'];
        }

        public function __call($method, $args)
        {
            if ( ! method_exists( $this->te, $method ) )
                $this->log()->logCrit('No such method: ['.$method.']');
            switch(count($args))
            {
                case 0:
                    return $this->te->$method();
                case 1:
                    return $this->te->$method($args[0]);
                case 2:
                    return $this->te->$method($args[0], $args[1]);
                case 3:
                    return $this->te->$method($args[0], $args[1], $args[2]);
                case 4:
                    return $this->te->$method($args[0], $args[1], $args[2], $args[3]);
                case 5:
                    return $this->te->$method($args[0], $args[1], $args[2], $args[3], $args[4]);
                default:
                    return call_user_func_array(array($this->te, $method), $args);
            }
            //return call_user_func_array(array($this->te, $method), $args);
        }

        /**
         * reset the template search path
         *
         * @access public
         * @param  string  $context (frontend|backend)
         * @return
         **/
        public function resetPath($context='frontend')
        {
            if(Backend::isBackend()) $context = 'backend';
            $this->log()->logDebug(sprintf('resetting path to [%s], context [%s]',$this->last,$context));
            if(!$this->last) return;
            $this->te->paths[$context]  = $this->last;
            $this->te->paths['current'] = $this->last;
            if(isset($this->te->paths[$context.'_fallback']))
                    $this->te->paths[$context.'_fallback'] = $this->last;
        }   // end function resetPath()

        /**
         * set current template search path
         *
         * @access public
         * @param  string  $path
         * @param  string  $context - frontend (default) or backend
         * @return boolean
         *
         **/
         public function setPath($path,$context='frontend')
         {
            if(Backend::isBackend()) $context = 'backend';
            $path = Directory::sanitizePath($path);
            $this->last = NULL;
            $this->log()->logDebug(sprintf('context [%s] path [%s]',$context,$path));
            if(file_exists($path))
            {
                if(isset($this->te->paths[$context]))
                    $this->last = $this->te->paths[$context];
                $this->te->paths[$context]  = $path;
                $this->te->paths['current'] = $path;
                if(!isset($this->te->paths[$context.'_fallback']))
                    $this->te->paths[$context.'_fallback'] = $path;
                return true;
            }
            else
            {
                $this->log()->logWarn( 'unable to set template path: does not exist!', $path );
                return false;
            }
        }   // end function setPath()

        /**
         * set template fallback path (for templates not found in default path)
         *
         * @access public
         * @param  string  $path
         * @param  string  $context - frontend (default) or backend
         * @return boolean
         *
         **/
        public function setFallbackPath($path,$context='frontend')
        {
            $path = Directory::sanitizePath($path);
            $this->log()->logDebug(sprintf('context [%s] fallback path [%s]', $context, $path ));
            if ( file_exists( $path ) ) {
                $this->te->paths[$context.'_fallback'] = $path;
            return true;
            }
            else
            {
                $this->log()->logWarn( 'unable to set fallback template path: does not exist!', $path );
                return false;
            }
        }   // end function setFallbackPath()

        /**
         * set global replacement values
         *
         * Usage
         *    $t->setGlobals( 'varname', 'value' );
         * or
         *    $t->setGlobals( array( 'var1' => 'val1', 'var2' => 'val2', ... ) );
         *
         * The second param is ignored if $var is an array
         *
         * @access public
         * @param  string || array  $var
         * @param  string           $value (optional)
         *
         **/
        public function setGlobals($var,$value=NULL)
        {
            $class = get_class($this->te);
            if(!is_array($var) && isset($value))
            {
               $class::$_globals[$var] = $value;
               return;
            }
            if(is_array($var))
            {
                foreach($var as $k => $v)
                {
                    if(!isset($class::$_globals[$k]))
                    {
                        $class::$_globals[$k] = $v;
                    }
                    else // allows to add items to already existing globals
                    {
                        if(is_array($class::$_globals[$k]))
                        {
                            if(is_array($v))
                            {
                                $class::$_globals[$k] = array_merge(
                                    $class::$_globals[$k],
                                    $v
                                );
                            }
                            else
                            {
                                $class::$_globals[$k][] = $v;
                            }
                        }
                    }
                }
            }

        }  // end function setGlobals()

        /**
         * check if template exists in current search path(s)
         **/
        public function hasTemplate($name)
        {
            $file = $this->findTemplate($name);
            if(is_array($file) && count($file)>0)
                return $file[0];
            else
                return false;
        }   // end function hasTemplate()

        /**
         *
         * @access public
         * @return
         **/
        public function findTemplate($_tpl)
        {
            // remove suffix
            $_tpl = preg_replace('~\.tpl|htt$~i','',$_tpl);

            // cached
            if(isset($this->seen[$this->te->paths['current'] . $_tpl]))
                return $this->seen[$this->te->paths['current'] . $_tpl];

            $suffix = pathinfo($_tpl,PATHINFO_EXTENSION);
            $has_suffix = ( $suffix != '' ) ? true : false;

            // scan search paths (if any)
            $paths = array();
            $s_paths = $this->te->paths;
            // sort paths by key; this sets 'workdir' to the end of the array
            ksort($s_paths);
            // move 'current' to begin
            $temp = array('current' => $s_paths['current']);
            unset($s_paths['current']);
            $s_paths = $temp + $s_paths;
            foreach($s_paths as $key => $value)
                if(isset($s_paths[$key]) && file_exists($s_paths[$key]))
                    $paths[] = $value;
            // remove doubles
            $paths = array_unique($paths);
            self::log()->logDebug('template search paths:',$paths);

            foreach($paths as $dir)
            {
                if($has_suffix && file_exists($dir.'/'.$_tpl))
                {
                    $file = $dir.'/'.$_tpl;
                }
                else
                {
                    $file = Directory::findFiles($dir,array('filename'=>$_tpl,'extensions'=>array('tpl','htt')));
                }
                if(is_array($file) && count($file)>0)
                {
                    $this->seen[$this->te->paths['current'] . $_tpl] = $file[0];
                    return $file[0];
                }
            }
            self::log()->addDebug( "The template [$_tpl] does not exist in one of the possible template paths!", $paths );
        }   // end function findTemplate()

    }
}