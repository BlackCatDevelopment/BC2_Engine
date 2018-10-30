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
use \CAT\Helper\Directory as Directory;
use \CAT\Helper\DB\CPDOExceptionHandler as CPDOExceptionHandler;

use Doctrine\Common\ClassLoader as ClassLoader;
require dirname(__FILE__).'/../../modules/lib_doctrine/Doctrine/Common/ClassLoader.php';

if(!class_exists('DB'))
{
    class DB extends \PDO
    {
        public  static $exc_trace = true;

        private static $instance    = NULL;
        private static $conn        = NULL;
        private static $prefix      = NULL;
        private static $qb          = NULL;
        private static $conn_failed = false;

        private $lasterror          = NULL;
        private $classLoader        = NULL;

        /**
         * constructor; initializes Doctrine ClassLoader and sets up a database
         * connection
         *
         * @access public
         * @return void
         **/
    	public function __construct($opt=array())
        {
            self::$prefix = defined('CAT_TABLE_PREFIX') ? CAT_TABLE_PREFIX : '';
            if(!$this->classLoader)
            {
                $this->classLoader = new ClassLoader('Doctrine', dirname(__FILE__).'/../../modules/lib_doctrine');
                $this->classLoader->register();
            }
            $this->connect($opt);
        }   // end function __construct()

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance($opt=array())
        {
            if(!self::$instance) self::$instance = new self($opt);
            return self::$instance;
        }   // end function getInstance()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function check()
        {
            if(self::$conn && is_object(self::$conn))
            {
                try {
                    self::$conn->query('SHOW TABLES');
                    return true;
                }
                catch ( Exception $e )
                {
                    return false;
                }
            }
        }   // end function check()

        /**
         * accessor to current connection object
         **/
        public static function conn()
        {
            return self::$conn;
        }   // end function conn()

        /**
         * accessor to currently used table prefix
         **/
        public static function prefix()
        {
            return self::$prefix;
        }   // end function prefix()

        /**
         * accessor to query builder
         **/
        public static function qb()
        {
            return self::$conn->createQueryBuilder();
        }   // end function qb()

        /**
         * connect to the database; returns Doctrine connection
         *
         * @access public
         * @return object
         **/
    	public static function connect($opt=array())
        {
            self::setExceptionHandler();
            if(!self::$conn)
            {
                $config = new \Doctrine\DBAL\Configuration();
                $config->setSQLLogger(new \Doctrine\DBAL\Logging\DebugStack());
                if(!defined('CAT_DB_NAME') && ( !count($opt) || !isset($opt['DB_NAME']) ) )
                {
                    $opt = self::getConfig($opt);
                }
                $connectionParams = array(
                    'charset'  => 'utf8',
                    'driver'   => 'pdo_mysql',
                    'dbname'   => (isset($opt['DB_NAME'])     ? $opt['DB_NAME']     : 'blackcat' ),
                    'host'     => (isset($opt['DB_HOST'])     ? $opt['DB_HOST']     : 'localhost'),
                    'password' => (isset($opt['DB_PASSWORD']) ? $opt['DB_PASSWORD'] : ''         ),
                    'user'     => (isset($opt['DB_USERNAME']) ? $opt['DB_USERNAME'] : 'root'     ),
                    'port'     => (isset($opt['DB_PORT'])     ? $opt['DB_PORT']     : 3306       ),
                );

                if(function_exists('xdebug_is_enabled'))
                    $xdebug_state = xdebug_is_enabled();
                else
                    $xdebug_state = false;
                #if(function_exists('xdebug_disable'))
                #    xdebug_disable();
                try
                {
                    self::$conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);
                }
                catch( \PDO\PDOException $e )
                {
                    self::$conn_failed = true;
                    $this->setError($e->message);
                    Base::printFatalError($e->message);
                }
                if(function_exists('xdebug_enable') && $xdebug_state)
                    xdebug_enable();
                if(isset($opt['DB_PREFIX']))
                {
                    self::$prefix = $opt['DB_PREFIX'];
                    define('CAT_TABLE_PREFIX',self::$prefix);
                }
            }
            self::restoreExceptionHandler();
            return self::$conn;
        }   // end function connect()

        /**
         *
         * @access protected
         * @return
         **/
        public static function connectionFailed()
        {
            return self::$conn_failed;
        }   // end function connectionFailed()

        /**
         * unsets connection object
         *
         * @access protected
         * @return void
         **/
    	final protected static function disconnect()
        {
            self::$conn = NULL;
        }   // end function disconnect()

        /**
         *
         * @access public
         * @return
         **/
        public function lastInsertId($seqname = NULL)
        {
            return self::$conn->lastInsertId($seqname);
        }   // end function lastInsertId()
        
        public function prepare($statement,$driver_options=array())
        {
            $statement = str_replace(':prefix:',self::$prefix,$statement);
            return self::$conn->prepare($statement,$driver_options);
        }

        /**
         * simple query; simple but has several drawbacks
         *
         * @params string $SQL
         * @return object
         **/
    	public function query($sql,$bind=array())
        {
            $this->setError(NULL);
            self::setExceptionHandler();
            try {
                if(is_array($bind))
                {
                    // allows to replace field names in statements
                    // Example:
                    // SELECT :field: FROM...
                    // array('field'=>'myfield')
                    // => SELECT `myfield` FROM...
                    foreach($bind as $_field => $_value)
                    {
                        if(substr_count($sql,':'.$_field.':'))
                        {
                            $sql = preg_replace(
                                '~(`?)(:'.$_field.':)(`?)~i',
                                '`'.$_value.'`',
                                $sql
                            );
                            unset($bind[$_field]);
                        }
                    }
                    $sql  = str_replace(':prefix:',self::$prefix,$sql);
                    $stmt = $this->prepare($sql);
                    $stmt->execute($bind);
                }
                else
                {
                    $sql  = str_replace(':prefix:',self::$prefix,$sql);
                    $stmt = self::$conn->query($sql);
                }
                self::restoreExceptionHandler();
                return new CAT_PDOStatementDecorator($stmt);
            } catch ( \Doctrine\DBAL\DBALException $e ) {
                $error = self::$conn->errorInfo();
                $this->setError(sprintf(
                    '[DBAL Error #%d] %s<br /><strong>Executed Query:</strong><br /><i>%s</i><br /><strong>Exception:</strong><br /><i>%s</i><br />',
					$error[1],
					$error[2],
					$sql,
                    $e->getMessage()
                ));
            } catch ( \PDOException $e ) {
                $error = self::$conn->errorInfo();
                $this->setError(sprintf(
                    '[PDO Error #%d] %s<br /><b>Executed Query:</b><br /><i>%s</i><br /><strong>Exception:</strong><br /><i>%s</i><br />',
					$error[1],
					$error[2],
					$sql,
                    $e->getMessage()
                ));
            }
            if($this->isError())
            {
                $logger = self::$conn->getConfiguration()->getSQLLogger();
                if(count($logger->queries))
                {
                    $last = array_pop($logger->queries);
                    if(is_array($last) && count($last))
                    {
                        $err_msg = sprintf(
                            "[SQL Error] %s<br />\n",
                            $last['sql']
                        );
                        if(is_array($bind) && count($bind))
                            $err_msg .= "\n[PARAMS] "
                                     .  var_export($bind,1);
                        $this->setError($err_msg);

                        if(isset($_REQUEST['_cat_ajax']))
                            return $this->getError();
                        else
                            throw new \PDOException($this->getError());
                            #Base::printFatalError($this->getError());
                    }
                }
            }
            self::restoreExceptionHandler();
            return false;
        }   // end function query()

        /**
         * extracts SQL statements from a string and executes them as single
         * statements
         *
         * @access public
         * @param  string  $import
         *
         **/
        public static function sqlImport($import,$replace_prefix=NULL,$replace_with=NULL)
        {
            $errors = array();
            $import = preg_replace( "%/\*(.*)\*/%Us", ''          , $import );
            $import = preg_replace( "%^--(.*)\n%mU" , ''          , $import );
            $import = preg_replace( "%^$\n%mU"      , ''          , $import );
            if($replace_prefix)
                $import = preg_replace( "%".$replace_prefix."%", $replace_with, $import );
            $import = preg_replace( "%\r?\n%"       , ''          , $import );
            $import = str_replace ( '\\\\r\\\\n'    , "\n"        , $import );
            $import = str_replace ( '\\\\n'         , "\n"        , $import );
            // split into chunks
            $sql = preg_split(
                '~(insert\s+(?:ignore\s+)into\s+|update\s+|replace\s+into\s+|create\s+table|truncate\s+table|delete\s+from)~i',
                $import,
                -1,
                PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY
            );
            if(!count($sql) || !count($sql)%2)
                return false;
            // index 1,3,5... is the matched delim, index 2,4,6... the remaining string
            $stmts = array();
            for($i=0;$i<count($sql);$i++)
                $stmts[] = $sql[$i] . $sql[++$i];
            foreach ($stmts as $imp){
                if ($imp != '' && $imp != ' '){
                    $ret = $this->query($imp);
                    if($this->isError())
                        $errors[] = $this->getError();
                }
            }
            if($errors)
                $this->errors = $errors;
            return ( count($errors) ? false : true );
        }   // end function sqlImport()

        /**
         *
         * @access public
         * @return
         **/
        public function getLastStatement($bind=NULL)
        {
            $statement = NULL;
            $params    = array();
            $logger    = self::$conn->getConfiguration()->getSQLLogger();
            if(count($logger->queries))
            {
                $last = array_pop($logger->queries);
                if(is_array($last) && count($last))
                {
                    $statement = $last['sql'];
                    if(is_array($bind) && count($bind))
                        $params = var_export($bind,1);
                }
            }
            return array($statement, $params);
        }   // end function getLastStatement()

        /**
         *
         * @access protected
         * @return
         **/
        public static function getConfig(&$opt=array())
        {
            // find file
            // note: .bc.php as suffix filter does not work!
            $configfiles = Directory::findFiles(dirname(__FILE__).'/DB',array('extension'=>'.php'));
            if(!is_array($configfiles) || !count($configfiles)>0)
            {
                self::$conn_failed = true;
                Base::printFatalError('Missing database configuration');
                exit;
            }

            // the first file with suffix .bc.php will be used
            foreach($configfiles as $file)
            {
                if($file=='index.php') continue;
                if(substr_compare($file,'.bc.php',-1,7))
                {
                    break;
                }
            }
            // read the file
            $configuration = parse_ini_file($file);

            if(!is_array($configuration) || !count($configuration))
                Base::printFatalError('Database configuration error');

            foreach($configuration as $key => $value)
                if(!isset($opt['DB_'.$key]))
                    $opt['DB_'.$key] = $value;

            self::$prefix = ( isset($opt['DB_PREFIX']) ? $opt['DB_PREFIX'] : '' );

            return $opt;
        }   // end function getConfig()

        /**
         * replacement for old class.order.php; re-orders items
         *
         * @access public
         * @param  string  $table - table name without prefix
         * @param  integer $id - element ID
         * @param  string  $order_field - column name, default 'position'
         * @param  string  $id_field - column name, default 'id'
         * @return
         **/
        public static function getNext(string $table, int $id, string $order_field='position', string $parent_field='parent')
        {
            $qb = self::qb()
                ->select('max(:field) AS `next`')
                ->from(self::$prefix.$table,'t1')
                ->where($parent_field.'=:id')
                ->setParameter('id',$id)
                ->setParameter('field',$order_field);
            $sth = $qb->execute();
            $data = $sth->fetch();
            if(is_int($data['next'])) {
                return $data['next']++;
            }
            return 1; // ignore errors
        }   // end function getNext()


        /**
         * check for DB error
         *
         * @access public
         * @return boolean
         **/
        public function isError()
        {
            return ( $this->lasterror ) ? true : false;
        }   // end function isError()

        /**
         * replacement for old class.order.php; re-orders items
         *
         * @access public
         * @param  string  $table - table name without prefix
         * @param  integer $id - element ID
         * @param  integer $newpos - new position
         * @param  string  $order_field - column name, default 'position'
         * @param  string  $id_field - column name, default 'id'
         * @return
         **/
        public function reorder(string $table, int $id, int $newpos, string $order_field='position', string $id_field='id', string $parent_field='parent') : bool
        {
            $tablename = sprintf('%s%s',self::$prefix,$table);
            // get original position
            $qb = self::qb()
                ->select('*')
                ->from($tablename,'t1')
                ->where($id_field.'=:id')
                ->setParameter('id',$id);
            $sth = $qb->execute();
            $data = $sth->fetch();

            if(is_array($data) && count($data))
            {
                $pos = $data[$order_field];
                // save new position
                self::query(
                    sprintf(
                        "UPDATE `%s` SET `%s`=? WHERE `%s`=?",
                        $tablename, $order_field, $id_field
                    ),
                    array($newpos,$id)
                );
                // calculate positions for previous items
                self::query(
                    sprintf(
                        "UPDATE `%s` SET `%s`=`%s`-1 WHERE `%s`=? AND `%s`<=? AND `%s`<>?",
                        $tablename, $order_field, $order_field, $parent_field, $order_field, $id_field
                    ),
                    array($data[$parent_field],$newpos,$id)
                );
                // calculate positions for next items
                self::query(
                    sprintf(
                        "UPDATE `%s` SET `%s`=`%s`+1 WHERE `%s`=? AND `%s`>? AND `%s`<>?",
                        $tablename, $order_field, $order_field, $parent_field, $order_field, $id_field
                    ),
                    array($data[$parent_field],$newpos,$id)
                );
                return true;
            }
            else
            {
                return false;
            }

        }   // end function reorder()
        

        /**
         * get last DB error
         *
         * @access public
         * @return string
         **/
        public function getError()
        {
            // show detailed error message only to global admin
            #if(User::is_authenticated() && User::is_root())
            return $this->lasterror;
            #else
            #    return "An internal error occured. We're sorry for inconvenience.";
        }   // end function getError()

        /**
         *
         * @access public
         * @return
         **/
        public function resetError()
        {
            $this->lasterror = NULL;
        }   // end function resetError()

        /**
         * Check if a table exists in the current database.
         *
         * @param PDO $pdo PDO instance connected to a database.
         * @param string $table Table to search for.
         * @return bool TRUE if table exists, FALSE if no table found.
         */
        public function tableExists($table)
        {
            if(function_exists('xdebug_is_enabled'))
                $xdebug_state = xdebug_is_enabled();
            else
                $xdebug_state = false;
            if(function_exists('xdebug_disable'))
                xdebug_disable();

            // Try a select statement against the table
            // Run it in try/catch in case PDO is in ERRMODE_EXCEPTION.
            try {
                $result = $this->query("SELECT 1 FROM `:prefix:$table` LIMIT 1");
            } catch (\PDO\PDOException $e) { // We got an exception == table not found
                return false;
            } catch (\Exception $e) {        // We got an exception == table not found
                return false;
            }

            // Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
            return $result !== false;
        }   // end function tableExists()

        /**
         * set error message
         *
         * @access protected
         * @param  string    error message
         * @return void
         **/
    	protected function setError($error = '')
        {
            $this->lasterror = $error;
        }   // end function setError

        /**
         * set exception handler to internal one; make sure that this is not
         * done more than once by checking prev handler
         *
         * @access protected
         * @return void
         **/
        protected static function setExceptionHandler()
        {
            $prevhandler = set_exception_handler(array("\CAT\Helper\DB\CPDOExceptionHandler", "exceptionHandler"));
            if(isset($prevhandler[0]) && $prevhandler[0] == 'CPDOExceptionHandler')
                restore_exception_handler();
        }   // end function setExceptionHandler()

        /**
         * reset exception handler to previous one
         *
         * @access protected
         * @return void
         **/
        protected static function restoreExceptionHandler()
        {
            // set dummy handler to get prev
            $prev = set_exception_handler(function(){});
            // reset
            restore_exception_handler();
            // if the previous one was ours...
            if(isset($prev[0]) && $prev[0] == 'CAT_PDOExceptionHandler')
                restore_exception_handler();
        }   // end function restoreExceptionHandler()

        /***********************************************************************
         * old function names wrap new ones
         **/
        public function get_one($sql,$type=\PDO::FETCH_ASSOC)
        {
            return $this->query($sql)->fetchColumn();
        }

        public function is_error()  { return $this->isError();      }
        public function get_error() { return $this->getError();     }
        public function insert_id() { return $this->lastInsertId(); }
    }
}

/**
 * decorates PDOStatement object with old WB methods numRows() and fetchRow()
 * for backward compatibility
 **/
class CAT_PDOStatementDecorator
{
    private $pdo_stmt = NULL;
    public function __construct($stmt)
    {
        $this->pdo_stmt = $stmt;
    }
    // route all other method calls directly to PDOStatement
    public function __call($method, $args)
    {
        return call_user_func_array(array($this->pdo_stmt, $method), $args);
    }
    public function numRows()
    {
        return $this->pdo_stmt->rowCount();
    }
    public function fetchRow($type=\PDO::FETCH_ASSOC)
    {
        // this is for backward compatibility
        if(defined('MYSQL_ASSOC') && $type===MYSQL_ASSOC) $type = \PDO::FETCH_ASSOC;
        return $this->pdo_stmt->fetch($type);
    }
}

namespace CAT\Helper\DB;

use \CAT\Base as Base;
use \CAT\Helper\DB as DB;

class CPDOExceptionHandler
{

    public function __call($method, $args)
    {
        return call_user_func_array(array($this, $method), $args);
    }
    /**
     * exception handler; allows to remove paths from error messages and show
     * optional stack trace if DB::$trace is true
     **/
    public static function exceptionHandler($exception)
    {
        if(DB::$exc_trace === true)
        {
            $traceline = "#%s %s(%s): %s(%s)";
            $msg   = "Uncaught exception '%s' with message '%s'<br />"
                   . "<div style=\"font-size:smaller;width:80%%;margin:5px auto;text-align:left;\">"
                   . "in %s:%s<br />Stack trace:<br />%s<br />"
                   . "thrown in %s on line %s</div>"
                   ;
            $trace = $exception->getTrace();

            foreach ($trace as $key => $stackPoint)
            {
                $trace[$key]['args'] = array_map('gettype', $trace[$key]['args']);
            }
            // build tracelines
            $result = array();
            foreach ($trace as $key => $stackPoint)
            {
                $result[] = sprintf(
                    $traceline,
                    $key,
                    ( isset($stackPoint['file']) ? $stackPoint['file'] : '-' ),
                    ( isset($stackPoint['line']) ? $stackPoint['line'] : '-' ),
                    $stackPoint['function'],
                    implode(', ', $stackPoint['args'])
                );
            }
            // trace always ends with {main}
            #$result[] = '#' . ++$key . ' {main}';
            // write tracelines into main template
            $msg = sprintf(
                $msg,
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                implode("<br />", $result),
                $exception->getFile(),
                $exception->getLine()
            );
        }
        else
        {
            // template
            $msg = "[DB Exception] %s<br />";
            // filter message
            $message = $exception->getMessage();
            preg_match('~SQLSTATE\[[^\]].+?\]\s+\[[^\]].+?\]\s+(.*)~i', $message, $match);
            $msg     = sprintf(
                $msg,
                ( isset($match[1]) ? $match[1] : $message )
            );
        }

        try {
            $logger = Base::log();
            $logger->emergency(sprintf(
                'Exception with message [%s] emitted in [%s] line [%s]',
                $exception->getMessage(),$exception->getFile(),$exception->getLine()
            ));
            $logger->emergency($msg);
        } catch ( Exception $e ) {}

        // log or echo as you please
        Base::printFatalError($msg);
    }
}