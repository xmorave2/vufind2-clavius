<?php
/**
 * VuFind Action Helper - Reserves Support Methods
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
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Zend action helper to perform reserves-related actions
 *
 * @category VuFind2
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VuFind_Action_Helper_Reserves extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * Default method -- get access to the object so other methods may be called.
     *
     * @return VuFind_Action_Helper_Renewals
     */
    public function direct()
    {
        return $this;
    }

    /**
     * Do we need to use the Solr index for reserves (true)
     *                            or the ILS driver (false)?
     *
     * @return bool
     */
    public function useIndex()
    {
        $config = VF_Config_Reader::getConfig();
        return isset($config->Reserves->search_enabled)
            && $config->Reserves->search_enabled;
    }

    /**
     * Get reserve info from the catalog or Solr reserves index.
     *
     * @param string $course Course ID to use as limit (optional)
     * @param string $inst   Instructor ID to use as limit (optional)
     * @param string $dept   Department ID to use as limit (optional)
     *
     * @return array
     */
    public function findReserves($course = null, $inst = null, $dept = null)
    {
        // Special case -- process reserves info using index
        if ($this->useIndex()) {
            // connect to reserves index
            $reservesIndex = VF_Connection_Manager::connectToIndex('SolrReserves');
            // get the selected reserve record from reserves index
            // and extract the bib IDs from it
            $result = $reservesIndex->findReserves($course, $inst, $dept);
            $bibs = array();
            $instructor = isset($result['instructor']) ? $result['instructor'] : '';
            $course = isset($result['course']) ? $result['course'] : '';
            foreach ($result['bib_id'] as $bib_id) {
                $bibs[] = array(
                    'BIB_ID' => $bib_id,
                    'bib_id' => $bib_id,
                    'course' => $course,
                    'instructor' => $instructor
                );
            }
            return $bibs;
        }

        // Default case -- find reserves info from the catalog
        $catalog = VF_Connection_Manager::connectToCatalog();
        return $catalog->findReserves($course, $inst, $dept);
    }
}