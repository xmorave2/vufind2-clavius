<?php
/**
 * Browse Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */

/**
 * BrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class BrowseController extends Zend_Controller_Action
{

    protected $disabledFacets;
    
    /**
     * init
     *
     * Configuration settings
     *
     * @return void
     */
    public function init()
    {
        $config = VF_Config_Reader::getConfig();

        // Initialize the array of top-level browse options.
        $browseOptions = array();
        
        $this->disabledFacets = array();
        foreach ($config->Browse as $key => $setting) {
            if ($setting == false) {
                $this->disabledFacets[] = $key;
            }
        }

        // First option: tags -- is it enabled in config.ini?  If no setting is
        // found, assume it is active.
        if (!isset($config->Browse->tag)
            || $config->Browse->tag == true
        ) {
            $browseOptions[] = $this->buildBrowseOption('Tag', 'Tag');
            $this->view->tagEnabled = true;
        }

        // Read configuration settings for LC / Dewey call number display; default
        // to LC only if no settings exist in config.ini.
        if (!isset($config->Browse->dewey)
            && !isset($config->Browse->lcc)
        ) {
            $lcc = true;
            $dewey = false;
        } else {
            $lcc = (isset($config->Browse->lcc)
                && $config->Browse->lcc);
            $dewey = (isset($config->Browse->dewey)
                && $config->Browse->dewey);
        }

        // Add the call number options as needed -- note that if both options exist,
        // we need to use special text to disambiguate them.
        if ($dewey) {
            $browseOptions[] = $this->buildBrowseOption(
                'Dewey', ($lcc ? 'browse_dewey' : 'Call Number')
            );
            $this->view->deweyEnabled = true;
        }
        if ($lcc) {
            $browseOptions[] = $this->buildBrowseOption(
                'LCC', ($dewey ? 'browse_lcc' : 'Call Number')
            );
            $this->view->lccEnabled = true;
        }

        // Loop through remaining browse options.  All may be individually disabled
        // in config.ini, but if no settings are found, they are assumed to be on.
        $remainingOptions = array(
            'Author', 'Topic', 'Genre', 'Region', 'Era'
        );
        foreach ($remainingOptions as $current) {
            $option = strToLower($current);
            if (!isset($config->Browse->$option)
                || $config->Browse->$option == true
            ) {
                $browseOptions[] = $this->buildBrowseOption($current, $current);
                $option .= 'Enabled';
                $this->view->$option = true;
            }
        }
        
        // CARRY
        if ($this->_request->getParam('findby')) {
            $this->view->findby = $this->_request->getParam('findby');
        }
        if ($this->_request->getParam('query')) {
            $this->view->query = $this->_request->getParam('query');
        }
        if ($this->_request->getParam('category')) {
            $this->view->category = $this->_request->getParam('category');
        }
        $this->view->browseOptions = $browseOptions;
    }

    /**
     * Build an array containing options describing a top-level Browse option.
     *
     * @param string $action      The name of the Action for this option
     * @param string $description A description of this Browse option
     *
     * @return array              The Browse option array
     */
    protected function buildBrowseOption($action, $description)
    {
        return array('action' => $action, 'description' => $description);
    }
    
    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return  void - info passed to view
     */
    public function homeAction()
    {
    }
        
    /**
     * Perform the search
     *
     * @return void
     */
    protected function performSearch()
    {
        // Remove disabled facets
        $facets = $this->view->categoryList;
        foreach ($this->disabledFacets as $facet) {
            unset($facets[$facet]);
        }
        $this->view->categoryList = $facets;
    
        // SEARCH (Tag does its own search)
        if ($this->_request->getParam('query')
            && $this->view->currentAction != 'Tag'
        ) {
            $results = $this->getFacetList(
                $this->_request->getParam('facet_field'),
                $this->_request->getParam('query_field'),
                'count', $this->_request->getParam('query')
            );
            $resultList = array();
            foreach ($results as $result) {
                $resultList[] = array(
                    'result' => $result['displayText'],
                    'count' => $result['count']
                );
            }
            // Don't make a second filter if it would be the same facet
            $this->view->paramTitle
                = ($this->_request->getParam('query_field') != $this->getCategory())
                ? 'filter[]=' . $this->_request->getParam('query_field') . ':'
                    . urlencode($this->_request->getParam('query')) . '&'
                : '';
            switch($this->view->currentAction) {
            case 'LCC':
                $this->view->paramTitle .= 'filter[]=callnumber-subject:';
                break;
            case 'Dewey':
                $this->view->paramTitle .= 'filter[]=dewey-ones:';
                break;
            default:
                $this->view->paramTitle .= 'filter[]='.$this->getCategory().':';
            }
            $this->view->paramTitle = str_replace(
                '+AND+',
                '&filter[]=',
                $this->view->paramTitle
            );
            $this->view->resultList = $resultList;
        }
        
        $this->render('browse/home', null, true);
    }

    /**
     * Browse tags
     *
     * @return void
     */
    public function tagAction()
    {
        $this->view->currentAction = 'Tag';
        
        $this->view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'popularity'   => 'By Popularity',
            'recent'       => 'By Recent'
        );
        
        if ($this->_request->getParam('findby')) {
            $params = $this->_request->getParams();
            $tagTable = new VuFind_Model_Db_Tags();
            // Special case -- display alphabet selection if necessary:
            if ($params['findby'] == 'alphabetical') {
                $legalLetters = $this->getAlphabetList();
                $this->view->secondaryList = $legalLetters;
                // Only display tag list when a valid letter is selected:
                if (isset($params['query'])) {
                    // Note -- this does not need to be escaped because 
                    // $params['query'] has already been validated against
                    // the _getAlphabetList() method below!
                    $tags = $tagTable->matchText($params['query']);
                    $tagList = array();
                    foreach ($tags as $tag) {
                        $count = $tagTable->getCount($tag['id']);
                        if ($count > 0) {
                            $tagList[] = array(
                                'result' => $tag['tag'],
                                'count' => $count
                            );
                        }
                    }
                    $config = VF_Config_Reader::getConfig();
                    $this->view->resultList = array_slice($tagList, 0, $config->Browse->result_limit);
                }
            } else {
                // Default case: always display tag list for non-alphabetical modes:
                $config = VF_Config_Reader::getConfig();
                $tagList = $tagTable->getTagList(
                    $params['findby'],
                    $config->Browse->result_limit
                );
                $resultList = array();
                foreach ($tagList as $i=>$tag) {
                    $resultList[$i] = array(
                        'result' => $tag['tag'],
                        'count'    => $tag['cnt']
                    );
                }
                $this->view->resultList = $resultList;
            }
            $this->view->paramTitle = 'lookfor=';
            $this->view->searchParams = array();
        }
        
        $this->performSearch();
    }
    
    /**
     * Browse LCC
     *
     * @return void
     */
    public function lccAction()
    {
        $this->view->currentAction = 'LCC';
        $this->view->secondaryList = $this->getSecondaryList('lcc');
        $this->view->secondaryParams = array(
            'query_field' => 'callnumber-first',
            'facet_field' => 'callnumber-subject'
        );    
        $this->view->searchParams = array();
        $this->performSearch();
    }
    
    /**
     * Browse Dewey
     *
     * @return void
     */
    public function deweyAction()
    {
        $this->view->currentAction = 'Dewey';
        $hundredsList = $this->getSecondaryList('dewey');
        $categoryList = array();
        foreach ($hundredsList as $dewey) {
            $categoryList[$dewey['value']] = $dewey['displayText'].' ('.$dewey['count'].')';
        }
        $this->view->categoryList = $categoryList;
        if ($this->_request->getParam('findby')) {
            $secondaryList = $this->quoteValues(
                $this->getFacetList(
                    'dewey-tens',
                    'dewey-hundreds',
                    'count',
                    $this->_request->getParam('findby')
                )
            );
            foreach ($secondaryList as $index=>$item) {
                $secondaryList[$index]['value'] .=
                    ' AND dewey-hundreds:'
                    . $this->_request->getParam('findby');
            }
            $this->view->secondaryList = $secondaryList;
            $this->view->secondaryParams = array(
                'query_field' => 'dewey-tens',
                'facet_field' => 'dewey-ones'
            );    
        }
        $this->performSearch();
    }
    
    /**
     * Generic action function that handles all the common parts of the below actions
     *
     * @param string $currentAction - name of the current action. profound stuff.
     * @param string $facetPrefix   - if this is true and we're looking
     * alphabetically, add a facet_prefix to the URL
     *
     * @return void
     */
    protected function browseAction($currentAction, $facetPrefix)
    {        
        $this->view->currentAction = $currentAction;
        
        $findby = $this->_request->getParam('findby');
        if ($findby) {
            $this->view->secondaryParams = array(
                'query_field' => $this->getCategory($findby),
                'facet_field' => $this->getCategory($currentAction)
            );
            $this->view->facetPrefix = $facetPrefix && $findby == 'alphabetical';
            $this->view->secondaryList = $this->getSecondaryList($findby);
        }
        
        $this->performSearch();
    }
    
    /**
     * Browse Author
     *
     * @return void
     */
    public function authorAction()
    {
        $this->view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'lcc'          => 'By Call Number',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era'
        );
        
        $this->browseAction('Author', false);
    }
    
    /**
     * Browse Topic
     *
     * @return void
     */
    public function topicAction()
    {        
        $this->view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'genre'        => 'By Genre',
            'region'       => 'By Region',
            'era'          => 'By Era'
        );
        
        $this->browseAction('Topic', true);
    }
    
    /**
     * Browse Genre
     *
     * @return void
     */
    public function genreAction()
    {
        $this->view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'region'       => 'By Region',
            'era'          => 'By Era'
        );
        
        $this->browseAction('Genre', true);
    }
    
    /**
     * Browse Region
     *
     * @return void
     */
    public function regionAction()
    {
        $this->view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'era'          => 'By Era'
        );   
        
        $this->browseAction('Region', true);
    }
    
    /**
     * Browse Era
     *
     * @return void
     */
    public function eraAction()
    {
        $this->view->categoryList = array(
            'alphabetical' => 'By Alphabetical',
            'topic'        => 'By Topic',
            'genre'        => 'By Genre',
            'region'       => 'By Region'
        );
        
        $this->browseAction('Era', true);
    }
    
    /**
     * Get a secondary list based on facets
     *
     * @param string $facet - the facet we need the contents of
     *
     * @return array
     */
    protected function getSecondaryList($facet)
    {
        $category = $this->getCategory();
        switch($facet) {
        case 'alphabetical':
            return $this->getAlphabetList();
        case 'dewey':
            $this->view->filter = 'dewey-tens';            
            return $this->quoteValues(
                $this->getFacetList('dewey-hundreds', $category, 'index')
            );
        case 'lcc':
            $this->view->filter = 'callnumber-first';            
            return $this->quoteValues(
                $this->getFacetList('callnumber-first', $category, 'index')
            );
        case 'topic':
            $this->view->filter = 'topic_facet';
            return $this->quoteValues(
                $this->getFacetList('topic_facet', $category)
            );
        case 'genre':
            $this->view->filter = 'genre_facet';
            return $this->quoteValues(
                $this->getFacetList('genre_facet', $category)
            );
        case 'region':
            $this->view->filter = 'geographic_facet';
            return $this->quoteValues(
                $this->getFacetList('geographic_facet', $category)
            );
        case 'era':
            $this->view->filter = 'era_facet';
            return $this->quoteValues(
                $this->getFacetList('era_facet', $category)
            );
        }
    }
    
    /**
     * Get a list of items from a facet.
     *
     * @param string $facet    - which facet we're searching in
     * @param string $category - which subfacet the search applies to
     * @param string $sort     - how are we ranking these? || 'index'
     * @param string $query    - is there a specific query? No = wildcard
     *
     * @return array, indexed by value with text of displayText and count
     */
    protected function getFacetList($facet, $category = null,
        $sort = 'count', $query = '[* TO *]'
    ) {                
        $params = new VF_Search_Solr_Params();
        $params->addFacet($facet);
        if ($category != null) {
            $query = $category . ':' . $query;
        } else {
            $query = $facet . ':' . $query;
        }
        $params->setOverrideQuery($query);
        $searchObject = new VF_Search_Solr_Results($params);
        // Get limit from config
        $config = VF_Config_Reader::getConfig();
        $params->setFacetLimit($config->Browse->result_limit);
        $params->setLimit(0);
        // Facet prefix
        if ($this->_request->getParam('facet_prefix')) {
            $params->setFacetPrefix($this->_request->getParam('facet_prefix'));
        }
        $params->setFacetSort($sort);
        $result = $searchObject->getFacetList();
        //var_dump($result[$facet]['list']);
        if (isset($result[$facet])) {
            return $result[$facet]['list'];
        } else {
            return array();
        }
    }
    
    /**
     * Helper class that adds quotes around the values of an array
     *
     * @param array $array - object array where each item has a value param
     *
     * @return array, indexed by value with text of displayText and count
     */
    protected function quoteValues($array)
    {
        foreach ($array as $i=>$result) {
            $result['value'] = '"'.$result['value'].'"';
            $array[$i] = $result;
        }
        return $array;
    }
    
    /**
     * Get the facet search term for an action
     *
     * @param string $action - action to be translated
     *
     * @return string
     */
    protected function getCategory($action = null)
    {
        if ($action == null) {
            $action = $this->view->currentAction;
        }
        switch(strToLower($action)) {
        case 'alphabetical':
            return $this->getCategory();
        case 'dewey':
            return 'dewey-hundreds';
        case 'lcc':
            return 'callnumber-first';
        case 'author':
            return 'authorStr';
        case 'topic':
            return 'topic_facet';
        case 'genre':
            return 'genre_facet';
        case 'region':
            return 'geographic_facet';
        case 'era':
            return 'era_facet';
        }
        return $action;
    }    
    
    /**
     * Get a list of letters to display in alphabetical mode.
     *
     * @return array
     */
    protected function getAlphabetList()
    {
        // ALPHABET TO ['value','displayText']
        $alphabet = str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        foreach ($alphabet as $index=>$letter) {
            $alphabet[$index] = array(
                'value'       => $letter,
                'displayText' => $letter
            );
        }
        if ($this->view->currentAction == 'Tag') {
            return $alphabet;
        }
        // ADD ASTERISK FOR THOSE THAT NEED IT
        foreach ($alphabet as $index=>$letter) {
            $letter['value'] .= '*';
            $alphabet[$index] = $letter;
        }
        if ($this->view->currentAction != 'Era') {
            return $alphabet;
        }
        // PUT NUMBERS FIRST FOR YEARS
        array_unshift($alphabet, $alphabet[count($alphabet)-10]);
        unset($alphabet[count($alphabet)-10]);
        for ($i=0;$i<9;$i++) {
            array_unshift($alphabet, array_pop($alphabet));
        }
        return $alphabet;
    }
}