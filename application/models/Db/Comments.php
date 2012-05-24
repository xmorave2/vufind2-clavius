<?php
/**
 * Table Definition for comments
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2012.
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
 * Table Definition for comments
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_Comments extends Zend_Db_Table_Abstract
{
    // @codingStandardsIgnoreStart
    protected $_name = 'comments';
    protected $_primary = 'id';
    // @codingStandardsIgnoreEnd

    /**
     * Get tags associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     *
     * @return array|Zend_Db_Table_Rowset_Abstract
     */
    public function getForResource($id, $source = 'VuFind')
    {
        $resourceTable = new VuFind_Model_Db_Resource();
        $resource = $resourceTable->findResource($id, $source, false);
        if (empty($resource)) {
            return array();
        }

        $select = $this->select()
            ->setIntegrityCheck(false)   // allow join
            ->from(array('c' => $this->_name))
            ->join(
                array('u' => 'user'), 'u.id = c.user_id',
                array('u.firstname', 'u.lastname')
            )
            ->where('c.resource_id = ?', $resource->id)
            ->order(array('c.created'));

        return $this->fetchAll($select);
    }

    /**
     * Delete a comment if the owner is logged in.  Returns true on success.
     *
     * @param int                        $id   ID of row to delete
     * @param Zend_Db_Table_Row_Abstract $user Logged in user object
     *
     * @return bool
     */
    public function deleteIfOwnedByUser($id, $user)
    {
        // User must be object with ID:
        if (!is_object($user) || !isset($user->id)) {
            return false;
        }

        // Comment row must exist:
        $matches = $this->find($id);
        if (count($matches) == 0 || !($row = $matches->current())) {
            return false;
        }

        // Row must be owned by user:
        if ($row->user_id != $user->id) {
            return false;
        }

        // If we got this far, everything is okay:
        $row->delete();
        return true;
    }
}
