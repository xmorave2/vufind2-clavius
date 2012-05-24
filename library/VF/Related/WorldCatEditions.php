<?php
/**
 * Related Records: WorldCat-based editions list (WorldCat results)
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
 * Related Records: WorldCat-based editions list (WorldCat results)
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Related_WorldCatEditions extends VF_Related_Editions
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
        // If we have query parts, we should try to find related records:
        $parts = $this->getQueryParts($driver);
        if (!empty($parts)) {
            // Assemble the query parts and filter out current record if it comes
            // from the Solr index.:
            $query = '(' . implode(' or ', $parts) . ')';
            if ($driver->getResourceSource() == 'WorldCat') {
                $query .= ' not srw.no all ' . $driver->getUniqueID();
            }

            // Perform the search and save results:
            $params = new VF_Search_WorldCat_Params();
            $params->setLimit(5);
            $params->setOverrideQuery($query);
            $result = new VF_Search_WorldCat_Results($params);
            $this->results = $result->getResults();
        }
    }

    /**
     * Try to build an array of OCLC Number, ISBN or ISSN-based sub-queries by
     * using OCLC X-services against a record driver object.
     *
     * @param VF_RecordDriver_Base $driver Record driver object
     *
     * @return array
     */
    protected function getQueryParts($driver)
    {
        $wc = new VF_Connection_WorldCatUtils();
        $parts = array();
        if (method_exists($driver, 'getCleanISBN')) {
            $isbn = $driver->getCleanISBN();
            if (!empty($isbn)) {
                $isbnList = $wc->getXISBN($isbn);
                $parts[] = '(srw.bn any "' . implode(' ', $isbnList) . '")';
            }
        }
        if (method_exists($driver, 'getCleanISSN')) {
            $issn = $driver->getCleanISSN();
            if (!empty($issn)) {
                $issnList = $wc->getXISSN($issn);
                $parts[] = '(srw.sn any "' . implode(' ', $issnList) . '")';
            }
        }
        return $parts;
    }
}
