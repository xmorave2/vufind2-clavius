<?php
/**
 * Author Search Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
 
/**
 * Author Search Options
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class AuthorController extends VF_Controller_Search
{
    /**
     * Sets the configuration for displaying author results
     * 
     * @return void
     */
    public function resultsAction()
    {
        $this->searchClassId = 'SolrAuthor';
        $this->saveToHistory = false;
        parent::resultsAction();
    }

    /**
     * Sets the configuration for performing an author search
     * 
     * @return void
     */
    public function searchAction()
    {
        $this->searchClassId = 'SolrAuthorFacets';
        $this->saveToHistory = false;
        $this->useResultScroller = false;
        $this->rememberSearch = false;
        parent::resultsAction();
    }

    /**
     * Displays the proper page for a search action
     * 
     * @return void
     */
    public function homeAction()
    {
        // If an author was requested, forward to the results page; otherwise,
        // display the search form:
        $author = $this->_request->getParam('author');
        if (!empty($author)) {
            $this->_forward('Results');
        }
    }
}

