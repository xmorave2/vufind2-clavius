<?php
/**
 * Author Controller Test Class
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
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */

/**
 * Author Controller Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Preetha Rao <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class AuthorControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
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
     * Confirm that a Hebrew author name can be retrieved correctly.
     *
     * @return void
     */
    public function testHebrewName()
    {
        $this->resetRequest();
        $request = $this->getRequest();
        $request->setParam(
            'author',
            urldecode(
                '%D7%A4%D7%A8%D7%95%D7%99%D7%A7%D7%98%20%D7%9E%D7%95%22%D7%A4%20%D7' .
                '%A7%D7%93%D7%A1%D7%98%D7%A8%20%D7%AA%D7%9C%D7%AA-%D7%9E%D7%9E%D7' .
                '%93%D7%99'
            )
        );

        $params = array(
            'action' => 'Home',
            'controller' => 'Author',
        );
        $url = $this->url($params);
        $this->dispatch($url);

        // If this worked correctly, the Home action should forward to Results:
        $this->assertController('Author');
        $this->assertAction('Results');

        // Confirm that author search results were found; this is a very obscure
        // string, so if there is any problem at all we should get nothing.  Looking
        // for the expected publication date should suffice to confirm that the
        // correct result was retrieved.
        $this->assertQueryContentContains("div.resultItemLine2", "Published 2004");
    }


}



