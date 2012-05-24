<?php
/**
 * Related Records: Solr-based similarity
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * Related Records: Solr-based similarity
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Related_Similar implements VF_Related_Interface
{
    protected $results;

    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string               $settings Settings from config.ini
     * @param VF_RecordDriver_Base $driver   Record driver object
     */
    public function __construct($settings, $driver)
    {
        $params = new VF_Search_Solr_Params();
        $searcher = new VF_Search_Solr_Results($params);
        $this->results = $searcher->getSimilarRecords($driver->getUniqueId());
    }

    /**
     * getResults
     *
     * Get an array of Record Driver objects representing items similar to the one
     * passed to the constructor.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}
