<?php
/**
 * Summon Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
 
/**
 * Summon Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class SummonController extends VF_Controller_Search
{
    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $this->searchClassId = 'Summon';
        $this->useResultScroller = false;
        $this->view->layout()->poweredBy
            = 'Powered by Summon™ from Serials Solutions, a division of ProQuest.';
        parent::init();
    }

    /**
     * Handle an advanced search
     *
     * @return void
     */
    public function advancedAction()
    {
        // Standard setup from base class:
        parent::advancedAction();

        // Set up facet information:
        $this->view->facetList = $this->processAdvancedFacets(
            $this->getAdvancedFacets()->getFacetList(), $this->view->saved
        );
    }

    /**
     * Home action
     *
     * @return void
     */
    public function homeAction()
    {
        $this->view->results = $this->getAdvancedFacets();
    }

    /**
     * Search action -- call standard results action
     *
     * @return void
     */
    public function searchAction()
    {
        $this->resultsAction();
    }

    /**
     * Forward unrecognized actions to record controller for legacy URL
     * compatibility.
     *
     * @param string $method Method name being called.
     * @param array  $params Parameters passed to method.
     *
     * @return void
     */
    public function __call($method, $params)
    {
        if (substr($method, -6) == 'Action') {
            $action = substr($method, 0, strlen($method) - 6);
            // Special case for default record action:
            if ($action == 'record') {
                $action = 'home';
            }
            return $this->_forward($action, 'SummonRecord');
        }
        throw new Exception('Unsupported method: ' . $method);
    }

    /**
     * Return a Search Results object containing advanced facet information.  This
     * data may come from the cache, and it is currently shared between the Home
     * page and the Advanced search screen.
     *
     * @return VF_Search_Summon_Results
     */
    protected function getAdvancedFacets()
    {
        // Check if we have facet results cached, and build them if we don't.
        $manager = new VF_Cache_Manager();
        $cache = $manager->getCache('object');
        if (!($results = $cache->load('summonSearchHomeFacets'))) {
            $params = new VF_Search_Summon_Params();
            $params->addFacet('Language,or,1,20');
            $params->addFacet('ContentType,or,1,20', 'Format');

            // We only care about facet lists, so don't get any results:
            $params->setLimit(0);

            $results = new VF_Search_Summon_Results($params);
            $results->getResults();
            $cache->save($results, 'summonSearchHomeFacets');
        }
        return $results;
    }

    /**
     * Process the facets to be used as limits on the Advanced Search screen.
     *
     * @param array  $facetList    The advanced facet values
     * @param object $searchObject Saved search object (false if none)
     *
     * @return array               Sorted facets, with selected values flagged.
     */
    protected function processAdvancedFacets($facetList, $searchObject = false)
    {
        // Process the facets, assuming they came back
        foreach ($facetList as $facet => $list) {
            foreach ($list['list'] as $key => $value) {
                // Build the filter string for the URL:
                $fullFilter = $facet.':"'.$value['value'].'"';

                // If we haven't already found a selected facet and the current
                // facet has been applied to the search, we should store it as
                // the selected facet for the current control.
                if ($searchObject && $searchObject->hasFilter($fullFilter)) {
                    $facetList[$facet]['list'][$key]['selected'] = true;
                    // Remove the filter from the search object -- we don't want
                    // it to show up in the "applied filters" sidebar since it
                    // will already be accounted for by being selected in the
                    // filter select list!
                    $searchObject->removeFilter($fullFilter);
                }
            }
        }
        return $facetList;
    }
}

