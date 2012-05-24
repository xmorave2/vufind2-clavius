<?php
/**
 * EuropeanaResults Recommendations Module
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
 * EuropeanaResults Recommendations Module
 *
 * This class provides recommendations by using the WorldCat Terminologies API.
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Lutz Biedinger <lutz.biedinger@gmail.com>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_EuropeanaResults implements VF_Recommend_Interface
{
    protected $requestParam;
    protected $limit;
    protected $baseUrl;
    protected $targetUrl;
    protected $excludeProviders;
    protected $searchSite;
    protected $sitePath;
    protected $key;
    protected $lookfor;
    protected $results;

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from searches.ini.
     */
    public function __construct($settings)
    {
        // Parse out parameters:
        $params = explode(':', $settings);
        $this->baseUrl = (isset($params[0]) && !empty($params[0]))
            ? $params[0] : 'api.europeana.eu/api/opensearch.rss';
        $this->requestParam = (isset($params[1]) && !empty($params[1]))
            ? $params[1] : 'searchTerms';
        $this->limit = isset($params[2]) && is_numeric($params[2])
                        && $params[2] > 0 ? intval($params[2]) : 5;
        $this->excludeProviders = (isset($params[3]) && !empty($params[3]))
            ? $params[3] : array();
        //make array
        if (!empty($this->excludeProviders)) {
            $this->excludeProviders = explode(',', $this->excludeProviders);
        }

        //get the key from config.ini
        $config = VF_Config_Reader::getConfig();
        $this->key = $config->Content->europeanaAPI;
        $this->searchSite = "Europeana.eu";
    }

    /**
     * getURL
     *
     * This method builds the url which will be send to retrieve the RSS results
     *
     * @param string $targetUrl        Base URL
     * @param string $requestParam     Parameter name to add
     * @param array  $excludeProviders An array of providers to exclude when
     * getting results.
     *
     * @return string The url to be sent
     */
    protected function getURL($targetUrl, $requestParam, $excludeProviders)
    {
        // build url
        $url = $targetUrl . "?" . $requestParam . "=" . $this->lookfor;
        //add providers to ignore
        foreach ($excludeProviders as $provider) {
            $provider = trim($provider);
            if (!empty($provider)) {
                $url .= urlencode(' NOT europeana_dataProvider:"' . $provider . '"');
            }
        }
        $url .= '&wskey=' . urlencode($this->key);

        //return complete url
        return $url;
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
        // Collect the best possible search term(s):
        $this->lookfor =  $request->getParam('lookfor', '');
        if (empty($this->lookfor) && is_object($params)) {
            $this->lookfor = $params->extractAdvancedTerms();
        }
        $this->lookfor = urlencode(trim($this->lookfor));
        $this->sitePath = 'http://www.europeana.eu/portal/search.html?query=' .
            $this->lookfor;
        $this->targetUrl = $this->getURL(
            'http://' . $this->baseUrl, $this->requestParam, $this->excludeProviders
        );
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
        Zend_Feed::setHttpClient(new VF_Http_Client());
        $parsedFeed = Zend_Feed::import($this->targetUrl);
        $resultsProcessed = array();
        foreach ($parsedFeed as $key => $value) {
            $link = (string)$value->link;
            if (!empty($link)) {
                $resultsProcessed[] = array(
                    'title' => (string)$value->title,
                    'link' => substr($link, 0, strpos($link, '.srw')) . '.html',
                    'enclosure' => (string)$value->enclosure['url']
                );
            }
            if (count($resultsProcessed) == $this->limit) {
                break;
            }
        }

        if (!empty($resultsProcessed)) {
            $this->results = array(
                'worksArray' => $resultsProcessed,
                'feedTitle' => $this->searchSite,
                'sourceLink' => $this->sitePath
            );
        } else {
            $this->results = false;
        }
    }

    /**
     * Get the results of the query (false if none).
     *
     * @return array|bool
     */
    public function getResults()
    {
        return $this->results;
    }
}