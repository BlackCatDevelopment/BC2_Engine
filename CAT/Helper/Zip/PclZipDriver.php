<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       2017 Black Cat Development
   @link            https://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

if(!class_exists('CAT_Helper_Zip_PclZipDriver',false))
{

	class CAT_Helper_Zip_PclZipDriver extends CAT_Object
	{

        // one instance per file
        private   static $instances = array();
	    // holds the PclZip object
	    private   $zip;
	    // holds the Directory helper object
	    private   $dirh;
	    //
	    protected $_config = array(
            'PATH' => false,
	        // ----- PclZip create options: -----
	        // PCLZIP_OPT_ADD_PATH, "/abs/path/to"
			// ability to insert a path
			// do not use by default
			'addPath' => false,
	        // PCLZIP_OPT_REMOVE_PATH, "/usr/local/user"
	        // removes path parts from files
	        // by default, we remove CAT_PATH
	        'removePath' => CAT_PATH,
	        // PCLZIP_OPT_REMOVE_ALL_PATH
	        // removes complete path info from all files
	        // do not use by default
	        'removeAllPath' => false,
	        // PCLZIP_OPT_COMMENT, "Comment"
	        // set a comment in the PKZIP archive
	        // not used by default
	        'setComment' => false,
	        // ----- PclZip extract options: -----
	        // PCLZIP_OPT_PATH, "extract/folder/"
	        // we set this to our temp dir by default
	        //'Path' => CAT_PATH.'/temp',
	        // other:
  			//   PCLZIP_OPT_ADD_PATH
  			//   PCLZIP_OPT_REMOVE_PATH
  			//   PCLZIP_OPT_REMOVE_ALL_PATH
  			// see above
	    );

	    /**
	     * constructor; creates an internal PclZip object
	     **/
		public function __construct($zipfile=NULL)
        {
			if(!class_exists('PclZip',false))
            {
			    define( 'PCLZIP_TEMPORARY_DIR', CAT_Helper_Directory::sanitizePath(CAT_ENGINE_PATH.'/temp'));
				@include CAT_Helper_Directory::sanitizePath(CAT_ENGINE_PATH.'/CAT/vendor/pclzip/pclzip/pclzip.lib.php');
			}
			$this->config('Path',PCLZIP_TEMPORARY_DIR);
		    $this->zip = new PclZip($zipfile);
		    return $this->zip;
		}   // end function __construct()

        /**
         * forward unknown methods to driver
         *
         */
        public function __call($method,$attr)
        {
            if ( method_exists( $this->zip, $method ) )
            {
                return $this->zip->$method($attr[0]);
            }
        }   // end function __call()

        /**
         *
         * @access public
         * @return
         **/
        public function config($attr,$value)
        {
            $this->_config[$attr] = $value;
        }   // end function config()

		/**
		 * accessor to PclZip->listContent()
		 **/
		public function listContent()
  		{
  		    return $this->zip->listContent();
  		}   // end function listContent()

  		/**
		 * accessor to PclZip->errorInfo()
		 * this also strips full path names for security
		 **/
  		public function errorInfo($p_full=false)
  		{
  		    return
                str_ireplace( array(CAT_PATH,'\\'), array('/abs/path/to','/'), $this->zip->errorInfo($p_full) );
  		}   // function errorInfo()

        /**
         *
         *
         *
         *
         **/
        public static function getInstance( $zipfile = NULL )
        {
            if (!isset(self::$instances[$zipfile]) || !is_object(self::$instances[$zipfile]) )
            {
                self::$instances[$zipfile] = new self($zipfile);
            }
            return self::$instances[$zipfile];
        }   // end function getInstance()

        /**
         *
         **/
        public function add($p_filelist)
        {
            // generate function call
			$ret     = NULL;

			if ( is_scalar($p_filelist) )
			{
			    #$p_filelist = CAT_Helper_Directory::sanitizePath($p_filelist);
			}

			$code = '$ret = $this->zip->add( $p_filelist'
			   . $this->compile_options()
			   . ' );';

			eval ( $code );
			return $ret;
        }   // end function add()

		/**
		 * accessor to create() method; only argument is the file list (or a
		 * directory to archive)
		 * All PclZip options have to be set using $zip_helper->config()!
		 *
		 * @access public
		 * @param  mixed  $p_filelist
		 *                An array of filenames or dirnames,
		 *					or
		 *				  A string containing the a filename or a dirname,
		 *					or
		 *				  A string containing a list of filename or dirname
		 *				  separated by a comma.
		 *
		 **/
		public function create($p_filelist)
		{
		    // generate function call
			$ret     = NULL;

			if ( is_scalar($p_filelist) )
			{
			    $p_filelist = CAT_Helper_Directory::sanitizePath($p_filelist);
			}

			$code = '$ret = $this->zip->create( $p_filelist'
			   . $this->compile_options()
			   . ' );';

			eval ( $code );
			return $ret;

		}   // end function create()

		/**
		 * accessor to extract() method
		 * All PclZip options have to be set using $zip_helper->config()!
		 *
		 * @access public
		 *
		 **/
		public function extract()
		{
		    // generate function call
			$options = array(
                'PCLZIP_OPT_PATH, "'.CAT_Helper_Directory::sanitizePath($this->_config['Path']).'"'
            );
			$ret     = NULL;
			if ( isset($this->_config['addPath']) && $this->_config['addPath'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_ADD_PATH, "'.CAT_Helper_Directory::sanitizePath($this->_config['addPath']).'"';
			}
			if ( isset($this->_config['removePath']) && $this->_config['removePath'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_REMOVE_PATH, "'.CAT_Helper_Directory::sanitizePath($this->_config['removePath']).'"';
			}
			if ( isset($this->_config['removeAllPath']) && $this->_config['removeAllPath'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_REMOVE_ALL_PATH';
			}

			$code = '$ret = $this->zip->extract( '
			   . (
			   		( is_array($options) && count($options) )
				  ? implode( ', ', $options )
				  : ''
				 )
			   . ' );';

			eval ( $code );
			return $ret;

		}   // end function extract()

		/**
         * accessor to PclZip->extractByIndex()
		 **/
        public function extractByIndex($p_index)
  		{
            $code = '$ret = $this->zip->extractByIndex( $p_index'
			   . $this->compile_options()
			   . ' );';

			eval ( $code );
			return $ret;
        }   // end function extractByIndex()


        private function compile_options()
        {
            $options = array();
            if ( isset($this->_config['addPath']) && $this->_config['addPath'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_ADD_PATH, "'.$this->_config['addPath'].'"';
			}
			if ( isset($this->_config['removePath']) && $this->_config['removePath'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_REMOVE_PATH, "'.$this->_config['removePath'].'"';
			}
			if ( isset($this->_config['setComment']) && $this->_config['setComment'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_COMMENT, "'.$this->_config['setComment'] . '"';
			}
			if ( isset($this->_config['removeAllPath']) && $this->_config['removeAllPath'] != '' )
			{
			    $options[] = 'PCLZIP_OPT_REMOVE_ALL_PATH';
			}
            return (
			      ( is_array($options) && count($options) )
			    ? ', ' . implode( ', ', $options )
				: ''
			 );
        }

	}   // end class

}   // class_exists()