<?php
/**
 * VuFind Action Helper - Holds Support Methods
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
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Zend action helper to perform renewal-related actions
 *
 * @category VuFind2
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VuFind_Action_Helper_Holds extends Zend_Controller_Action_Helper_Abstract
{
    protected $session;

    /**
     * Grab the Zend_Session_Namespace object for storing helper-specific session
     * data.
     *
     * @return Zend_Session_Namespace
     */
    protected function getSession()
    {
        if (!isset($this->session)) {
            $this->session = new Zend_Session_Namespace('Holds_Helper');
        }
        return $this->session;
    }

    /**
     * Default method -- get access to the object so other methods may be called.
     *
     * @return VuFind_Action_Helper_Renewals
     */
    public function direct()
    {
        return $this;
    }

    /**
     * Reset the array of valid IDs in the session (used for form submission
     * validation)
     *
     * @return void
     */
    public function resetValidation()
    {
        $this->getSession()->validIds = array();
    }

    /**
     * Update ILS details with renewal-specific information, if appropriate.
     *
     * @param VF_ILS_Connection $catalog      ILS connection object
     * @param array             $ilsDetails   Hold details from ILS driver's
     * getMyHolds() method
     * @param array             $cancelStatus Cancel settings from ILS driver's
     * checkFunction() method
     *
     * @return array $ilsDetails with renewal info added
     */
    public function addCancelDetails($catalog, $ilsDetails, $cancelStatus)
    {
        // Generate Form Details for cancelling Holds if Cancelling Holds
        // is enabled
        if ($cancelStatus) {
            if ($cancelStatus['function'] == "getCancelHoldLink") {
                // Build OPAC URL
                $ilsDetails['cancel_link']
                    = $catalog->getCancelHoldLink($ilsDetails);
            } else {
                // Form Details
                $ilsDetails['cancel_details'] = $this->getSession()->validIds[]
                    = $catalog->getCancelHoldDetails($ilsDetails);
            }
        }

        return $ilsDetails;
    }

    /**
     * Process renewal requests.
     *
     * @param Zend_Controller_Request_Abstract $request Request object
     * @param VF_ILS_Connection                $catalog ILS connection object
     * @param array                            $patron  Current logged in patron
     *
     * @return array                           The result of the renewal, an
     * associative array keyed by item ID (empty if no renewals performed)
     */
    public function cancelHolds($request, $catalog, $patron)
    {
        // Retrieve the flashMessenger helper:
        $flashMsg
            = Zend_Controller_Action_HelperBroker::getStaticHelper('flashMessenger');

        // Pick IDs to renew based on which button was pressed:
        $all = $request->getParam('cancelAll');
        $selected = $request->getParam('cancelSelected');
        if (!empty($all)) {
            $details = $request->getParam('cancelAllIDS');
        } else if (!empty($selected)) {
            $details = $request->getParam('cancelSelectedIDS');
        } else {
            // No button pushed -- no action needed
            return array();
        }

        if (!empty($details)) {
            foreach ($details as $info) {
                // If the user input contains a value not found in the session
                // whitelist, something has been tampered with -- abort the process.
                if (!in_array($info, $this->getSession()->validIds)) {
                    $flashMsg->setNamespace('error')
                        ->addMessage('error_inconsistent_parameters');
                    return array();
                }
            }

            // Add Patron Data to Submitted Data
            $cancelResults = $catalog->cancelHolds(
                array('details' => $details, 'patron' => $patron)
            );
            if ($cancelResults == false) {
                $flashMsg->setNamespace('error')->addMessage('hold_cancel_fail');
            } else {
                if ($cancelResults['count'] > 0) {
                    // TODO : add a mechanism for inserting tokens into translated
                    // messages so we can avoid a double translation here.
                    $flashMsg->setNamespace('info')->addMessage(
                        $cancelResults['count'] . ' ' .
                        VF_Translator::translate('hold_cancel_success_items')
                    );
                }
                return $cancelResults;
            }
        } else {
             $flashMsg->setNamespace('error')->addMessage('hold_empty_selection');
        }
        return array();
    }

    /**
     * Method for validating contents of a "place hold" request; returns an array of
     * collected details if request is valid, otherwise returns false.
     *
     * @param Zend_Controller_Request_Abstract $request  Request object
     * @param array                            $linkData An array of keys to check
     *
     * @return boolean|array
     */
    public function validateRequest($request, $linkData)
    {
        $keyValueArray = array();
        foreach ($linkData as $details) {
            $keyValueArray[$details] = $request->getParam($details);
        }
        $hashKey = VF_Crypt_HMAC::generate($linkData, $keyValueArray);

        if ($request->getParam('hashKey') != $hashKey) {
            return false;
        }

        // Initialize gatheredDetails with any POST values we find; this will
        // allow us to repopulate the hold form with user-entered values if there
        // is an error.  However, it is important that we load the POST data
        // FIRST and then override it with GET values in order to ensure that
        // the user doesn't bypass the hashkey verification by manipulating POST
        // values.
        $gatheredDetails = $request->getPost('gatheredDetails', array());

        // Make sure the bib ID is included, even if it's not loaded as part of
        // the validation loop below.
        $gatheredDetails['id'] = $request->getParam('id');

        // Get Values Passed from holdings.php
        $gatheredDetails += $keyValueArray;

        return $gatheredDetails;
    }

    /**
     * Check if the user-provided pickup location is valid.
     *
     * @param string $pickup          User-specified pickup location
     * @param array  $extraHoldFields Hold form fields enabled by
     * configuration/driver
     * @param array  $pickUpLibs      Pickup library list from driver
     *
     * @return bool
     */
    public function validatePickUpInput($pickup, $extraHoldFields, $pickUpLibs)
    {
        // Not having to care for pickUpLocation is equivalent to having a valid one.
        if (!in_array('pickUpLocation', $extraHoldFields)) {
            return true;
        }

        // Check the valid pickup locations for a match against user input:
        return $this->validatePickUpLocation($pickup, $pickUpLibs);
    }

    /**
     * Check if the provided pickup location is valid.
     *
     * @param string $location   Location to check
     * @param array  $pickUpLibs Pickup locations list from driver
     *
     * @return bool
     */
    public function validatePickUpLocation($location, $pickUpLibs)
    {
        foreach ($pickUpLibs as $lib) {
            if ($location == $lib['locationID']) {
                return true;
            }
        }

        // If we got this far, something is wrong!
         return false;
    }

    /**
     * Getting a default required date based on hold settings.
     *
     * @param array $checkHolds Hold settings returned by the ILS driver's
     * checkFunction method.
     *
     * @return string A formatted default required date
     */
    public function getDefaultRequiredDate($checkHolds)
    {
        $formatDate = new VF_Date_Converter();

        $dateArray = isset($checkHolds['defaultRequiredDate'])
             ? explode(":", $checkHolds['defaultRequiredDate'])
             : array(0, 1, 0);
        list($d, $m, $y) = $dateArray;
        $nextMonth  = mktime(
            0, 0, 0, date("m")+$m,   date("d")+$d,   date("Y")+$y
        );

        return $formatDate->convertToDisplayDate("U", $nextMonth);
    }
}