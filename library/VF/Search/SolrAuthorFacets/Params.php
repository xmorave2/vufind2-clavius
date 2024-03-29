<?php
/**
 * AuthorFacets aspect of the Search Multi-class (Params)
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
 * @link     http://vufind.org   Main Site
 */
 
/**
 * AuthorFacets Search Parameters
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Search_SolrAuthorFacets_Params extends VF_Search_Solr_Params
{
    /**
     * Set parameters based on a search object
     *
     * @param Zend_Controller_Request_Abstract $request - Zend request object
     *
     * @return void
     */
    public function initFromRequest($request)
    {
        parent::initFromRequest($request);

        // Force custom facet settings:
        $this->facetConfig = array();
        $this->addFacet('authorStr');
        $this->setFacetOffset(($this->getPage() - 1) * $this->getLimit());
        $this->setFacetLimit($this->getLimit() * 10);
        // Sorting - defaults to off with unlimited facets, so let's
        //           be explicit here for simplicity.
        if ($this->getSort() == 'author') {
            $this->setFacetSort('index');
        } else {
            $this->setFacetSort('count');
        }
    }

    /**
     * Support method for _initSearch() -- handle basic settings.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (is_null($lookfor = $request->getParam('lookfor'))) {
            return false;
        }

        // Set the search (handler is always Author for this module):
        $this->setBasicSearch($lookfor, 'Author');
        return true;
    }

    /**
     * Load all recommendation settings from the relevant ini file.  Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @return array associative: location (top/side) => search settings
     */
    protected function getRecommendationSettings()
    {
        // No recommendations here:
        return array();
    }

    /**
     * Initialize view
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return void
     */
    protected function initView($request)
    {
        $this->view = 'authorfacets';
    }
}