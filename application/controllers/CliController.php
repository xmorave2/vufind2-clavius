<?php
/**
 * CLI Controller Module
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * This controller handles various command-line tools
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class CliController extends Zend_Controller_Action
{
    protected $request;

    /**
     * init
     *
     * Set up the command line environment.
     *
     * @return void
     */
    public function init()
    {
        // This controller should only be accessed from the command line!
        if (PHP_SAPI != 'cli') {
            throw new Exception('Access denied to command line tools.');
        }

        // We don't want to use views or layouts in this controller.
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        // Get access to information about the CLI request.
        $this->consoleOpts = new Zend_Console_Getopt(array());
    }

    /**
     * Build the Reserves index.
     *
     * @return void
     */
    public function indexreservesAction()
    {
        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        // Setup Solr Connection
        $solr = VF_Connection_Manager::connectToIndex('SolrReserves');

        // Connect to ILS
        $catalog = VF_Connection_Manager::connectToCatalog();

        // Records to index
        $index = array();

        // Get instructors
        $instructors = $catalog->getInstructors();

        // Get Courses
        $courses = $catalog->getCourses();

        // Get Departments
        $departments = $catalog->getDepartments();

        // Get all reserve records
        $reserves = $catalog->findReserves('', '', '');

        if (!empty($instructors) && !empty($courses) && !empty($departments)
            && !empty($reserves)
        ) {
            // Delete existing records
            $solr->deleteAll();

            // Build the index
            $solr->buildIndex($instructors, $courses, $departments, $reserves);

            // Commit and Optimize the Solr Index
            $solr->commit();
            $solr->optimize();
        }
    }

    /**
     * Optimize the Solr index.
     *
     * @return void
     */
    public function optimizeAction()
    {
        ini_set('memory_limit', '50M');
        ini_set('max_execution_time', '3600');

        // Setup Solr Connection -- Allow core to be specified as first command line
        // param.
        $argv = $this->consoleOpts->getRemainingArgs();
        $solr = VF_Connection_Manager::connectToIndex(
            null, isset($argv[0]) ? $argv[0] : ''
        );

        // Commit and Optimize the Solr Index
        $solr->commit();
        $solr->optimize();
    }

    /**
     * Generate a Sitemap
     *
     * @return void
     */
    public function sitemapAction()
    {
        // Build sitemap and display appropriate warnings if needed:
        $generator = new VF_Sitemap();
        $generator->generate();
        foreach ($generator->getWarnings() as $warning) {
            echo "$warning\n";
        }
    }

    /**
     * Command-line tool to batch-delete records from the Solr index.
     *
     * @return void
     */
    public function deletesAction()
    {
        // Parse the command line parameters -- see if we are in "flat file" mode,
        // find out what file we are reading in,
        // and determine the index we are affecting!
        $argv = $this->consoleOpts->getRemainingArgs();
        $filename = isset($argv[0]) ? $argv[0] : null;
        $mode = isset($argv[1]) ? $argv[1] : 'marc';
        $index = isset($argv[2]) ? $argv[2] : 'Solr';

        // No filename specified?  Give usage guidelines:
        if (empty($filename)) {
            echo "Delete records from VuFind's index.\n\n",
                "Usage: deletes.php [filename] [format] [index]\n\n",
                "[filename] is the file containing records to delete.\n",
                "[format] is the format of the file",
                " -- it may be one of the following:\n",
                "\tflat - flat text format",
                " (deletes all IDs in newline-delimited file)\n",
                "\tmarc - binary MARC format",
                " (delete all record IDs from 001 fields)\n",
                "\tmarcxml - MARC-XML format",
                " (delete all record IDs from 001 fields)\n",
                '"marc" is used by default if no format is specified.' . "\n",
                "[index] is the index to use (default = Solr)\n";
            return;
        }

        // File doesn't exist?
        if (!file_exists($filename)) {
            echo "Cannot find file: {$filename}\n";
            return;
        }

        // Setup Solr Connection
        $solr = VF_Connection_Manager::connectToIndex($index);

        // Build list of records to delete:
        $ids = array();

        // Flat file mode:
        if ($mode == 'flat') {
            foreach (explode("\n", file_get_contents($filename)) as $id) {
                $id = trim($id);
                if (!empty($id)) {
                    $ids[] = $id;
                }
            }
        } else {
            // MARC file mode...  We need to load the MARC record differently if it's
            // XML or binary:
            $collection = ($mode == 'marcxml')
                ? new File_MARCXML($filename) : new File_MARC($filename);

            // Once the records are loaded, the rest of the logic is always the same:
            while ($record = $collection->next()) {
                $idField = $record->getField('001');
                $ids[] = (string)$idField->getData();
            }
        }

        // Delete, Commit and Optimize if necessary:
        if (!empty($ids)) {
            $solr->deleteRecords($ids);
            $solr->commit();
            $solr->optimize();
        }
    }

    /**
     * Command-line tool to clear unwanted entries
     * from search history database table.
     *
     * @return void
     */
    public function expiresearchesAction()
    {
        // Get command-line arguments
        $argv = $this->consoleOpts->getRemainingArgs();

        // Use command line value as expiration age, or default to 2.
        $daysOld = isset($argv[0]) ? intval($argv[0]) : 2;

        // Abort if we have an invalid expiration age.
        if ($daysOld < 2) {
            echo "Expiration age must be at least two days.\n";
            return;
        }

        // Delete the expired searches--this cleans up any junk left in the database
        // from old search histories that were not
        // caught by the session garbage collector.
        $search = new VuFind_Model_Db_Search();
        $expired = $search->getExpiredSearches($daysOld);
        if (count($expired) == 0) {
            echo "No expired searches to delete.\n";
            return;
        }
        $count = count($expired);
        foreach ($expired as $oldSearch) {
            $oldSearch->delete();
        }
        echo "\n{$count} expired searches deleted.\n";
    }

    /**
     * Command-line tool to delete suppressed records from the index.
     *
     * @return void
     */
    public function suppressedAction()
    {
        // Setup Solr Connection
        $this->consoleOpts->addRules(
            array(
                'authorities' =>
                    'Delete authority records instead of bibliographic records'
            )
        );
        $core = $this->consoleOpts->getOption('authorities')
            ? 'authority' : 'biblio';

        $solr = VF_Connection_Manager::connectToIndex('Solr', $core);

        // Make ILS Connection
        try {
            $catalog = VF_Connection_Manager::connectToCatalog();
            if ($core == 'authority') {
                $result = $catalog->getSuppressedAuthorityRecords();
            } else {
                $result = $catalog->getSuppressedRecords();
            }
        } catch (Exception $e) {
            echo "ILS error -- " . $e->getMessage() . "\n";
            return;
        }

        // Validate result:
        if (!is_array($result)) {
            echo "Could not obtain suppressed record list from ILS.\n";
            return;
        } else if (empty($result)) {
            echo "No suppressed records to delete.\n";
            return;
        }

        // Get Suppressed Records and Delete from index
        $status = $solr->deleteRecords($result);
        if ($status) {
            // Commit and Optimize
            $solr->commit();
            $solr->optimize();
        } else {
            echo "Delete failed.\n";
        }
    }

    /**
     * Warn the user if VUFIND_LOCAL_DIR is not set.
     *
     * @return void
     */
    protected function checkLocalSetting()
    {
        if (!getenv('VUFIND_LOCAL_DIR')) {
            echo "WARNING: The VUFIND_LOCAL_DIR environment variable is not set.\n";
            echo "This should point to your local configuration directory (i.e. \n";
            echo realpath(APPLICATION_PATH . '/../local') . ").\n";
            echo "Without it, inappropriate default settings may be loaded.\n\n";
        }
    }

    /**
     * Harvest the LC Name Authority File.
     *
     * @return void
     */
    public function harvestnafAction()
    {
        $this->checkLocalSetting();

        // Perform the harvest -- note that first command line parameter may be used to
        // start at a particular date.
        try {
            $harvest = new VF_Harvest_NAF();
            $argv = $this->consoleOpts->getRemainingArgs();
            if (isset($argv[0])) {
                $harvest->setStartDate($argv[0]);
            }
            $harvest->launch();
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * Harvest OAI-PMH records.
     *
     * @return void
     */
    public function harvestoaiAction()
    {
        $this->checkLocalSetting();

        // Read Config files
        $configFile = VF_Config_Reader::getConfigPath('oai.ini', 'harvest');
        $oaiSettings = @parse_ini_file($configFile, true);
        if (empty($oaiSettings)) {
            echo "Please add OAI-PMH settings to oai.ini.\n";
            return;
        }

        // If first command line parameter is set, see if we can limit to just the
        // specified OAI harvester:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (isset($argv[0])) {
            if (isset($oaiSettings[$argv[0]])) {
                $oaiSettings = array($argv[0] => $oaiSettings[$argv[0]]);
            } else {
                echo "Could not load settings for {$argv[0]}.\n";
                return;
            }
        }

        // Loop through all the settings and perform harvests:
        $processed = 0;
        foreach ($oaiSettings as $target => $settings) {
            if (!empty($target) && !empty($settings)) {
                echo "Processing {$target}...\n";
                try {
                    $harvest = new VF_Harvest_OAI($target, $settings);
                    $harvest->launch();
                } catch (Exception $e) {
                    echo $e->getMessage() . "\n";
                    return;
                }
                $processed++;
            }
        }

        // All done.
        echo "Completed without errors -- {$processed} source(s) processed.\n";
    }

    /**
     * XSLT Import Tool
     *
     * @return void
     */
    public function importXslAction()
    {
        // Parse switches:
        $this->consoleOpts->addRules(
            array('test-only' => 'Use test mode', 'index-s' => 'Solr index to use')
        );
        $testMode = $this->consoleOpts->getOption('test-only') ? true : false;
        $index = $this->consoleOpts->getOption('index');
        if (empty($index)) {
            $index = 'Solr';
        }

        // Display help message if parameters missing:
        $argv = $this->consoleOpts->getRemainingArgs();
        if (!isset($argv[1])) {
            echo "Usage: import-xsl.php [--test-only] [--index <type>] XML_file "
                . "properties_file\n"
                . "\tXML_file - source file to index\n"
                . "\tproperties_file - import configuration file\n"
                . "If the optional --test-only flag is set, transformed XML will "
                . "be displayed\non screen for debugging purposes, but it will "
                . "not be indexed into VuFind.\n\n"
                . "If the optional --index parameter is set, it must be followed by "
                . "the name of\na class for accessing Solr; it defaults to the "
                . "standard Solr class, but could\nbe overridden with, for example, "
                . "SolrAuth to load authority records.\n\n"
                . "Note: See vudl.properties and ojs.properties for configuration "
                . "examples.\n";
            exit(1);
        }

        // Try to import the document if successful:
        try {
            VF_XSLT_Import::save($argv[0], $argv[1], $index, $testMode);
        } catch (Exception $e) {
            echo "Fatal error: " . $e->getMessage() . "\n";
            exit(1);
        }
        if (!$testMode) {
            echo "Successfully imported {$argv[0]}...\n";
        }
    }
}
