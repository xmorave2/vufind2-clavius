<?php
/**
 * Help Controller Test Class
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
 * Help Controller Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class HelpControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
{
    /**
     * Standard setup method.
     *
     * @return void
     */
    public function setUp()
    {
        $this->bootstrap = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
        parent::setUp();
    }

    /**
     * Test handling of invalid topics
     *
     * @return void
     */
    public function testInvalidTopic()
    {
        $params = array('action' => 'Home', 'controller' => 'Help');
        $request = $this->getRequest();
        $request->setParam('topic', 'garbage');
        $url = $this->url($params);
        $this->dispatch($url);

        // Confirm default English behavior:
        $this->assertController('error');
        $this->assertAction('error');
    }

    /**
     * Test the search topic (default language)
     *
     * @return void
     */
    public function testSearchTopicEnglish()
    {
        $params = array('action' => 'Home', 'controller' => 'Help');
        $request = $this->getRequest();
        $request->setParam('topic', 'search');
        $url = $this->url($params);
        $this->dispatch($url);

        // Confirm default English behavior:
        $this->assertController('Help');
        $this->assertAction('Home');
        $this->assertQueryContentContains("ul.HelpMenu li a", "Wildcard Searches");
    }

    /**
     * Test the search topic in an unsupported language (note that this test will
     * fail if we ever receive a Welsh translation of the help pages!!)
     *
     * @return void
     */
    public function testSearchTopicUnsupportedWelsh()
    {
        $params = array('action' => 'Home', 'controller' => 'Help');
        $request = $this->getRequest();
        $request->setParam('topic', 'search');
        $url = $this->url($params);
        $request->setPost('mylang', 'cy');
        $this->dispatch($url);
        $this->assertController('Help');
        $this->assertAction('Home');
        $this->assertQueryContentContains("p.warning", 'Ymddiheuriadau');
        $this->assertQueryContentContains("ul.HelpMenu li a", "Wildcard Searches");
    }

    /**
     * Test the search topic (non-default language)
     *
     * @return void
     */
    public function testSearchTopicGerman()
    {
        $params = array('action' => 'Home', 'controller' => 'Help');
        $request = $this->getRequest();
        $request->setParam('topic', 'search');
        $url = $this->url($params);
        $request->setPost('mylang', 'de');
        $this->dispatch($url);
        $this->assertController('Help');
        $this->assertAction('Home');
        $this->assertQueryContentContains("ul.HelpMenu li a", "Suche mit Platzhaltern");
    }
}



