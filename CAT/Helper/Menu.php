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
        public static function get(bool $group_by_type=false)
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
        public static function getSettings(string $for, int $id) : array
        {
            switch($for) {
                case 'type':
                    $stmt = self::db()->query(
                          'SELECT `type_name`, `default_value`, `value`, `option_name` '
                        . 'FROM `:prefix:menutypes` AS `t1` '
                        . 'JOIN `:prefix:menutype_settings` as `t2` '
                        . 'ON `t1`.`type_id`=`t2`.`type_id` '
                        . 'join `:prefix:menutype_options` as `t3` '
                        . 'on `t2`.`option_id`=`t3`.`option_id` '
                        . 'WHERE `t1`.`type_id`=?',
                        array($id)
                    );
                    break;
                case 'menu':
                    /*
                    $stmt = self::db()->query(
                          'SELECT * '
                        . 'FROM `:prefix:menus` AS `t1` '
                        . 'JOIN `:prefix:menutypes` AS `t2` '
                        . 'ON `t1`.`type_id`=`t2`.`type_id` '
                        . 'JOIN `:prefix:menutype_settings` as `t3` '
                        . 'ON `t2`.`type_id`=`t3`.`type_id` '
                        . 'join `:prefix:menutype_options` as `t4` '
                        . 'on `t3`.`option_id`=`t4`.`option_id` '
                        . 'WHERE `t1`.`menu_id`=?',
                        array($id)
                    );
                    */
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// FALLBACK auf Defaults fehlt
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                    $stmt = self::db()->query(
                          'SELECT DISTINCT '
                        . '   `t1`.`option_name`, '
                        . '   `t2`.value '
                        . 'FROM '
                        . '	  `:prefix:menutype_options` AS `t1` '
                        . 'JOIN '
                        . '   `:prefix:menu_options` AS `t2` '
                        . 'ON '
                        . '	`t1`.`option_id`=`t2`.`option_id` '
                        . 'WHERE '
                        . '	`t2`.`menu_id`=?',
                        array($id)
                    );
                    break;
            }
            $data     = $stmt->fetchAll();

            $settings = array(); // fallback for empty options
            if(is_array($data) && count($data)>0) {
                $settings['type'] = ( isset($data[0]['type_name']) ? $data[0]['type_name'] : 'fullmenu' );
                foreach($data as $i => $item) {
                    $settings[$item['option_name']]
                        = ( empty($item['value']) ? $item['default_value'] : $item['value'] );
                }
            }

            return $settings;
        }   // end function getSettings()

        /**
         *
         * @access public
         * @return
         **/
        public static function show(int $id) : string
        {
            // get type
            $stmt = self::db()->query(
                'SELECT `type_id` FROM `:prefix:menus` WHERE `menu_id`=?',
                array($id)
            );
            $type     = $stmt->fetch();
            $defaults = self::getSettings('type',$type['type_id']); // type (defaults)
            $settings = self::getSettings('menu',$id); // menu
            $settings = array_merge($settings,$defaults); // merge defaults with special settings
            $renderer = self::getRenderer($settings); // pass settings to renderer
            
            return $renderer->render(self::tree($settings['type']));
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
        protected static function getRenderer(array $settings)
        {
            // default formatter
            $formatter = '\wblib\wbList\Formatter\ListFormatter';
            // special
            switch($settings['type']) {
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
                        $pid    = \CAT\Page::getID();
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
