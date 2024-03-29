<?php
/**
 * Solr Autocomplete Module
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */

/**
 * Solr Autocomplete Module
 *
 * This class provides suggestions by using the local Solr index.
 *
 * @category VuFind
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/autocomplete Wiki
 */
class VF_Autocomplete_Solr implements VF_Autocomplete_Interface
{
    protected $handler;
    protected $displayField;
    protected $defaultDisplayField = 'title';
    protected $sortField;
    protected $filters;
    protected $searchObject;

    /**
     * Constructor
     *
     * Establishes base settings for making autocomplete suggestions.
     *
     * @param string $params Additional settings from searches.ini.
     */
    public function __construct($params)
    {
        // Save the basic parameters:
        $params = explode(':', $params);
        $this->handler = (isset($params[0]) && !empty($params[0])) ?
            $params[0] : null;
        $this->displayField = (isset($params[1]) && !empty($params[1])) ?
            explode(',', $params[1]) : array($this->defaultDisplayField);
        $this->sortField = (isset($params[2]) && !empty($params[2])) ?
            $params[2] : null;
        $this->filters = array();
        if (count($params > 3)) {
            for ($x = 3; $x < count($params); $x += 2) {
                if (isset($params[$x + 1])) {
                    $this->filters[] = $params[$x] . ':' . $params[$x + 1];
                }
            }
        }

        // Set up the Search Object:
        $this->initSearchObject();
    }

    /**
     * initSearchObject
     *
     * Initialize the search object used for finding recommendations.
     *
     * @return void
     */
    protected function initSearchObject()
    {
        // Build a new search object:
        $params = new VF_Search_Solr_Params();
        $this->searchObject = new VF_Search_Solr_Results($params);
        $this->searchObject->spellcheckEnabled(false);
    }

    /**
     * mungeQuery
     *
     * Process the user query to make it suitable for a Solr query.
     *
     * @param string $query Incoming user query
     *
     * @return string       Processed query
     */
    protected function mungeQuery($query)
    {
        // Modify the query so it makes a nice, truncated autocomplete query:
        $forbidden = array(':', '(', ')', '*', '+', '"');
        $query = str_replace($forbidden, " ", $query);
        if (substr($query, -1) != " ") {
            $query .= "*";
        }
        return $query;
    }

    /**
     * getSuggestions
     *
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     *
     * @return array        The suggestions for the provided query
     */
    public function getSuggestions($query)
    {
        try {
            $this->searchObject->setBasicSearch(
                $this->mungeQuery($query), $this->handler
            );
            $this->searchObject->setSort($this->sortField);
            foreach ($this->filters as $current) {
                $this->searchObject->addFilter($current);
            }

            // Perform the search:
            $searchResults = $this->searchObject->getResults();

            // Build the recommendation list -- first we'll try with exact matches;
            // if we don't get anything at all, we'll try again with a less strict
            // set of rules.
            $results = $this->getSuggestionsFromSearch($searchResults, $query, true);
            if (empty($results)) {
                $results = $this->getSuggestionsFromSearch(
                    $searchResults, $query, false
                );
            }
        } catch(Exception $e) {
            // Ignore errors -- just return empty results if we must.
        }
        return array_unique($results);
    }

    /**
     * Try to turn an array of record drivers into an array of suggestions.
     *
     * @param array  $searchResults An array of record drivers
     * @param string $query         User search query
     * @param bool   $exact         Ignore non-exact matches?
     *
     * @return array
     */
    protected function getSuggestionsFromSearch($searchResults, $query, $exact)
    {
        $results = array();
        foreach ($searchResults as $object) {
            $current = $object->getAllFields();
            foreach ($this->displayField as $field) {
                if (isset($current[$field])) {
                    $bestMatch = $this->pickBestMatch(
                        $current[$field], $query, $exact
                    );
                    if ($bestMatch) {
                        $results[] = $bestMatch;
                        break;
                    }
                }
            }
        }
        return $results;
    }

    /**
     * Given the values from a Solr field and the user's search query, pick the best
     * match to display as a recommendation.
     *
     * @param array|string $value Field value (or array of field values)
     * @param string       $query User search query
     * @param bool         $exact Ignore non-exact matches?
     *
     * @return bool|string        String to use as recommendation, or false if
     * no appropriate value was found.
     */
    protected function pickBestMatch($value, $query, $exact)
    {
        // By default, assume no match:
        $bestMatch = false;

        // Different processing for arrays vs. non-arrays:
        if (is_array($value) && !empty($value)) {
            // Do any of the values within this multi-valued array match the
            // query?  Try to find the closest available match.
            foreach ($value as $next) {
                if ($this->matchQueryTerms($next, $query)) {
                    $bestMatch = $next;
                    break;
                }
            }

            // If we didn't find an exact match, use the first value unless
            // we have the "precise matches only" property set, in which case
            // we don't want to use any of these values.
            if (!$bestMatch && !$exact) {
                $bestMatch = $value[0];
            }
        } else {
            // If we have a single value, we will use it if we're in non-strict
            // mode OR if we're in strict mode and it actually matches.
            if (!$exact || $this->matchQueryTerms($value, $query)) {
                $bestMatch = $value;
            }
        }
        return $bestMatch;
    }

    /**
     * setDisplayField
     *
     * Set the display field list.  Useful for child classes.
     *
     * @param array $new Display field list.
     *
     * @return void
     */
    protected function setDisplayField($new)
    {
        $this->displayField = $new;
    }

    /**
     * setSortField
     *
     * Set the sort field list.  Useful for child classes.
     *
     * @param string $new Sort field list.
     *
     * @return void
     */
    protected function setSortField($new)
    {
        $this->sortField = $new;
    }

    /**
     * Return true if all terms in the query occurs in the field data string.
     *
     * @param string $data  The data field returned from solr
     * @param string $query The query string entered by the user
     *
     * @return bool
     */
    protected function matchQueryTerms($data, $query)
    {
        $terms = preg_split("/\s+/", $query);
        foreach ($terms as $term) {
            if (stripos($data, $term) === false) {
                return false;
            }
        }
        return true;
    }

}
