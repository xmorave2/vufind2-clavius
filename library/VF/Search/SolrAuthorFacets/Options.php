<?php
/**
 * AuthorFacets aspect of the Search Multi-class (Options)
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
 * AuthorFacets Search Options
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Search_SolrAuthorFacets_Options extends VF_Search_Solr_Options
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Special sort options...
        // It's important to remember here we are talking about on-screen
        //   sort values, not what is sent to Solr, since this screen
        //   is really using facet sorting.
        $this->sortOptions = array(
            'relevance' => 'sort_author_relevance',
            'author' => 'sort_author_author'
        );

        // No spell check needed in author module:
        $this->spellcheck = false;
    }

    /**
     * Return an array describing the action used for rendering search results
     * (same format as expected by the URL view helper).
     *
     * @return array
     */
    public function getSearchAction()
    {
        return array('controller' => 'Author', 'action' => 'Search');
    }
}