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

namespace CAT\Backend;

use \CAT\Base as Base;
use \CAT\Backend as Backend;
use \CAT\Helper\Dashboard as HDash;
use \CAT\Helper\Json as Json;
use \CAT\Helper\Validate as Validate;
use \CAT\Helper\Widget as Widget;

if(!class_exists('\CAT\Backend\Dashboard'))
{
    class Dashboard extends Base
    {
        protected static $instance = NULL;
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $debug    = true;
        //protected static $loglevel = \Monolog\Logger::DEBUG;

        /**
         *
         * @access public
         * @return
         **/
        public static function getInstance()
        {
            if(!is_object(self::$instance))
                self::$instance = new self();
            return self::$instance;
        }   // end function getInstance()

        /**
         *
         * @access public
         * @return
         **/
        public static function add($dash=NULL)
        {
            // validate path
            if(!$dash)
                $dash = self::getDashID();
            // check if dashboard exists
            if(!HDash::exists($dash))
                echo Json::printError('no such dashboard');
            $widget = Validate::sanitizePost('widget_id');
            $result = HDash::addWidget($widget,$dash);
            echo Json::printSuccess('ok');
        }   // end function add()

        /**
         *
         * @access public
         * @return
         **/
        public static function get()
        {
            $page = Validate::sanitizePost('page');
            HDash::getDashboard($page);
        }   // end function getDashboard()
        
        /**
         * tries to retrieve 'page_id' by checking (in this order):
         *
         *    - $_POST['page_id']
         *    - $_GET['page_id']
         *    - Route param['page_id']
         *
         * also checks for numeric value
         *
         * @access private
         * @return integer
         **/
        public static function getDashID()
        {
            $dashID  = Validate::sanitizePost('dash_id','numeric');

            if(!$dashID)
                $dashID  = Validate::sanitizeGet('dash_id','numeric');

            if(!$dashID)
                $dashID = self::router()->getParam(-1);

            if(!$dashID)
                $dashID = self::router()->getRoutePart(-1);

#            if(!$dashID || !is_numeric($dashID) || !HPage::exists($dashID))
#                $dashID = NULL;

            return intval($dashID);
        }   // end function getDashID()

        /**
         * show dashboard; if no path is given, will try to resolve the
         * dashboard path from the current route
         *
         * will throw a fatal error if the dashboard does not exist
         *
         * @access public
         * @return
         **/
        public static function index($path=NULL)
        {
            // validate path
            if(!$path)
                $path = self::router()->getRoute();
            $dash = HDash::getDashboardID($path);

            // check if dashboard exists
            if(!HDash::exists($dash))
            {
                if($path)
                {
                    HDash::saveDashboardConfig(
                        NULL,
                        self::user()->getID(),
                        $path,
                        2
                    );
                }
                else
                {
                    self::log()->addAlert(sprintf('No such dashboard! [id: %d; path: %s]',$dash,$path));
                    self::printFatalError('Access denied');
                }
            }

            // forward query data to widget
            $query   = self::router()->getQuery();
            if($query)
            {
                // widget id?
                if(isset($query['widget']))
                {
                    // check if widget exists
                    if(Widget::exists($query['widget']))
                    {
                        // check if widget is visible on current dashboard
                        if(Widget::isOnDashboard($query['widget'],$dash))
                        {
                            // forward
                            $widget = Widget::getWidget($query['widget']);
                            Widget::handleCall($widget,$query);
                        }
                    }
                }
/*
Array
(
    [widget] => 3
    [widget_logs_file] => core_CAT_User_01-18-2017.log
    [_] => 1485528661687
)
*/
            }

            // get the template contents
            $tpl_data = array(
                'dashboard' => array_merge(
                    array(
                        'widgets' => HDash::renderDashboard($dash),
                    ),
                    HDash::getDashboardConfig($path)
                ),
                'MAIN_MENU' => Backend::getMainMenu(),
            );

            Backend::printHeader();
            self::tpl()->output('backend_dashboard', $tpl_data);
            Backend::printFooter();
        }   // end function index()
        
        /**
         * re-order dashboard widgets
         *
         * @access public
         * @return
         **/
        public static function order()
        {
            $dash = Validate::sanitizePost('dashboard');
            $id   = Validate::sanitizePost('id');
            $col  = Validate::sanitizePost('col');
            $pos  = Validate::sanitizePost('row');
            if(!$col>0) $col = 1;
            if(!$pos>0) $pos = 1;
            
            if($dash)
            {
                // update position
                self::db()->query(
                    'UPDATE `:prefix:dashboard_has_widgets` SET `column`=?, `position`=? WHERE `dashboard_id`=? AND `widget_id`=?',
                    array($col,$pos,$dash,$id)
                );
                self::log()->addDebug(sprintf(
                    'updated dash [%s] widget [%s] col [%s] pos [%s]',
                    $dash,$id,$col,$pos
                ));
                // update order
                self::db()->query(
                      'SET @pos := ?; '
                    . 'UPDATE `:prefix:dashboard_has_widgets` '
                    . 'SET `position` = ( SELECT @pos := @pos + 1 ) '
                    . 'WHERE `column`=? AND `position`>? AND `widget_id`!=? AND `dashboard_id`=? '
                    . 'ORDER BY `position` ASC;',
                    array($pos,$col,$pos,$id,$dash)
                );
                // update order
                self::db()->query(
                      'SET @pos := ?; '
                    . 'UPDATE `:prefix:dashboard_has_widgets` '
                    . 'SET `position` = ( SELECT @pos := @pos - 1 ) '
                    . 'WHERE `column`=? AND `position`<=? AND `widget_id`!=? AND `dashboard_id`=? '
                    . 'ORDER BY `position` ASC;',
                    array($pos,$col,$pos,$id,$dash)
                );
                $result = Json::printSuccess('ok');
            }
            else {
                self::log()->addWarn(sprintf('no such dashboard: [%s]',$dash));
                $result = Json::printError('not ok');
            }

            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo $result;
                return;
            }
        }   // end function order()

        public static function reload($dash=NULL)
        {
            // validate path
            if(!$dash)
            {
                // remove "reload" from route
                $route = self::router()->getRoute();
                $route = preg_replace('~\/reload$~i','',$route);
                $dash  = HDash::getDashboardID($route);
            }
            // check if dashboard exists
            if(!HDash::exists($dash))
                echo Json::printError('Invalid data')
                   . (self::$debug ? '(Backend_Dashboard::reload())' : '');
            $widget = Validate::sanitizePost('widget_id');
            // check if widget exists
            if(Widget::exists($widget))
            {
                // check if widget is visible on current dashboard
                if(Widget::isOnDashboard($widget,$dash))
                {
                    // forward
                    $widget  = Widget::getWidget($widget);
                    $content = Widget::execute($widget,$dash);
                    if(self::asJSON())
                    {
                        echo header('Content-Type: application/json');
                        echo Json::printSuccess($content);
                        return;
                    } else {

                    }
                }
            }

        }

        /**
         *
         * @access public
         * @return
         **/
        public static function remove($dash=NULL)
        {
            // validate path
            if(!$dash)
                $dash = self::getDashID();
            // check if dashboard exists
            if(!HDash::exists($dash))
                echo Json::printError('Invalid data')
                   . (self::$debug ? '(Backend_Dashboard::remove())' : '');
            $widget = Validate::sanitizePost('widget_id');
            HDash::removeWidget($widget,$dash);
            echo Json::printSuccess('ok');
        }   // end function remove()

        /**
         *
         * @access public
         * @return
         **/
        public static function reset($dash=NULL)
        {
            // validate path
            if(!$dash)
                $dash = self::getDashID();
            // check if dashboard exists
            if(!HDash::exists($dash))
                echo Json::printError('Invalid data')
                   . (self::$debug ? '(Backend_Dashboard::reset())' : '');
            // remove current settings
            self::db()->query(
                'DELETE FROM `:prefix:dashboard_has_widgets` WHERE `dashboard_id`=?',
                array($dash)
            );
            self::db()->query(
                'DELETE FROM `:prefix:dashboard_widget_data` WHERE `dashboard_id`=?',
                array($dash)
            );
            if(self::asJSON())
            {
                echo Json::printSuccess('success');
            }

        }   // end function reset()

        /**
         *
         * @access public
         * @return
         **/
        public static function toggle()
        {
            $id   = Validate::sanitizePost('id');
            $vis  = Validate::sanitizePost('vis');
            $dash = Validate::sanitizePost('dashboard');
            if($dash)
            {
                self::db()->query(
                    'UPDATE `:prefix:dashboard_has_widgets` SET `open`=? WHERE `dashboard_id`=? AND `widget_id`=?',
                    array($vis,$dash,$id)
                );
                $result = Json::printSuccess('ok');
            }
            else {
                $result = Json::printError('not ok');
            }
            if(self::asJSON())
            {
                echo header('Content-Type: application/json');
                echo $result;
                return;
            }
        }   // end function toggle()

        /**
         * returns a list of widgets that are not already on the current
         * dashboard
         *
         * @access public
         * @param  mixed  $dash - id or dashboard path
         * @return mixed
         **/
        public static function widgets($dash=NULL)
        {
            // validate path
            if(!$dash)
                $dash = self::getDashID();
            // check if dashboard exists
            if(!HDash::exists($dash))
                echo Json::printError('No such dashboard id: '.$dash);
            // get list of widgets the user is allowed to see
            $all  = Widget::getAllowed();
            // get list of widgets already an the dashboard
            $vis  = HDash::renderDashboard($dash);
            // filter array $all
            $diff = array_diff(array_column($all,'widget_id'),array_column($vis,'widget_id'));

            $result = array_filter(
                $all,
                function ($e) use($diff) {
                    return (
                        in_array($e['widget_id'],$diff)
                        ? true
                        : false
                    );
                }
            );
            echo Json::printSuccess(array_values($result));
        }   // end function widgets()
        
        
    } // class HDash

} // if class_exists()