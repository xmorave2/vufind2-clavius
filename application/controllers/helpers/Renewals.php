<?php
/**
 * VuFind Action Helper - Renewals Support Methods
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
class VuFind_Action_Helper_Renewals extends Zend_Controller_Action_Helper_Abstract
{
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
     * Update ILS details with renewal-specific information, if appropriate.
     *
     * @param VF_ILS_Connection $catalog     ILS connection object
     * @param array             $ilsDetails  Transaction details from ILS driver's
     * getMyTransactions() method
     * @param array             $renewStatus Renewal settings from ILS driver's
     * checkFunction() method
     *
     * @return array $ilsDetails with renewal info added
     */
    public function addRenewDetails($catalog, $ilsDetails, $renewStatus)
    {
        // Only add renewal information if enabled:
        if ($renewStatus) {
            if ($renewStatus['function'] == 'renewMyItemsLink') {
                // Build OPAC URL
                $ilsDetails['renew_link'] = $catalog->renewMyItemsLink($ilsDetails);
            } else {
                // Form Details
                $ilsDetails['renew_details']
                    = $catalog->getRenewDetails($ilsDetails);
            }
        }

        // Send back the modified array:
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
    public function processRenewals($request, $catalog, $patron)
    {
        // Pick IDs to renew based on which button was pressed:
        $all = $request->getParam('renewAll');
        $selected = $request->getParam('renewSelected');
        if (!empty($all)) {
            $ids = $request->getParam('renewAllIDS');
        } else if (!empty($selected)) {
            $ids = $request->getParam('renewSelectedIDS');
        } else {
            $ids = array();
        }

        // Retrieve the flashMessenger helper:
        $flashMsg
            = Zend_Controller_Action_HelperBroker::getStaticHelper('flashMessenger');

        // If there is actually something to renew, attempt the renewal action:
        if (is_array($ids) && !empty($ids)) {
            $renewResult = $catalog->renewMyItems(
                array('details' => $ids, 'patron' => $patron)
            );
            if ($renewResult !== false) {
                // Assign Blocks to the Template
                if (isset($renewResult['block'])
                    && is_array($renewResult['block'])
                ) {
                    foreach ($renewResult['block'] as $block) {
                        $flashMsg->setNamespace('info')->addMessage($block);
                    }
                }

                // Send back result details:
                return $renewResult['details'];
            } else {
                // System failure:
                $flashMsg->setNamespace('error')->addMessage('renew_system_error');
            }
        } else if (!empty($all) || !empty($selected)) {
            // Button was clicked but no items were selected:
            $flashMsg->setNamespace('error')->addMessage('renew_empty_selection');
        }

        return array();
    }
}