<?php
/**
 * AlphaBrowse Module Controller
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
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */

/**
 * AlphabrowseController Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Mark Triggs <vufind-tech@lists.sourceforge.net>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class AlphabrowseController extends Zend_Controller_Action
{
    /**
     * Gathers data for the view of the AlphaBrowser and does some initialization
     *
     * @return  void - info passed to view
     */
    public function homeAction()
    {
        $config = VF_Config_Reader::getConfig();

        // Load browse types from config file, or use defaults if unavailable:
        if (isset($config->AlphaBrowse_Types)
            && !empty($config->AlphaBrowse_Types)
        ) {
            $types = array();
            foreach ($config->AlphaBrowse_Types as $key => $value) {
                $types[$key] = $value;
            }
        } else {
            $types = array(
                'topic'  => 'By Topic',
                'author' => 'By Author',
                'title'  => 'By Title',
                'lcc'    => 'By Call Number'
            );
        }

        // Connect to Solr:
        $db = VF_Connection_Manager::connectToIndex();

        // Process incoming parameters:
        $source = $this->_request->getParam('source', false);
        $from   = $this->_request->getParam('from', false);
        $page   = intval($this->_request->getParam('page', 0));
        $limit  = isset($config->AlphaBrowse->page_size) ?
                    $config->AlphaBrowse->page_size 
                    :
                    20;

        // If required parameters are present, load results:
        if ($source && $from !== false) {
            // Load Solr data or die trying:
            try {
                $result = $db->alphabeticBrowse($source, $from, $page, $limit);

                // No results?    Try the previous page just in case we've gone past
                // the end of the list....
                if ($result['Browse']['totalCount'] == 0) {
                    $page--;
                    $result = $db->alphabeticBrowse($source, $from, $page, $limit);
                }
            } catch (VF_Exception_Solr $e) {
                if ($e->isMissingBrowseIndex()) {
                    throw new Exception(
                        "Alphabetic Browse index missing.    See " .
                        "http://vufind.org/wiki/alphabetical_heading_browse for " .
                        "details on generating the index."
                    );
                }
                throw $e;
            }

            // Only display next/previous page links when applicable:
            if ($result['Browse']['totalCount'] > $limit) {
                $this->view->nextpage = $page + 1;
            }
            if ($result['Browse']['offset'] + $result['Browse']['startRow'] > 1) {
                $this->view->prevpage = $page - 1;
            }
            $this->view->result = $result;
        }

        $this->view->alphaBrowseTypes = $types;
        $this->view->from = $from;
        $this->view->source = $source;
    }

    /**
     * Results is functionally identical to Home -- this action is here for URL
     * compatibility with VuFind 1.x.
     *
     * @return void
     */
    public function resultsAction()
    {
        $this->_forward('Home');
    }
}