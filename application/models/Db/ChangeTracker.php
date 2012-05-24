<?php
/**
 * Table Definition for change_tracker
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
 * Table Definition for change_tracker
 *
 * @category VuFind2
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_ChangeTracker extends Zend_Db_Table_Abstract
{
    // @codingStandardsIgnoreStart
    protected $_name = 'change_tracker';
    protected $_primary = array('core', 'id');
    // @codingStandardsIgnoreEnd

    protected $dateFormat = 'Y-m-d H:i:s';   // date/time format for database

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function retrieve($core, $id)
    {
        return $this->fetchRow(
            $this->select()->where('core = ?', $core)->where('id = ?', $id)
        );
    }

    /**
     * Retrieve a set of deleted rows from the database.
     *
     * @param string $core  The Solr core holding the record.
     * @param string $from  The beginning date of the range to search.
     * @param string $until The end date of the range to search.
     *
     * @return Zend_Db_Table_Rowset
     */
    public function retrieveDeleted($core, $from, $until)
    {
        // Quote the 'deleted' identifier to avoid potential reserved word issues:
        $db = $this->getAdapter();
        $deleted = $db->quoteIdentifier('deleted');

        $select = $this->select()
            ->where('core = ?', $core)
            ->where($deleted . ' >= ?', $from)
            ->where($deleted . ' <= ?', $until)
            ->order('deleted');

        return $this->fetchAll($select);
    }

    /**
     * Retrieve a row from the database based on primary key; create a new
     * row if no existing match is found.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function retrieveOrCreate($core, $id)
    {
        $row = $this->retrieve($core, $id);
        if (empty($row)) {
            $row = $this->createRow();
            $row->core = $core;
            $row->id = $id;
            $row->first_indexed = $row->last_indexed = date($this->dateFormat);
        }
        return $row;
    }

    /**
     * Update the change tracker table to indicate that a record has been deleted.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function markDeleted($core, $id)
    {
        // Get a row matching the specified details:
        $row = $this->retrieveOrCreate($core, $id);

        // If the record is already deleted, we don't need to do anything!
        if (!empty($row->deleted)) {
            return $row;
        }

        // Save new value to the object:
        $row->deleted = date($this->dateFormat);
        $row->save();
        return $row;
    }

    /**
     * Update the change_tracker table to reflect that a record has been indexed.
     * We need to know the date of the last change to the record (independent of
     * its addition to the index) in order to tell the difference between a
     * reindex of a previously-encountered record and a genuine change.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core   The Solr core holding the record.
     * @param string $id     The ID of the record being indexed.
     * @param int    $change The timestamp of the last record change.
     *
     * @return Zend_Db_Table_Row_Abstract
     */
    public function index($core, $id, $change)
    {
        // Get a row matching the specified details:
        $row = $this->retrieveOrCreate($core, $id);

        // Flag to indicate whether we need to save the contents of $row:
        $saveNeeded = false;

        // Make sure there is a change date in the row (this will be empty
        // if we just created a new row):
        if (empty($row->last_record_change)) {
            $row->last_record_change = date($this->dateFormat, $change);
            $saveNeeded = true;
        }

        // Are we restoring a previously deleted record, or was the stored
        // record change date before current record change date?  Either way,
        // we need to update the table!
        if (!empty($row->deleted)
            || strtotime($row->last_record_change) < $change
        ) {
            // Save new values to the object:
            $row->last_indexed = date($this->dateFormat);
            $row->last_record_change = date($this->dateFormat, $change);

            // If first indexed is null, we're restoring a deleted record, so
            // we need to treat it as new -- we'll use the current time.
            if (empty($this->first_indexed)) {
                $row->first_indexed = $row->last_indexed;
            }

            // Make sure the record is "undeleted" if necessary:
            $row->deleted = null;

            $saveNeeded = true;
        }

        // Save the row if changes were made:
        if ($saveNeeded) {
            $row->save();
        }

        // Send back the row:
        return $row;
    }
}
