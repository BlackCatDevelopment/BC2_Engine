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
use \CAT\Helper\Page as HPage;
use \wblib\wbList\Tree as Tree;

if(!class_exists('\CAT\Helper\Menu',false))
{
	class Menu extends Base
	{
        protected static $loglevel  = \Monolog\Logger::EMERGENCY;

        /**
         * for object oriented use
         **/
        public function __call($method, $args)
            {
            if ( ! isset($this) || ! is_object($this) )
                return false;
            if ( method_exists( $this, $method ) )
                return call_user_func_array(array($this, $method), $args);
        }   // end function __call()

        /**
         *
         * @access public
         * @return
         **/
        public static function get($skip_protected = true)
        {
            $stmt = self::db()->query(
                  'SELECT * '
                . 'FROM `:prefix:menus` as `t1` '
                . 'LEFT JOIN `:prefix:menutypes` AS `t2` '
                . 'ON `t1`.`type_id`=`t2`.`type_id` '
                . ( $skip_protected ? 'WHERE `protected`="N" ' : '' )
                . 'ORDER BY `t1`.`menu_name` ASC'
            );
            $data = $stmt->fetchAll();
            return $data;
        }   // end function get()
        
        /**
         *
         * @access public
         * @return
         **/
        public static function getMenusByType(bool $group_by_type=false)
        {
            $stmt = self::db()->query(
                  'SELECT * '
                . 'FROM `:prefix:menutypes` as `t1` '
                . 'LEFT JOIN `:prefix:menus` AS `t2` '
                . 'ON `t1`.`type_id`=`t2`.`type_id` '
                . 'ORDER BY `t1`.`type_name` ASC'
            );
            $data = $stmt->fetchAll();

            if($group_by_type) {
                $menus = array();
                for($i=0;$i<count($data);$i++) {
                    if(isset($menus[$data[$i]['type_id']])) {
                        $menus[$data[$i]['type_id']][] = $data[$i];
                    } else {
                        $menus[$data[$i]['type_id']] = array($data[$i]);
                    }
                }
                return $menus;
            } else {
                return $data;
            }
        }   // end function get()

        /**
         *
         * @access public
         * @return
         **/
        public static function getMenu($id)
        {
            $field = 'menu_id';
            if(!is_numeric($id)) {
                $field = 'menu_name';
            }
            // base
            $stmt = self::db()->query(
                'SELECT * FROM `:prefix:menus` WHERE `'.$field.'`=?',
                array($id)
            );
            $data = $stmt->fetchAll();
            if(is_array($data) && count($data)>0) {
                return $data[0];
            }
        }   // end function getMenu()


        /**
         *
         * @access public
         * @return
         **/
        public static function getSettings(string $for, int $id) : array
        {
            switch($for) {
                // options set for this type of menu
                case 'type':
                    $stmt = self::db()->query(
                          'SELECT DISTINCT '
                        . '  `type_name`, '
                        . '  ifnull(`t2`.`value`,`t2`.`default_value`) as `value`, '
                        . '  `option_name` '
                        . 'FROM `:prefix:menutypes` AS `t1` '
                        . '  JOIN `:prefix:menutype_settings` as `t2` '
                        . '   	ON `t1`.`type_id`=`t2`.`type_id` '
                        . '  JOIN `:prefix:menutype_options` as `t3` '
                        . '   	ON `t2`.`option_id`=`t3`.`option_id` '
                        . 'WHERE `t1`.`type_id`=?',
                        array($id)
                    );
                    break;
                case 'menu':
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// FALLBACK auf Defaults fehlt
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                    $stmt = self::db()->query(
                          'SELECT DISTINCT '
                        . '   `option_name`, `value` '
                        . 'FROM '
                        . '	  `:prefix:menu_options` AS `t1` '
                        . 'JOIN '
                        . '  `:prefix:menutype_options` AS `t2` '
                        . 'ON '
                        . '  `t1`.`option_id`=`t2`.`option_id` '
                        . 'WHERE '
                        . '	`t1`.`menu_id`=?',
                        array($id)
                    );
                    break;
            }

#echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
#print_r(self::db()->getLastStatement());
#echo "</textarea><br />";

            $data     = $stmt->fetchAll();

#echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
#print_r($data);
#echo "</textarea><br />";

            $settings = array(); // fallback for empty options
            if(is_array($data) && count($data)>0) {
                foreach($data as $i => $item) {
                    $settings[$item['option_name']] = $item['value'];
                }
            }

            return $settings;
        }   // end function getSettings()

        /**
         *
         * @access public
         * @return
         **/
        public static function show($id) : string
        {
            if(is_numeric($id)) {
                $field = 'menu_id';
            } else {
                $field = 'menu_name';
            }

            $stmt = self::db()->query(
                  'SELECT `menu_id`, `t1`.`type_id`, `type_name` '
                . 'FROM `:prefix:menus` AS `t1` '
                . '  JOIN `:prefix:menutypes` AS `t2` '
                . '    ON `t1`.`type_id`=`t2`.`type_id` '
                . 'WHERE `'.$field.'`=?',
                array($id)
            );

            $menu     = $stmt->fetch();
            $defaults = self::getSettings('type',$menu['type_id']); // type (defaults)
#echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">
#DEFAULTS\n";
#print_r($defaults);
#echo "</textarea><br />";
            $id       = $menu['menu_id']; // in case we got the name as param
            $settings = self::getSettings('menu',$id); // menu
#echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">
#SETTINGS FOR MENU $id\n";
#print_r($settings);
#echo "</textarea><br />";

            $settings = array_merge($defaults,$settings); // merge defaults with special settings
#echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
#print_r($settings);
#echo "</textarea><br />";

            $renderer = self::getRenderer($menu['type_name'],$settings); // pass settings to renderer
            return $renderer->render(self::tree($menu['type_name']));
        }   // end function show()

        /**
         *
         * @access public
         * @return
         **/
        public static function showType(int $type) : string
        {
            $settings = self::getSettings('type',$type);
            $renderer = self::getRenderer($settings);
            return $renderer->render(self::tree($settings['type']));
        }   // end function showType()
        

        /**
         * makes sure that we have a valid page id; the visibility does not
         * matter here
         *
         * @access protected
         * @param  integer   $id (reference!)
         * @return void
         **/
        protected static function checkPageId(&$pid=NULL)
        {
            if($pid===NULL) {
                if(self::router()->isBackend()) {
                    $pid = \CAT\Backend::getArea(1);
                } else {
                    $pid = \CAT\Page::getID();
                }
            }
            #if($pid===0)    $pid = \CAT\Helper\Page::getRootParent($page_id);
        }   // end function checkPageId()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function getRenderer(string $type, array $settings)
        {
            // default formatter
            $formatter = '\wblib\wbList\Formatter\ListFormatter';

            // special
            switch($type) {
                case 'breadcrumb':
                    $formatter = '\wblib\wbList\Formatter\BreadcrumbFormatter';
                    break;
                case 'siblings':
                    $settings['mindepth'] = 1;
                    $settings['maxdepth'] = 1;
                    break;
            }

            $renderer = new $formatter($settings);
            $renderer->setOption('id_prefix','area','li');

            if(isset($settings['template_variant'])) {
                $renderer->setOption('template_variant',$settings['template_variant']);
            }
            if(isset($settings['template_dir'])) {
                $renderer->setOption('template_dir',$settings['template_dir']);
            }

            foreach(array_values(array('ul','li','a')) as $tag) {
                if(isset($settings[$tag.'_level_classes'])) {
                    $renderer->setLevelClasses($tag,$settings[$tag.'_level_classes']);
                }
                $knownClasses = $renderer->getKnownClasses($tag);
                if(is_array($knownClasses)) {
                    for($i=0;$i<count($knownClasses);$i++) {
                        if(isset($settings[$tag.'_'.$knownClasses[$i]])) {
                            $renderer->setClasses($tag,$knownClasses[$i],$settings[$tag.'_'.$knownClasses[$i]],true);
                        }
                    }
                }
            }
#echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
#print_r($renderer);
#echo "</textarea><br />";
            return $renderer;
        }   // end function getRenderer()

        /**
         * creates a wbList Tree object
         *   + frontend: pages
         *   + backend: depends
         *
         * @access protected
         * @param  string    $type
         * @return object
         **/
        protected static function tree(string $type) : \wblib\wbList\Tree
        {
            if(self::router()->isBackend()) {
                $rootid = 0;
                $pid    = NULL;

                switch($type) {
                    case 'breadcrumb':
                        $menu   = \CAT\Backend::getBreadcrumb();
                        $rootid = $menu[0]['id'];
                        end($menu);
                        $pid    = $menu[key($menu)]['id'];
                        reset($menu);
                        break;
                    default:
                        $menu   = \CAT\Backend::getMainMenu();
                        $pid    = 0;
                        foreach($menu as $index => $item) {
                            if(isset($item['current']) && $item['current']==1) {
                                $pid = $item['id'];
                            }
                        }
                        break;
                }

                $options = array('value'=>'title','linkKey'=>'href','root_id'=>$rootid,'current'=>$pid);
                return new Tree($menu,$options);
            }

            // ----- frontend -----
            $pid = NULL;
            self::checkPageId($pid);
            $options = array('id'=>'page_id','value'=>'menu_title','linkKey'=>'href','current'=>$pid);

            if($type=='language') {
                $pages = HPage::getLinkedByLanguage($pid);
                return new Tree($pages,$options);
            } else {
                return new Tree(HPage::getPages(),$options);
            }
        }


    }
}
