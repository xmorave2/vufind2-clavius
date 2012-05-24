<?php
/**
 * Table Definition for oai_resumption
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
 * Table Definition for oai_resumption
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_OaiResumption extends Zend_Db_Table_Abstract
{
    // @codingStandardsIgnoreStart
    protected $_name = 'oai_resumption';
    protected $_primary = 'id';
    protected $_rowClass = 'VuFind_Model_Db_OaiResumptionRow';
    // @codingStandardsIgnoreEnd

    /**
     * Remove all expired tokens from the database.
     *
     * @return void
     */
    public function removeExpired()
    {
        $db = $this->getAdapter();
        $now = date('Y-m-d H:i:s');
        $where = $db->quoteInto('expires <= ?', $now);
        $this->delete($where);
    }

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $token The resumption token to retrieve.
     *
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findToken($token)
    {
        return $this->fetchRow($this->select()->where('id = ?', $token));
    }

    /**
     * Create a new resumption token
     *
     * @param array $params   Parameters associated with the token.
     * @param int   $expire   Expiration time for token (Unix timestamp).
     *
     * @return int          ID of new token
     */
    public function saveToken($params, $expire)
    {
        $row = $this->createRow();
        $row->saveParams($params);
        $row->expires = date('Y-m-d H:i:s', $expire);
        return $row->save();
    }
}
