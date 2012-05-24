<?php
/**
 * Model for MARC records in WorldCat.
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
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */

/**
 * Model for MARC records in WorldCat.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */
class VF_RecordDriver_WorldCat extends VF_RecordDriver_SolrMarc
{
    protected $marcRecord;

    /**
     * Constructor.
     *
     * @param array $data Raw data from WorldCat representing the record.
     */
    public function __construct($data)
    {
        // Make sure the XML has an appropriate header:
        if (strlen($data) > 2 && substr($data, 0, 2) != '<?') {
            $data = '<?xml version="1.0"?>' . $data;
        }

        // Map the WorldCat response into a format that the parent Solr-based
        // record driver can understand.
        parent::__construct(array('fullrecord' => $data));

        // Set the correct resource source for database entries:
        $this->resourceSource = 'WorldCat';

        // Use the WorldCat.ini file instead of config.ini for loading record
        // settings (i.e. "related" record handlers):
        $this->recordIni = 'WorldCat';
    }

    /**
     * Get an array of information about record holdings, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHoldings()
    {
        // Not supported here:
        return array();
    }

    /**
     * Get an array of information about record history, obtained in real-time
     * from the ILS.
     *
     * @return array
     */
    public function getRealTimeHistory()
    {
        // Not supported here:
        return array();
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return false;
    }

    /**
     * Get an array of all ISBNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISBNs()
    {
        return $this->getFieldArray('020');
    }

    /**
     * Get an array of all ISSNs associated with the record (may be empty).
     *
     * @return array
     */
    public function getISSNs()
    {
        return $this->getFieldArray('022');
    }

    /**
     * Get an array of all the formats associated with the record.
     *
     * @return array
     */
    public function getFormats()
    {
        return $this->getFieldArray('245', array('h'));
    }

    /**
     * Return the unique identifier of this record within the Solr index;
     * useful for retrieving additional information (like tags and user
     * comments) from the external MySQL database.
     *
     * @return string Unique identifier.
     */
    public function getUniqueID()
    {
        return (string)$this->marcRecord->getField('001')->getData();
    }

    /**
     * Get the call number associated with the record (empty string if none).
     * If both LC and Dewey call numbers exist, LC will be favored.
     *
     * @return string
     */
    public function getCallNumber()
    {
        $callNo = $this->getFirstFieldValue('090', array('a', 'b'));
        if (empty($callNo)) {
            $callNo = $this->getFirstFieldValue('050', array('a', 'b'));
        }
        return empty($callNo) ? $this->getDeweyCallNumber() : $callNo;
    }

    /**
     * Get the Dewey call number associated with this record (empty string if none).
     *
     * @return string
     */
    public function getDeweyCallNumber()
    {
        return $this->getFirstFieldValue('082', array('a'));
    }

    /**
     * Get the main author of the record.
     *
     * @return string
     */
    public function getPrimaryAuthor()
    {
        return $this->getFirstFieldValue('100', array('a'));
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    public function getLanguages()
    {
        $retVal = array();
        $field = $this->marcRecord->getField('008');
        if ($field) {
            $content = $field->getData();
            if (strlen($content) >= 38) {
                $retVal[] = substr($content, 35, 3);
            }
        }
        return $retVal;
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getFirstFieldValue('245', array('a', 'b'));
    }

    /**
     * Get a sortable title for the record (i.e. no leading articles).
     *
     * @return string
     */
    public function getSortTitle()
    {
        $field = $this->marcRecord->getField('245');
        if ($field) {
            $title = $field->getSubfield('a');
            if ($title) {
                $skip = $field->getIndicator(2);
                return substr($title->getData(), $skip);
            }
        }
        return parent::getSortTitle();
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     *
     * @return string
     */
    public function getShortTitle()
    {
        return $this->getFirstFieldValue('245', array('a'));
    }

    /**
     * Get the subtitle of the record.
     *
     * @return string
     */
    public function getSubtitle()
    {
        return $this->getFirstFieldValue('245', array('b'));
    }

    /**
     * Get the publishers of the record.
     *
     * @return array
     */
    public function getPublishers()
    {
        return $this->getFieldArray('260', array('b'));
    }

    /**
     * Get the publication dates of the record.  See also getDateSpan().
     *
     * @return array
     */
    public function getPublicationDates()
    {
        return $this->getFieldArray('260', array('c'));
    }

    /**
     * Get an array of all secondary authors (complementing getPrimaryAuthor()).
     *
     * @return array
     */
    public function getSecondaryAuthors()
    {
        return $this->getFieldArray('700', array('a', 'b', 'c', 'd'));
    }

    /**
     * Get the name of the route used to build links to URLs representing the record.
     *
     * @return string
     */
    public function getRecordRoute()
    {
        return 'worldcatrecord';
    }

    /**
     * Get an array of newer titles for the record.
     *
     * @return array
     */
    public function getNewerTitles()
    {
        return $this->getFieldArray('785', array('a', 's', 't'));
    }

    /**
     * Get an array of previous titles for the record.
     *
     * @return array
     */
    public function getPreviousTitles()
    {
        return $this->getFieldArray('780', array('a', 's', 't'));
    }

    /**
     * Get holdings information from WorldCat.
     *
     * @return SimpleXMLElement
     */
    public function getWorldCatHoldings()
    {
        $wc = new VF_Connection_WorldCat();
        return $wc->getHoldings($this->getUniqueId());
    }
}
