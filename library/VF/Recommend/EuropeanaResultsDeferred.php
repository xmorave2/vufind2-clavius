<?php
/**
 * EuropeanaResultsDeferred Recommendations Module
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
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * EuropeanaResultsDeferred Recommendations Module
 *
 * This class sets up an AJAX call to trigger a call to the EuropeanaResults
 * module.  
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedigner@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_EuropeanaResultsDeferred {
    protected $rawParams;
    protected $lookfor;
    protected $processedParams;

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from searches.ini.
     */
    public function __construct($settings)
    {
        $this->rawParams = $settings;
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
        // Parse out parameters:
        $settings = explode(':', $this->rawParams);

        // Make sure all elements of the params array are filled in, even if just
        // with a blank string, so we can rebuild the parameters to pass through
        // AJAX later on!
        for ($i = 0; $i < 4; $i++) {
            $settings[$i] = isset($settings[$i]) ? $settings[$i] : '';
        }

        // Collect the best possible search term(s):
        $this->lookfor =  $request->getParam('lookfor', '');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->extractAdvancedTerms();
        }
        $this->lookfor = trim($this->lookfor);
        $this->processedParams = implode(':', $settings);
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
        // No action needed
    }

    /**
     * Get the URL parameters needed to make the AJAX recommendation request.
     *
     * @return string
     */
    public function getUrlParams()
    {
        return 'mod=EuropeanaResults&params=' . urlencode($this->processedParams)
            . '&lookfor=' . urlencode($this->lookfor);
    }
}