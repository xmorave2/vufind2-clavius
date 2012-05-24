<?php
/**
 * AuthorityRecommend Recommendations Module
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
 * @package  Recommendations
 * @author   Lutz Biedinger (National Library of Ireland)
 * <vufind-tech@lists.sourceforge.net>
 * @author   Ronan McHugh (National Library of Ireland)
 * <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * AuthorityRecommend Module
 *
 * This class provides recommendations based on Authority records.
 * i.e. searches for a pseudonym will provide the user with a link
 * to the official name (according to the Authority index)
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Lutz Biedinger (National Library of Ireland)
 * <vufind-tech@lists.sourceforge.net>
 * @author   Ronan McHugh (National Library of Ireland)
 * <vufind-tech@lists.sourceforge.net>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VF_Recommend_AuthorityRecommend implements VF_Recommend_Interface
{
    protected $searchObject;
    protected $lookfor;
    protected $filters = array();
    protected $results = array();

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from searches.ini.
     */
    public function __construct($settings)
    {
        $params = explode(':', $settings);
        for ($i = 0; $i < count($params); $i += 2) {
            if (isset($params[$i+1])) {
                $this->filters[] = $params[$i] . ':(' . $params[$i + 1] . ')';
            }
        }
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
        // Save user search query:
        $this->lookfor = $request->getParam('lookfor');
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
        // function will return blank on Advanced Search
        if ($results->getSearchType()== 'advanced') {
            return;
        }

        // Build an advanced search request that prevents Solr from retrieving
        // records that would already have been retrieved by a search of the biblio
        // core, i.e. it only returns results where $lookfor IS found in in the
        // "Heading" search and IS NOT found in the "MainHeading" search defined
        // in authsearchspecs.yaml.
        $request = new Zend_Controller_Request_Simple();
        $request->setParam('join', 'AND');
        $request->setParam('bool0', array('AND'));
        $request->setParam('lookfor0', array($this->lookfor));
        $request->setParam('type0', array('Heading'));
        $request->setParam('bool1', array('NOT'));
        $request->setParam('lookfor1', array($this->lookfor));
        $request->setParam('type1', array('MainHeading'));

        // Initialise and process search:
        $authParams = new VF_Search_SolrAuth_Params();
        $authParams->initFromRequest($request);
        foreach ($this->filters as $filter) {
            $authParams->addHiddenFilter($filter);
        }
        $authResults = new VF_Search_SolrAuth_Results($authParams);

        // loop through records and assign id and headings to separate arrays defined
        // above
        foreach ($authResults->getResults() as $result) {
            // Extract relevant details:
            $recordArray = array(
                'id' => $result->getUniqueID(),
                'heading' => $result->getBreadcrumb()
            );

            // check for duplicates before adding record to recordSet
            if (!$this->inArrayR($recordArray['heading'], $this->results)) {
                array_push($this->results, $recordArray);
            } else {
                continue;
            }
        }
    }

    /**
     * Get results (for use in the view).
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * inArrayR
     *
     * Helper function to do recursive searches of multi-dimensional arrays.
     *
     * @param string $needle   Search term
     * @param array  $haystack Multi-dimensional array
     *
     * @return bool
     */
    public function inArrayR($needle, $haystack)
    {
        foreach ($haystack as $v) {
            if ($needle == $v) {
                return true;
            } elseif (is_array($v)) {
                if ($this->inArrayR($needle, $v)) {
                    return true;
                }
            }
        }
        return false;
    }
}
