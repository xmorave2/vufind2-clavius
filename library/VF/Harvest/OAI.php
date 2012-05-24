<?php
/**
 * OAI-PMH Harvest Tool
 *
 * PHP version 5
 *
 * Copyright (c) Demian Katz 2010.
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
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/importing_records#oai-pmh_harvesting Wiki
 */

/**
 * VF_Harvest_OAI Class
 *
 * This class harvests records via OAI-PMH using settings from oai.ini.
 *
 * @category VuFind
 * @package  Harvest_Tools
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/importing_records#oai-pmh_harvesting Wiki
 */
class VF_Harvest_OAI
{
    protected $baseURL;               // URL to harvest from
    protected $set = null;            // Target set to harvest (null for all records)
    protected $metadata = 'oai_dc';   // Metadata type to harvest
    protected $idPrefix = '';         // OAI prefix to strip from ID values
    protected $idSearch = array();    // Regular expression searches
    protected $idReplace = array();   // Replacements for regular expression matches
    protected $basePath;              // Directory for storing harvested files
    protected $lastHarvestFile;       // File for tracking last harvest date
    protected $startDate = null;      // Harvest start date (null for all records)
    protected $granularity = 'auto';  // Date granularity
    protected $injectId = false;      // Tag to use for injecting IDs into XML
    protected $injectSetSpec = false; // Tag to use for injecting setSpecs
    protected $injectSetName = false; // Tag to use for injecting set names
    protected $injectDate = false;    // Tag to use for injecting datestamp
    protected $setNames = array();    // Associative array of setSpec => setName
    protected $harvestedIdLog = false;// Filename for logging harvested IDs.
    protected $verbose = false;       // Should we display debug output?

    // As we harvest records, we want to track the most recent date encountered
    // so we can set a start point for the next harvest.
    protected $endDate = 0;

    /**
     * Constructor.
     *
     * @param string $target   Target directory for harvest.
     * @param array  $settings OAI-PMH settings from oai.ini.
     */
    public function __construct($target, $settings)
    {
        // Don't time out during harvest!!
        set_time_limit(0);

        // Set up base directory for harvested files:
        $this->setBasePath($target);

        // Check if there is a file containing a start date:
        $this->lastHarvestFile = $this->basePath . 'last_harvest.txt';
        $this->loadLastHarvestedDate();

        // Set up base URL:
        if (empty($settings['url'])) {
            throw new Exception("Missing base URL for {$target}.");
        }
        $this->baseURL = $settings['url'];
        if (isset($settings['set'])) {
            $this->set = $settings['set'];
        }
        if (isset($settings['metadataPrefix'])) {
            $this->metadata = $settings['metadataPrefix'];
        }
        if (isset($settings['idPrefix'])) {
            $this->idPrefix = $settings['idPrefix'];
        }
        if (isset($settings['idSearch'])) {
            $this->idSearch = $settings['idSearch'];
        }
        if (isset($settings['idReplace'])) {
            $this->idReplace = $settings['idReplace'];
        }
        if (isset($settings['harvestedIdLog'])) {
            $this->harvestedIdLog = $settings['harvestedIdLog'];
        }
        if (isset($settings['injectId'])) {
            $this->injectId = $settings['injectId'];
        }
        if (isset($settings['injectSetSpec'])) {
            $this->injectSetSpec = $settings['injectSetSpec'];
        }
        if (isset($settings['injectSetName'])) {
            $this->injectSetName = $settings['injectSetName'];
            $this->loadSetNames();
        }
        if (isset($settings['injectDate'])) {
            $this->injectDate = $settings['injectDate'];
        }
        if (isset($settings['dateGranularity'])) {
            $this->granularity = $settings['dateGranularity'];
        }
        if (isset($settings['verbose'])) {
            $this->verbose = $settings['verbose'];
        }
        if ($this->granularity == 'auto') {
            $this->loadGranularity();
        }
    }

    /**
     * Set a start date for the harvest (only harvest records AFTER this date).
     *
     * @param string $date Start date (YYYY-MM-DD format).
     *
     * @return void
     */
    public function setStartDate($date)
    {
        $this->startDate = $date;
    }

    /**
     * Harvest all available documents.
     *
     * @return void
     */
    public function launch()
    {
        // Start harvesting at the requested date:
        $token = $this->getRecordsByDate($this->startDate, $this->set);

        // Keep harvesting as long as a resumption token is provided:
        while ($token !== false) {
            $token = $this->getRecordsByToken($token);
        }
    }

    /**
     * Set up directory structure for harvesting (support method for constructor).
     *
     * @param string $target The OAI-PMH target directory to create.
     *
     * @return void
     */
    protected function setBasePath($target)
    {
        // Get the base VuFind path:
        if (strlen(LOCAL_OVERRIDE_DIR) > 0) {
            $home = LOCAL_OVERRIDE_DIR;
        } else {
            $home = realpath(APPLICATION_PATH . '/..');
        }

        // Build the full harvest path:
        $this->basePath = $home . '/harvest/' . $target . '/';

        // Create the directory if it does not already exist:
        if (!is_dir($this->basePath)) {
            if (!mkdir($this->basePath)) {
                throw new Exception("Problem creating directory {$this->basePath}.");
            }
        }
    }

    /**
     * Retrieve the date from the "last harvested" file and use it as our start
     * date if it is available.
     *
     * @return void
     */
    protected function loadLastHarvestedDate()
    {
        if (file_exists($this->lastHarvestFile)) {
            $lines = file($this->lastHarvestFile);
            if (is_array($lines)) {
                $date = trim($lines[0]);
                if (!empty($date)) {
                    $this->setStartDate(trim($date));
                }
            }
        }
    }

    /**
     * Normalize a date to a Unix timestamp.
     *
     * @param string $date Date (ISO-8601 or YYYY-MM-DD HH:MM:SS)
     *
     * @return integer     Unix timestamp (or false if $date invalid)
     */
    protected function normalizeDate($date)
    {
        // Remove timezone markers -- we don't want PHP to outsmart us by adjusting
        // the time zone!
        $date = str_replace(array('T', 'Z'), array(' ', ''), $date);

        // Translate to a timestamp:
        return strtotime($date);
    }

    /**
     * Save a date to the "last harvested" file.
     *
     * @param string $date Date to save.
     *
     * @return void
     */
    protected function saveLastHarvestedDate($date)
    {
        file_put_contents($this->lastHarvestFile, $date);
    }

    /**
     * Make an OAI-PMH request.  Die if there is an error; return a SimpleXML object
     * on success.
     *
     * @param string $verb   OAI-PMH verb to execute.
     * @param array  $params GET parameters for ListRecords method.
     *
     * @return object        SimpleXML-formatted response.
     */
    protected function sendRequest($verb, $params = array())
    {
        // Debug:
        if ($this->verbose) {
            echo "Sending request: verb = {$verb}, params = ";
            print_r($params);
        }

        // Set up retry loop:
        while (true) {
            // Set up the request:
            $request = new VF_Http_Client(
                null, array('timeout' => 60)    // TODO: make timeout configurable
            );
            $request->setUri($this->baseURL);

            // Load request parameters:
            $request->setParameterGet('verb', $verb);
            foreach ($params as $key => $value) {
                $request->setParameterGet($key, $value);
            }

            // Perform request and die on error:
            $result = $request->request('GET');
            if ($result->getStatus() == 503) {
                $delay = $result->getHeader('Retry-After');
                if ($delay > 0) {
                    if ($this->verbose) {
                        echo "Received 503 response; waiting {$delay} seconds...\n";
                    }
                    sleep($delay);
                }
            } else if ($result->isError()) {
                throw new Exception('HTTP Error');
            } else {
                // If we didn't get an error, we can leave the retry loop:
                break;
            }
        }

        // If we got this far, there was no error -- send back response.
        return $this->processResponse($result->getBody());
    }

    /**
     * Process an OAI-PMH response into a SimpleXML object.  Die if an error is
     * detected.
     *
     * @param string $xml OAI-PMH response XML.
     *
     * @return object     SimpleXML-formatted response.
     */
    protected function processResponse($xml)
    {
        // Parse the XML:
        $result = simplexml_load_string($xml);
        if (!$result) {
            throw new Exception("Problem loading XML: {$xml}");
        }

        // Detect errors and die if one is found:
        if ($result->error) {
            $attribs = $result->error->attributes();
            throw new Exception(
                "OAI-PMH error -- code: {$attribs['code']}, " .
                "value: {$result->error}"
            );
        }

        // If we got this far, we have a valid response:
        return $result;
    }

    /**
     * Get the filename for a specific record ID.
     *
     * @param string $id  ID of record to save.
     * @param string $ext File extension to use.
     *
     * @return string     Full path + filename.
     */
    protected function getFilename($id, $ext)
    {
        return $this->basePath . time() . '_' .
            preg_replace('/[^\w]/', '_', $id) . '.' . $ext;
    }

    /**
     * Create a tracking file to record the deletion of a record.
     *
     * @param string $id ID of deleted record.
     *
     * @return void
     */
    protected function saveDeletedRecord($id)
    {
        $filename = $this->getFilename($id, 'delete');
        file_put_contents($filename, $id);
    }

    /**
     * Save a record to disk.
     *
     * @param string $id     ID of record to save.
     * @param object $record Record to save (in SimpleXML format).
     *
     * @return void
     */
    protected function saveRecord($id, $record)
    {
        if (!isset($record->metadata)) {
            throw new Exception("Unexpected missing record metadata.");
        }

        // Extract the actual metadata from inside the <metadata></metadata> tags;
        // there is probably a cleaner way to do this, but this simple method avoids
        // the complexity of dealing with namespaces in SimpleXML:
        $xml = trim($record->metadata->asXML());
        $xml = preg_replace('/(^<metadata>)|(<\/metadata>$)/m', '', $xml);

        // If we are supposed to inject any values, do so now inside the first
        // tag of the file:
        $insert = '';
        if (!empty($this->injectId)) {
            $insert .= "<{$this->injectId}>" . htmlspecialchars($id) .
                "</{$this->injectId}>";
        }
        if (!empty($this->injectDate)) {
            $insert .= "<{$this->injectDate}>" .
                htmlspecialchars((string)$record->header->datestamp) .
                "</{$this->injectDate}>";
        }
        if (!empty($this->injectSetSpec)) {
            if (isset($record->header->setSpec)) {
                foreach ($record->header->setSpec as $current) {
                    $insert .= "<{$this->injectSetSpec}>" .
                        htmlspecialchars((string)$current) .
                        "</{$this->injectSetSpec}>";
                }
            }
        }
        if (!empty($this->injectSetName)) {
            if (isset($record->header->setSpec)) {
                foreach ($record->header->setSpec as $current) {
                    $name = $this->setNames[(string)$current];
                    $insert .= "<{$this->injectSetName}>" .
                        htmlspecialchars($name) .
                        "</{$this->injectSetName}>";
                }
            }
        }
        if (!empty($insert)) {
            $xml = preg_replace('/>/', '>' . $insert, $xml, 1);
        }

        // Save our XML:
        file_put_contents($this->getFilename($id, 'xml'), trim($xml));
    }

    /**
     * Load date granularity from the server.
     *
     * @return void
     */
    protected function loadGranularity()
    {
        echo "Autodetecting date granularity... ";
        $response = $this->sendRequest('Identify');
        $this->granularity = (string)$response->Identify->granularity;
        echo "found {$this->granularity}.\n";
    }

    /**
     * Load set list from the server.
     *
     * @return void
     */
    protected function loadSetNames()
    {
        echo "Loading set list... ";

        // On the first pass through the following loop, we want to get the
        // first page of sets without using a resumption token:
        $params = array();

        // Grab set information until we have it all (at which point we will
        // break out of this otherwise-infinite loop):
        while (true) {
            // Process current page of results:
            $response = $this->sendRequest('ListSets', $params);
            if (isset($response->ListSets->set)) {
                foreach ($response->ListSets->set as $current) {
                    $spec = (string)$current->setSpec;
                    $name = (string)$current->setName;
                    if (!empty($spec)) {
                        $this->setNames[$spec] = $name;
                    }
                }
            }

            // Is there a resumption token?  If so, continue looping; if not,
            // we're done!
            if (isset($response->ListSets->resumptionToken)
                && !empty($response->ListSets->resumptionToken)
            ) {
                $params['resumptionToken']
                    = (string)$response->ListSets->resumptionToken;
            } else {
                echo "found " . count($this->setNames) . "\n";
                return;
            }
        }
    }

    /**
     * Extract the ID from a record object (support method for _processRecords()).
     *
     * @param object $record SimpleXML record.
     *
     * @return string        The ID value.
     */
    protected function extractID($record)
    {
        // Normalize to string:
        $id = (string)$record->header->identifier;

        // Strip prefix if found:
        if (substr($id, 0, strlen($this->idPrefix)) == $this->idPrefix) {
            $id = substr($id, strlen($this->idPrefix));
        }

        // Apply regular expression matching:
        if (!empty($this->idSearch)) {
            $id = preg_replace($this->idSearch, $this->idReplace, $id);
        }

        // Return final value:
        return $id;
    }

    /**
     * Save harvested records to disk and track the end date.
     *
     * @param object $records SimpleXML records.
     *
     * @return void
     */
    protected function processRecords($records)
    {
        echo 'Processing ' . count($records) . " records...\n";

        // Array for tracking successfully harvested IDs:
        $harvestedIds = array();

        // Loop through the records:
        foreach ($records as $record) {
            // Die if the record is missing its header:
            if (empty($record->header)) {
                throw new Exception("Unexpected missing record header.");
            }

            // Get the ID of the current record:
            $id = $this->extractID($record);

            // Save the current record, either as a deleted or as a regular file:
            $attribs = $record->header->attributes();
            if (strtolower($attribs['status']) == 'deleted') {
                $this->saveDeletedRecord($id);
            } else {
                $this->saveRecord($id, $record);
                $harvestedIds[] = $id;
            }

            // If the current record's date is newer than the previous end date,
            // remember it for future reference:
            $date = $this->normalizeDate($record->header->datestamp);
            if ($date && $date > $this->endDate) {
                $this->endDate = $date;
            }
        }

        // Do we have IDs to log and a log filename?  If so, log them:
        if (!empty($this->harvestedIdLog) && !empty($harvestedIds)) {
            $file = fopen($this->basePath . $this->harvestedIdLog, 'a');
            if (!$file) {
                throw new Exception("Problem opening {$this->harvestedIdLog}.");
            }
            fputs($file, implode(PHP_EOL, $harvestedIds));
            fclose($file);
        }
    }

    /**
     * Harvest records using OAI-PMH.
     *
     * @param array $params GET parameters for ListRecords method.
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecords($params)
    {
        // Make the OAI-PMH request:
        $response = $this->sendRequest('ListRecords', $params);

        // Save the records from the response:
        if ($response->ListRecords->record) {
            $this->processRecords($response->ListRecords->record);
        }

        // If we have a resumption token, keep going; otherwise, we're done -- save
        // the end date.
        if (isset($response->ListRecords->resumptionToken)
            && !empty($response->ListRecords->resumptionToken)
        ) {
            return $response->ListRecords->resumptionToken;
        } else if ($this->endDate > 0) {
            $dateFormat = ($this->granularity == 'YYYY-MM-DD') ?
                'Y-m-d' : 'Y-m-d\TH:i:s\Z';
            $this->saveLastHarvestedDate(date($dateFormat, $this->endDate));
        }
        return false;
    }

    /**
     * Harvest records via OAI-PMH using date and set.
     *
     * @param string $date Harvest start date (null for all records).
     * @param string $set  Set to harvest (null for all records).
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecordsByDate($date = null, $set = null)
    {
        $params = array('metadataPrefix' => $this->metadata);
        if (!empty($date)) {
            $params['from'] = $date;
        }
        if (!empty($set)) {
            $params['set'] = $set;
        }
        return $this->getRecords($params);
    }

    /**
     * Harvest records via OAI-PMH using resumption token.
     *
     * @param string $token Resumption token.
     *
     * @return mixed        Resumption token if provided, false if finished
     */
    protected function getRecordsByToken($token)
    {
        return $this->getRecords(array('resumptionToken' => (string)$token));
    }
}
