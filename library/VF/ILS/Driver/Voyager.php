<?php
/**
 * Voyager ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

/**
 * Voyager ILS Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class VF_ILS_Driver_Voyager implements VF_ILS_Driver_Interface
{
    protected $db;
    protected $dbName;
    protected $config;
    protected $statusRankings = false;        // used by pickStatus() method
    protected $dateFormat;

    /**
     * Constructor
     *
     * @param string $configFile The location of an alternative config file
     */
    public function __construct($configFile = false)
    {
        // Load configuration file:
        if (!$configFile) {
            $configFile = 'Voyager.ini';
        }
        $configFilePath = VF_Config_Reader::getConfigPath($configFile);
        if (!file_exists($configFilePath)) {
            throw new VF_Exception_ILS(
                'Cannot access config file - ' . $configFilePath
            );
        }
        $this->config = parse_ini_file($configFilePath, true);

        // Set up object for formatting dates and times:
        $this->dateFormat = new VF_Date_Converter();

        // Define Database Name
        $this->dbName = $this->config['Catalog']['database'];

        // Based on the configuration file, use either "SID" or "SERVICE_NAME"
        // to connect (correct value varies depending on Voyager's Oracle setup):
        $connectType = isset($this->config['Catalog']['connect_with_sid']) &&
            $this->config['Catalog']['connect_with_sid'] ?
            'SID' : 'SERVICE_NAME';

        $tns = '(DESCRIPTION=' .
                 '(ADDRESS_LIST=' .
                   '(ADDRESS=' .
                     '(PROTOCOL=TCP)' .
                     '(HOST=' . $this->config['Catalog']['host'] . ')' .
                     '(PORT=' . $this->config['Catalog']['port'] . ')' .
                   ')' .
                 ')' .
                 '(CONNECT_DATA=' .
                   "({$connectType}={$this->config['Catalog']['service']})" .
                 ')' .
               ')';
        try {
            $this->db = new PDO(
                "oci:dbname=$tns",
                $this->config['Catalog']['user'],
                $this->config['Catalog']['password']
            );
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw $e;
        }
    }

    /**
     * Protected support method for building sql strings.
     *
     * @param array $sql An array of keyed sql data
     *
     * @return array               An string query string and bind data
     */
    protected function buildSqlFromArray($sql)
    {
        $modifier = isset($sql['modifier']) ? $sql['modifier'] . " " : "";

        // Put String Together
        $sqlString = "SELECT ". $modifier . implode(", ", $sql['expressions']);
        $sqlString .= " FROM " .implode(", ", $sql['from']);
        $sqlString .= " WHERE " .implode(" AND ", $sql['where']);
        $sqlString .= (!empty($sql['order']))
            ? " ORDER BY " .implode(", ", $sql['order']) : "";

        return array('string' => $sqlString, 'bind' => $sql['bind']);
    }

    /**
     * Protected support method to pick which status message to display when multiple
     * options are present.
     *
     * @param array $statusArray Array of status messages to choose from.
     *
     * @throws VF_Exception_ILS
     * @return string            The best status message to display.
     */
    protected function pickStatus($statusArray)
    {
        // Pick the first entry by default, then see if we can find a better match:
        $status = $statusArray[0];
        $rank = $this->getStatusRanking($status);
        for ($x = 1; $x < count($statusArray); $x++) {
            if ($this->getStatusRanking($statusArray[$x]) < $rank) {
                $status = $statusArray[$x];
            }
        }

        return $status;
    }

    /**
     * Support method for pickStatus() -- get the ranking value of the specified
     * status message.
     *
     * @param string $status Status message to look up
     *
     * @return int
     */
    protected function getStatusRanking($status)
    {
        // This array controls the rankings of possible status messages.  The lower
        // the ID in the ITEM_STATUS_TYPE table, the higher the priority of the
        // message.  We only need to load it once -- after that, it's cached in the
        // driver.
        if ($this->statusRankings == false) {
            // Execute SQL
            $sql = "SELECT * FROM $this->dbName.ITEM_STATUS_TYPE";
            try {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute();
            } catch (PDOException $e) {
                throw new VF_Exception_ILS($e->getMessage());
            }

            // Read results
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->statusRankings[$row['ITEM_STATUS_DESC']]
                    = $row['ITEM_STATUS_TYPE'];
            }
        }

        // We may occasionally get a status message not found in the array (i.e. the
        // "No information available" message that we hard-code when items are
        // missing); return a large number in this case to avoid an undefined index
        // error and to allow recognized statuses to take precedence.
        return isset($this->statusRankings[$status])
            ? $this->statusRankings[$status] : 32000;
    }

    /**
     * Protected support method to take an array of status strings and determine
     * whether or not this indicates an available item.  Returns an array with
     * two keys: 'available', the boolean availability status, and 'otherStatuses',
     * every status code found other than "Not Charged" - for use with
     * pickStatus().
     *
     * @param array $statusArray The status codes to analyze.
     *
     * @return array             Availability and other status information.
     */
    protected function determineAvailability($statusArray)
    {
        // It's possible for a record to have multiple status codes.  We
        // need to loop through in search of the "Not Charged" (i.e. on
        // shelf) status, collecting any other statuses we find along the
        // way...
        $notCharged = false;
        $otherStatuses = array();
        foreach ($statusArray as $status) {
            switch ($status) {
            case 'Not Charged':
                $notCharged = true;
                break;
            default:
                $otherStatuses[] = $status;
                break;
            }
        }

        // If we found other statuses or if we failed to find "Not Charged,"
        // the item is not available!
        $available = (count($otherStatuses) == 0 && $notCharged);

        return array('available' => $available, 'otherStatuses' => $otherStatuses);
    }

    /**
     * Protected support method for getStatus -- get components required for standard
     * status lookup SQL.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getStatusSQL($id)
    {
        // Expressions
        $sqlExpressions = array(
            "BIB_ITEM.BIB_ID", "ITEM.ITEM_ID",
            "ITEM.ON_RESERVE", "ITEM_STATUS_DESC as status",
            "NVL(LOCATION.LOCATION_DISPLAY_NAME, " .
                "LOCATION.LOCATION_NAME) as location",
            "MFHD_MASTER.DISPLAY_CALL_NO as callnumber",
            "ITEM.TEMP_LOCATION"
        );

        // From
        $sqlFrom = array(
            $this->dbName.".BIB_ITEM", $this->dbName.".ITEM",
            $this->dbName.".ITEM_STATUS_TYPE",
            $this->dbName.".ITEM_STATUS",
            $this->dbName.".LOCATION", $this->dbName.".MFHD_ITEM",
            $this->dbName.".MFHD_MASTER"
        );

        // Where
        $sqlWhere = array(
            "BIB_ITEM.BIB_ID = :id",
            "BIB_ITEM.ITEM_ID = ITEM.ITEM_ID",
            "ITEM.ITEM_ID = ITEM_STATUS.ITEM_ID",
            "ITEM_STATUS.ITEM_STATUS = ITEM_STATUS_TYPE.ITEM_STATUS_TYPE",
            "LOCATION.LOCATION_ID = ITEM.PERM_LOCATION",
            "MFHD_ITEM.ITEM_ID = ITEM.ITEM_ID",
            "MFHD_MASTER.MFHD_ID = MFHD_ITEM.MFHD_ID",
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'"
        );

        // Bind
        $sqlBind = array(':id' => $id);

        $sqlArray = array(
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind,
        );

        return $sqlArray;
    }

    /**
     * Protected support method for getStatus -- get components for status lookup
     * SQL to use when a bib record has no items.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getStatusNoItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = array("BIB_MFHD.BIB_ID",
                                "1 as ITEM_ID", "'N' as ON_RESERVE",
                                "'No information available' as status",
                                "NVL(LOCATION.LOCATION_DISPLAY_NAME, " .
                                    "LOCATION.LOCATION_NAME) as location",
                                "MFHD_MASTER.DISPLAY_CALL_NO as callnumber",
                                "0 AS TEMP_LOCATION"
                               );

        // From
        $sqlFrom = array($this->dbName.".BIB_MFHD", $this->dbName.".LOCATION",
                         $this->dbName.".MFHD_MASTER"
                        );

        // Where
        $sqlWhere = array("BIB_MFHD.BIB_ID = :id",
                          "LOCATION.LOCATION_ID = MFHD_MASTER.LOCATION_ID",
                          "MFHD_MASTER.MFHD_ID = BIB_MFHD.MFHD_ID",
                          "MFHD_MASTER.SUPPRESS_IN_OPAC='N'"
                         );

        // Bind
        $sqlBind = array(':id' => $id);

        $sqlArray = array('expressions' => $sqlExpressions,
                          'from' => $sqlFrom,
                          'where' => $sqlWhere,
                          'bind' => $sqlBind,
                          );

        return $sqlArray;
    }

    /**
     * Protected support method for getStatus -- process rows returned by SQL
     * lookup.
     *
     * @param array $sqlRows Sql Data
     *
     * @return array Keyed data
     */
    protected function getStatusData($sqlRows)
    {
        $data = array();

        foreach ($sqlRows as $row) {
            if (!isset($data[$row['ITEM_ID']])) {
                $data[$row['ITEM_ID']] = array(
                    'id' => $row['BIB_ID'],
                    'status' => $row['STATUS'],
                    'status_array' => array($row['STATUS']),
                    'location' => $row['TEMP_LOCATION'] > 0
                        ? $this->getLocationName($row['TEMP_LOCATION'])
                        : utf8_encode($row['LOCATION']),
                    'reserve' => $row['ON_RESERVE'],
                    'callnumber' => $row['CALLNUMBER']
                );
            } else {
                if (!in_array(
                    $row['STATUS'], $data[$row['ITEM_ID']]['status_array']
                )) {
                    $data[$row['ITEM_ID']]['status_array'][] = $row['STATUS'];
                }
            }
        }
        return $data;
    }

    /**
     * Protected support method for getStatus -- process all details collected by
     * getStatusData().
     *
     * @param array $data SQL Row Data
     *
     * @throws VF_Exception_ILS
     * @return array Keyed data
     */
    protected function processStatusData($data)
    {
        // Process the raw data into final status information:
        $status = array();
        foreach ($data as $current) {
            // Get availability/status info based on the array of status codes:
            $availability = $this->determineAvailability($current['status_array']);

            // If we found other statuses, we should override the display value
            // appropriately:
            if (count($availability['otherStatuses']) > 0) {
                $current['status']
                    = $this->pickStatus($availability['otherStatuses']);
            }
            $current['availability'] = $availability['available'];
            $status[] = $current;
        }

        return $status;
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws VF_Exception_ILS
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        // There are two possible queries we can use to obtain status information.
        // The first (and most common) obtains information from a combination of
        // items and holdings records.  The second (a rare case) obtains
        // information from the holdings record when no items are available.
        $sqlArrayItems = $this->getStatusSQL($id);
        $sqlArrayNoItems = $this->getStatusNoItemsSQL($id);
        $possibleQueries = array(
            $this->buildSqlFromArray($sqlArrayItems),
            $this->buildSqlFromArray($sqlArrayNoItems)
        );

        // Loop through the possible queries and try each in turn -- the first one
        // that yields results will cause us to break out of the loop.
        foreach ($possibleQueries as $sql) {
            // Execute SQL
            try {
                $sqlStmt = $this->db->prepare($sql['string']);
                $sqlStmt->execute($sql['bind']);
            } catch (PDOException $e) {
                throw new VF_Exception_ILS($e->getMessage());
            }

            $sqlRows = array();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $sqlRows[] = $row;
            }

            $data = $this->getStatusData($sqlRows);

            // If we found data, we can leave the foreach loop -- we don't need to
            // try any more queries.
            if (count($data) > 0) {
                break;
            }
        }
        return $this->processStatusData($data);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws VF_Exception_ILS
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        $status = array();
        foreach ($idList as $id) {
            $status[] = $this->getStatus($id);
        }
        return $status;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = array(
            "BIB_ITEM.BIB_ID",
            "ITEM_BARCODE.ITEM_BARCODE", "ITEM.ITEM_ID",
            "ITEM.ON_RESERVE", "ITEM.ITEM_SEQUENCE_NUMBER",
            "ITEM.RECALLS_PLACED", "ITEM.HOLDS_PLACED",
            "ITEM_STATUS_TYPE.ITEM_STATUS_DESC as status",
            "MFHD_DATA.RECORD_SEGMENT", "MFHD_ITEM.ITEM_ENUM",
            "NVL(LOCATION.LOCATION_DISPLAY_NAME, " .
                "LOCATION.LOCATION_NAME) as location",
            "ITEM.TEMP_LOCATION",
            "MFHD_MASTER.DISPLAY_CALL_NO as callnumber",
            "to_char(CIRC_TRANSACTIONS.CURRENT_DUE_DATE, 'MM-DD-YY') as duedate",
            "(SELECT TO_CHAR(MAX(CIRC_TRANS_ARCHIVE.DISCHARGE_DATE), " .
            "'MM-DD-YY HH24:MI') FROM $this->dbName.CIRC_TRANS_ARCHIVE " .
            "WHERE CIRC_TRANS_ARCHIVE.ITEM_ID = ITEM.ITEM_ID) RETURNDATE"
        );

        // From
        $sqlFrom = array(
            $this->dbName.".BIB_ITEM", $this->dbName.".ITEM",
            $this->dbName.".ITEM_STATUS_TYPE",
            $this->dbName.".ITEM_STATUS",
            $this->dbName.".LOCATION", $this->dbName.".MFHD_ITEM",
            $this->dbName.".MFHD_MASTER", $this->dbName.".MFHD_DATA",
            $this->dbName.".CIRC_TRANSACTIONS",
            $this->dbName.".ITEM_BARCODE"
        );

        // Where
        $sqlWhere = array(
            "BIB_ITEM.BIB_ID = :id",
            "BIB_ITEM.ITEM_ID = ITEM.ITEM_ID",
            "ITEM.ITEM_ID = ITEM_STATUS.ITEM_ID",
            "ITEM_STATUS.ITEM_STATUS = ITEM_STATUS_TYPE.ITEM_STATUS_TYPE",
            "ITEM_BARCODE.ITEM_ID (+)= ITEM.ITEM_ID",
            "LOCATION.LOCATION_ID = ITEM.PERM_LOCATION",
            "CIRC_TRANSACTIONS.ITEM_ID (+)= ITEM.ITEM_ID",
            "MFHD_ITEM.ITEM_ID = ITEM.ITEM_ID",
            "MFHD_MASTER.MFHD_ID = MFHD_ITEM.MFHD_ID",
            "MFHD_DATA.MFHD_ID = MFHD_ITEM.MFHD_ID",
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'"
        );

        // Order
        $sqlOrder = array(
            "ITEM.ITEM_SEQUENCE_NUMBER", "MFHD_DATA.MFHD_ID", "MFHD_DATA.SEQNUM"
        );

        // Bind
        $sqlBind = array(':id' => $id);

        $sqlArray = array(
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind,
        );

        return $sqlArray;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $id A Bibliographic id
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getHoldingNoItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = array("null as ITEM_BARCODE", "null as ITEM_ID",
                                "MFHD_DATA.RECORD_SEGMENT", "null as ITEM_ENUM",
                                "'N' as ON_RESERVE", "1 as ITEM_SEQUENCE_NUMBER",
                                "'No information available' as status",
                                "NVL(LOCATION.LOCATION_DISPLAY_NAME, " .
                                    "LOCATION.LOCATION_NAME) as location",
                                "MFHD_MASTER.DISPLAY_CALL_NO as callnumber",
                                "BIB_MFHD.BIB_ID", "null as duedate",
                                "0 AS TEMP_LOCATION"
                               );

        // From
        $sqlFrom = array($this->dbName.".BIB_MFHD", $this->dbName.".LOCATION",
                         $this->dbName.".MFHD_MASTER", $this->dbName.".MFHD_DATA"
                        );

        // Where
        $sqlWhere = array("BIB_MFHD.BIB_ID = :id",
                          "LOCATION.LOCATION_ID = MFHD_MASTER.LOCATION_ID",
                          "MFHD_MASTER.MFHD_ID = BIB_MFHD.MFHD_ID",
                          "MFHD_DATA.MFHD_ID = BIB_MFHD.MFHD_ID",
                          "MFHD_MASTER.SUPPRESS_IN_OPAC='N'"
                         );

        // Order
        $sqlOrder = array("MFHD_DATA.MFHD_ID", "MFHD_DATA.SEQNUM");

        // Bind
        $sqlBind = array(':id' => $id);

        $sqlArray = array('expressions' => $sqlExpressions,
                          'from' => $sqlFrom,
                          'where' => $sqlWhere,
                          'order' => $sqlOrder,
                          'bind' => $sqlBind,
                          );

        return $sqlArray;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $sqlRows Sql Data
     *
     * @return array Keyed data
     */
    protected function getHoldingData($sqlRows)
    {
        $data = array();

        foreach ($sqlRows as $row) {
            // Determine Copy Number (always use sequence number; append volume
            // when available)
            $number = $row['ITEM_SEQUENCE_NUMBER'];
            if (isset($row['ITEM_ENUM'])) {
                $number .= ' (' . utf8_encode($row['ITEM_ENUM']) . ')';
            }

            // Concat wrapped rows (MARC data more than 300 bytes gets split
            // into multiple rows)
            if (isset($data[$row['ITEM_ID']][$number])) {
                // We don't want to concatenate the same MARC information to
                // itself over and over due to a record with multiple status
                // codes -- we should only concat wrapped rows for the FIRST
                // status code we encounter!
                $record = & $data[$row['ITEM_ID']][$number];
                if ($record['STATUS_ARRAY'][0] == $row['STATUS']) {
                    $record['RECORD_SEGMENT'] .= $row['RECORD_SEGMENT'];
                }

                // If we've encountered a new status code, we should track it:
                if (!in_array(
                    $row['STATUS'], $record['STATUS_ARRAY']
                )) {
                    $record['STATUS_ARRAY'][] = $row['STATUS'];
                }
            } else {
                // This is the first time we've encountered this row number --
                // initialize the row and start an array of statuses.
                $data[$row['ITEM_ID']][$number] = $row;
                $data[$row['ITEM_ID']][$number]['STATUS_ARRAY']
                    = array($row['STATUS']);
            }
        }
        return $data;
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $recordSegment A Marc Record Segment obtained from an SQL query
     *
     * @return array Keyed data
     */
    protected function processRecordSegment($recordSegment)
    {
        $marcDetails = array();

        try {
            $marc = new File_MARC(
                str_replace(array("\n", "\r"), '', $recordSegment),
                File_MARC::SOURCE_STRING
            );
            if ($record = $marc->next()) {
                // Get Notes
                if ($fields = $record->getFields('852')) {
                    foreach ($fields as $field) {
                        if ($subfields = $field->getSubfields('z')) {
                            foreach ($subfields as $subfield) {
                                // If this is the first time through,
                                // assume a single-line summary
                                if (!isset($marcDetails['notes'])) {
                                    $marcDetails['notes']
                                        = $subfield->getData();
                                } else {
                                    // If we already have a summary
                                    // line, convert it to an array and
                                    // append more data
                                    if (!is_array($marcDetails['notes'])) {
                                        $marcDetails['notes']
                                            = array($marcDetails['notes']);
                                    }
                                    $marcDetails['notes'][]
                                        = $subfield->getData();
                                }
                            }
                        }
                    }
                }

                // Get Summary (may be multiple lines)
                if ($fields = $record->getFields('866')) {
                    foreach ($fields as $field) {
                        if ($subfield = $field->getSubfield('a')) {
                            // If this is the first time through, assume
                            // a single-line summary
                            if (!isset($marcDetails['summary'])) {
                                $marcDetails['summary']
                                    = $subfield->getData();
                                // If we already have a summary line,
                                // convert it to an array and append
                                // more data
                            } else {
                                if (!is_array($marcDetails['summary'])) {
                                    $marcDetails['summary']
                                        = array($marcDetails['summary']);
                                }
                                $marcDetails['summary'][] = $subfield->getData();
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            trigger_error(
                'Poorly Formatted MFHD Record', E_USER_NOTICE
            );
        }
        return $marcDetails;
    }

    /**
     * Look up a location name by ID.
     *
     * @param int $id Location ID to look up
     *
     * @return string
     */
    protected function getLocationName($id)
    {
        static $cache = array();

        // Fill cache if empty:
        if (!isset($cache[$id])) {
            $sql = "SELECT NVL(LOCATION_DISPLAY_NAME, LOCATION_NAME) as location " .
                "FROM {$this->dbName}.LOCATION WHERE LOCATION_ID=:id";
            $bind = array('id' => $id);
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bind);
            $sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $cache[$id] = utf8_encode($sqlRow['LOCATION']);
        }

        return $cache[$id];
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $sqlRow SQL Row Data
     *
     * @return array Keyed data
     */
    protected function processHoldingRow($sqlRow)
    {
        return array(
            'id' => $sqlRow['BIB_ID'],
            'status' => $sqlRow['STATUS'],
            'location' => $sqlRow['TEMP_LOCATION'] > 0
                ? $this->getLocationName($sqlRow['TEMP_LOCATION'])
                : utf8_encode($sqlRow['LOCATION']),
            'reserve' => $sqlRow['ON_RESERVE'],
            'callnumber' => $sqlRow['CALLNUMBER'],
            'barcode' => $sqlRow['ITEM_BARCODE']
        );
    }

    /**
     * Protected support method for getHolding.
     *
     * @param array $data   Item Data
     * @param array $patron Patron Data
     *
     * @throws VF_Exception_Date
     * @throws VF_Exception_ILS
     * @return array Keyed data
     */
    protected function processHoldingData($data, $patron = false)
    {
        $holding = array();

        // Build Holdings Array
        $i = 0;
        foreach ($data as $item) {
            foreach ($item as $number => $row) {
                // Get availability/status info based on the array of status codes:
                $availability = $this->determineAvailability($row['STATUS_ARRAY']);

                // If we found other statuses, we should override the display value
                // appropriately:
                if (count($availability['otherStatuses']) > 0) {
                    $row['STATUS']
                        = $this->pickStatus($availability['otherStatuses']);
                }

                 // Convert Voyager Format to display format
                $dueDate = false;
                if (!empty($row['DUEDATE'])) {
                    $dueDate = $this->dateFormat->convertToDisplayDate(
                        "m-d-y", $row['DUEDATE']
                    );
                }
                $returnDate = false;
                if (!empty($row['RETURNDATE'])) {
                    $returnDate = $this->dateFormat->convertToDisplayDate(
                        "m-d-y H:i", $row['RETURNDATE']
                    );
                    $returnTime = $this->dateFormat->convertToDisplayTime(
                        "m-d-y H:i", $row['RETURNDATE']
                    );
                    $returnDate .=  " " . $returnTime;
                }

                $returnDate = (in_array("Discharged", $row['STATUS_ARRAY']))
                    ? $returnDate : false;

                $requests_placed = isset($row['HOLDS_PLACED'])
                    ? $row['HOLDS_PLACED'] : 0;
                if (isset($row['RECALLS_PLACED'])) {
                    $requests_placed += $row['RECALLS_PLACED'];
                }

                $holding[$i] = $this->processHoldingRow($row);
                $holding[$i] += array(
                    'availability' => $availability['available'],
                    'duedate' => $dueDate,
                    'number' => $number,
                    'requests_placed' => $requests_placed,
                    'returnDate' => $returnDate
                );

                // Parse Holding Record
                if ($row['RECORD_SEGMENT']) {
                    $marcDetails
                        = $this->processRecordSegment($row['RECORD_SEGMENT']);
                    if (!empty($marcDetails)) {
                        $holding[$i] += $marcDetails;
                    }
                }

                $i++;
            }
        }
        return $holding;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws VF_Exception_Date
     * @throws VF_Exception_ILS
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, $patron = false)
    {
        $possibleQueries = array();

        // There are two possible queries we can use to obtain status information.
        // The first (and most common) obtains information from a combination of
        // items and holdings records.  The second (a rare case) obtains
        // information from the holdings record when no items are available.

        $sqlArrayItems = $this->getHoldingItemsSQL($id);
        $possibleQueries[] = $this->buildSqlFromArray($sqlArrayItems);

        $sqlArrayNoItems = $this->getHoldingNoItemsSQL($id);
        $possibleQueries[] = $this->buildSqlFromArray($sqlArrayNoItems);

        // Loop through the possible queries and try each in turn -- the first one
        // that yields results will cause us to break out of the loop.
        foreach ($possibleQueries as $sql) {
            // Execute SQL
            try {
                $sqlStmt = $this->db->prepare($sql['string']);
                $sqlStmt->execute($sql['bind']);
            } catch (PDOException $e) {
                throw new VF_Exception_ILS($e->getMessage());
            }

            $sqlRows = array();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $sqlRows[] = $row;
            }

            $data = $this->getHoldingData($sqlRows);

            // If we found data, we can leave the foreach loop -- we don't need to
            // try any more queries.
            if (count($data) > 0) {
                break;
            }
        }
        return $this->processHoldingData($data, $patron);
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws VF_Exception_ILS
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        $sql = "select SERIAL_ISSUES.ENUMCHRON " .
               "from $this->dbName.SERIAL_ISSUES, $this->dbName.COMPONENT, ".
               "$this->dbName.ISSUES_RECEIVED, $this->dbName.SUBSCRIPTION, ".
               "$this->dbName.LINE_ITEM " .
               "where SERIAL_ISSUES.COMPONENT_ID = COMPONENT.COMPONENT_ID " .
               "and ISSUES_RECEIVED.ISSUE_ID = SERIAL_ISSUES.ISSUE_ID " .
               "and ISSUES_RECEIVED.COMPONENT_ID = COMPONENT.COMPONENT_ID " .
               "and COMPONENT.SUBSCRIPTION_ID = SUBSCRIPTION.SUBSCRIPTION_ID " .
               "and SUBSCRIPTION.LINE_ITEM_ID = LINE_ITEM.LINE_ITEM_ID " .
               "and SERIAL_ISSUES.RECEIVED > 0 " .
               "and ISSUES_RECEIVED.OPAC_SUPPRESSED = 1 " .
               "and LINE_ITEM.BIB_ID = :id " .
               "order by SERIAL_ISSUES.ISSUE_ID DESC";
        try {
            $data = array();
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':id' => $id));
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = array('issue' => $row['ENUMCHRON']);
            }
            return $data;
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $barcode The patron barcode
     * @param string $login   The patron's last name or PIN (depending on config)
     *
     * @throws VF_Exception_ILS
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($barcode, $login)
    {
        // Load the field used for verifying the login from the config file, and
        // make sure there's nothing crazy in there:
        $login_field = isset($this->config['Catalog']['login_field'])
            ? $this->config['Catalog']['login_field'] : 'LAST_NAME';
        $login_field = preg_replace('/[^\w]/', '', $login_field);

        $sql = "SELECT PATRON.PATRON_ID, PATRON.FIRST_NAME, PATRON.LAST_NAME " .
               "FROM $this->dbName.PATRON, $this->dbName.PATRON_BARCODE " .
               "WHERE PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID AND " .
               "lower(PATRON.{$login_field}) = :login AND " .
               "lower(PATRON_BARCODE.PATRON_BARCODE) = :barcode";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->bindParam(
                ':login', strtolower(utf8_decode($login)), PDO::PARAM_STR
            );
            $sqlStmt->bindParam(
                ':barcode', strtolower(utf8_decode($barcode)), PDO::PARAM_STR
            );
            $sqlStmt->execute();
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            if (isset($row['PATRON_ID']) && ($row['PATRON_ID'] != '')) {
                return array(
                    'id' => utf8_encode($row['PATRON_ID']),
                    'firstname' => utf8_encode($row['FIRST_NAME']),
                    'lastname' => utf8_encode($row['LAST_NAME']),
                    'cat_username' => $barcode,
                    'cat_password' => $login,
                    // There's supposed to be a getPatronEmailAddress stored
                    // procedure in Oracle, but I couldn't get it to work here;
                    // might be worth investigating further if needed later.
                    'email' => null,
                    'major' => null,
                    'college' => null);
            } else {
                return null;
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getMyTransactionsSQL($patron)
    {
        // Expressions
        $sqlExpressions = array(
            "to_char(CIRC_TRANSACTIONS.CURRENT_DUE_DATE, 'MM-DD-YY HH24:MI')" .
            " as DUEDATE",
            "to_char(CURRENT_DUE_DATE, 'YYYYMMDD HH24:MI') as FULLDATE",
            "BIB_ITEM.BIB_ID",
            "CIRC_TRANSACTIONS.ITEM_ID as ITEM_ID",
            "MFHD_ITEM.ITEM_ENUM",
            "MFHD_ITEM.YEAR",
            "BIB_TEXT.TITLE_BRIEF",
            "BIB_TEXT.TITLE"
        );

        // From
        $sqlFrom = array(
            $this->dbName.".CIRC_TRANSACTIONS",
            $this->dbName.".BIB_ITEM",
            $this->dbName.".MFHD_ITEM",
            $this->dbName.".BIB_TEXT"
        );

        // Where
        $sqlWhere = array(
            "CIRC_TRANSACTIONS.PATRON_ID = :id",
            "BIB_ITEM.ITEM_ID = CIRC_TRANSACTIONS.ITEM_ID",
            "CIRC_TRANSACTIONS.ITEM_ID = MFHD_ITEM.ITEM_ID(+)",
            "BIB_TEXT.BIB_ID = BIB_ITEM.BIB_ID"
        );

        // Order
        $sqlOrder = array("FULLDATE ASC");

        // Bind
        $sqlBind = array(':id' => $patron['id']);

        $sqlArray = array(
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind
        );

        return $sqlArray;
    }

    /**
     * Protected support method for getMyTransactions.
     *
     * @param array $sqlRow An array of keyed data
     * @param array $patron An array of keyed patron data
     *
     * @throws VF_Exception_Date
     * @return array Keyed data for display by template files
     */
    protected function processMyTransactionsData($sqlRow, $patron = false)
    {
        // Convert Voyager Format to display format
        if (!empty($sqlRow['DUEDATE'])) {
            $dueDate = $this->dateFormat->convertToDisplayDate(
                "m-d-y H:i", $sqlRow['DUEDATE']
            );
            $dueTime = $this->dateFormat->convertToDisplayTime(
                "m-d-y H:i", $sqlRow['DUEDATE']
            );
        }

        $dueStatus = false;
        if (!empty($sqlRow['FULLDATE'])) {
            $now = time();
            $dueTimeStamp = strtotime($sqlRow['FULLDATE']);
            if (is_numeric($dueTimeStamp)) {
                if ($now > $dueTimeStamp) {
                    $dueStatus = "overdue";
                } else if ($now > $dueTimeStamp-(1*24*60*60)) {
                    $dueStatus = "due";
                }
            }
        }

        return array(
            'id' => $sqlRow['BIB_ID'],
            'item_id' => $sqlRow['ITEM_ID'],
            'duedate' => $dueDate,
            'dueTime' => $dueTime,
            'dueStatus' => $dueStatus,
            'volume' => str_replace("v.", "", utf8_encode($sqlRow['ITEM_ENUM'])),
            'publication_year' => $sqlRow['YEAR'],
            'title' => empty($sqlRow['TITLE_BRIEF'])
                ? $sqlRow['TITLE'] : $sqlRow['TITLE_BRIEF']
        );
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws VF_Exception_Date
     * @throws VF_Exception_ILS
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($patron)
    {
        $transList = array();

        $sqlArray = $this->getMyTransactionsSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->db->prepare($sql['string']);
            $sqlStmt->execute($sql['bind']);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $processRow = $this->processMyTransactionsData($row, $patron);
                $transList[] = $processRow;
            }
            return $transList;
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Protected support method for getMyFines.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getFineSQL($patron)
    {
        // Modifier
        $sqlSelectModifier = "distinct";

        // Expressions
        $sqlExpressions = array(
            "FINE_FEE_TYPE.FINE_FEE_DESC",
            "PATRON.PATRON_ID", "FINE_FEE.FINE_FEE_AMOUNT",
            "FINE_FEE.FINE_FEE_BALANCE",
            "to_char(FINE_FEE.CREATE_DATE, 'MM-DD-YY') as CREATEDATE",
            "to_char(FINE_FEE.ORIG_CHARGE_DATE, 'MM-DD-YY') as CHARGEDATE",
            "to_char(FINE_FEE.DUE_DATE, 'MM-DD-YY') as DUEDATE",
            "BIB_ITEM.BIB_ID"
        );

        // From
        $sqlFrom = array(
            $this->dbName.".FINE_FEE", $this->dbName.".FINE_FEE_TYPE",
            $this->dbName.".PATRON", $this->dbName.".BIB_ITEM"
        );

        // Where
        $sqlWhere = array(
            "PATRON.PATRON_ID = :id",
            "FINE_FEE.FINE_FEE_TYPE = FINE_FEE_TYPE.FINE_FEE_TYPE",
            "FINE_FEE.PATRON_ID  = PATRON.PATRON_ID",
            "FINE_FEE.ITEM_ID = BIB_ITEM.ITEM_ID(+)",
            "FINE_FEE.FINE_FEE_BALANCE > 0"
        );

        // Bind
        $sqlBind = array(':id' => $patron['id']);

        $sqlArray = array(
            'modifier' => $sqlSelectModifier,
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind
        );

        return $sqlArray;
    }

    /**
     * Protected support method for getMyFines.
     *
     * @param array $sqlRow An array of keyed data
     *
     * @throws VF_Exception_Date
     * @return array Keyed data for display by template files
     */
    protected function processFinesData($sqlRow)
    {
        $dueDate = VF_Translator::translate("not_applicable");
        // Convert Voyager Format to display format
        if (!empty($sqlRow['DUEDATE'])) {
            $dueDate = $this->dateFormat->convertToDisplayDate(
                "m-d-y", $sqlRow['DUEDATE']
            );
        }

        $createDate = VF_Translator::translate("not_applicable");
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CREATEDATE'])) {
            $createDate = $this->dateFormat->convertToDisplayDate(
                "m-d-y", $sqlRow['CREATEDATE']
            );
        }

        $chargeDate = VF_Translator::translate("not_applicable");
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CHARGEDATE'])) {
            $chargeDate = $this->dateFormat->convertToDisplayDate(
                "m-d-y", $sqlRow['CHARGEDATE']
            );
        }

        return array('amount' => $sqlRow['FINE_FEE_AMOUNT'],
              'fine' => $sqlRow['FINE_FEE_DESC'],
              'balance' => $sqlRow['FINE_FEE_BALANCE'],
              'createdate' => $createDate,
              'checkout' => $chargeDate,
              'duedate' => $dueDate,
              'id' => $sqlRow['BIB_ID']);
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws VF_Exception_Date
     * @throws VF_Exception_ILS
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $fineList = array();

        $sqlArray = $this->getFineSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->db->prepare($sql['string']);
            $sqlStmt->execute($sql['bind']);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $processFine= $this->processFinesData($row);
                $fineList[] = $processFine;
            }
            return $fineList;
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Protected support method for getMyHolds.
     *
     * @param array $patron Patron data for use in an sql query
     *
     * @return array Keyed data for use in an sql query
     */
    protected function getMyHoldsSQL($patron)
    {
        // Modifier
        $sqlSelectModifier = "distinct";

        // Expressions
        $sqlExpressions = array(
            "HOLD_RECALL.HOLD_RECALL_ID", "HOLD_RECALL.BIB_ID",
            "HOLD_RECALL.PICKUP_LOCATION",
            "HOLD_RECALL.HOLD_RECALL_TYPE",
            "to_char(HOLD_RECALL.EXPIRE_DATE, 'MM-DD-YY') as EXPIRE_DATE",
            "to_char(HOLD_RECALL.CREATE_DATE, 'MM-DD-YY') as CREATE_DATE",
            "HOLD_RECALL_ITEMS.ITEM_ID",
            "HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS",
            "HOLD_RECALL_ITEMS.QUEUE_POSITION",
            "MFHD_ITEM.ITEM_ENUM",
            "MFHD_ITEM.YEAR",
            "BIB_TEXT.TITLE_BRIEF",
            "BIB_TEXT.TITLE"
        );

        // From
        $sqlFrom = array(
            $this->dbName.".HOLD_RECALL",
            $this->dbName.".HOLD_RECALL_ITEMS",
            $this->dbName.".MFHD_ITEM",
            $this->dbName.".BIB_TEXT"
        );

        // Where
        $sqlWhere = array(
            "HOLD_RECALL.PATRON_ID = :id",
            "HOLD_RECALL.HOLD_RECALL_ID = HOLD_RECALL_ITEMS.HOLD_RECALL_ID(+)",
            "HOLD_RECALL_ITEMS.ITEM_ID = MFHD_ITEM.ITEM_ID(+)",
            "(HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS IS NULL OR " .
            "HOLD_RECALL_ITEMS.HOLD_RECALL_STATUS < 3)",
            "BIB_TEXT.BIB_ID = HOLD_RECALL.BIB_ID"
        );

        // Bind
        $sqlBind = array(':id' => $patron['id']);

        $sqlArray = array(
            'modifier' => $sqlSelectModifier,
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'bind' => $sqlBind
        );

        return $sqlArray;
    }

    /**
     * Protected support method for getMyHolds.
     *
     * @param array $sqlRow An array of keyed data
     *
     * @throws VF_Exception_Date
     * @return array Keyed data for display by template files
     */
    protected function processMyHoldsData($sqlRow)
    {
        $available = ($sqlRow['HOLD_RECALL_STATUS'] == 2) ? true : false;
        $expireDate = VF_Translator::translate("Unknown");
        // Convert Voyager Format to display format
        if (!empty($sqlRow['EXPIRE_DATE'])) {
            $expireDate = $this->dateFormat->convertToDisplayDate(
                "m-d-y", $sqlRow['EXPIRE_DATE']
            );
        }

        $createDate = VF_Translator::translate("Unknown");
        // Convert Voyager Format to display format
        if (!empty($sqlRow['CREATE_DATE'])) {
            $createDate = $this->dateFormat->convertToDisplayDate(
                "m-d-y", $sqlRow['CREATE_DATE']
            );
        }

        return array(
            'id' => $sqlRow['BIB_ID'],
            'type' => $sqlRow['HOLD_RECALL_TYPE'],
            'location' => $sqlRow['PICKUP_LOCATION'],
            'expire' => $expireDate,
            'create' => $createDate,
            'position' => $sqlRow['QUEUE_POSITION'],
            'available' => $available,
            'reqnum' => $sqlRow['HOLD_RECALL_ID'],
            'item_id' => $sqlRow['ITEM_ID'],
            'volume' => str_replace("v.", "", utf8_encode($sqlRow['ITEM_ENUM'])),
            'publication_year' => $sqlRow['YEAR'],
            'title' => empty($sqlRow['TITLE_BRIEF'])
                ? $sqlRow['TITLE'] : $sqlRow['TITLE_BRIEF']
        );
    }

    /**
     * Process Holds List
     *
     * This is responsible for processing holds to ensure only one record is shown
     * for each hold.
     *
     * @param array $holdList The Hold List Array
     *
     * @return mixed Array of the patron's holds.
     */
    protected function processHoldsList($holdList)
    {
        $returnList = array();

        if (!empty($holdList)) {

            $sortHoldList = array();
            // Get a unique List of Bib Ids
            foreach ($holdList as $holdItem) {
                $sortHoldList[$holdItem['id']][] = $holdItem;
            }

            // Use the first copy hold only
            foreach ($sortHoldList as $bibHold) {
                $returnList[] = $bibHold[0];
            }
        }
        return $returnList;
    }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws VF_Exception_Date
     * @throws VF_Exception_ILS
     * @return array        Array of the patron's holds on success.
     */
    public function getMyHolds($patron)
    {
        $holdList = array();
        $returnList = array();

        $sqlArray = $this->getMyHoldsSQL($patron);

        $sql = $this->buildSqlFromArray($sqlArray);

        try {
            $sqlStmt = $this->db->prepare($sql['string']);
            $sqlStmt->execute($sql['bind']);
            while ($sqlRow = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $holds = $this->processMyHoldsData($sqlRow);
                $holdList[] = $holds;
            }
            $returnList = $this->processHoldsList($holdList);
            return $returnList;
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws VF_Exception_ILS
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $sql = "SELECT PATRON.LAST_NAME, PATRON.FIRST_NAME, " .
               "PATRON.HISTORICAL_CHARGES, PATRON_ADDRESS.ADDRESS_LINE1, " .
               "PATRON_ADDRESS.ADDRESS_LINE2, PATRON_ADDRESS.ZIP_POSTAL, ".
               "PATRON_PHONE.PHONE_NUMBER, PATRON_GROUP.PATRON_GROUP_NAME " .
               "FROM $this->dbName.PATRON, $this->dbName.PATRON_ADDRESS, ".
               "$this->dbName.PATRON_PHONE, $this->dbName.PATRON_BARCODE, " .
               "$this->dbName.PATRON_GROUP " .
               "WHERE PATRON.PATRON_ID = PATRON_ADDRESS.PATRON_ID (+) " .
               "AND PATRON_ADDRESS.ADDRESS_ID = PATRON_PHONE.ADDRESS_ID (+) " .
               "AND PATRON.PATRON_ID = PATRON_BARCODE.PATRON_ID (+) " .
               "AND PATRON_BARCODE.PATRON_GROUP_ID = " .
               "PATRON_GROUP.PATRON_GROUP_ID (+) " .
               "AND PATRON.PATRON_ID = :id";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':id' => $patron['id']));
            $patron = array();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['FIRST_NAME'])) {
                    $patron['firstname'] = utf8_encode($row['FIRST_NAME']);
                }
                if (!empty($row['LAST_NAME'])) {
                    $patron['lastname'] = utf8_encode($row['LAST_NAME']);
                }
                if (!empty($row['PHONE_NUMBER'])) {
                    $patron['phone'] = utf8_encode($row['PHONE_NUMBER']);
                }
                if (!empty($row['PATRON_GROUP_NAME'])) {
                    $patron['group'] = utf8_encode($row['PATRON_GROUP_NAME']);
                }
                $validator = new Zend_Validate_EmailAddress();
                $addr1 = utf8_encode($row['ADDRESS_LINE1']);
                if ($validator->isValid($addr1)) {
                    $patron['email'] = $addr1;
                } else if (!isset($patron['address1'])) {
                    if (!empty($addr1)) {
                        $patron['address1'] = $addr1;
                    }
                    if (!empty($row['ADDRESS_LINE2'])) {
                        $patron['address2'] = utf8_encode($row['ADDRESS_LINE2']);
                    }
                    if (!empty($row['ZIP_POSTAL'])) {
                        $patron['zip'] = utf8_encode($row['ZIP_POSTAL']);
                    }
                }
            }
            return (empty($patron) ? null : $patron);
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Get Hold Link
     *
     * The goal for this method is to return a URL to a "place hold" web page on
     * the ILS OPAC. This is used for ILSs that do not support an API or method
     * to place Holds.
     *
     * @param string $recordId The id of the bib record
     * @param array  $details  Item details from getHoldings return array
     *
     * @return string          URL to ILS's OPAC's place hold screen.
     */
    public function getHoldLink($recordId, $details)
    {
        // There is no easy way to link directly to hold screen; let's just use
        // the record view.  For better hold behavior, use the VoyagerRestful
        // driver.
        return $this->config['Catalog']['pwebrecon'] . '?BBID=' . $recordId;
    }

    /**
     * Get New Items
     *
     * Retrieve the IDs of items recently added to the catalog.
     *
     * @param int $page    Page number of results to retrieve (counting starts at 1)
     * @param int $limit   The size of each page of results to retrieve
     * @param int $daysOld The maximum age of records to retrieve in days (max. 30)
     * @param int $fundId  optional fund ID to use for limiting results (use a value
     * returned by getFunds, or exclude for no limit); note that "fund" may be a
     * misnomer - if funds are not an appropriate way to limit your new item
     * results, you can return a different set of values from getFunds. The
     * important thing is that this parameter supports an ID returned by getFunds,
     * whatever that may mean.
     *
     * @throws VF_Exception_ILS
     * @return array       Associative array with 'count' and 'results' keys
     */
    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $items = array();

        // Prevent unnecessary load on Voyager -- no point in exceeding the maximum
        // configured date range.
        $maxAge = 30;
        $searchSettings = VF_Config_Reader::getConfig('searches');
        if (isset($searchSettings->NewItem->ranges)) {
            $tmp = explode(',', $searchSettings->NewItem->ranges);
            foreach ($tmp as $current) {
                if (intval($current) > $maxAge) {
                    $maxAge = intval($current);
                }
            }
        }
        if ($daysOld > $maxAge) {
            $daysOld = $maxAge;
        }

        $bindParams = array(
            ':enddate' => date('d-m-Y', strtotime('now')),
            ':startdate' => date('d-m-Y', strtotime("-$daysOld day"))
        );

        $sql = "select count(distinct LINE_ITEM.BIB_ID) as count " .
               "from $this->dbName.LINE_ITEM, " .
               "$this->dbName.LINE_ITEM_COPY_STATUS, " .
               "$this->dbName.LINE_ITEM_FUNDS, $this->dbName.FUND " .
               "where LINE_ITEM.LINE_ITEM_ID = LINE_ITEM_COPY_STATUS.LINE_ITEM_ID " .
               "and LINE_ITEM_COPY_STATUS.COPY_ID = LINE_ITEM_FUNDS.COPY_ID " .
               "and LINE_ITEM_FUNDS.FUND_ID = FUND.FUND_ID ";
        if ($fundId) {
            // Although we're getting an ID value from getFunds() passed in here,
            // it's not actually an ID -- we use names as IDs (see note in getFunds
            // itself for more details).
            $sql .= "and lower(FUND.FUND_NAME) = :fund ";
            $bindParams[':fund'] = strtolower($fundId);
        }
        $sql .= "and LINE_ITEM.CREATE_DATE >= to_date(:startdate, 'dd-mm-yyyy') " .
               "and LINE_ITEM.CREATE_DATE < to_date(:enddate, 'dd-mm-yyyy')";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bindParams);
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['COUNT'];
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        $page = ($page) ? $page : 1;
        $limit = ($limit) ? $limit : 20;
        $bindParams[':startRow'] = (($page-1)*$limit)+1;
        $bindParams[':endRow'] = ($page*$limit);
        /*
        $sql = "select * from " .
               "(select a.*, rownum rnum from " .
               "(select LINE_ITEM.BIB_ID, BIB_TEXT.TITLE, FUND.FUND_NAME, " .
               "LINE_ITEM.CREATE_DATE, LINE_ITEM_STATUS.LINE_ITEM_STATUS_DESC " .
               "from $this->dbName.BIB_TEXT, $this->dbName.LINE_ITEM, " .
               "$this->dbName.LINE_ITEM_COPY_STATUS, " .
               "$this->dbName.LINE_ITEM_STATUS, $this->dbName.LINE_ITEM_FUNDS, " .
               "$this->dbName.FUND " .
               "where BIB_TEXT.BIB_ID = LINE_ITEM.BIB_ID " .
               "and LINE_ITEM.LINE_ITEM_ID = LINE_ITEM_COPY_STATUS.LINE_ITEM_ID " .
               "and LINE_ITEM_COPY_STATUS.COPY_ID = LINE_ITEM_FUNDS.COPY_ID " .
               "and LINE_ITEM_STATUS.LINE_ITEM_STATUS = " .
               "LINE_ITEM_COPY_STATUS.LINE_ITEM_STATUS " .
               "and LINE_ITEM_FUNDS.FUND_ID = FUND.FUND_ID ";
        */
        $sql = "select * from " .
               "(select a.*, rownum rnum from " .
               "(select LINE_ITEM.BIB_ID, LINE_ITEM.CREATE_DATE " .
               "from $this->dbName.LINE_ITEM, " .
               "$this->dbName.LINE_ITEM_COPY_STATUS, " .
               "$this->dbName.LINE_ITEM_STATUS, $this->dbName.LINE_ITEM_FUNDS, " .
               "$this->dbName.FUND " .
               "where LINE_ITEM.LINE_ITEM_ID = LINE_ITEM_COPY_STATUS.LINE_ITEM_ID " .
               "and LINE_ITEM_COPY_STATUS.COPY_ID = LINE_ITEM_FUNDS.COPY_ID " .
               "and LINE_ITEM_STATUS.LINE_ITEM_STATUS = " .
               "LINE_ITEM_COPY_STATUS.LINE_ITEM_STATUS " .
               "and LINE_ITEM_FUNDS.FUND_ID = FUND.FUND_ID ";
        if ($fundId) {
            $sql .= "and lower(FUND.FUND_NAME) = :fund ";
        }
        $sql .= "and LINE_ITEM.CREATE_DATE >= to_date(:startdate, 'dd-mm-yyyy') " .
               "and LINE_ITEM.CREATE_DATE < to_date(:enddate, 'dd-mm-yyyy') " .
               "group by LINE_ITEM.BIB_ID, LINE_ITEM.CREATE_DATE " .
               "order by LINE_ITEM.CREATE_DATE desc) a " .
               "where rownum <= :endRow) " .
               "where rnum >= :startRow";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['BIB_ID'];
            }
            return $items;
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }
    }

    /**
     * Get Funds
     *
     * Return a list of funds which may be used to limit the getNewItems list.
     *
     * @throws VF_Exception_ILS
     * @return array An associative array with key = fund ID, value = fund name.
     */
    public function getFunds()
    {
        $list = array();

        // Are funds disabled?  If so, do no work!
        if (isset($this->config['Funds']['disabled'])
            && $this->config['Funds']['disabled']
        ) {
            return $list;
        }

        // Load and normalize whitelist and blacklist if necessary:
        if (isset($this->config['Funds']['whitelist'])
            && is_array($this->config['Funds']['whitelist'])
        ) {
            $whitelist = array();
            foreach ($this->config['Funds']['whitelist'] as $current) {
                $whitelist[] = strtolower($current);
            }
        } else {
            $whitelist = false;
        }
        if (isset($this->config['Funds']['blacklist'])
            && is_array($this->config['Funds']['blacklist'])
        ) {
            $blacklist = array();
            foreach ($this->config['Funds']['blacklist'] as $current) {
                $blacklist[] = strtolower($current);
            }
        } else {
            $blacklist = false;
        }

        // Retrieve the data from Voyager; if we're limiting to a parent fund, we
        // need to apply a special WHERE clause and bind parameter.
        if (isset($this->config['Funds']['parent_fund'])) {
            $bindParams = array(':parent' => $this->config['Funds']['parent_fund']);
            $whereClause = 'WHERE FUND.PARENT_FUND = :parent';
        } else {
            $bindParams = array();
            $whereClause = '';
        }
        $sql = "select distinct lower(FUND.FUND_NAME) as name " .
            "from $this->dbName.FUND {$whereClause} order by name";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                // Process blacklist and whitelist to skip illegal values:
                if ((is_array($blacklist) && in_array($row['NAME'], $blacklist))
                    || (is_array($whitelist) && !in_array($row['NAME'], $whitelist))
                ) {
                    continue;
                }

                // Normalize the capitalization of the name:
                $name = ucwords($row['NAME']);

                // Set the array key to the lookup ID used by getNewItems and the
                // array value to the on-screen display name.
                //
                // We actually want to use the NAME of the fund to do lookups, not
                // its ID.  This is because multiple funds may share the same name,
                // and it is useful to collate all these results together.  To
                // achieve the effect, we just fill the same value in as the name
                // and the ID in the return array.
                //
                // If you want to change this code to use numeric IDs instead,
                // you can adjust the SQL above, change the array key used in the
                // line below, and adjust the lookups done in getNewItems().
                $list[$name] = $name;
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        return $list;
    }

    /**
     * Get Departments
     *
     * Obtain a list of departments for use in limiting the reserves list.
     *
     * @throws VF_Exception_ILS
     * @return array An associative array with key = dept. ID, value = dept. name.
     */
    public function getDepartments()
    {
        $deptList = array();

        $sql = "select DEPARTMENT.DEPARTMENT_ID, DEPARTMENT.DEPARTMENT_NAME " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.DEPARTMENT " .
               "where " .
               "RESERVE_LIST.RESERVE_LIST_ID = " .
               "RESERVE_LIST_COURSES.RESERVE_LIST_ID and " .
               "RESERVE_LIST_COURSES.DEPARTMENT_ID = DEPARTMENT.DEPARTMENT_ID " .
               "group by DEPARTMENT.DEPARTMENT_ID, DEPARTMENT_NAME " .
               "order by DEPARTMENT_NAME";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $deptList[$row['DEPARTMENT_ID']] = $row['DEPARTMENT_NAME'];
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        return $deptList;
    }

    /**
     * Get Instructors
     *
     * Obtain a list of instructors for use in limiting the reserves list.
     *
     * @throws VF_Exception_ILS
     * @return array An associative array with key = ID, value = name.
     */
    public function getInstructors()
    {
        $instList = array();

        $bindParams = array();

        $sql = "select INSTRUCTOR.INSTRUCTOR_ID, " .
               "INSTRUCTOR.LAST_NAME || ', ' || INSTRUCTOR.FIRST_NAME as NAME " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.INSTRUCTOR " .
               "where RESERVE_LIST.RESERVE_LIST_ID = " .
               "RESERVE_LIST_COURSES.RESERVE_LIST_ID and " .
               "RESERVE_LIST_COURSES.INSTRUCTOR_ID = INSTRUCTOR.INSTRUCTOR_ID " .
               "group by INSTRUCTOR.INSTRUCTOR_ID, LAST_NAME, FIRST_NAME " .
               "order by LAST_NAME";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $instList[$row['INSTRUCTOR_ID']] = $row['NAME'];
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        return $instList;
    }

    /**
     * Get Courses
     *
     * Obtain a list of courses for use in limiting the reserves list.
     *
     * @throws VF_Exception_ILS
     * @return array An associative array with key = ID, value = name.
     */
    public function getCourses()
    {
        $courseList = array();

        $bindParams = array();

        $sql = "select COURSE.COURSE_NUMBER || ': ' || COURSE.COURSE_NAME as NAME," .
               " COURSE.COURSE_ID " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.COURSE " .
               "where RESERVE_LIST.RESERVE_LIST_ID = " .
               "RESERVE_LIST_COURSES.RESERVE_LIST_ID and " .
               "RESERVE_LIST_COURSES.COURSE_ID = COURSE.COURSE_ID " .
               "group by COURSE.COURSE_ID, COURSE_NUMBER, COURSE_NAME " .
               "order by COURSE_NUMBER";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $courseList[$row['COURSE_ID']] = $row['NAME'];
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        return $courseList;
    }

    /**
     * Find Reserves
     *
     * Obtain information on course reserves.
     *
     * This version of findReserves was contributed by Matthew Hooper and includes
     * support for electronic reserves (though eReserve support is still a work in
     * progress).
     *
     * @param string $course ID from getCourses (empty string to match all)
     * @param string $inst   ID from getInstructors (empty string to match all)
     * @param string $dept   ID from getDepartments (empty string to match all)
     *
     * @throws VF_Exception_ILS
     * @return array An array of associative arrays representing reserve items.
     */
    public function findReserves($course, $inst, $dept)
    {
        $recordList = array();

        $reserveWhere = array();
        $bindParams = array();

        if ($course != '') {
            $reserveWhere[] = "RESERVE_LIST_COURSES.COURSE_ID = :course" ;
            $bindParams[':course'] = $course;
        }
        if ($inst != '') {
            $reserveWhere[] = "RESERVE_LIST_COURSES.INSTRUCTOR_ID = :inst" ;
            $bindParams[':inst'] = $inst;
        }
        if ($dept != '') {
            $reserveWhere[] = "RESERVE_LIST_COURSES.DEPARTMENT_ID = :dept" ;
            $bindParams[':dept'] = $dept;
        }

        $reserveWhere = empty($reserveWhere) ?
            "" : "where (" . implode(' AND ', $reserveWhere) . ")";

        /* OLD SQL -- simpler but without support for the Solr-based reserves
         * module:
        $sql = " select MFHD_MASTER.DISPLAY_CALL_NO, BIB_TEXT.BIB_ID, " .
               " BIB_TEXT.AUTHOR, BIB_TEXT.TITLE, " .
               " BIB_TEXT.PUBLISHER, BIB_TEXT.PUBLISHER_DATE " .
               " FROM $this->dbName.BIB_TEXT, $this->dbName.MFHD_MASTER where " .
               " bib_text.bib_id = (select bib_mfhd.bib_id " .
               " from $this->dbName.bib_mfhd " .
               " where bib_mfhd.mfhd_id = mfhd_master.mfhd_id) " .
               " and " .
               "  mfhd_master.mfhd_id in ( ".
               "  ((select distinct eitem.mfhd_id from $this->dbName.eitem where " .
               "    eitem.eitem_id in " .
               "    (select distinct reserve_list_eitems.eitem_id from " .
               "     $this->dbName.reserve_list_eitems" .
               "     where reserve_list_eitems.reserve_list_id in " .
               "     (select distinct reserve_list_courses.reserve_list_id from " .
               "      $this->dbName.reserve_list_courses " .
               "      $reserveWhere )) )) union " .
               "  ((select distinct mfhd_item.mfhd_id from $this->dbName.mfhd_item" .
               "    where mfhd_item.item_id in " .
               "    (select distinct reserve_list_items.item_id from " .
               "    $this->dbName.reserve_list_items" .
               "    where reserve_list_items.reserve_list_id in " .
               "    (select distinct reserve_list_courses.reserve_list_id from " .
               "      $this->dbName.reserve_list_courses $reserveWhere )) )) " .
               "  ) ";
         */
        $sql = " select MFHD_MASTER.DISPLAY_CALL_NO, BIB_TEXT.BIB_ID, " .
               " BIB_TEXT.AUTHOR, BIB_TEXT.TITLE, " .
               " BIB_TEXT.PUBLISHER, BIB_TEXT.PUBLISHER_DATE, subquery.COURSE_ID, " .
               " subquery.INSTRUCTOR_ID, subquery.DEPARTMENT_ID " .
               " FROM $this->dbName.BIB_TEXT " .
               " JOIN $this->dbName.BIB_MFHD ON BIB_TEXT.BIB_ID=BIB_MFHD.BIB_ID " .
               " JOIN $this->dbName.MFHD_MASTER " .
               " ON BIB_MFHD.MFHD_ID = MFHD_MASTER.MFHD_ID" .
               " JOIN " .
               "  ( ".
               "  ((select distinct eitem.mfhd_id, subsubquery1.COURSE_ID, " .
               "     subsubquery1.INSTRUCTOR_ID, subsubquery1.DEPARTMENT_ID " .
               "     from $this->dbName.eitem join " .
               "    (select distinct reserve_list_eitems.eitem_id, " .
               "     RESERVE_LIST_COURSES.COURSE_ID, " .
               "     RESERVE_LIST_COURSES.INSTRUCTOR_ID, " .
               "     RESERVE_LIST_COURSES.DEPARTMENT_ID from " .
               "     $this->dbName.reserve_list_eitems" .
               "     JOIN $this->dbName.reserve_list_courses ON " .
               "      reserve_list_courses.reserve_list_id = " .
               "      reserve_list_eitems.reserve_list_id" .
               "      $reserveWhere ) subsubquery1 ON " .
               "      subsubquery1.eitem_id = eitem.eitem_id)) union " .
               "  ((select distinct mfhd_item.mfhd_id, subsubquery2.COURSE_ID, " .
               "    subsubquery2.INSTRUCTOR_ID, subsubquery2.DEPARTMENT_ID " .
               "    from $this->dbName.mfhd_item join" .
               "    (select distinct reserve_list_items.item_id, " .
               "     RESERVE_LIST_COURSES.COURSE_ID, " .
               "     RESERVE_LIST_COURSES.INSTRUCTOR_ID, " .
               "     RESERVE_LIST_COURSES.DEPARTMENT_ID from " .
               "    $this->dbName.reserve_list_items" .
               "    JOIN $this->dbName.reserve_list_courses on " .
               "    reserve_list_items.reserve_list_id = " .
               "    reserve_list_courses.reserve_list_id" .
               "    $reserveWhere) subsubquery2 ON " .
               "    subsubquery2.item_id = mfhd_item.item_id )) " .
               "  ) subquery ON mfhd_master.mfhd_id = subquery.mfhd_id ";

        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute($bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $recordList[] = $row;
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        return $recordList;
    }

    /**
     * Get suppressed records.
     *
     * @throws VF_Exception_ILS
     * @return array ID numbers of suppressed records in the system.
     */
    public function getSuppressedRecords()
    {
        $list = array();

        $sql = "select BIB_MASTER.BIB_ID " .
               "from $this->dbName.BIB_MASTER " .
               "where BIB_MASTER.SUPPRESS_IN_OPAC='Y'";
        try {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $list[] = $row['BIB_ID'];
            }
        } catch (PDOException $e) {
            throw new VF_Exception_ILS($e->getMessage());
        }

        return $list;
    }
}
