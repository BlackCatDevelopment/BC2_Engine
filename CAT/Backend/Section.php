<?php

/*
   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___
  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)
   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \
  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/

   @author          Black Cat Development
   @copyright       2018 Black Cat Development
   @link            https://blackcat-cms.org
   @license         http://www.gnu.org/licenses/gpl.html
   @category        CAT_Core
   @package         CAT_Core

*/

namespace CAT\Backend;
use \CAT\Base as Base;

if (!class_exists('\CAT\Backend\Section'))
{
    class Section extends Base
    {
        protected static $loglevel = \Monolog\Logger::EMERGENCY;
        protected static $instance = NULL;
        protected static $debug    = true;

        /**
         * create an instance (singleton)
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
        public static function add()
        {
            $pageID = self::getPageID();
            self::checkPerm($pageID,'pages_section_add');
            $addon  = \CAT\Helper\Validate::sanitizePost('addon','numeric');
            if(!\CAT\Helper\Addons::exists($addon))
                self::printFatalError('Invalid data!')
                . (self::$debug ? '(CAT_Backend_Section::add())' : '');
            else
                $module = \CAT\Helper\Addons::getDetails($addon,'addon_id');
            $blockID = \CAT\Helper\Validate::sanitizePost('block','numeric');
            $result  = \CAT\Sections::addSection($pageID,$module,$blockID);
            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult($result,($result?'Success':'Failed'));
                return;
            }
        }   // end function add()
        
        /**
         * delete a section
         *
         * @access public
         * @return
         **/
        public static function delete()
        {
            $pageID = self::getPageID();
            self::checkPerm($pageID,'pages_section_delete');
            $sectionID = self::getSectionID();
            if(!\CAT\Sections::exists($sectionID))
                Base::printFatalError('Invalid data!')
                . (self::$debug ? '(CAT_Backend_Section::delete())' : '');
            $result = \CAT\Sections::deleteSection($sectionID);
            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult($result, '');
                return;
            }
        }   // end function delete()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            $sectionID = self::getSectionID();

            // check permissions
            if(!self::user()->hasPerm('section_edit'))
                self::printFatalError('You are not allowed for the requested action!');

            // option name
            $opt     = \CAT\Helper\Validate::get('name','string');
            // new value
            $value   = \CAT\Helper\Validate::get('value','string');
            // id
            $pageID  = \CAT\Helper\Validate::get('pk');
            // default block number
            $blockNr = 1;

            if(substr_count($pageID,'#')) {
                list($pageID,$blockNr) = explode('#',$pageID,2);
            }

            if($sectionID && $opt && $value && $pageID)
            {
                self::db()->query(
                      'UPDATE `:prefix:pages_sections` '
                    . 'SET `:field:`=:value '
                    . 'WHERE `page_id`=:page AND `section_id`=:section AND `block`=:block',
                    array(
                        'page'    => $pageID,
                        'section' => $sectionID,
                        'field'   => $opt,
                        'value'   => $value,
                        'block'   => $blockNr,
                    )
                );
echo "FILE [",__FILE__,"] FUNC [",__FUNCTION__,"] LINE [",__LINE__,"]<br /><textarea style=\"width:100%;height:200px;color:#000;background-color:#fff;\">";
print_r(self::db()->getLastStatement(array(
                        'page'    => $pageID,
                        'section' => $sectionID,
                        'field'   => $opt,
                        'value'   => $value,
                        'block'   => $blockNr,
                    )));
echo "</textarea><br />";
            }

        }   // end function edit()

        /**
         *
         * @access public
         * @return
         **/
        public static function move()
        {
            // the user needs perms on current page...
            $pageID = self::getPageID();
            self::checkPerm($pageID,'pages_section_move');
            // ...and on target page...
            $toID = \CAT\Helper\Validate::sanitizePost('to');
            self::checkPerm($toID,'pages_section_move');
            // get the section ID
            $sectionID = self::getSectionID();
            if(!\CAT\Sections::exists($sectionID))
                Base::printFatalError('Invalid data! (Section does not exist)');
            self::db()->query(
                'UPDATE `:prefix:pages_sections` SET `page_id`=? WHERE `section_id`=?',
                array($toID,$sectionID)
            );
            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult(
                    (self::db()->isError() ? false : true),
                    ''
                );
                return;
            }
        }   // end function move()

        /**
         *
         * @access public
         * @return
         **/
        public static function order()
        {
            $pageID  = self::getPageID();
            self::checkPerm($pageID,NULL);
            $order = \CAT\Helper\Validate::sanitizePost('order');
            if(is_array($order) && count($order))
            {
                foreach($order as $i => $id)
                {
                    if(!\CAT\Sections::exists($id)) continue;
                    $i++;
                    self::db()->query(
                        'UPDATE `:prefix:pages_sections` SET `position`=? WHERE `section_id`=?',
                        array($i,$id)
                    );
                }
                if(self::asJSON())
                {
                    echo \CAT\Helper\Json::printResult(true, '');
                    return;
                }
            }
        }   // end function order()

        /**
         *
         * @access public
         * @return
         **/
        public static function publish()
        {
            $pageID = self::getPageID();
            self::checkPerm($pageID,'pages_section_publishing');
            $sectionID = self::getSectionID();
            if(!\CAT\Sections::exists($sectionID))
                Base::printFatalError('Invalid data! (Section does not exist)');
            $data = array();
            foreach(array_values(array('publ_start','publ_end','publ_by_time_start','publ_by_time_end')) as $key)
            {
                $value = \CAT\Helper\Validate::sanitizePost($key,'string');
                if($value) { // not empty or 0
                    try {
                        $d = new DateTime($value);
                        $data[$key] = $d->getTimestamp();
                    } catch ( \Exception $e ) {
                        echo "invalid time format $value - ", $e->getMessage(), "-<br />";
                    }
                } else {
                    $data[$key] = 0;
                }
            }

            if(count($data))
            {
                $result = \CAT\Sections::updateSection($sectionID, $data);
                if(self::asJSON())
                {
                    echo \CAT\Helper\Json::printData($data);
                    return;
                }
            }

        }   // end function publish()

        /**
         * delete a section
         *
         * @access public
         * @return
         **/
        public static function recover()
        {
            $pageID = self::getPageID();
            self::checkPerm($pageID,'pages_section_recover');
            $sectionID = self::getSectionID();
            self::checkPerm($pageID,'pages_section_recover');
            if(!\CAT\Sections::exists($sectionID))
                Base::printFatalError('Invalid data!')
                . (self::$debug ? '(CAT_Backend_Section::recover())' : '');
            $result = \CAT\Sections::recoverSection($sectionID);
            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult($result, '');
                return;
            }
        }   // end function recover()

        /**
         *
         * @access public
         * @return
         **/
        public static function save()
        {
            $sectionID = self::getSectionID();
            if(!\CAT\Sections::exists($sectionID))
                Base::printFatalError('Invalid data! (Section does not exist)');
            // get section details
            $section = \CAT\Sections::getSection($sectionID,true);
            // get page ID
            $pageID  = self::getPageID(); //$section['page_id'];
            self::checkPerm($pageID,null);
            // set variant?
            if(null!=($variant=\CAT\Helper\Validate::sanitizePost('variant')))
            {
                $result = \CAT\Sections::setVariant($sectionID,$variant);
                $module_path = \CAT\Helper\Directory::sanitizePath(CAT_ENGINE_PATH.'/modules/'.$section['module']);
                if(file_exists($module_path.'/templates/'.$variant.'/inc.forms.php'))
                {
                    // get default form data
                    $form = \wblib\wbForms\Form::loadFromFile('options','inc.forms.php',$module_path.'/templates/'.$variant);
                    $defaults = $form->getData();
                    if(is_array($defaults) && count($defaults)>0)
                    {
                        foreach($defaults as $key => $val)
                        {
                            if($key=='options') continue;
                            self::db()->query(
                                'REPLACE INTO `:prefix:section_options` (`page_id`,`section_id`,`option`,`value`) '
                                . 'VALUES(?,?,?,?)',
                                array($pageID,$sectionID,strip_tags($key),strip_tags($val))
                            );
                        }
                    }
                }
            }
            // options
            if(null!=($options=\CAT\Helper\Validate::sanitizePost('options')))
            {
                $optnames = explode(',',$options);
                foreach(array_values($optnames) as $key) {
                    $value=\CAT\Helper\Validate::sanitizePost($key);
                    if(null!=$value)
                    {
                        if(is_array($value)) $value=implode('|',$value);
                        self::db()->query(
                            'REPLACE INTO `:prefix:section_options` (`page_id`,`section_id`,`option`,`value`) '
                            . 'VALUES(?,?,?,?)',
                            array($pageID,$sectionID,strip_tags($key),strip_tags($value))
                        );
                    } else {
                        self::db()->query(
                            'DELETE FROM `:prefix:section_options` WHERE `page_id`=? AND `section_id`=? and `option`=?',
                            array($pageID,$sectionID,$key)
                        );
                    }
                }
            }

            // special case
            if($section['module']=='wysiwyg')
            {
                \CAT\Addon\WYSIWYG::initialize($section);
                $result = \CAT\Addon\WYSIWYG::save($sectionID);
            }
            else
            {
            }

            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult(
                    ( $errors>0 ? 'Error' : 'Success'),
                    ''
                );
                return;
            }
        }   // end function save()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function checkPerm($pageID,$perm)
        {
            $result = true;
            // the user needs to have the global pages_edit permission plus
            // permissions for the current page
            if(!self::user()->hasPerm('pages_edit') || !self::user()->hasPagePerm($pageID,'pages_edit'))
            {
                self::log()->addWarning(sprintf(
                    'User [%s] requested edit permissions for page [%s]',
                    self::user()->get('display_name'), $pageID
                ));
                $result = false;
            }
            if($perm && !self::user()->hasPerm($perm))
            {
                self::log()->addWarning(sprintf(
                    'User [%s] requested [%s] permissions for page [%s]',
                    self::user()->get('display_name'),$perm,$pageID
                ));
                $result = false;
            }
            if(!$result)
            {
                self::printFatalError('You are not allowed for the requested action!');
            }
        }   // end function checkPerm()
        
        /**
         * try to retrive the page id
         *     + from _POST
         *     + from _GET
         *     + from route
         *     + by section id
         * @access protected
         * @return
         **/
        protected static function getPageID()
        {
            $self    = self::getInstance();
            $pageID  = \CAT\Helper\Validate::sanitizePost('page_id','numeric');

            if(!$pageID)
                $pageID  = \CAT\Helper\Validate::sanitizeGet('page_id','numeric');

            if(!$pageID)
                $pageID = $self->router()->getParam(-1);

            if(!$pageID)
                $pageID = $self->router()->getRoutePart(-1);

            if(!$pageID)
                $pageID = \CAT\Sections::getPageForSection(self::getSectionID());

            if(!$pageID || !is_numeric($pageID) || !\CAT\Helper\Page::exists($pageID))
                Base::printFatalError('Invalid data')
                . (self::$debug ? '(CAT_Backend_Section::getPageID())' : '');

            return $pageID;
        }   // end function getPageID()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function getSectionID()
        {
            $sectID  = \CAT\Helper\Validate::sanitizePost('section_id','numeric');

            if(!$sectID)
                $sectID  = \CAT\Helper\Validate::sanitizeGet('section_id','numeric');

            if(!$sectID)
                $sectID = self::router()->getParam(-1);

            if(!$sectID)
                $sectID = self::router()->getRoutePart(-1);

            if(!$sectID || !is_numeric($sectID) || !\CAT\Sections::exists($sectID))
                Base::printFatalError('Invalid data')
                . (self::$debug ? '(CAT_Backend_Section::getSectionID())' : '');

            return $sectID;
        }   // end function getSectionID()

    } // class Section

} // if class_exists()