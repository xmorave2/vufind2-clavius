<?php
/**
 * Table Definition for user
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * Table Definition for user
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_User extends Zend_Db_Table_Abstract
{
    // @codingStandardsIgnoreStart
    protected $_name = 'user';
    protected $_primary = 'id';
    protected $_rowClass = 'VuFind_Model_Db_UserRow';
    // @codingStandardsIgnoreEnd

    /**
     * Retrieve a user object from the database based on username; create a new
     * row if no existing match is found.
     *
     * @param string $username Username to use for retrieval.
     * @param bool   $create   Should we create users that don't already exist?
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public static function getByUsername($username, $create = true)
    {
        $user = new VuFind_Model_Db_User();
        $row = $user->fetchRow($user->select()->where('username = ?', $username));
        if ($create && empty($row)) {
            $row = $user->createRow();
            $row->username = $username;
            $row->created = date('Y-m-d h:i:s');
        }
        return $row;
    }

    /**
     * Retrieve a user object from the database based on email; create a new
     * row if no existing match is found.
     *
     * @param string $email email to use for retrieval.
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public static function getByEmail($email)
    {
        $user = new VuFind_Model_Db_User();
        $row = $user->fetchRow($user->select()->where('email = ?', $email));
        return $row;
    }

}
