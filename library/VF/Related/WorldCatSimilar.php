<?php
/**
 * Related Records: WorldCat-based similarity
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Related Records: WorldCat-based similarity
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Related_WorldCatSimilar extends VF_Related_Similar
{
    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string               $settings Settings from config.ini
     * @param VF_RecordDriver_Base $driver   Record driver object
     */
    public function __construct($settings, $driver)
    {
        // Create array of query parts:
        $parts = array();

        // Add Dewey class to query
        $deweyClass = method_exists($driver, 'getDeweyCallNumber')
            ? $driver->getDeweyCallNumber() : '';
        if (!empty($deweyClass)) {
            // Skip "English Fiction" Dewey class -- this won't give us useful
            // matches because there's too much of it and it's too broad.
            if (substr($deweyClass, 0, 3) != '823') {
                $parts[] = 'srw.dd any "' . $deweyClass . '"';
            }
        }

        // Add author to query
        $author = $driver->getPrimaryAuthor();
        if (!empty($author)) {
            $parts[] = 'srw.au all "' . $author . '"';
        }

        // Add subjects to query
        $subjects = $driver->getAllSubjectHeadings();
        foreach ($subjects as $current) {
            $parts[] = 'srw.su all "' . implode(' ', $current) . '"';
        }

        // Add title to query
        $title = $driver->getTitle();
        if (!empty($title)) {
            $parts[] = 'srw.ti any "' . str_replace('"', '', $title) . '"';
        }

        // Build basic query:
        $query = '(' . implode(' or ', $parts) . ')';

        // Not current record ID if this is already a WorldCat record:
        if ($driver->getResourceSource() == 'WorldCat') {
            $id = $driver->getUniqueId();
            $query .= " not srw.no all \"$id\"";
        }

        // Perform the search and save results:
        $params = new VF_Search_WorldCat_Params();
        $params->setLimit(5);
        $params->setOverrideQuery($query);
        $result = new VF_Search_WorldCat_Results($params);
        $this->results = $result->getResults();
    }
}
