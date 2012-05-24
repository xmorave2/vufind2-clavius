<?php
/**
 * Record Controller Test Class
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
 * Record Controller Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class RecordControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
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
     * Test "blocked hold" action.
     *
     * @return void
     */
    public function testBlockedHoldAction()
    {
        $params = array('action' => 'BlockedHold', 'id' => 'testbug1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // assertions -- should redirect to Record/Home
        $this->assertRedirectTo('/Record/testbug1#top');

        // now follow the redirect and make sure the error message is really there:
        $this->resetRequest();
        $this->resetResponse();
        $params = array('action' => 'Home', 'id' => 'testbug1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);
        $this->assertController('Record');
        $this->assertAction('Holdings');
        $this->assertQueryContentContains(".error", "You do not have sufficient privileges");
    }

    /**
     * Support method -- check for the specified header.  Note:  We use this instead
     * of Zend Framework's assertHeaderContains() functionality, which as of this
     * writing does not work correctly.
     *
     * @param string $key Header name to check
     * @param string $val Value to look for in header
     *
     * @return bool
     */
    protected function checkHeader($key, $val)
    {
        foreach ($this->getResponse()->getHeaders() as $current) {
            if (strcasecmp(trim($current['name']), trim($key)) == 0) {
                if (strcasecmp(trim($current['value']), trim($val)) == 0) {
                    return;
                }
            }
        }
        $this->fail('Expected header -- ' . $key . ':' . $val . ' -- missing.');
    }

    /**
     * Support method -- assert presence of expected headers from export.ini.
     *
     * @param string $style Export type to check.
     *
     * @return void
     */
    protected function checkExportHeaders($style)
    {
        $config = VF_Config_Reader::getConfig('export');
        if (isset($config->$style->headers) && count($config->$style->headers) > 0) {
            foreach ($config->$style->headers as $header) {
                $parts = explode(':', $header, 2);
                $this->checkHeader($parts[0], $parts[1]);
            }
        }
    }

    /**
     * Test MARC export.
     *
     * @return void
     */
    public function testMARCExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'MARC');
        $params = array('action' => 'Export', 'id' => 'testsample1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('MARC');

        // check content
        $marc = new File_MARC(
            $this->getResponse()->outputBody(), File_MARC::SOURCE_STRING
        );
        $marc = $marc->next();
        $field = $marc->getFields(245);
        $title = $field[0]->getSubfield('a')->getData();
        $this->assertEquals($title, 'Journal of rational emotive therapy :');
    }

    /**
     * Test RDF action (which forwards to export).
     *
     * @return void
     */
    public function testRDFAction()
    {
        $params = array('action' => 'RDF', 'id' => 'testsample1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->assertController('Record');
        $this->assertAction('Export');
        $this->checkExportHeaders('RDF');

        // check content
        $this->assertTrue(
            false !== stristr($this->getResponse()->outputBody(), 'rdf')
        );
    }

    /**
     * Test RDF export.
     *
     * @return void
     */
    public function testRDFExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'RDF');
        $params = array('action' => 'Export', 'id' => 'testsample1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('RDF');

        // check content
        $this->assertTrue(
            false !== stristr($this->getResponse()->outputBody(), 'rdf')
        );
    }

    /**
     * Test EndNote export (1 of 2).
     *
     * @return void
     */
    public function testEndNoteJournalExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'EndNote');
        $params = array('action' => 'Export', 'id' => 'testsample1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('EndNote');

        // check content
        $expectedLines = array(
            '%0 Journal',
            '%E Institute for Rational-Emotive Therapy (New York, N.Y.)',
            '%I The Institute',
            '%D 1983',
            '%C New York',
            '%G English',
            '%@ 0748-1985',
            '%@ 0034-0049',
            '%@ 0894-9085',
            '%T Journal of rational emotive therapy'
        );
        $body = $this->getResponse()->outputBody();
        foreach ($expectedLines as $line) {
            $this->assertTrue(false !== stristr($body, $line));
        }
    }

    /**
     * Test EndNote export (2 of 2).
     *
     * @return void
     */
    public function testEndNoteBookExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'EndNote');
        $params = array('action' => 'Export', 'id' => 'testbug2');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('EndNote');

        // check content
        $expectedLines = array(
            '%0 Book',
            '%A Vico, Giambattista, 1668-1744.',
            '%E Pandolfi, Claudia.',
            '%I Centro di Studi Vichiani',
            '%D 1992',
            '%C Morano',
            '%G Italian',
            '%G Latin',
            '%B Vico, Giambattista, 1668-1744. Works. 1982 ;',
            '%T La congiura dei Principi Napoletani 1701 : (prima e seconda stesura)',
            '%U http://fictional.com/sample/url',
            '%7 Fictional edition.'
        );
        $body = $this->getResponse()->outputBody();
        foreach ($expectedLines as $line) {
            $this->assertTrue(false !== stristr($body, $line));
        }
    }

    /**
     * Test BibTeX export (1 of 2).
     *
     * @return void
     */
    public function testBibTeXJournalExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'BibTeX');
        $params = array('action' => 'Export', 'id' => 'testsample1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('BibTeX');

        // check content
        $expectedLines = array(
            '@misc{',
            'VuFind-testsample1',
            'title = Journal of rational emotive therapy : the journal of the Institute for Rational-Emotive Therapy.,',
            'editor = Institute for Rational-Emotive Therapy (New York, N.Y.),',
            'address = New York,',
            'publisher = The Institute,',
            'year = 1983,',
            'note = Title from cover.,',
            'note = Vols. for <spring 1985-> published by Human Sciences Press, Inc.,',
            'crossref = http://library.myuniversity.edu/Record/testsample1',
            '}'
        );
        $body = $this->getResponse()->outputBody();
        foreach ($expectedLines as $line) {
            $this->assertTrue(false !== stristr($body, $line));
        }
    }

    /**
     * Test BibTeX export (2 of 2).
     *
     * @return void
     */
    public function testBibTeXBookExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'BibTeX');
        $params = array('action' => 'Export', 'id' => 'testbug2');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('BibTeX');

        // check content
        $expectedLines = array(
            '@book{',
            'VuFind-testbug2',
            'title = La congiura dei Principi Napoletani 1701 : (prima e seconda stesura),',
            'series = Vico, Giambattista, 1668-1744. Works. 1982 ;,',
            'author = Vico, Giambattista, 1668-1744.,',
            'editor = Pandolfi, Claudia.,',
            'address = Morano,',
            'publisher = Centro di Studi Vichiani,',
            'year = 1992,',
            'edition = Fictional edition.,',
            'pages = 296,',
            'note = Italian and Latin.,',
            'url = http://fictional.com/sample/url,',
            'crossref = http://library.myuniversity.edu/Record/testbug2',
            '}'
        );
        $body = $this->getResponse()->outputBody();
        foreach ($expectedLines as $line) {
            $this->assertTrue(false !== stristr($body, $line));
        }
    }

    /**
     * Test RefWorks export (1 of 2).
     *
     * @return void
     */
    public function testRefWorksJournalExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'RefWorks');
        $params = array('action' => 'Export', 'id' => 'testsample1');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // assertions -- should redirect to RefWorks
        $this->assertRedirectTo('http://www.refworks.com/express/expressimport.asp?vendor=VuFind&filter=RefWorks%20Tagged%20Format&url=http%3A%2F%2Flibrary.myuniversity.edu%2FRecord%2Ftestsample1%2FExport%3Fcallback%3D1%26style%3DRefWorks');

        // Now get the callback and make sure it's valid:
        $request->setParam('callback', 1);
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('RefWorks');

        // check content
        $expectedLines = array(
            'RT Journal',
            'T1 Journal of rational emotive therapy : the journal of the Institute for Rational-Emotive Therapy.',
            'A2 Institute for Rational-Emotive Therapy (New York, N.Y.)',
            'LA English',
            'PP New York',
            'PB The Institute',
            'YR 1983',
            'UL http://library.myuniversity.edu/Record/testsample1',
            'NO Title from cover.',
            'NO Vols. for <spring 1985-> published by Human Sciences Press, Inc.',
            'CN AC489.R3',
            'K1 Rational-emotive psychotherapy : Periodicals.',
            'K1 Cognitive therapy : Periodicals.',
            'K1 Psychotherapy : periodicals.'
        );
        $body = $this->getResponse()->outputBody();
        foreach ($expectedLines as $line) {
            $this->assertTrue(false !== stristr($body, $line));
        }
    }

    /**
     * Test RefWorks export (2 of 2).
     *
     * @return void
     */
    public function testRefWorksBookExport()
    {
        $request = $this->getRequest();
        $request->setParam('style', 'RefWorks');
        $params = array('action' => 'Export', 'id' => 'testbug2');
        $url = $this->url($params, 'record');
        $this->dispatch($url);

        // assertions -- should redirect to RefWorks
        $this->assertRedirectTo('http://www.refworks.com/express/expressimport.asp?vendor=VuFind&filter=RefWorks%20Tagged%20Format&url=http%3A%2F%2Flibrary.myuniversity.edu%2FRecord%2Ftestbug2%2FExport%3Fcallback%3D1%26style%3DRefWorks');

        // Now get the callback and make sure it's valid:
        $request->setParam('callback', 1);
        $this->dispatch($url);

        // check headers
        $this->checkExportHeaders('RefWorks');

        // check content
        $expectedLines = array(
            'RT Book',
            'T1 La congiura dei Principi Napoletani 1701 : (prima e seconda stesura)',
            'T2 Vico, Giambattista, 1668-1744. Works. 1982 ;',
            'A1 Vico, Giambattista, 1668-1744.',
            'A2 Pandolfi, Claudia.',
            'LA Italian',
            'LA Latin',
            'PP Morano',
            'PB Centro di Studi Vichiani',
            'YR 1992',
            'ED Fictional edition.',
            'UL http://library.myuniversity.edu/Record/testbug2',
            'AB Sample abstract.',
            'OP 296',
            'NO Italian and Latin.',
            'CN DG848.15',
            'SN 8820737493',
            'K1 Naples (Kingdom) : History : Spanish rule, 1442-1707 : Sources.'
        );
        $body = $this->getResponse()->outputBody();
        foreach ($expectedLines as $line) {
            $this->assertTrue(false !== stristr($body, $line));
        }
    }
}
