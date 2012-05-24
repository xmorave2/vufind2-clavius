<?php
/**
 * Table Definition for search
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
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Table Definition for search
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_Search extends Zend_Db_Table_Abstract
{
    // @codingStandardsIgnoreStart
    protected $_name = 'search';
    protected $_primary = 'id';
    // @codingStandardsIgnoreEnd

    /**
     * Delete unsaved searches for a particular session.
     *
     * @param string $sid Session ID of current user.
     *
     * @return void
     */
    public function destroySession($sid)
    {
        $db = $this->getAdapter();
        $where = $db->quoteInto($db->quoteIdentifier('session_id') . ' = ?', $sid)
            . ' AND '. $db->quoteInto($db->quoteIdentifier('saved') . ' = ?', 0);
        $this->delete($where);
    }

    /**
     * Get an array of rows for the specified user.
     *
     * @param string $sid Session ID of current user.
     * @param int    $uid User ID of current user (optional).
     *
     * @return array      Matching SearchEntry objects.
     */
    public function getSearches($sid, $uid = null)
    {
        $db = $this->getAdapter();
        $select = $this->select()
            ->where($db->quoteIdentifier('session_id') . ' = ?', $sid);
        if ($uid != null) {
            $select->orWhere($db->quoteIdentifier('user_id') . ' = ?', $uid);
        }
        $select->order('id');

        return $this->fetchAll($select);
    }

    /**
     * Get an array of rows representing expired, unsaved searches.
     *
     * @param int $daysOld Age in days of an "expired" search.
     *
     * @return array       Matching SearchEntry objects.
     */
    public function getExpiredSearches($daysOld = 2)
    {
        // Determine the expiration date:
        $expireDate = date('Y-m-d', time() - $daysOld * 24 * 60 * 60);

        // Find expired, unsaved searches:
        $db = $this->getAdapter();
        $select = $this->select()
            ->where($db->quoteIdentifier('saved') . ' = ?', 0)
            ->where($db->quoteIdentifier('created') . ' < ?', $expireDate);

        return $this->fetchAll($select);
    }

    /**
     * Get a single row matching a primary key value.
     *
     * @param int $id Primary key value.
     *
     * @throws Exception
     * @return Zend_Db_Table_Row
     */
    public function getRowById($id)
    {
        $rows = $this->find($id);
        if (count($rows) < 1) {
            throw new Exception('Cannot find id ' . $id);
        }
        return $rows->getRow(0);
    }

    /**
     * Set the "saved" flag for a specific row.
     *
     * @param int  $id      Primary key value of row to change.
     * @param bool $saved   New status value to save.
     * @param int  $user_id ID of user saving row (only required if $saved == true)
     *
     * @return void
     */
    public function setSavedFlag($id, $saved, $user_id = false)
    {
        $row = $this->getRowById($id);
        $row->saved = $saved ? 1 : 0;
        if ($user_id !== false) {
            $row->user_id = $user_id;
        }
        $row->save();
    }

    /**
     * Add a search into the search table (history)
     *
     * @param VF_Search_Base_Results $newSearch     Search to save
     * @param array                  $searchHistory Existing saved searches (for
     * deduplication purposes)
     *
     * @return void
     */
    public function saveSearch($newSearch, $searchHistory = array())
    {
        // Duplicate elimination
        $dupSaved  = false;
        foreach ($searchHistory as $oldSearch) {
            // Deminify the old search
            $minSO = unserialize($oldSearch->search_object);
            $dupSearch = $minSO->deminify();
            // See if the classes and urls match
            $oldUrl = $dupSearch->getUrl()->getParams();
            $newUrl = $newSearch->getUrl()->getParams();
            if (get_class($dupSearch) == get_class($newSearch)
                && $oldUrl == $newUrl
            ) {
                // Is the older search saved?
                if ($oldSearch->saved) {
                    // Return existing saved row instead of creating a new one:
                    $newSearch->updateSaveStatus($oldSearch);
                    return;
                } else {
                    // Delete the old search since we'll be creating a new, more
                    // current version below:
                    $oldSearch->delete();
                }
            }
        }

        // If we got this far, we didn't find a saved duplicate, so we should
        // save the new search:
        $data = array(
            'session_id' => Zend_Session::getId(),
            'created' => date('Y-m-d'),
            'search_object' => serialize(new minSO($newSearch))
        );
        $row = $this->getRowById($this->insert($data));

        // Chicken and egg... We didn't know the id before insert
        $newSearch->updateSaveStatus($row);
        $row->search_object = serialize(new minSO($newSearch));
        $row->save();
    }
}

/**
 * Support class for legacy compatibility (this old class name used to be
 * used by earlier versions of VuFind; by instantiating the class here, we
 * make sure that the minSO name will work any time the database is accessed).
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
 
// @codingStandardsIgnoreStart - lowercase class name
class minSO extends VF_MS // @codingStandardsIgnoreEnd
{
}