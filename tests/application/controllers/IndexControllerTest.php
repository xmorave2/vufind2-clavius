<?php
/**
 * Index Controller Test Class
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
 * Index Controller Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class IndexControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
{
    protected static $config;
    protected static $account;
    protected static $savedAuthentication;
    
	/**
     * Standard setup method.
     *
     * @return void
     */
	public static function setUpBeforeClass()
	{
		// Use DB authentication since it can be tested without external dependencies
        self::$config = VF_Config_Reader::getConfig();
        self::$savedAuthentication = self::$config->Authentication->method;
        self::$config->Authentication->method = 'DB';
        VF_Account_Manager::resetInstance();

        // Get a convenient pointer to the account manager
        self::$account = VF_Account_Manager::getInstance();
	}
	
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
     * Helper function: create a test user : 'testuser'.
     *
     * @return void
     */
    public function createTestAccount()
    {
        $this->resetRequest();
        
        $params = array(
            'action' => 'Account',
            'controller' => 'MyResearch',
        );
        $url = $this->url($params, 'default', true);
        $request = $this->getRequest();
        $request->setParam('submit', 1);
        $request->setParam('username', 'testuser');
        $request->setParam('password', 'testpass');
        $request->setParam('password2', 'testpass');
        $request->setParam('email', 'user@test.com');
        $this->dispatch($url);
    }
    
    /**
     * Support method -- log out the current user.
     *
     * @return void
     */
    protected function logout()
    {
        $this->resetRequest();
        $params = array(
            'action' => 'Logout',
            'controller' => 'MyResearch'
        );
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
    }

    /**
     * Test the default action.
     *
     * @return void
     */
    public function testIndexAction()
    {
        // Logged out
        $params = array('action' => 'home', 'controller' => 'Index', 'module' => 'default');
        $url = $this->url($params);
        $this->dispatch($url);

        // assertions -- index action should redirect to Search/Home by default
        $this->assertModule($params['module']);
        $this->assertController('Search');
        $this->assertAction('Home');
        $this->assertQueryContentContains("form#searchForm a.small", "Advanced");
        
        // Logged in
        $this->createTestAccount();
        $user = self::$account->isLoggedIn();
        $params = array('action' => 'home', 'controller' => 'Index', 'module' => 'default');
        $url = $this->url($params);
        $this->dispatch($url);

        // assertions -- index action should redirect to MyResearch/MyList when logged in
        $this->assertModule($params['module']);
        $this->assertController('MyResearch');
        $this->assertAction('MyList');
        $this->assertQueryContentContains("form#searchForm a.small", "Advanced");
        
        // Clean up
        $this->logout();
    }

    /**
     * Test switching the display language.
     *
     * @return void
     */
    public function testGermanTranslation()
    {
        $request = $this->getRequest();
        $request->setPost('mylang', 'de');

        $params = array('action' => 'home', 'controller' => 'Index', 'module' => 'default');
        $url = $this->url($params);
        $this->dispatch($url);

        // assertions -- index action should redirect to Search/Home by default
        $this->assertModule($params['module']);
        $this->assertController('Search');
        $this->assertAction('Home');
        $this->assertQueryContentContains("form#searchForm a.small", "Erweitert");
    }

    /**
     * Test switching to invalid display language -- should reset to english.
     *
     * @return void
     */
    public function testInvalidLanguageCode()
    {
        $request = $this->getRequest();
        $request->setPost('mylang', 'bad-language-that-does-not-exist');

        $params = array('action' => 'home', 'controller' => 'Index', 'module' => 'default');
        $url = $this->url($params);
        $this->dispatch($url);

        // assertions -- index action should redirect to Search/Home by default
        $this->assertModule($params['module']);
        $this->assertController('Search');
        $this->assertAction('Home');
        $this->assertQueryContentContains("form#searchForm a.small", "Advanced");
    }

    /**
     * Standard teardown method.
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        // Remove users created for testing purposes:
        $userTable = new VuFind_Model_Db_User();
        $user = $userTable->getByUsername('testuser', false);
        if (is_object($user)) {
            $user->delete();
        }

        // Restore previous authentication setting:
        self::$config->Authentication->method = self::$savedAuthentication;
        VF_Account_Manager::resetInstance();
    }
}



