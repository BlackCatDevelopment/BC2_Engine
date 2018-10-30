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
        public static function show(int $id, $attr) : string
        {
// !!!!! TODO !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// $attr verarbeiten
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
            $formatter = '\wblib\wbList\Formatter\ListFormatter';
            $variant   = 'navbar';
            switch($settings['type']) {
                case 'breadcrumb':
                    $formatter = '\wblib\wbList\Formatter\BreadcrumbFormatter';
                    $variant   = 'no_defaults';
                    break;
            }

            $renderer = new $formatter(array('template_variant'=>$variant));
            $renderer->setMaxDepth(self::tree($settings['type'])->getDepth());
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
         *
         * @access protected
         * @return
         **/
        protected static function getSettings(string $for, int $id) : array
        {
            switch($for) {
                case 'type':
                    $stmt = self::db()->query(
                          'SELECT `t1`.`type_name`, `t2`.`attribute`, `t2`.`value` '
                        . 'FROM  `:prefix:menu_types` AS `t1` '
                        . 'LEFT JOIN `:prefix:menutype_options` AS `t2` '
                        . 'ON `t1`.`type_id`=`t2`.`type_id` '
                        . 'WHERE `t1`.`type_id`=?',
                        array($id)
                    );
                    break;
                case 'menu':
                    $stmt = self::db()->query(
                          'SELECT `type_name`, `attribute`, `value` FROM `:prefix:menus` AS `t1` '
                        . 'LEFT JOIN `:prefix:menu_options` AS `t2` '
                        . 'ON `t1`.`menu_id`=`t2`.`menu_id` '
                        . 'JOIN `:prefix:menu_types` AS `t3` '
                        . 'ON `t1`.`type_id`=`t3`.`type_id` '
                        . 'WHERE `t1`.`menu_id`=?',
                        array($id)
                    );
                    break;
            }
            $data     = $stmt->fetchAll();
            $settings = array();
            if(is_array($data) && count($data)>0) {
                $settings['type'] = ( isset($data[0]['type_name']) ? $data[0]['type_name'] : 'fullmenu' );
                foreach($data as $i => $item) {
                    $settings[$item['attribute']] = $item['value'];
                }
            }
            return $settings;
        }   // end function getSettings()
        

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
