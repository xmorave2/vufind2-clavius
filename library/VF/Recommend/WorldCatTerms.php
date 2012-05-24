<?php
/**
 * WorldCatTerms Recommendations Module
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
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * WorldCatTerms Recommendations Module
 *
 * This class provides recommendations by using the WorldCat Terminologies API.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_WorldCatTerms implements VF_Recommend_Interface
{
    protected $searchObject;
    protected $vocab;

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from searches.ini.
     */
    public function __construct($settings)
    {
        // Pick a vocabulary (either user-specified, or LCSH by default):
        $params = trim($settings);
        $this->vocab = empty($params) ? 'lcsh' : $params;
    }

    /**
     * init
     *
     * Called at the end of VF_Search_Base_Params::initFromRequest().  This method
     * is responsible for setting search parameters needed by the recommendation
     * module and for reading any existing search parameters that may be needed.
     *
     * @param VF_Search_Base_Params            $params  Search parameter object
     * @param Zend_Controller_Request_Abstract $request Zend request object
     *
     * @return void
     */
    public function init($params, $request)
    {
        // No action needed.
    }

    /**
     * process
     *
     * Called after the SearchObject has performed its main search.  This may be
     * used to extract necessary information from the SearchObject or to perform
     * completely unrelated processing.
     *
     * @param VF_Search_Base_Results $results sent after search
     *
     * @return void
     */
    public function process($results)
    {
        $this->searchObject = $results;
    }

    /**
     * Get terms related to the query.
     *
     * @return array
     */
    public function getTerms()
    {
        // Extract the first search term from the search object:
        $search = $this->searchObject->getSearchTerms();
        $lookfor = isset($search[0]['lookfor']) ? $search[0]['lookfor'] : '';

        // Get terminology information:
        $wc = new VF_Connection_WorldCatUtils();
        $terms = $wc->getRelatedTerms($lookfor, $this->vocab);

        // Wipe out any empty or unexpected sections of the related terms array;
        // this will make it easier to only display content in the template if
        // we have something worth displaying.
        if (is_array($terms)) {
            $desiredKeys = array('exact', 'broader', 'narrower');
            foreach ($terms as $key => $value) {
                if (empty($value) || !in_array($key, $desiredKeys)) {
                    unset($terms[$key]);
                }
            }
        }
        return $terms;
    }
}