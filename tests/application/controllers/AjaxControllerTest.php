<?php
/**
 * AJAX Controller Test Class
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */

/**
 * AJAX Controller Test Class
 *
 * @category VuFind2
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/unit_tests Wiki
 */
class AjaxControllerTest extends Zend_Test_PHPUnit_ControllerTestCase
{
    protected static $config;
    protected static $account;
    protected static $savedAuthentication;
    protected static $savedDriver;

    /**
     * Standard setup method.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        // Make the autoloader work
        $something = new Zend_Application(APPLICATION_ENV, APPLICATION_PATH . '/configs/application.ini');
        $something->bootstrap();

        // Use DB authentication since it can be tested without external dependencies
        self::$config = VF_Config_Reader::getConfig();
        self::$savedAuthentication = self::$config->Authentication->method;
        self::$config->Authentication->method = 'DB';
        self::$savedDriver = self::$config->Catalog->driver;
        self::$config->Catalog->driver = 'Demo';
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
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
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
     * Test lightbox
     *
     * @return response
     */
    public function testGetLightbox() {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('subaction', 'Home');
        $request->setParam('submodule', 'MyResearch');
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method','getLightbox');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        $this->assertAction('login'); // home will redirect to login
        $this->assertController('MyResearch');
    }

    /**
     * Test invalid AJAX method
     *
     * @return void
     */
    public function testInvalidMethod()
    {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $url = $this->url($params, 'default', true);
        $request->setParam('method','fakityfakefake');
        $this->dispatch($url);
        $data = json_decode($this->response->outputBody());
        $this->assertEquals('Invalid Method', $data->data);
    }

    /**
     * Get salt from AJAX command
     *
     * @return string
     */
    protected function generateSalt()
    {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $url = $this->url($params, 'default', true);
        $request->setParam('method','getSalt');
        $this->dispatch($url);
        $data = json_decode($this->response->outputBody());
        return $data->data;
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
        $salt = $this->generateSalt();
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('processLogin', 1);
        $request->setParam('username', $username);
        // encrypt password
        $password = VF_Crypt_RC4::encrypt($salt, $password);
        $password = implode('',unpack('H*',$password));
        $request->setParam('password', $password);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'login');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        return json_decode($this->response->outputBody());
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
     * Test addList
     *
     * @return void
     */
    public function testAddList()
    {
        // Create account before anything else
        $this->createTestAccount();
        $this->logout();
        
        // No login
        $request = $this->saveList();
        $this->assertEquals('NEED_AUTH', $request->status);
        // - Login
        $this->login('testuser', 'testpass');
        // TO-DO: No permission
        // Missing fields
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('title', 'Invalid list');
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'addList');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);        
        $response = json_decode($this->response->outputBody());
        $this->assertEquals('ERROR', $response->status);        
        // - Logout
        $this->logout();
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
        $response = $this->login('', '');
        $this->assertEquals('ERROR', $response->status);
        $response = $this->login('test', '');
        $this->assertEquals('ERROR', $response->status);
        $response = $this->login('', 'test');
        $this->assertEquals('ERROR', $response->status);
        $response = $this->login('testuser3', 'does not exist');
        $this->assertEquals('ERROR', $response->status);
        $response = $this->login('testuser', 'bad password');
        $this->assertEquals('ERROR', $response->status);

        // Test successful login:
        $response = $this->login('testuser', 'testpass');
        $user = self::$account->isLoggedIn();
        $this->assertTrue(is_object($user));
        $this->assertEquals('testuser', $user->username);

        // Test successful logout:
        $this->logout();
        $user = self::$account->isLoggedIn();
        $this->assertTrue(empty($user));

        // Log out for the sake of cleanliness:
        $this->logout();
        $user = self::$account->isLoggedIn();
        $this->assertTrue(empty($user));
    }

    /**
     * Helper for testTagRecord
     *
     */
    protected function tagRecord($id, $tag, $source = 'VuFind')
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'tagRecord');
        $request->setParam('id', $id);
        $request->setParam('tag', $tag);
        $request->setParam('source', $source);
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        
        return json_decode($this->response->outputBody());
    }

    /**
     * Test tagRecord
     *
     * @return void
     */
    public function testTagRecord()
    {  
        // Make sure we're not already logged in:
        $this->logout();
        
        // Reset tags
        $resourceTable = new VuFind_Model_Db_Resource();
        $resource = $resourceTable->findResource('testbug2');
        $resource->delete();
        
        // Test adding a tag without being logged in
        $response = $this->tagRecord('testbug', 'TEST_TAG');
        $this->assertEquals($response->status, 'NEED_AUTH');
        // - Log in
        $this->login('testuser', 'testpass');
        // All empty
        $response = $this->tagRecord('', '');
        $this->assertEquals('ERROR', $response->status);
        // Empty record
        $response = $this->tagRecord('', 'TEST_TAG');
        $this->assertEquals('ERROR', $response->status);
        // Empty tag, non-existing record
        $response = $this->tagRecord('imagination', '');
        $this->assertEquals('ERROR', $response->status);
        // Non-existant record
        $response = $this->tagRecord('imagination', 'TEST_TAG');
        $this->assertEquals('ERROR', $response->status);        
        // Empty tag, existing record
        $response = $this->tagRecord('testbug2', '');
        $this->assertEquals('OK', $response->status);
        // - Check no tag was made
        $record = VF_Record::load('testbug2');
        $tags = $record->getTags();
        $this->assertEquals(0, count($tags));
        // Bad source
        $response = $this->tagRecord('testbug2', 'TEST_TAG', 'magnetic_north');
        $this->assertEquals('ERROR', $response->status);
        
        // Do it right
        $response = $this->tagRecord('testbug2', 'TEST_TAG');
        $this->assertEquals('Done', $response->data);
        // - Count tags        
        $record = VF_Record::load('testbug2');
        $tags = $record->getTags();
        $this->assertEquals(1, count($tags));
        
        // Do it right with a valid source
        $response = $this->tagRecord('testbug2', 'TEST_TAG2', 'VuFind');
        $this->assertEquals('Done', $response->data);
        // - Count tags        
        $record = VF_Record::load('testbug2');
        $tags = $record->getTags();
        $this->assertEquals(2, count($tags));
        
        // No duplicate tags
        $response = $this->tagRecord('testbug2', 'TEST_TAG');
        $this->assertEquals('Done', $response->data);
        // - Count tags        
        $record = VF_Record::load('testbug2');
        $tags = $record->getTags();
        $this->assertEquals(2, count($tags));
        
        // Advanced tag parsing
        $response = $this->tagRecord('testbug2', '"multi word tag" some more tags');
        $this->assertEquals('Done', $response->data);
        // - Check tags        
        $record = VF_Record::load('testbug2');
        $tags = $record->getTags();
        $this->assertEquals(6, count($tags));
        $tagsAsStrings = array();
        foreach ($tags as $tag) {
            $tagsAsStrings[] = $tag->tag;
        }
        $this->assertTrue(in_array('multi word tag', $tagsAsStrings));
        $this->assertTrue(in_array('some', $tagsAsStrings));
        $this->assertTrue(in_array('more', $tagsAsStrings));
        $this->assertTrue(in_array('tags', $tagsAsStrings));
        
        // Log out for the sake of cleanliness
        $this->logout();
        $user = self::$account->isLoggedIn();
        $this->assertTrue(empty($user));
    }
    
    /**
     * Get the item statuses for these ids via AJAX
     *
     * @param string $ids - array of ids
     *
     * @return void
     */
    protected function getItemStatuses($ids)
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'getItemStatuses');
        // Test empty array
        $request->setParam('id', $ids);
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        
        return json_decode($this->response->outputBody());
    }
    
    /**
     * Test some item statuses
     *
     * @return void
     */
    public function testGetItemStatuses()
    {
        // Set some status information
        $catalog = VF_Connection_Manager::connectToCatalog();
        $catalog->setStatus('testbug2', array('location'=>'TRUE1'), false);
        $catalog->setStatus('testsample1', array('location'=>'TRUE2'), false);
        $catalog->setStatus('testsample2', array('location'=>'TRUE3'), false);
        $catalog->setStatus('testsample3', array('location'=>'TRUE4'), false);
        $catalog->setInvalidId('invalid');
        // Test empty
        $response = $this->getItemStatuses(array());
        $this->assertEquals(0, count($response->data));
        // Test string
        $response = $this->getItemStatuses('testbug2');
        $this->assertEquals(0, count($response->data));
        // Test invalid ids
        $response = $this->getItemStatuses(array('invalid'));
        $this->assertEquals(true, $response->data[0]->missing_data);
        // Test single item
        $response = $this->getItemStatuses(array('testbug2'));
        $this->assertEquals('TRUE1', $response->data[0]->location);
        // Test valid array
        $response = $this->getItemStatuses(array('testbug2', 'testsample1', 'testsample2', 'testsample3'));
        $this->assertEquals('TRUE1', $response->data[0]->location);
        $this->assertEquals('TRUE2', $response->data[1]->location);
        $this->assertEquals('TRUE3', $response->data[2]->location);
        $this->assertEquals('TRUE4', $response->data[3]->location);
    }

    /**
     * Support method -- create or update a list.
     *
     * @param string $title  List title
     * @param string $desc   List description
     * @param bool   $public Is list public?
     *
     * @return void
     */
    protected function saveList($title = 'New List', $desc = '', $public = false)
    {
        $this->resetRequest();
        $this->resetResponse();
        $request = $this->getRequest();
        $request->setParam('title', $title);
        $request->setParam('desc', $desc);
        $request->setParam('public', $public ? 1 : 0);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'addList');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        
        return json_decode($this->response->outputBody());
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
        $request->setParam('id', $recordId);
        $request->setParam('list', $listId);
        $request->setParam('submit', 1);
        $request->setParam('mytags', $tags);
        $request->setParam('notes', $notes);
        if (!is_null($service)) {
            $request->setParam('service', $service);
        }
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'saveRecord');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
    }
    
    /**
     * Get the save statuses for these ids via AJAX
     *
     * @param array $ids - array of ids
     * @param array $source - optional source array
     *
     * @return void
     */
    protected function getSaveStatuses($ids, $source = array('VuFind'))
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('id', $ids);
        $request->setParam('source', $source);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'getSaveStatuses');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        
        return json_decode($this->response->outputBody());
    }
    
    /**
     * Test some item's save statuses
     *
     * @return void
     */
    public function testGetSaveStatuses()
    {
        // Test no login
        $response = $this->getSaveStatuses(array('testbug2'));
        $this->assertEquals($response->status, 'NEED_AUTH');
        // - Login
        $this->login('testuser', 'testpass');
        // - Insert testbug2 into a new list
        $list1id = $this->saveList('New List');
        $list1id = $list1id->id;
        $this->saveItem($list1id, 'testbug2');
        // - Confirm new list created properly
        $results = $this->retrieveList($list1id);
        $recordsInList = $results->getResults();
        $current = isset($recordsInList[0]) ? $recordsInList[0] : null;
        if (!is_object($current)) {
            $this->fail('Could not load expected record.');
        }
        $this->assertEquals('testbug2', $current->getUniqueId());
        // Test empty id
        $response = $this->getSaveStatuses(array());
        $this->assertEquals(0, count($response->data));
        // Test string id
        $response = $this->getSaveStatuses('testbug');
        $this->assertEquals('ERROR', $response->status);
        // Test string source
        $response = $this->getSaveStatuses(array(), 'VuFind');
        $this->assertEquals('ERROR', $response->status);
        // Test unlisted id
        $response = $this->getSaveStatuses(array('invalid'));
        $this->assertEquals(0, count($response->data));
        // Test invalid source
        $response = $this->getSaveStatuses(array('testbug2'), array('lies'));
        $this->assertEquals(0, count($response->data));
        // - test for valid content
        // Test valid
        $response = $this->getSaveStatuses(array('testbug2'));
        $this->assertEquals(1, count($response->data));
        // - test for valid content
        // - Insert testbug2 into another list
        $list2id = $this->saveList('Another List');
        $list2id = $list2id->id;
        $this->saveItem($list2id, 'testbug2');
        // - Confirm new list created properly
        $results = $this->retrieveList($list2id);
        $recordsInList = $results->getResults();
        $current = $recordsInList[0];
        $this->assertEquals('testbug2', $current->getUniqueId());
        // Test valid
        $response = $this->getSaveStatuses(array('testbug2'));
        $this->assertEquals(2, count($response->data));
        // Logout to be polite
        $this->logout();
    }
    
    /**
     * Helper function for testDeleteFavorites
     *
     * @return json
     */
    protected function deleteFavorites($ids = null, $listID = null)
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('ids', $ids);
        $request->setParam('listID', $listID);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'deleteFavorites');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);
        
        return json_decode($this->response->outputBody());
    }
    /**
     * Test the mass item removing function
     *
     * @return void
     */
    public function testDeleteFavorites() {
        // Test signed out
        $this->logout();
        $response = $this->deleteFavorites(array(0), 0);
        $this->assertEquals('NEED_AUTH', $response->status);
        // - Log in
        // Missing ids
        // Missing listID
        // Empty array
        // Empty listID
        // Invalid ids
        // Valid ids
    }
    
    /**
     * Helper function that gets tags via AJAX
     *
     * @param array $ids - array of ids
     * @param array $source - optional source array
     *
     * @return void
     */
    protected function getRecordTags($ids, $source = array('VuFind'))
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('id', $ids);
        $request->setParam('source', $source);   
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'getRecordTags');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);     
        
        return json_decode($this->response->outputBody());
    }
    
    /**
     * Test getTagRecords and tagRecord
     *
     * @return void
     */
    public function testGetTagRecords()
    {
        // Test invalid id
        $catalog = VF_Connection_Manager::connectToCatalog();
        $catalog->setInvalidId('invalid');
        $response = $this->getRecordTags(array('invalid'));
        $this->assertEquals('ERROR', $response->status);
        // Test invalid source
        $response = $this->getRecordTags(array('testbug2'), array('invalid'));
        $this->assertEquals('ERROR', $response->status);
        // - Get expected tag number
        $tagTable = new VuFind_Model_Db_Tags();
        $tags = $tagTable->getForResource('testbug2');
        $tagCount = count($tags);
        // Test string id
        $response = $this->getRecordTags('testbug2');
        $this->assertEquals($tagCount, count($response->data));
        // Test string source
        $response = $this->getRecordTags(array('testbug2'), 'VuFind');
        $this->assertEquals($tagCount, count($response->data));
        // Test both array
        $response = $this->getRecordTags(array('testbug2'), array('VuFind'));
        $this->assertEquals($tagCount, count($response->data));
    }
    
    /**
     * Helper class - Get autocomplete response
     *
     * @return string array
     */
    public function getACSuggestions($q, $type = 'AllFields')
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('q', $q);
        $request->setParam('type', $type);   
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'getACSuggestions');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);     
        
        $json = json_decode($this->response->outputBody());
        return $json->data;
    }
    
    /**
     * Get longest string from string array
     *
     * @param array $array - string array
     *
     * @return string
     */
    private function getLongest($array) {
        $longest = $array[0];
        for($i=1;$i<count($array);$i++) {
            if(strlen($longest) < strlen($array[$i])) {
                $longest = $array[$i];
            }
        }
        return $longest;
    }
    
    /**
     * Test autocomplete
     *
     * @return void
     */
    public function testGetACSuggestions()
    {
        // Empty q
        $response = $this->getACSuggestions('', 'AllFields');
        // - Pick the longest title to ensure no subtitles
        $testTitle = $this->getLongest($response);
        // Test unique AC
        $response = $this->getACSuggestions($testTitle, 'AllFields');
        $this->assertEquals($testTitle, $response[0]);
        // Empty type
        $response = $this->getACSuggestions($testTitle, '');
        $this->assertEquals($testTitle, $response[0]);
        // Invalid type
        $response = $this->getACSuggestions($testTitle, 'invalid');
        $this->assertEquals($testTitle, $response[0]);
        // Non-matching q
        $response = $this->getACSuggestions('!@#$%^&*()!@#$%^&*()');
        $this->assertEquals(0, count($response));
        // Title
        $response = $this->getACSuggestions('', 'Title');
        $testTitle = $this->getLongest($response);
        // Test unique title
        $response = $this->getACSuggestions($testTitle, 'Title');
        $this->assertEquals($testTitle, $response[0]);
        // Author
        $response = $this->getACSuggestions('', 'Author');
        $testAuthor = $this->getLongest($response);
        // Test unique author
        $response = $this->getACSuggestions($testAuthor, 'Author');
        $this->assertEquals($testAuthor, $response[0]);
        // Subject
        $response = $this->getACSuggestions('', 'Subject');
        $testSubject = $this->getLongest($response);
        // Test unique subject
        $response = $this->getACSuggestions($testSubject, 'Subject');
        $this->assertEquals($testSubject, $response[0]);
        // Call Number
        $response = $this->getACSuggestions('', 'CallNumber');
        $testCallNumber = $this->getLongest($response);
        // Test unique CallNumber
        $response = $this->getACSuggestions($testCallNumber, 'CallNumber');
        $this->assertEquals($testCallNumber, $response[0]);
        // ISBN/ISSN
        $response = $this->getACSuggestions('', 'ISN');
        $testISN = $this->getLongest($response);
        // Test unique ISN
        $response = $this->getACSuggestions($testISN, 'ISN');
        $this->assertEquals($testISN, $response[0]);
        // Tag
        $response = $this->getACSuggestions('', 'tag');
        $testTag = $this->getLongest($response);
        // Test unique ISN
        $response = $this->getACSuggestions($testTag, 'tag');
        $this->assertEquals($testTag, $response[0]);
    }
    
    /**
     * Helper function - add comment to record
     *
     * @return string array
     */
    public function addComment($id, $comment, $source = 'VuFind')
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('id', $id);
        $request->setParam('comment', $comment);
        $request->setParam('source', $source);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'commentRecord');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);     
        
        return json_decode($this->response->outputBody());
    }
    
    /**
     * Helper function - get comments for record
     *
     * @return string array
     */
    public function getComments($id, $source = 'VuFind')
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
          
        $request->setParam('id', $id);
        $request->setParam('source', $source); 
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'getRecordCommentsAsHTML');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);     
        
        return json_decode($this->response->outputBody());
    }
    
    /**
     * Helper function - delete comment
     *
     * @return string array
     */
    public function deleteComment($commentID)
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('id', $commentID);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'deleteRecordComment');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);     
        
        return json_decode($this->response->outputBody());
    }
    
    /**
     * Test of all the comment functions
     *
     * @return void
     */
     public function testComments()
     {
        // Test logged out
        $response = $this->addComment(0, '$comment');
        $this->assertEquals('NEED_AUTH', $response->status);
        $response = $this->deleteComment(0);
        $this->assertEquals('NEED_AUTH', $response->status);
        // - Log in
        $this->login('testuser', 'testpass');
        // No comments
        $response = $this->getComments('testbug2');
        // - trimming off the line breaks
        $this->assertEquals('<li>Be the first to leave a comment!</li>', trim($response->data));
        // Add bad comments
        // Empty id
        $response = $this->addComment('', 'thisshouldfail');
        $this->assertEquals('ERROR', $response->status);
        // Empty comment
        $response = $this->addComment('thisshouldfail', '');
        $this->assertEquals('ERROR', $response->status);
        // Bad id
        $response = $this->addComment('fakityfakefake', 'thisshouldfail');
//         $this->assertEquals('ERROR', $response->status);
        // Add comment
        $response = $this->addComment('testbug2', 'Makes a great Christmas present');
        $this->assertEquals('OK', $response->status);
        $id = $response->data;
        // Check comment
        $response = $this->getComments('testbug2');
        $this->assertTrue(strpos($response->data, 'Makes a great Christmas present') > -1);
        // Delete comment
        $response = $this->deleteComment($id);
        $this->assertEquals('OK', $response->status);
        // Delete fake comment
        $response = $this->deleteComment('fakityfakefake');
//         $this->assertEquals('ERROR', $response->status);
        // Check lack of comment
        $response = $this->getComments('testbug2');
        $this->assertEquals('<li>Be the first to leave a comment!</li>', trim($response->data));
        // - Logout to be polite
        $this->logout();
     }
     
    /**
     * testValidRequest helper
     *
     * @return JSON response
     */
    private function checkRequest($id, $data)
    {
        $this->resetRequest();
        $this->resetResponse();  
        $request = $this->getRequest();
        
        $request->setParam('id', $id);
        $request->setParam('data', $data);
        $params = array(
            'action' => 'JSON',
            'controller' => 'AJAX'
        );
        $request->setParam('method', 'checkRequestIsValid');
        $url = $this->url($params, 'default', true);
        $this->dispatch($url);     
        
        return json_decode($this->response->outputBody());
    }

    /**
     * Test checkRequestIsValid
     *
     * @return void
     */
    public function testValidRequest()
    {
        // Empty
        $response = $this->checkRequest('', 'thisshouldfail');
        $this->assertEquals('ERROR', $response->status);
        $response = $this->checkRequest('thisshouldfail', '');
        $this->assertEquals('ERROR', $response->status);
        // Not logged in
        $this->logout();
        $response = $this->checkRequest('thisshouldfail', 'thisshouldfail');
        $this->assertEquals('NEED_AUTH', $response->status);
        // - Log in
        // 
        // - Log out to be polite
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
        self::$config->Catalog->driver = self::$savedDriver;
        VF_Account_Manager::resetInstance();
    }
}
