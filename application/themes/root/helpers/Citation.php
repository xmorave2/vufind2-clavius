<?php
/**
 * Citation view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Citation view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_Citation extends Zend_View_Helper_Abstract
{
    protected $details = array();
    protected $driver;

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param VF_RecordDriver_Base $driver Record driver object.
     *
     * @return VuFind_Theme_Root_Helper_Record
     */
    public function citation($driver)
    {
        // Build author list:
        $authors = array();
        $primary = $driver->tryMethod('getPrimaryAuthor');
        if (!empty($primary)) {
            $authors[] = $primary;
        }
        $secondary = $driver->tryMethod('getSecondaryAuthors');
        if (is_array($secondary) && !empty($secondary)) {
            $authors = array_unique(array_merge($authors, $secondary));
        }

        // Get best available title details:
        $title = $driver->tryMethod('getShortTitle');
        $subtitle = $driver->tryMethod('getSubtitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        if (empty($title)) {
            $title = $driver->getBreadcrumb();
        }

        // Extract the additional details from the record driver:
        $publishers = $driver->tryMethod('getPublishers');
        $pubDates = $driver->tryMethod('getPublicationDates');
        $pubPlaces = $driver->tryMethod('getPlacesOfPublication');
        $edition = $driver->tryMethod('getEdition');

        // Store everything:
        $this->details = array(
            'authors' => $authors, 'title' => $title, 'subtitle' => $subtitle,
            'pubPlace' => isset($pubPlaces[0]) ? $pubPlaces[0] : null,
            'pubName' => isset($publishers[0]) ? $publishers[0] : null,
            'pubDate' => isset($pubDates[0]) ? $pubDates[0] : null,
            'edition' => empty($edition) ? array() : array($edition),
            'journal' => $driver->tryMethod('getContainerTitle')
        );
        $this->driver = $driver;

        return $this;
    }

    /**
     * Retrieve a citation in a particular format
     * 
     * Returns the citation in the format specified
     * 
     * @param string $format Citation format ('APA' or 'MLA')
     *
     * @return string        Formatted citation
     */
    public function getCitation($format)
    {
        // Construct method name for requested format:
        $method = 'getCitation' . $format;

        // Avoid calls to inappropriate/missing methods:
        if (!empty($format) && method_exists($this, $method)) {
            return $this->$method();
        }

        // Return blank string if no valid method found:
        return '';
    }

    /**
     * Get APA citation.
     *
     * This function assigns all the necessary variables and then returns an APA
     * citation.
     *
     * @return string
     */
    public function getCitationAPA()
    {
        $apa = array(
            'title' => $this->getAPATitle(),
            'authors' => $this->getAPAAuthors(),
            'edition' => $this->getEdition()
        );
        // Show a period after the title if it does not already have punctuation
        // and is not followed by an edition statement:
        $apa['periodAfterTitle']
            = (!$this->isPunctuated($apa['title']) && empty($apa['edition']));

        // Behave differently for books vs. journals:
        if (empty($this->details['journal'])) {
            $apa['publisher'] = $this->getPublisher();
            $apa['year'] = $this->getYear();
            return $this->view->partial('Citation/apa.phtml', $apa);
        } else {
            list($apa['number'], $apa['date']) = $this->getAPANumberAndDate();
            $apa['journal'] = $this->details['journal'];
            $apa['pageRange'] = $this->getPageRange();
            return $this->view->partial('Citation/apa-article.phtml', $apa);
        }
    }

    /**
     * Get MLA citation.
     *
     * This function assigns all the necessary variables and then returns an MLA
     * citation.
     *
     * @return string
     */
    public function getCitationMLA()
    {
        $mla = array(
            'title' => $this->getMLATitle(),
            'authors' => $this->getMLAAuthors()
        );
        $mla['periodAfterTitle'] = !$this->isPunctuated($mla['title']);

        // Behave differently for books vs. journals:
        if (empty($this->details['journal'])) {
            $mla['publisher'] = $this->getPublisher();
            $mla['year'] = $this->getYear();
            $mla['edition'] = $this->getEdition();
            return $this->view->partial('Citation/mla.phtml', $mla);
        } else {
            // Add other journal-specific details:
            $mla['pageRange'] = $this->getPageRange();
            $mla['journal'] =  $this->capitalizeTitle($this->details['journal']);
            $mla['numberAndDate'] = $this->getMLANumberAndDate();
            return $this->view->partial('Citation/mla-article.phtml', $mla);
        }
    }

    /**
     * Construct page range portion of citation.
     *
     * @return string
     */
    protected function getPageRange()
    {
        $start = $this->driver->tryMethod('getContainerStartPage');
        $end = $this->driver->tryMethod('getContainerEndPage');
        return ($start == $end || empty($end))
            ? $start : $start . '-' . $end;
    }

    /**
     * Construct volume/issue/date portion of MLA citation.
     *
     * @return string
     */
    protected function getMLANumberAndDate()
    {
        $vol = $this->driver->tryMethod('getContainerVolume');
        $num = $this->driver->tryMethod('getContainerIssue');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            $converter = new VF_Date_Converter();
            try {
                $year = $converter->convertFromDisplayDate('Y', $date);
                $month = $converter->convertFromDisplayDate('M', $date) . '.';
                $day = $converter->convertFromDisplayDate('j', $date);
            } catch (VF_Exception_Date $e) {
                // If conversion fails, use raw date as year -- not ideal,
                // but probably better than nothing:
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        // We need to supply additional date information if no vol/num:
        if (!empty($vol) || !empty($num)) {
            // If volume and number are both non-empty, separate them with a
            // period; otherwise just use the one that is set.
            $volNum = (!empty($vol) && !empty($num))
                ? $vol . '.' . $num : $vol . $num;
            return $volNum . ' (' . $year . ')';
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            return (($day > 1) ? $day . ' ' : '')
                . (empty($month) ? '' : $month . ' ')
                . $year;
        }
    }

    /**
     * Construct volume/issue/date portion of APA citation.  Returns an array with
     * two elements: numbering and date (since these need to end up in different
     * areas of the final citation, we don't return a single string, but since their
     * determination is related, we need to do the work in a single function).
     *
     * @return array
     */
    protected function getAPANumberAndDate()
    {
        $vol = $this->driver->tryMethod('getContainerVolume');
        $num = $this->driver->tryMethod('getContainerIssue');
        $date = $this->details['pubDate'];
        if (strlen($date) > 4) {
            $converter = new VF_Date_Converter();
            try {
                $year = $converter->convertFromDisplayDate('Y', $date);
                $month = $converter->convertFromDisplayDate('F', $date);
                $day = $converter->convertFromDisplayDate('j', $date);
            } catch (VF_Exception_Date $e) {
                // If conversion fails, use raw date as year -- not ideal,
                // but probably better than nothing:
                $year = $date;
                $month = $day = '';
            }
        } else {
            $year = $date;
            $month = $day = '';
        }

        // We need to supply additional date information if no vol/num:
        if (!empty($vol) || !empty($num)) {
            // If volume and number are both non-empty, separate them with a
            // period; otherwise just use the one that is set.
            $volNum = (!empty($vol) && !empty($num))
                ? $vol . '(' . $num . ')' : $vol . $num;
            return array($volNum, $year);
        } else {
            // Right now, we'll assume if day == 1, this is a monthly publication;
            // that's probably going to result in some bad citations, but it's the
            // best we can do without writing extra record driver methods.
            $finalDate = $year
                . (empty($month) ? '' : ', ' . $month)
                . (($day > 1) ? ' ' . $day : '');
            return array('', $finalDate);
        }
    }

    /**
     * Is the string a valid name suffix?
     *
     * @param string $str The string to check.
     *
     * @return bool       True if it's a name suffix.
     */
    protected function isNameSuffix($str)
    {
        $str = $this->stripPunctuation($str);

        // Is it a standard suffix?
        $suffixes = array('Jr', 'Sr');
        if (in_array($str, $suffixes)) {
            return true;
        }

        // Is it a roman numeral?  (This check could be smarter, but it's probably
        // good enough as it is).
        if (preg_match('/^[MDCLXVI]+$/', $str)) {
            return true;
        }

        // If we got this far, it's not a suffix.
        return false;
    }

    /**
     * Is the string a date range?
     *
     * @param string $str The string to check.
     *
     * @return bool       True if it's a date range.
     */
    protected function isDateRange($str)
    {
        $str = trim($str);
        return preg_match('/^([0-9]+)-([0-9]*)\.?$/', $str);
    }

    /**
     * Abbreviate a first name.
     *
     * @param string $name The name to abbreviate
     *
     * @return string      The abbreviated name.
     */
    protected function abbreviateName($name)
    {
        $parts = explode(', ', $name);
        $name = $parts[0];

        // Attach initials... but if we encountered a date range, the name
        // ended earlier than expected, and we should stop now.
        if (isset($parts[1]) && !$this->isDateRange($parts[1])) {
            $fnameParts = explode(' ', $parts[1]);
            for ($i = 0; $i < count($fnameParts); $i++) {
                // Use the multi-byte substring function if available to avoid
                // problems with accented characters:
                if (function_exists('mb_substr')) {
                    $fnameParts[$i] = mb_substr($fnameParts[$i], 0, 1, 'utf8') . '.';
                } else {
                    $fnameParts[$i] = substr($fnameParts[$i], 0, 1) . '.';
                }
            }
            $name .= ', ' . implode(' ', $fnameParts);
            if (isset($parts[2]) && $this->isNameSuffix($parts[2])) {
                $name = trim($name) . ', ' . $parts[2];
            }
        }

        return trim($name);
    }

    /**
     * Strip the dates off the end of a name.
     *
     * @param string $str Name to clean.
     *
     * @return string     Cleaned name.
     */
    protected function cleanNameDates($str)
    {
        $arr = explode(', ', $str);
        $name = $arr[0];
        if (isset($arr[1]) && !$this->isDateRange($arr[1])) {
            $name .= ', ' . $arr[1];
            if (isset($arr[2]) && $this->isNameSuffix($arr[2])) {
                $name .= ', ' . $arr[2];
            }
        }
        return $name;
    }

    /**
     * Does the string end in punctuation that we want to retain?
     *
     * @param string $string String to test.
     *
     * @return boolean       Does string end in punctuation?
     */
    protected function isPunctuated($string)
    {
        $punctuation = array('.', '?', '!');
        return (in_array(substr($string, -1), $punctuation));
    }

    /**
     * Strip unwanted punctuation from the right side of a string.
     *
     * @param string $text Text to clean up.
     *
     * @return string      Cleaned up text.
     */
    protected function stripPunctuation($text)
    {
        $punctuation = array('.', ',', ':', ';', '/');
        $text = trim($text);
        if (in_array(substr($text, -1), $punctuation)) {
            $text = substr($text, 0, -1);
        }
        return trim($text);
    }

    /**
     * Turn a "Last, First" name into a "First Last" name.
     *
     * @param string $str Name to reverse.
     *
     * @return string     Reversed name.
     */
    protected function reverseName($str)
    {
        $arr = explode(', ', $str);

        // If the second chunk is a date range, there is nothing to reverse!
        if (!isset($arr[1]) || $this->isDateRange($arr[1])) {
            return $arr[0];
        }

        $name = $arr[1] . ' ' . $arr[0];
        if (isset($arr[2]) && $this->isNameSuffix($arr[2])) {
            $name .= ', ' . $arr[2];
        }
        return $name;
    }

    /**
     * Capitalize all words in a title, except for a few common exceptions.
     *
     * @param string $str Title to capitalize.
     *
     * @return string     Capitalized title.
     */
    protected function capitalizeTitle($str)
    {
        $exceptions = array('a', 'an', 'the', 'against', 'between', 'in', 'of',
            'to', 'and', 'but', 'for', 'nor', 'or', 'so', 'yet', 'to');

        $words = explode(' ', $str);
        $newwords = array();
        $followsColon = false;
        foreach ($words as $word) {
            // Capitalize words unless they are in the exception list...  but even
            // exceptional words get capitalized if they follow a colon.
            if (!in_array($word, $exceptions) || $followsColon) {
                $word = ucfirst($word);
            }
            array_push($newwords, $word);

            $followsColon = substr($word, -1) == ':';
        }

        return ucfirst(join(' ', $newwords));
    }

    /**
     * Get the full title for an APA citation.
     *
     * @return string
     */
    protected function getAPATitle()
    {
        // Create Title
        $title = $this->stripPunctuation($this->details['title']);
        if (isset($this->details['subtitle'])) {
            $subtitle = $this->stripPunctuation($this->details['subtitle']);
            // Capitalize subtitle and apply it, assuming it really exists:
            if (!empty($subtitle)) {
                $subtitle
                    = strtoupper(substr($subtitle, 0, 1)) . substr($subtitle, 1);
                $title .= ': ' . $subtitle;
            }
        }

        return $title;
    }

    /**
     * Get an array of authors for an APA citation.
     *
     * @return array
     */
    protected function getAPAAuthors()
    {
        $authorStr = '';
        if (isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            foreach ($this->details['authors'] as $author) {
                $author = $this->abbreviateName($author);
                if (($i + 1 == count($this->details['authors']))
                    && ($i > 0)
                ) { // Last
                    $authorStr .= '& ' . $this->stripPunctuation($author) . '.';
                } elseif (count($this->details['authors']) > 1) {
                    $authorStr .= $author . ', ';
                } else { // First and only
                    $authorStr .= $this->stripPunctuation($author) . '.';
                }
                $i++;
            }
        }
        return (empty($authorStr) ? false : $authorStr);
    }

    /**
     * Get edition statement for inclusion in a citation.  Shared by APA and
     * MLA functionality.
     *
     * @return string
     */
    protected function getEdition()
    {
        // Find the first edition statement that isn't "1st ed."
        if (isset($this->details['edition'])
            && is_array($this->details['edition'])
        ) {
            foreach ($this->details['edition'] as $edition) {
                // Strip punctuation from the edition to get rid of unwanted
                // junk...  but if there is nothing left after stripping, put
                // back at least one period!
                $edition = $this->stripPunctuation($edition);
                if (empty($edition)) {
                    continue;
                }
                if (!$this->isPunctuated($edition)) {
                    $edition .= '.';
                }
                if ($edition !== '1st ed.') {
                    return $edition;
                }
            }
        }

        // No edition statement found:
        return false;
    }

    /**
     * Get the full title for an MLA citation.
     *
     * @return string
     */
    protected function getMLATitle()
    {
        // MLA titles are just like APA titles, only capitalized differently:
        return $this->capitalizeTitle($this->getAPATitle());
    }

    /**
     * Get an array of authors for an APA citation.
     *
     * @return array
     */
    protected function getMLAAuthors()
    {
        $authorStr = '';
        if (isset($this->details['authors'])
            && is_array($this->details['authors'])
        ) {
            $i = 0;
            if (count($this->details['authors']) > 4) {
                $author = $this->details['authors'][0];
                $authorStr = $this->cleanNameDates($author) . ', et al';
            } else {
                foreach ($this->details['authors'] as $author) {
                    if (($i+1 == count($this->details['authors'])) && ($i > 0)) {
                        // Last
                        $authorStr .= ', and ' .
                            $this->reverseName($this->stripPunctuation($author));
                    } elseif ($i > 0) {
                        $authorStr .= ', ' .
                            $this->reverseName($this->stripPunctuation($author));
                    } else {
                        // First
                        $authorStr .= $this->cleanNameDates($author);
                    }
                    $i++;
                }
            }
        }
        return (empty($authorStr) ? false : $this->stripPunctuation($authorStr));
    }

    /**
     * Get publisher information (place: name) for inclusion in a citation.
     * Shared by APA and MLA functionality.
     *
     * @return string
     */
    protected function getPublisher()
    {
        $parts = array();
        if (isset($this->details['pubPlace'])
            && !empty($this->details['pubPlace'])
        ) {
            $parts[] = $this->stripPunctuation($this->details['pubPlace']);
        }
        if (isset($this->details['pubName'])
            && !empty($this->details['pubName'])
        ) {
            $parts[] = $this->details['pubName'];
        }
        if (empty($parts)) {
            return false;
        }
        return $this->stripPunctuation(implode(': ', $parts));
    }

    /**
     * Get the year of publication for inclusion in a citation.
     * Shared by APA and MLA functionality.
     *
     * @return string
     */
    protected function getYear()
    {
        if (isset($this->details['pubDate'])) {
            if (strlen($this->details['pubDate']) > 4) {
                $converter = new VF_Date_Converter();
                try {
                    return $converter->convertFromDisplayDate(
                        'Y', $this->details['pubDate']
                    );
                } catch (Exception $e) {
                    // Ignore date errors -- no point in dying here:
                    return false;
                }
            }
            return preg_replace('/[^0-9]/', '', $this->details['pubDate']);
        }
        return false;
    }
}