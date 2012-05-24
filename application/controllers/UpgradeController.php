<?php
/**
 * Upgrade Controller
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
 * Class controls VuFind upgrading.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class UpgradeController extends Zend_Controller_Action
{
    protected $session;

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $this->view->flashMessenger = $this->_helper->flashMessenger;
        $this->session = new Zend_Session_Namespace('upgrade');
    }

    /**
     * Support method -- given a directory, extract a version number from the
     * build.xml file within that directory.
     *
     * @param string $dir Directory to search for build.xml
     *
     * @return string
     */
    protected function getVersion($dir)
    {
        $xml = simplexml_load_file($dir . '/build.xml');
        if (!$xml) {
            throw new Exception('Cannot load ' . $dir . '/build.xml.');
        }
        $parts = $xml->xpath('/project/property[@name="version"]/@value');
        return (string)$parts[0];
    }

    /**
     * Display a fatal error message.
     *
     * @return void
     */
    public function errorAction()
    {
        // Just display template
    }

    /**
     * Figure out which version(s) are being used.
     *
     * @return void
     */
    public function establishversionsAction()
    {
        $this->session->oldVersion = $this->getVersion(
            realpath(APPLICATION_PATH . '/..')
        );
        $this->session->newVersion = $this->getVersion(
            $this->session->sourceDir
        );

        // Block upgrade when encountering common errors:
        if (empty($this->session->oldVersion)) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('Cannot determine source version.');
            unset($this->session->oldVersion);
            return $this->_forward('Error');
        }
        if (empty($this->session->newVersion)) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('Cannot determine destination version.');
            unset($this->session->newVersion);
            return $this->_forward('Error');
        }
        if ($this->session->newVersion == $this->session->oldVersion) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('Cannot upgrade version to itself.');
            unset($this->session->newVersion);
            return $this->_forward('Error');
        }

        // If we got this far, everything is okay:
        return $this->_forward('Home');
    }

    /**
     * Upgrade the configuration files.
     *
     * @return void
     */
    public function fixconfigAction()
    {
        $upgrader = new VF_Config_Upgrade(
            $this->session->oldVersion, $this->session->newVersion,
            $this->session->sourceDir . '/web/conf',
            APPLICATION_PATH . '/configs',
            LOCAL_OVERRIDE_DIR . '/application/configs'
        );
        try {
            $upgrader->run();
            $this->session->warnings = $upgrader->getWarnings();
            $this->session->configOkay = true;
            return $this->_forward('Home');
        } catch (Exception $e) {
            $extra = is_a($e, 'VF_Exception_FileAccess')
                ? '  Check file permissions.' : '';
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('Config upgrade failed: ' . $e->getMessage() . $extra);
            return $this->_forward('Error');
        }
    }

    /**
     * Upgrade the database.
     *
     * @return void
     */
    public function fixdatabaseAction()
    {
        try {
            // Set up the helper with information from our SQL file:
            $this->_helper->dbUpgrade->loadSql(APPLICATION_PATH . '/sql/mysql.sql');

            // Check for missing tables.  Note that we need to finish dealing with
            // missing tables before we proceed to the missing columns check, or else
            // the missing tables will cause fatal errors during the column test.
            $missingTables = $this->_helper->dbUpgrade->getMissingTables();
            if (!empty($missingTables)) {
                if (!isset($this->session->dbRootUser)
                    || !isset($this->session->dbRootPass)
                ) {
                    return $this->_forward('GetDbCredentials');
                }
                $db = VF_DB::connect(
                    $this->session->dbRootUser, $this->session->dbRootPass
                );
                $this->_helper->dbUpgrade->createMissingTables($missingTables, $db);
                $this->session->warnings[] = "Created missing table(s): "
                    . implode(', ', $missingTables);
            }

            // Check for missing columns.
            $missingCols = $this->_helper->dbUpgrade->getMissingColumns();
            if (!empty($missingCols)) {
                if (!isset($this->session->dbRootUser)
                    || !isset($this->session->dbRootPass)
                ) {
                    return $this->_forward('GetDbCredentials');
                }
                if (!isset($db)) {  // connect to DB if not already connected
                    $db = VF_DB::connect(
                        $this->session->dbRootUser, $this->session->dbRootPass
                    );
                }
                $this->_helper->dbUpgrade->createMissingColumns($missingCols, $db);
                $this->session->warnings[] = "Added column(s) to table(s): "
                    . implode(', ', array_keys($missingCols));
            }

            // Don't keep DB credentials in session longer than necessary:
            unset($this->session->dbRootUser);
            unset($this->session->dbRootPass);

            // Check for legacy "anonymous tag" bug:
            $anonymousTags = VuFind_Model_Db_Tags::getAnonymousCount();
            if ($anonymousTags > 0 && !isset($this->session->skipAnonymousTags)) {
                $this->view->anonymousCount = $anonymousTags;
                return $this->_forward('FixAnonymousTags');
            }
        } catch (Exception $e) {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('Database upgrade failed: ' . $e->getMessage());
            return $this->_forward('Error');
        }

        $this->session->databaseOkay = true;
        return $this->_forward('Home');
    }

    /**
     * Prompt the user for database credentials.
     *
     * @return void
     */
    public function getdbcredentialsAction()
    {
        $this->view->dbrootuser = $this->_request->getParam('dbrootuser', 'root');

        // Process form submission:
        if (strlen($this->_request->getParam('submit', '')) > 0) {
            $pass = $this->_request->getParam('dbrootpass');

            // Test the connection:
            try {
                $db = VF_DB::connect($this->view->dbrootuser, $pass);
                $db->query("SELECT * FROM user;");  // query a table known to exist
                $this->session->dbRootUser = $this->view->dbrootuser;
                $this->session->dbRootPass = $pass;
                return $this->_forward('FixDatabase');
            } catch (Exception $e) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage('Could not connect; please try again.');
            }
        }
    }

    /**
     * Prompt the user about fixing anonymous tags.
     *
     * @return void
     */
    public function fixanonymoustagsAction()
    {
        // Handle skip action:
        if (strlen($this->_request->getParam('skip', '')) > 0) {
            $this->session->skipAnonymousTags = true;
            return $this->_forward('FixDatabase');
        }

        // Handle submit action:
        if (strlen($this->_request->getParam('submit', '')) > 0) {
            $user = $this->_request->getParam('username');
            if (empty($user)) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage('Username must not be empty.');
            } else {
                $user = VuFind_Model_Db_User::getByUsername($user, false);
                if (empty($user) || !is_object($user) || !isset($user->id)) {
                    $this->_helper->flashMessenger->setNamespace('error')
                        ->addMessage("User {$user} not found.");
                } else {
                    $table = new VuFind_Model_Db_ResourceTags();
                    $table->assignAnonymousTags($user->id);
                    $this->session->warnings[]
                        = "Assigned all anonymous tags to {$user->username}.";
                    return $this->_forward('FixDatabase');
                }
            }
        }
    }

    /**
     * Fix missing metadata in the resource table.
     *
     * @return void
     */
    public function fixmetadataAction()
    {
        // User requested skipping this step?  No need to do further work:
        if (strlen($this->_request->getParam('skip', '')) > 0) {
            $this->session->metadataOkay = true;
            return $this->_forward('Home');
        }

        // Check for problems:
        $table = new VuFind_Model_Db_Resource();
        $problems = $table->findMissingMetadata();

        // No problems?  We're done here!
        if (count($problems) == 0) {
            $this->session->metadataOkay = true;
            return $this->_forward('Home');
        }

        // Process submit button:
        if (strlen($this->_request->getParam('submit', '')) > 0) {
            foreach ($problems as $problem) {
                try {
                    $driver = VF_Record::load($problem->record_id, $problem->source);
                    $problem->assignMetadata($driver)->save();
                } catch (VF_Exception_RecordMissing $e) {
                    $this->session->warnings[]
                        = "Unable to load metadata for record "
                        . "{$problem->source}:{$problem->record_id}";
                }
            }
            $this->session->metadataOkay = true;
            return $this->_forward('Home');
        }
    }

    /**
     * Prompt the user for a source directory.
     *
     * @return void
     */
    public function getsourcedirAction()
    {
        // Process form submission:
        $dir = $this->_request->getParam('sourcedir');
        if (!empty($dir)) {
            $this->session->sourceDir = rtrim($dir, '\/');
            // Clear out request to avoid infinite loop:
            $this->_request->setParam('sourcedir', '');
            return $this->_forward('Home');
        }

        // If a bad directory was provided, display an appropriate error:
        if (isset($this->session->sourceDir)) {
            if (!is_dir($this->session->sourceDir)) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage($this->session->sourceDir . ' does not exist.');
            } else if (!file_exists($this->session->sourceDir . '/build.xml')) {
                $this->_helper->flashMessenger->setNamespace('error')->addMessage(
                    'Could not find build.xml in source directory;'
                    . ' upgrade does not support VuFind versions prior to 1.1.'
                );
            }
        }
    }

    /**
     * Display summary of installation status
     *
     * @return void
     */
    public function homeAction()
    {
        // If the cache is messed up, nothing is going to work right -- check that
        // first:
        $cache = new VF_Cache_Manager();
        if ($cache->hasDirectoryCreationError()) {
            return $this->_redirect('/Install/fixcache');
        }

        // First find out which version we are upgrading:
        if (!isset($this->session->sourceDir)
            || !is_dir($this->session->sourceDir)
        ) {
            return $this->_forward('GetSourceDir');
        }

        // Next figure out which version(s) are involved:
        if (!isset($this->session->oldVersion)
            || !isset($this->session->newVersion)
        ) {
            return $this->_forward('EstablishVersions');
        }

        // Now make sure we have a configuration file ready:
        if (!isset($this->session->configOkay)) {
            return $this->_forward('FixConfig');
        }

        // Now make sure the database is up to date:
        if (!isset($this->session->databaseOkay)) {
            return $this->_forward('FixDatabase');
        }

        // Check for missing metadata in the resource table; note that we do a
        // redirect rather than a forward here so that a submit button clicked
        // in the database action doesn't cause the metadata action to also submit!
        if (!isset($this->session->metadataOkay)) {
            return $this->_redirect('/Upgrade/FixMetadata');
        }

        // We're finally done -- display any warnings that we collected during
        // the process.
        $this->session->warnings = isset($this->session->warnings)
            ? $this->session->warnings : array();
        foreach ($this->session->warnings as $warning) {
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage($warning);
        }
    }

    /**
     * Start over with the upgrade process in case of an error.
     *
     * @return void
     */
    public function resetAction()
    {
        foreach ($this->session as $k => $v) {
            unset($this->session->$k);
        }
        return $this->_forward('Home');
    }
}

