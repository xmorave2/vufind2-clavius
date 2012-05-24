<?php
/**
 * ResultFeed Test Class
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */

/**
 * ResultFeed Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class ResultFeedHelperTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test feed generation
     *
     * @return void
     */
    public function testRSS()
    {
        $view = Zend_Controller_Front::getInstance()->getParam('bootstrap')
            ->getResource('view');

        // Set up a request -- we'll sort by title to ensure a predictable order
        // for the result list (relevance or last_indexed may lead to unstable test
        // cases).
        $request = new Zend_Controller_Request_HttpTestCase();
        $request->setParam('lookfor', 'id:testbug2 OR id:testsample1');
        $request->setParam('skip_rss_sort', 1);
        $request->setParam('sort', 'title');
        $request->setParam('view', 'rss');

        $params = new VF_Search_Solr_Params();
        $params->initFromRequest($request);

        $results = new VF_Search_Solr_Results($params);
        $feed = $view->resultFeed($results);
        $rss = $feed->export('rss');

        // Make sure it's really an RSS feed:
        $this->assertTrue(strstr($rss, '<rss') !== false);

        // Make sure custom Dublin Core elements are present:
        $this->assertTrue(strstr($rss, 'dc:format') !== false);

        // Now re-parse it and check for some expected values:
        $parsedFeed = Zend_Feed::importString($rss);
        $this->assertEquals(
            $parsedFeed->description(),
            'Displaying the top 2 search results of 2 found'
        );
        $items = array();
        $i = 0;
        foreach ($parsedFeed as $item) {
            $items[$i++] = $item;
        }
        $this->assertEquals(
            $items[1]->title(), 'Journal of rational emotive therapy : '
            . 'the journal of the Institute for Rational-Emotive Therapy.'
        );
    }
}