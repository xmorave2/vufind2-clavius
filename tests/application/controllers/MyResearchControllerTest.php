<?php
/**
 * MyResearch Controller Test Class
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
 * MyResearch Controller Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class MyResearchControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
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

        // Fail if there are already users in the database (we don't want to run this
        // on a real system -- it's only meant for the continuous integration server)
        $userTable = new VuFind_Model_Db_User();
        $rows = $userTable->fetchAll();
        if (count($rows) > 0) {
            throw new Exception('Test cannot run with pre-existing user data!');
        }
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
     * Support method -- log out the current user.
     *
     * @return void
     */
    protected function logout()
    {
        $this->resetRequest();
        $this->resetResponse();
        $params = array(
            'action' => 'Logout',
            'controller' => 'MyResearch'
        );
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
    }

    /**
     * Support method -- attempt to log in with provided credentials.
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @return void
     */
    protected function login($username, $password)
    {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('processLogin', 1);
        $request->setParam('username', $username);
        $request->setParam('password', $password);
        $params = array(
            'action' => 'Home',
            'controller' => 'MyResearch'
        );
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
    }

    /**
     * Support method -- create or update a list.
     *
     * @param string $id     List ID (or "NEW" for new)
     * @param string $title  List title
     * @param string $desc   List description
     * @param bool   $public Is list public?
     *
     * @return void
     */
    protected function saveList($id, $title, $desc, $public)
    {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('id', $id);
        $request->setParam('submit', 1);
        $request->setParam('title', $title);
        $request->setParam('desc', $desc);
        $request->setParam('public', $public ? 1 : 0);
        $params = array(
            'action' => 'EditList',
            'controller' => 'MyResearch'
        );
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
    }

    /**
     * Support method -- retrieve the contents of a list by ID.
     *
     * @param string $id List ID
     *
     * @return VF_Search_Favorites_Results
     */
    protected function retrieveList($id)
    {
        $params = new VF_Search_Favorites_Params();
        $params->initFromRequest(
            new Zend_Controller_Request_Simple(
                null, null, null, array('id' => $id)
            )
        );
        return new VF_Search_Favorites_Results($params);
    }

    /**
     * Save an item to a list.
     *
     * @param string $listId   ID of list to save to
     * @param string $recordId ID of record to save
     * @param string $tags     Tags to associate with record
     * @param string $notes    Notes to associate with record
     * @param string $service  Service that saved record came from (null = use
     * default)
     *
     * @return void
     */
    public function saveItem($listId, $recordId, $tags = '', $notes = '',
        $service = null
    ) {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('list', $listId);
        $request->setParam('submit', 1);
        $request->setParam('mytags', $tags);
        $request->setParam('notes', $notes);
        if (!is_null($service)) {
            $request->setParam('service', $service);
        }
        $params = array(
            'action' => 'Save',
            'id' => $recordId
        );
        $url = $this->url($params, 'record', true);
        $this->dispatch($url);
    }

    /**
     * Test account creation.
     *
     * @return void
     */
    public function testAccountCreation()
    {
        $this->resetRequest();
        $request = $this->getRequest();
        $request->setParam('submit', 1);
        $request->setParam('username', '');

        $params = array(
            'action' => 'Account',
            'controller' => 'MyResearch',
        );

        $url = $this->url($params, 'default', true);

        // Confirm that test users do not already exist:
        $userTable = new VuFind_Model_Db_User();
        $user = $userTable->getByUsername('testuser1', false);
        $this->assertTrue(empty($user));
        $user = $userTable->getByUsername('testuser2', false);
        $this->assertTrue(empty($user));

        // Create first test user and check various error conditions:
        $this->dispatch($url);
        $this->assertQueryContentContains("div.error", "Username cannot be blank");
        $request->setParam('username', 'testuser1');
        $request->setParam('password', '');
        $this->resetResponse();
        $this->dispatch($url);
        $this->assertQueryContentContains("div.error", "Password cannot be blank");
        $request->setParam('password', 'testpass1');
        $request->setParam('password2', '');
        $this->resetResponse();
        $this->dispatch($url);
        $this->assertQueryContentContains("div.error", "Passwords do not match");
        $request->setParam('password2', 'testpass1');
        $request->setParam('email', '');
        $this->resetResponse();
        $this->dispatch($url);
        $this->assertQueryContentContains("div.error", "Email address is invalid");
        $request->setParam('email', 'user1@test.com');
        $this->resetResponse();
        $this->dispatch($url);

        // Confirm that user exists:
        $user = $userTable->getByUsername('testuser1', false);
        $this->assertTrue(is_object($user));
        $this->assertEquals($user->username, 'testuser1');

        // Create second test user and check other error conditions:
        $this->resetResponse();
        $this->dispatch($url);
        $this->assertQueryContentContains("div.error", "That username is already taken");
        $request->setParam('username', 'testuser2');
        $this->resetResponse();
        $this->dispatch($url);
        $this->assertQueryContentContains("div.error", "That email address is already used");
        $request->setParam('email', 'user2@test.com');
        $request->setParam('password', 'testpass2');
        $request->setParam('password2', 'testpass2');
        $this->resetResponse();
        $this->dispatch($url);

        // Confirm that user exists:
        $user = $userTable->getByUsername('testuser2', false);
        $this->assertTrue(is_object($user));
        $this->assertEquals($user->username, 'testuser2');

        // If this worked correctly, we should be forwarded to favorites:
        $this->assertController('MyResearch');
        $this->assertAction('MyList');
    }

    /**
     * Test log in / log out
     *
     * @return void
     */
    public function testLogInLogOut()
    {
        // Make sure we're not already logged in:
        $this->logout();

        // Test bad username/bad password:
        $this->login('', '');
        $this->assertQueryContentContains("div.error", "Login information cannot be blank.");
        $this->login('test', '');
        $this->assertQueryContentContains("div.error", "Login information cannot be blank.");
        $this->login('', 'test');
        $this->assertQueryContentContains("div.error", "Login information cannot be blank.");
        $this->login('testuser3', 'does not exist');
        $this->assertQueryContentContains("div.error", "Invalid login -- please try again.");
        $this->login('testuser1', 'bad password');
        $this->assertQueryContentContains("div.error", "Invalid login -- please try again.");

        // Test successful login:
        $this->login('testuser1', 'testpass1');
        $user = self::$account->isLoggedIn();
        $this->assertTrue(is_object($user));
        $this->assertEquals($user->username, 'testuser1');

        // Test successful logout:
        $this->logout();
        $user = self::$account->isLoggedIn();
        $this->assertTrue(empty($user));

        // Test successful login of a different user:
        $this->login('testuser2', 'testpass2');
        $user = self::$account->isLoggedIn();
        $this->assertTrue(is_object($user));
        $this->assertEquals($user->username, 'testuser2');

        // Log out for the sake of cleanliness:
        $this->logout();
        $user = self::$account->isLoggedIn();
        $this->assertTrue(empty($user));
    }

    /**
     * Test favorites functionality.
     *
     * @return void
     */
    public function testFavorites()
    {
        // Confirm that user 1 has no lists to begin with:
        $this->login('testuser1', 'testpass1');
        $user = self::$account->isLoggedIn();
        $this->assertEquals(count($user->getLists()), 0);

        // Create some new lists:
        $title = 'User 1 public list 1';
        $desc = 'Description of ' . $title;
        $this->saveList('NEW', $title, $desc, true);
        $lists = $user->getLists();
        $this->assertEquals(count($lists), 1);
        $list = $lists[0];
        $this->assertEquals($list->title, $title);
        $this->assertEquals($list->description, $desc);
        $this->assertTrue((bool)$list->public);
        $user1PublicListId = $list->id;

        $title = 'User 1 private list 1';
        $desc = 'Description of ' . $title;
        $this->saveList('NEW', $title, $desc, false);
        $lists = $user->getLists();
        $this->assertEquals(count($lists), 2);
        foreach ($lists as $list) {
            if ($list->id != $user1PublicListId) {
                break;
            }
        }
        $this->assertEquals($list->title, $title);
        $this->assertEquals($list->description, $desc);
        $this->assertFalse((bool)$list->public);
        $user1PrivateListId = $list->id;

        // Add items to lists:
        $resourceTable = new VuFind_Model_Db_Resource();
        $favorites = $resourceTable->getFavorites($user->id, $user1PrivateListId);
        $this->assertEquals(count($favorites), 0);
        $this->saveItem($user1PrivateListId, 'testbug2');
        $favorites = $resourceTable->getFavorites($user->id, $user1PrivateListId);
        $this->assertEquals(count($favorites), 1);
        $this->assertEquals($favorites[0]['record_id'], 'testbug2');
        $this->assertEquals($favorites[0]['source'], 'VuFind');

        // Set up list for user 2, testing auto-creation feature and tag parsing:
        $this->logout();
        $this->login('testuser2', 'testpass2');
        $user = self::$account->isLoggedIn();
        $this->assertEquals(count($user->getLists()), 0);
        $this->saveItem('NEW', 'testbug2', '"second user" tag list', 'my notes');
        $lists = $user->getLists();
        $this->assertEquals(count($lists), 1);
        $list = $lists[0];
        $this->assertEquals($list->title, 'My Favorites');
        $this->assertEquals($list->description, '');
        $this->assertFalse((bool)$list->public);
        $user2PrivateListId = $list->id;
        $results = $this->retrieveList($user2PrivateListId);
        $this->assertEquals($results->getResultTotal(), 1);
        $recordsInList = $results->getResults();
        $current = $recordsInList[0];
        $this->assertEquals($current->getUniqueId(), 'testbug2');
        $notes = $current->getListNotes($user2PrivateListId, $user->id);
        $this->assertEquals($notes[0], 'my notes');
        $tags = $current->getTags($user2PrivateListId, $user->id);
        $this->assertEquals(count($tags), 3);
        $tagsAsStrings = array();
        foreach ($tags as $tag) {
            $tagsAsStrings[] = $tag->tag;
        }
        $this->assertTrue(in_array('second user', $tagsAsStrings));
        $this->assertTrue(in_array('tag', $tagsAsStrings));
        $this->assertTrue(in_array('list', $tagsAsStrings));
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
        $user = $userTable->getByUsername('testuser1', false);
        if (is_object($user)) {
            $user->delete();
        }
        $user = $userTable->getByUsername('testuser2', false);
        if (is_object($user)) {
            $user->delete();
        }

        // Restore previous authentication setting:
        self::$config->Authentication->method = self::$savedAuthentication;
        VF_Account_Manager::resetInstance();
    }
}