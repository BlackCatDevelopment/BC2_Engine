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

if (!class_exists('\CAT\Backend\Sites'))
{
    class Sites extends Base
    {
        protected static $loglevel    = \Monolog\Logger::EMERGENCY;

        /**
         *
         * @access public
         * @return
         **/
        public static function add()
        {
            if(!self::user()->hasPerm('site_admin')) {
                self::printFatalError('You are not allowed for the requested action!');
            }

            $form   = self::populateForm();
            $errors = 0;

            $form->setAttribute('action',CAT_ADMIN_URL.'/sites/add');

            if($form->isValid())
            {
                $data   = $form->getData();
/*
Array
(
    [site_name] => sss
    [site_basedir] => fff
    [site_folder] => asdfasdf
    [site_owner] => 1
    [site_url] => xxx
    [Speichern] => Speichern
    [Abbrechen] =>
)
*/
                if(!is_dir(\CAT\Helper\Directory::sanitizePath($data['site_basedir']))) {
                    $form->addError(self::lang()->t('No such folder!'));
                    $form->getElement('site_basedir')->setError();
                    $errors++;
                }
                // the site name / folder must not exist!
                if(\CAT\Helper\Sites::exists($data['site_name']))
                {
                    $form->addError(self::lang()->t('A site with the same name already exists!'));
                    $form->getElement('site_name')->setError();
                    $errors++;
                }
                if(\CAT\Helper\Sites::exists($data['site_folder'],'folder'))
                {
                    $form->addError(self::lang()->t('A site with the same folder name already exists!'));
                    $form->getElement('site_folder')->setError();
                    $errors++;
                }
                $fullpath = \CAT\Helper\Directory::sanitizePath($data['site_basedir'].'/'.$data['site_folder']);
                if(is_dir($fullpath))
                {
                    $form->addError(self::lang()->t('The folder [{{folder}}] already exists!',array('folder'=>$fullpath)));
                    $form->getElement('site_basedir')->setError();
                    $form->getElement('site_folder')->setError();
                    $errors++;
                }

                if(!$errors) {
                    // create db entry
                    self::db()->query(
                          'INSERT INTO `:prefix:sites` (`site_owner`,`site_basedir`,`site_folder`,`site_name`,`site_url`) '
                        . 'VALUES(?,?,?,?,?)',
                        array($data['site_owner'],$data['site_basedir'],$data['site_folder'],$data['site_name'],$data['site_url'])
                    );
                    // create folder
                    \CAT\Helper\Directory::createDirectory($fullpath);
                    \CAT\Helper\Directory::createDirectory($fullpath.'/assets');
                    \CAT\Helper\Directory::createDirectory($fullpath.'/media');
                    self::createFiles($fullpath,self::db()->lastInsertId(),$data);
                }
            }

            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult(
                    ( ($errors>0) ? false : true ),
                    $form->render(1)
                );
                return;
            }

            Backend::show(
                'backend_sites',
                array(
                    'new_site_form' => (isset($form) ? $form->render(1) : ''),
                )
            );
        }   // end function add()

        /**
         *
         * @access public
         * @return
         **/
        public static function edit()
        {
            if (!self::user()->hasPerm('site_edit')) {
                \CAT\Helper\Json::printError('You are not allowed for the requested action!');
            }
            $id   = self::getItemID();
            $form = self::populateForm($id);
            $form->setAttribute('action',CAT_ADMIN_URL.'/sites/edit/'.$id);
            $form->getElement('create_site')->setLabel(self::lang()->t('Edit site'));
            $form->getElement('site_basedir')->setReadonly(true);
            $form->getElement('site_folder')->setReadonly(true);

            if($form->isValid())
            {
                $data   = $form->getData();
/*
Array
(
    [site_id] => 7
    [site_owner] => 1
    [site_basedir] => p:\BlackCat2\htdocs
    [site_folder] => site3
    [site_name] => Site3
    [site_url] => //localhost:444/
    [Speichern] => Speichern
    [Abbrechen] =>
)
*/
            }

            if(self::asJSON())
            {
                echo \CAT\Helper\Json::printResult(
                    true,
                    $form->render(1)
                );
                return;
            }

            Backend::show(
                'backend_sites',
                array(
                    'edit_site_form' => (isset($form) ? $form->render(1) : ''),
                )
            );
        }   // end function edit()

        /**
         *
         * @access public
         * @return
         **/
        public static function index()
        {
            if(!self::user()->hasPerm('site_admin'))
                self::printFatalError('You are not allowed for the requested action!');

            $users = array();

            $stmt = self::db()->query(
                  'SELECT * FROM `:prefix:sites` AS `t1` '
                . 'JOIN `:prefix:rbac_users` AS `t2` '
                . 'ON `t1`.`site_owner`=`t2`.`user_id` '
            );
            $sites = $stmt->fetchAll();

            // get site data
            if(is_array($sites) && count($sites)>0) {
                for($i=0;$i<count($sites);$i++) {
                    $basedir = \CAT\Helper\Directory::sanitizePath($sites[$i]['site_basedir'].'/'.$sites[$i]['site_folder']);
                    if(is_dir($basedir)) {
                        $sites[$i]['asset_size'] = \CAT\Helper\Directory::getDirectorySize($basedir,true);
                    }
                }
            }

            // to add new sites, the admin must have the users_list permission!
            if(self::user()->hasPerm('users_list'))
            {
                $form = self::populateForm();
            }

            Backend::show(
                'backend_sites',
                array(
                    'sites'         => $sites,
                    'users'         => $users,
                    'new_site_form' => (isset($form) ? $form->render(1) : ''),
                )
            );
        }   // end function index()

        /**
         *
         * @access protected
         * @return
         **/
        protected static function createFiles($dir,$id,$data)
        {
            $fh = fopen($dir.'/.htaccess','w');
            fwrite($fh,"Options FollowSymLinks\n");
            fwrite($fh,"RewriteEngine on\n");
            fwrite($fh,"RewriteBase /".basename($dir)."/\n");
            fwrite($fh,"RewriteRule ^(tmp)\/|\.ini$ - [R=404]\n");
            fwrite($fh,"RewriteCond %{REQUEST_FILENAME} !-l\n");
            fwrite($fh,"RewriteCond %{REQUEST_FILENAME} !-f\n");
            fwrite($fh,"RewriteCond %{REQUEST_FILENAME} !-d\n");
            fwrite($fh,"RewriteCond ^(/?.+)$ !^favicon\.ico\n");
            fwrite($fh,"RewriteRule .* index.php [L,QSA,E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n");
            fclose($fh);

            $fh = fopen($dir.'/config.php','w');
            fwrite($fh,'<'.'?'."php\n\n");
            fwrite($fh,"/*\n");
            fwrite($fh,"   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___\n");
            fwrite($fh,"  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)\n");
            fwrite($fh,"   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \ \n");
            fwrite($fh,"  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/\n\n");

            fwrite($fh,"   @author          Black Cat Development\n");
            fwrite($fh,"   @copyright       ".date('Y')." Black Cat Development\n");
            fwrite($fh,"   @link            https://blackcat-cms.org\n");
            fwrite($fh,"   @license         http://www.gnu.org/licenses/gpl.html\n");
            fwrite($fh,"   @category        CAT_Core\n");
            fwrite($fh,"   @package         CAT_Core\n\n");

            fwrite($fh,"   SITE CONFIGURATION FILE. DO NOT CHANGE!\n\n");

            fwrite($fh,"*/\n\n");

            fwrite($fh,"define('CAT_SITE_ID',".$id.");\n");
            fwrite($fh,"define('CAT_URL','".CAT_URL."');\n");
            fwrite($fh,"define('CAT_SITE_URL','".$data['site_url']."/".$data['site_folder']."');\n");
            fwrite($fh,"define('CAT_PATH',str_replace('\\\\','/',__DIR__));\n");
            fwrite($fh,"define('CAT_ENGINE_PATH',str_replace('\\\\','/',__DIR__).'/../../cat_engine');\n");
            fwrite($fh,"define('CAT_BACKEND_PATH','backend');\n");
            fclose($fh);

            $fh = fopen($dir.'/index.php','w');
            fwrite($fh,'<'.'?'."php\n\n");
            fwrite($fh,"/*\n");
            fwrite($fh,"   ____  __      __    ___  _  _  ___    __   ____     ___  __  __  ___\n");
            fwrite($fh,"  (  _ \(  )    /__\  / __)( )/ )/ __)  /__\ (_  _)   / __)(  \/  )/ __)\n");
            fwrite($fh,"   ) _ < )(__  /(__)\( (__  )  (( (__  /(__)\  )(    ( (__  )    ( \__ \ \n");
            fwrite($fh,"  (____/(____)(__)(__)\___)(_)\_)\___)(__)(__)(__)    \___)(_/\/\_)(___/\n\n");
            fwrite($fh,"*/\n\n");

            fwrite($fh,"require __DIR__.'/config.php';\n");
            fwrite($fh,"require CAT_ENGINE_PATH.'/CAT/bootstrap.php';\n");
            fwrite($fh,"\CAT\Frontend::dispatch();\n");
            fclose($fh);

        }   // end function createFiles()
        
        
        /**
         *
         * @access protected
         * @return
         **/
        protected static function populateForm($id=null)
        {
            $form = \CAT\Helper\FormBuilder::generateForm('be_site',array());

            // user select
            $stmt = self::db()->query(
                  'SELECT `user_id`, `username`, `display_name` '
                . 'FROM `:prefix:rbac_users` '
                . 'WHERE `active`=1 AND `username`<>?',
                array('guest')
            );
            $temp = $stmt->fetchAll();
            if(is_array($temp) && count($temp)>0) {
                for($i=0;$i<count($temp);$i++) {
                    $users[$temp[$i]['user_id']] = $temp[$i]['username'].' ('.$temp[$i]['display_name'].')';
                }
            }
            $form->getElement('site_owner')->setData($users);
            $form->getElement('site_url')->setValue(CAT_URL.'/');

            if($id) {
                $stmt = self::db()->query(
                      'SELECT * '
                    . 'FROM `:prefix:sites` '
                    . 'WHERE `site_id`=?',
                    array($id)
                );
                $data = $stmt->fetchAll();
                $form->setData($data[0]);
            }
            return $form;
        }   // end function populateForm()
        
    }
}