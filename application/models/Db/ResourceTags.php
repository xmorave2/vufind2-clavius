<?php
/**
 * Table Definition for resource_tags
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
 * Table Definition for resource_tags
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_ResourceTags extends Zend_Db_Table_Abstract
{
    // @codingStandardsIgnoreStart
    protected $_name = 'resource_tags';
    protected $_primary = 'id';
    // @codingStandardsIgnoreEnd

    /**
     * Look up a row for the specified resource.
     *
     * @param string $resource_id ID of resource to link up
     * @param string $tag_id      ID of tag to link up
     * @param string $user_id     ID of user creating link (optional but recommended)
     * @param string $list_id     ID of list to link up (optional)
     *
     * @return void
     */
    public function createLink($resource_id, $tag_id, $user_id = null,
        $list_id = null
    ) {
        $select = $this->select();
        $select->where('resource_id = ?', $resource_id)
            ->where('tag_id = ?', $tag_id);
        if (!is_null($list_id)) {
            $select->where('list_id = ?', $list_id);
        } else {
            $select->where('list_id is null');
        }
        if (!is_null($user_id)) {
            $select->where('user_id = ?', $user_id);
        } else {
            $select->where('user_id is null');
        }
        $result = $this->fetchRow($select);

        // Only create row if it does not already exist:
        if (is_null($result)) {
            $result = $this->createRow();
            $result->resource_id = $resource_id;
            $result->tag_id = $tag_id;
            if (!is_null($list_id)) {
                $result->list_id = $list_id;
            }
            if (!is_null($user_id)) {
                $result->user_id = $user_id;
            }
            $result->save();
        }
    }

    /**
     * Check whether or not the specified tags are present in the table.
     *
     * @param array $ids IDs to check.
     *
     * @return array     Associative array with two keys: present and missing
     */
    public function checkForTags($ids)
    {
        // Set up return arrays:
        $retVal = array('present' => array(), 'missing' => array());

        // Look up IDs in the table:
        $select = $this->select()->distinct()->from($this->_name, 'tag_id');
        foreach ($ids as $current) {
            $select->orWhere('tag_id = ?', $current);
        }
        $results = $this->fetchAll($select);

        // Record all IDs that are present:
        foreach ($results as $current) {
            $retVal['present'][] = $current->tag_id;
        }

        // Detect missing IDs:
        foreach ($ids as $current) {
            if (!in_array($current, $retVal['present'])) {
                $retVal['missing'][] = $current;
            }
        }

        // Send back the results:
        return $retVal;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param string|array $resource_id ID (or array of IDs) of resource(s) to
     *                                  unlink (null for ALL matching resources)
     * @param string       $user_id     ID of user removing links
     * @param string       $list_id     ID of list to unlink (null for ALL matching
     *                                  lists, 'none' for tags not in a list)
     * @param string       $tag_id      ID of tag to unlink (null for ALL matching
     *                                  tags)
     *
     * @return void
     */
    public function destroyLinks($resource_id, $user_id, $list_id = null,
        $tag_id = null
    ) {
        $db = $this->getAdapter();

        $where = $db->quoteInto('user_id = ?', $user_id);

        if (!is_null($resource_id)) {
            if (is_array($resource_id)) {
                $resourceSQL = array();
                foreach ($resource_id as $current) {
                    $resourceSQL[] = $db->quoteInto('resource_id = ?', $current);
                }
                $where .= ' AND (' . implode(' OR ', $resourceSQL) . ')';
            } else {
                $where .= $db->quoteInto(' AND resource_id = ?', $resource_id);
            }
        }
        if (!is_null($list_id)) {
            if ($list_id != 'none') {
                $where .= $db->quoteInto(' AND list_id = ?', $list_id);
            } else {
                // special case -- if $list_id is set to the string "none", we
                // want to delete tags that are not associated with lists.
                $where .= ' AND list_id is null';
            }
        }
        if (!is_null($tag_id)) {
            $where .= $db->quoteInto(' AND tag_id = ?', $tag_id);
        }

        // Get a list of all tag IDs being deleted; we'll use these for
        // orphan-checking:
        $select = $this->select()->distinct()->from($this->_name, 'tag_id')
            ->where($where);
        $potentialOrphans = $this->fetchAll($select);

        // Now delete the unwanted rows:
        $this->delete($where);

        // Check for orphans:
        if (count($potentialOrphans) > 0) {
            $ids = array();
            foreach ($potentialOrphans as $current) {
                $ids[] = $current->tag_id;
            }
            $checkResults = $this->checkForTags($ids);
            if (count($checkResults['missing']) > 0) {
                $tagTable = new VuFind_Model_Db_Tags();
                $tagTable->deleteByIdArray($checkResults['missing']);
            }
        }
    }

    /**
     * Assign anonymous tags to the specified user ID.
     *
     * @param int $id User ID to own anonymous tags.
     *
     * @return void
     */
    public function assignAnonymousTags($id)
    {
        $this->update(array('user_id' => $id), 'user_id IS NULL');
    }
}
