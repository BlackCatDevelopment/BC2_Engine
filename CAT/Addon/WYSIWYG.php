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

namespace CAT\Addon;

use \CAT\Base as Base;
use \CAT\Registry as Registry;
use \CAT\Helper\Validate as Validate;

if(!class_exists('\CAT\Addon\WYSIWYG',false))
{
    class WYSIWYG extends Page implements iAddon, iPage
    {
        private static $e_url  = null;
        private static $e_name = null;
        private static $e      = null;

        public static function init()
        {
            self::$e_name = Registry::get('wysiwyg_editor');     // name
            self::$e_url  = Registry::get('wysiwyg_editor_url'); // url
            self::$e      = new \CAT\Addon\WYSIWYG\CKEditor4();
        }

        /**
         * @inheritdoc
         **/
        public static function view($section)
        {
            parent::view($section);

            // get the contents, ordered by 'order' column; returns an array
            $contents = self::getContent($section['section_id']);

            // variant
            $variant  = \CAT\Sections::getVariant($section['section_id']);

            // add to template search path
            self::tpl()->setPath(CAT_ENGINE_PATH.'/modules/wysiwyg/templates/'.$variant);

            // render template
            $output  = self::tpl()->get(
                'view.tpl',
                array(
                    'section_id' => $section['section_id'],
                    'columns'    => $contents,
                    'options'    => (
                        isset($section['options'])
                        ? $section['options']
                        : null
                    )
                )
            );

            return $output;
        }   // end function view()

        /**
         *
         **/
        public static function save(int $section_id)
        {

            $curr_data = self::getContent($section_id);

            // ----- contents -----
            if(null!=($contents=\CAT\Helper\Validate::sanitizePost('contents')))
            {
                if(is_array($contents))
                {
                    $errors = 0;
                    $c      = self::db()->conn();
                    foreach($contents as $item)
                    {
                        $attr = (isset($item['attribute']) ? $item['attribute'] : null);
                        $col  = (isset($item['column'])    ? $item['column']    : 1   );

                        if(isset($curr_data[$col])) {
                            $qb = \CAT\Helper\DB::qb()
                                      ->update(sprintf('%smod_wysiwyg',CAT_TABLE_PREFIX))
                                      ->set($c->quoteIdentifier('content'),'?')
                                      ->set($c->quoteIdentifier('text'),'?')
                                      ->where($c->quoteIdentifier('section_id').'=?')
                                      ->andWhere($c->quoteIdentifier('column').'=?')
                                      ->setParameter(0,$item['content'])
                                      ->setParameter(1,strip_tags($item['content']))
                                      ->setParameter(2,$section_id)
                                      ->setParameter(3,$col)
                                      ;

                            if($attr) {
                                $qb->andWhere($c->quoteIdentifier('attribute').'=?')->setParameter(4,$attr);
                            }

                            $qb->execute();

                        } else {

                            $qb = \CAT\Helper\DB::qb()
                                      ->insert(sprintf('%smod_wysiwyg',CAT_TABLE_PREFIX))
                                      ->setValue($c->quoteIdentifier('section_id'),'?')
                                      ->setValue($c->quoteIdentifier('content'),'?')
                                      ->setValue($c->quoteIdentifier('text'),'?')
                                      ->setParameter(0,$section_id)
                                      ->setParameter(1,$item['content'])
                                      ->setParameter(2,strip_tags($item['content']))
                                      ;

                            $qb->execute();

                        }

                        if(self::db()->isError())
                        {
                            $errors++;
                        }
                    }
                }
            }

            if(self::asJSON())
            {
                if($errors) {
                    \CAT\Helper\Json::printError('An error occured when trying to save the data');
                } else {
                    \CAT\Helper\Json::printSuccess('The section was saved successfully');
                }
                exit;
            }
        }

        public static function getJS()
        {
            if(!is_object(self::$e)) self::init();
            return self::$e->getJS();
        }

        /**
         *
         * @access  public
         * @param   integer  $section_id
         * @return  string
         **/
		public static function modify(array $section)
        {
            parent::modify($section); // sets template path(s)

            if(!is_object(self::$e)) self::init();

            $id       = $section['section_id'];

            // get the contents, ordered by 'order' column; returns an array
            $contents = self::getContent($id);

            $am = \CAT\Helper\AssetFactory::getInstance('backend_page');
            $am->addJS(
                self::$e->getJS(),
                'footer'
            );

            // editor has some init code
            $editor_js = self::$e->getEditorJS();
            if(!empty($editor_js))
            {
                $am->addCode(
                    self::tpl()->get(
                        new \Dwoo\Template\Str(),
                        array(
                            'section_id' => $section['section_id'],
                            'action'     => CAT_ADMIN_URL.'/section/save/'.$section['section_id'],
                            'width'      => self::$e->getWidth(),
                            'height'     => self::$e->getHeight(),
                            'id'         => $id,
                            'content'    => $content
                        )
                    ),
                    'footer'
                );
            }

            // render template
            $output  = self::tpl()->get(
                'modify.tpl',
                array(
                    'section_id' => $section['section_id'],
                    'action'     => CAT_ADMIN_URL.'/section/save/'.$section['section_id'],
                    'width'      => self::$e->getWidth(),
                    'height'     => self::$e->getHeight(),
                    'columns'    => $contents,
                    'options'    => (isset($section['options']) ? $section['options'] : null),
                    'editor'     => self::tpl()->get(
                        new \Dwoo\Template\Str(self::$e->showEditor()),
                        array(
                            'section_id' => $section['section_id'],
                            'action'     => CAT_ADMIN_URL.'/section/save/'.$section['section_id'],
                            'width'      => self::$e->getWidth(),
                            'height'     => self::$e->getHeight(),
                            'id'         => $id,
                            'content'    => $contents
                        )
                    ),
                )
            );
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
// Ich verstehe nicht warum der CKE nicht erscheint wenn ich nicht hier ein
// echo einbaue... :(
#echo "\n";
#echo "<div style=\"display:none;\"></div>";
// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

            return $output;
        }

        /**
         *
         * @access protected
         * @return
         **/
        protected static function getContent(int $section_id, bool $escape=false)
        {
            $content = array();
            // get content
            $result  = self::db()->query(
                "SELECT * FROM `:prefix:mod_wysiwyg` WHERE `section_id`=:section_id ORDER BY `column`",
                array('section_id'=>$section_id)
            );
            $data    = $result->fetchAll();
/*
Array
(
    [0] => Array
        (
            [section_id] => 56
            [column] => 1
            [attribute] => plan_tier_heading
            [content] => <p>Angebot 1<br></p>
            [text] => Angebot 1
        )
)
*/
            if($data && is_array($data) && count($data)>0)
            {
                foreach($data as $i => $c)
                {
                    if($escape)
                    {
                        $c['content'] = htmlentities($c['content']);
                        $c['text']    = htmlentities($c['text']);
                    }
                    if($c['attribute']=='')
                    {
                        $c['attribute'] = 'content';
                    }
                    $content[$c['column']][$c['attribute']] = $c['content'];
                }
                return $content;
            }
        }   // end function getContent()
        
    }
}