<?php
/**
 * Empty Search Object
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Simple search results object to represent an empty set (used when dealing with
 * exceptions that prevent a "real" search object from being constructed).
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VF_Search_Empty_Results extends VF_Search_Base_Results
{
    /**
     * Support method for constructor -- perform a search based on the parameters
     * passed to the object.
     *
     * @return void
     */
    protected function performSearch()
    {
        // Do nothing
    }

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing all
     * of the desired facet fields; set to null to get all configured values.
     *
     * @return array                Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        return array();
    }

    /**
     * Static method to retrieve a record by ID.  Returns a record driver object.
     *
     * @param string $id Unique identifier of record
     *
     * @throws VF_Exception_RecordMissing
     * @return VF_RecordDriver_Base
     */
    public static function getRecord($id)
    {
        throw new Exception('Cannot get record from empty set.');
    }
}