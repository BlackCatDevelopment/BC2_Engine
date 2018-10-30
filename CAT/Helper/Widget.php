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


if (!class_exists('\CAT\Helper\Widget'))
{
    class Widget extends Base
    {
        private   static $instance;
        //protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $loglevel = \Monolog\Logger::DEBUG;

        public static function getInstance()
        {
            if (!self::$instance)
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        public function __call($method, $args)
        {
            if(!isset($this) || !is_object($this))
                return false;
            if(method_exists($this,$method))
                return call_user_func_array(array($this, $method), $args);
        }   // end function __call()

/*
Array
(
    [id] => 3
    [dashboard_id] =>
    [widget_id] =>
    [column] => 3
    [position] => 1
    [open] => Y
    [widget_name] => Logs
    [widget_module] => dashboard
    [widget_controller] => dashboard_widget_logs
    [preferred_column] => 3
    [icon] => fa-align-left
    [allow_in_global] => Y
    [data] =>
)
*/
        /**
         *
         * @access public
         * @return
         **/
        public static function execute($widget,$dashboard_id)
        {
            $path = CAT_ENGINE_PATH.'/modules/'.$widget['widget_module'];

            // load widget language file
            $lang = strtoupper(self::lang()->getLang());
            if(file_exists($path.'/languages/'.$lang.'.php'))
            {
                self::lang()->addFile($lang,$path.'/languages');
            }

            if(file_exists($path.'/inc/'.$widget['widget_controller'].'.php'))
            {
                $id = isset($widget['id'])
                    ? $widget['id']
                    : $widget['widget_id']
                    ;
                require_once $path.'/inc/'.$widget['widget_controller'].'.php';
                return $widget['widget_controller']::view($id,$dashboard_id);
            }
        }   // end function execute()

        /**
         *
         * @access public
         * @return
         **/
        public static function exists($id)
        {
            $field = ( is_numeric($id) ? 'widget_id' : 'widget_name' );
            $sth  = self::db()->query(
                'SELECT * FROM `:prefix:dashboard_widgets` WHERE `'.$field.'`=?',
                array($id)
            );
            return $sth->rowCount();
        }   // end function exists()
        

        /**
         * gets the list of widgets a user is allowed to see
         *
         * @access public
         * @return
         **/
        public static function getAllowed($global=false,$alldata=false)
        {
            // all data or json data?
            if($alldata) $fields = '*';
            else         $fields = 't2.widget_id,widget_name,preferred_column,icon';
            // get query builder (save some typing)
            $query = self::db()->qb();
            // basics
            $query->select($fields)
                  ->from(self::db()->prefix().'dashboard_widget_permissions','t1')
                  ->rightJoin('t1',self::db()->prefix().'dashboard_widgets','t2','t1.widget_id=t2.widget_id')
                  ;

            if($global)
            {
                $query->where('allow_in_global=?')
                      ->setParameter(0, 'Y');
            }

            // root is allowed all
            if(!self::user()->is_root())
            {
                // get the user's groups
                $groups = self::user()->getGroups(1);
                $query->andWhere(
                    $query->expr()->orX(
                        'needed_group IS NULL',
                        'needed_group IN (:ids)'
                    )
                )->setParameter('ids', array_values($groups), \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
            }

            $sth = $query->execute();
            $data = $sth->fetchAll();

            return $data;
        }   // end function getAllowed()

        /**
         *
         * @access public
         * @return
         **/
        public static function handleCall($widget,$data=array())
        {
            $path = CAT_ENGINE_PATH.'/modules/'.$widget['widget_module'].'/inc';
            if(file_exists($path.'/'.$widget['widget_controller'].'.php'))
            {
                $id = isset($widget['id'])
                    ? $widget['id']
                    : $widget['widget_id']
                    ;
                require_once $path.'/'.$widget['widget_controller'].'.php';
                return $widget['widget_controller']::handleCall($data);
            }
        }   // end function handleCall()

        /**
         *
         * @access public
         * @return
         **/
        public static function isOnDashboard($id,$dash)
        {
            $sth = self::db()->query(
                  'SELECT * FROM `:prefix:dashboard_has_widgets` '
                . 'WHERE `widget_id`=? AND `dashboard_id`=?',
                array($id,$dash)
            );
            $data = $sth->fetch();
            if(count($data)) return true;
            else             return false;
        }   // end function isOnDashboard()

        /**
         *
         * @access public
         * @return
         **/
        public static function getWidget($id)
        {
            $sql  = 'SELECT `t1`.*, `t2`.`data` '
                  . 'FROM `:prefix:dashboard_widgets` AS `t1` '
                  . 'LEFT OUTER JOIN `:prefix:dashboard_widget_data` AS `t2` '
                  . 'ON `t1`.`widget_id`=`t2`.`widget_id` '
                  . 'WHERE `t1`.`widget_id`=?';
            $sth  = self::db()->query(
                 $sql, array($id)
            );
            $data = $sth->fetch();
            if(isset($data['data']) && strlen($data['data']))
                $data['data'] = unserialize($data['data']);
            return $data;
        }   // end function getWidget()

        /**
         *
         * @access public
         * @return
         **/
        public static function saveWidgetData($widget_id,$dash_id,$data)
        {
            $sth   = self::db()->query(
                'REPLACE INTO `:prefix:dashboard_widget_data` VALUES (?,?,?)',
                array($widget_id,$dash_id,serialize($data))
            );
        }   // end function saveWidgetData()
        

    }

}