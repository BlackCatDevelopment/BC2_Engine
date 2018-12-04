<?php

/**
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 3 of the License, or (at
 *   your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful, but
 *   WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 *   General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, see <http://www.gnu.org/licenses/>.
 *
 *   @author          Black Cat Development
 *   @copyright       2013 - Black Cat Development
 *   @link            http://blackcat-cms.org
 *   @license         http://www.gnu.org/licenses/gpl.html
 *   @category        CAT_Core
 *   @package         CAT_Core
 *
 */

namespace CAT\Helper;
use \CAT\Base as Base;

if (!class_exists('\CAT\Helper\Sites'))
{
    class Sites extends Base
    {
        /**
         *
         * @access public
         * @return
         **/
        public static function exists($name,$check='name')
        {
            $stmt = self::db()->query(
                'SELECT `site_id` FROM `:prefix:sites` WHERE `:field:`=:name',
                array('field'=>'site_'.$check,'name'=>$name)
            );
            return ($stmt->rowCount()>0);
        }   // end function exists()

    } // class Sites

} // if class_exists()