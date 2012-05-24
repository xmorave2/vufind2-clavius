<?php
/**
 * OpenLibrarySubjects Recommendations Module
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan � Carrag�in <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * OpenLibrarySubjects Recommendations Module
 *
 * This class provides recommendations by doing a search of the catalog; useful
 * for displaying catalog recommendations in other modules (i.e. Summon, Web, etc.)
 *
 * @category VuFind2
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan � Carrag�in <eoghan.ocarragain@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_OpenLibrarySubjects implements VF_Recommend_Interface
{
    protected $requestParam;
    protected $limit;
    protected $pubFilter;
    protected $publishedIn = '';
    protected $subject;
    protected $subjectTypes;
    protected $result = false;

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
        $this->requestParam = empty($params[0]) ? 'lookfor' : $params[0];
        $this->limit = isset($params[1]) && is_numeric($params[1]) && $params[1] > 0
            ? intval($params[1]) : 5;
        $this->pubFilter = (!isset($params[2]) || empty($params[2])) ?
            'publishDate' : $params[2];
        if (strtolower(trim($this->pubFilter)) == 'false') {
            $this->pubFilter = false;
        }

        if (isset($params[3])) {
            $this->subjectTypes = explode(',', $params[3]);
        } else {
            $this->subjectTypes = array("topic");
        }

        // A 4th parameter is not specified in searches.ini, if it exists
        //     it has been passed in by an AJAX call and carries the
        //     publication date range in the form YYYY-YYYY
        if (isset($params[4]) && strstr($params[4], '-') != false) {
            $this->publishedIn = $params[4];
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
        // Get and normalise $requestParam
        $this->subject =  $request->getParam($this->requestParam);

        // Set up the published date range if it has not already been provided:
        if (empty($this->publishedIn) && $this->pubFilter) {
            $this->publishedIn = $this->getPublishedDates(
                $this->pubFilter, $params, $request
            );
        }
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
        // Only proceed if we have a request parameter value
        if (!empty($this->subject)) {
            $result = array();
            $ol = new VF_Connection_OpenLibrary();
            $result = $ol->getSubjects(
                $this->subject, $this->publishedIn, $this->subjectTypes, true, false,
                $this->limit, null, true
            );

            if (!empty($result)) {
                $this->result = array(
                    'worksArray' => $result, 'subject' => $this->subject
                );
            }
        }
    }

    /**
     * Support function to get publication date range. Return string in the form
     * "YYYY-YYYY"
     *
     * @param string                           $field   Name of filter field to
     * check for date limits
     * @param VF_Search_Params_Base            $params  Search parameter object
     * @param Zend_Controller_Request_Abstract $request Zend request object
     *
     * @return string
     * @access protected
     */
    protected function getPublishedDates($field, $params, $request)
    {
        // Try to extract range details from request parameters or SearchObject:
        $from = $request->getParam($field . 'from');
        $to = $request->getParam($field . 'to');
        if (!is_null($from) && !is_null($to)) {
            $range = array('from' => $from, 'to' => $to);
        } else if (is_object($params)) {
            $currentFilters = $params->getFilters();
            if (isset($currentFilters[$field][0])) {
                $range = VF_Solr_Utils::parseRange($currentFilters[$field][0]);
            }
        }

        // Normalize range if we found one:
        if (isset($range)) {
            if (empty($range['from']) || $range['from'] == '*') {
                $range['from'] = 0;
            }
            if (empty($range['to']) || $range['to'] == '*') {
                $range['to'] = date('Y') + 1;
            }
            return $range['from'] . '-' . $range['to'];
        }

        // No range found?  Return empty string:
        return '';
    }

    /**
     * Get the results of the subject query -- false if none, otherwise an array
     * with 'worksArray' and 'subject' keys.
     *
     * @return bool|array
     */
    public function getResult()
    {
        return $this->result;
    }
}