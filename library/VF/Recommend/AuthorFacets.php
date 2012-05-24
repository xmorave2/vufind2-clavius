<?php
/**
 * AuthorFacets Recommendations Module
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * AuthorFacets Recommendations Module
 *
 * This class provides recommendations displaying authors on top of the page. Default
 * on author searches.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_AuthorFacets implements VF_Recommend_Interface
{
    protected $settings;
    protected $searchObject;

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from searches.ini.
     */
    public function __construct($settings)
    {
        // Save the basic parameters:
        $this->settings = $settings;
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
        // No action needed here.
    }

    /**
     * process
     *
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param VF_Search_Base_Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        $this->results = $results;
    }

    /**
     * Returns search term.
     *
     * @return string
     */
    public function getSearchTerm()
    {
        $search = $this->results->getSearchTerms();
        if (isset($search[0]['lookfor'])) {
            return $search[0]['lookfor'];
        }
        return '';
    }

    /**
     * Process similar authors from an author search
     *
     * @return  array     Facets data arrays
     */
    public function getSimilarAuthors()
    {
        // Do not provide recommendations for blank searches:
        $lookfor = $this->getSearchTerm();
        if (empty($lookfor)) {
            return array('count' => 0, 'list' => array());
        }

        // Set up a special limit for the AuthorFacets search object:
        $options = new VF_Search_SolrAuthorFacets_Options();
        $options->setLimitOptions(array(10));

        // Initialize an AuthorFacets search object using parameters from the
        // current Solr search object.
        $request = new Zend_Controller_Request_Simple();
        $request->setParam('lookfor', $lookfor);
        $params = new VF_Search_SolrAuthorFacets_Params($options);
        $params->initFromRequest($request);

        // Send back the results:
        $results = new VF_Search_SolrAuthorFacets_Results($params);
        return array(
            // Total authors (currently there is no way to calculate this without
            // risking out-of-memory errors or slow results, so we set this to
            // false; if we are able to find this information out in the future,
            // we can fill it in here and the templates will display it).
            'count' => false,
            'list' => $results->getResults()
        );
    }
}