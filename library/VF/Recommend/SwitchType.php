<?php
/**
 * SwitchType Recommendations Module
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
 * SwitchType Recommendations Module
 *
 * This class recommends switching to a different search type.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Recommend_SwitchType implements VF_Recommend_Interface
{
    protected $newHandler;      // search handler to try
    protected $newHandlerName;  // on-screen description of handler
    protected $active;          // is this module active?

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string $settings Settings from searches.ini.
     *
     * TopFacets:[ini section]:[ini name]
     *      Display facets listed in the specified section of the specified ini file;
     *      if [ini name] is left out, it defaults to "facets."
     */
    public function __construct($settings)
    {
        $params = explode(':', $settings);
        $this->newHandler = !empty($params[0]) ? $params[0] : 'AllFields';
        $this->newHandlerName = isset($params[1]) ? $params[1] : 'All Fields';
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
        $this->active = ($results->getSearchHandler() != $this->newHandler);
    }

    /**
     * Get the new search handler, or false if it does not apply.
     *
     * @return string
     */
    public function getNewHandler()
    {
        return $this->active ? $this->newHandler : false;
    }

    /**
     * Get the description of the new search handler.
     *
     * @return string
     */
    public function getNewHandlerName()
    {
        return $this->newHandlerName;
    }
}