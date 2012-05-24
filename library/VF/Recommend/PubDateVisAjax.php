<?php
/**
 * publishDateVis
 *
 * PHP version 5
 *
 * Copyright (C) Till Kinstler 2011.
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
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * PubDateVisAjax Recommendations Module
 *
 * This class displays a visualisation of facet values in a recommendation module
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Till Kinstler <kinstler@gbv.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_PubDateVisAjax implements VF_Recommend_Interface
{
    protected $settings;
    protected $results;
    protected $facets;
    protected $zooming;
    protected $dateFacets = array();

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

        // Parse the additional settings:
        $params = explode(':', $settings);
        if ($params[0] == "true" || $params[0] == "false") {
            $this->zooming = $params[0];
            $this->dateFacets = array_slice($params, 1);
        } else {
            $this->zooming = "false";
            $this->dateFacets = $params;
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
     * getVisFacets
     *
     * Basic get
     *
     * @return array
     */
    public function getVisFacets()
    {
        return $this->_processDateFacets(
            $this->searchObject->getFilters()
        );
    }

    /**
     * getZooming
     *
     * Basic get
     *
     * @return array
     */
    public function getZooming()
    {
        if (isset($this->zooming)) {
            return $this->zooming;
        }
        return 'false';
    }

    /**
     * getFacetFields
     *
     * Basic get
     *
     * @return array
     */
    public function getFacetFields()
    {
        return implode(':', $this->dateFacets);
    }

    /**
     * getSearchParams
     *
     * @return string of params
     */
    public function getSearchParams()
    {
        // Get search parameters and return them minus the leading ?:
        return substr($this->searchObject->getUrl()->getParams(false), 1);
    }

    /**
     * Support method for getVisData() -- extract details from applied filters.
     *
     * @param array $filters Current filter list
     *
     * @return array
     */
    private function _processDateFacets($filters)
    {
        $result = array();
        foreach ($this->dateFacets as $current) {
            $from = $to = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if (preg_match('/\[\d+ TO \d+\]/', $filter)) {
                        $range = explode(' TO ', trim($filter, '[]'));
                        $from = $range[0] == '*' ? '' : $range[0];
                        $to = $range[1] == '*' ? '' : $range[1];
                        break;
                    }
                }
            }
            $result[$current] = array($from, $to);
            $result[$current]['label']
                = $this->searchObject->getFacetLabel($current);
        }
        return $result;
    }
}
