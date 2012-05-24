<?php
/**
 * SIP2 authentication module.
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
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
require_once '3rdparty/sip2.class.php';

/**
 * SIP2 authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class VF_Auth_SIP2 extends VF_Auth_Base
{
    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param Zend_Controller_Request_Abstract $request Request object containing
     * account credentials.
     *
     * @throws VF_Exception_Auth
     * @return Zend_Db_Table_Row_Abstract Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $username = trim($request->getParam('username', ''));
        $password = trim($request->getParam('password', ''));
        if ($username == '' || $password == '') {
            throw new VF_Exception_Auth('authentication_error_blank');
        }
        
        // Attempt SIP2 Authentication
        $mysip = new sip2();
        if (isset($this->config->SIP2)) {
            $mysip->hostname = $this->config->SIP2->host;
            $mysip->port = $this->config->SIP2->port;
        }

        if (!$mysip->connect()) {
            throw new VF_Exception_Auth('authentication_error_technical');
        }

        //send selfcheck status message
        $in = $mysip->msgSCStatus();
        $msg_result = $mysip->get_message($in);

        // Make sure the response is 98 as expected
        if (!preg_match("/^98/", $msg_result)) {
            $mysip->disconnect();
            throw new VF_Exception_Auth('authentication_error_technical');
        }
        $result = $mysip->parseACSStatusResponse($msg_result);

        //  Use result to populate SIP2 setings
        $mysip->AO = $result['variable']['AO'][0];
        $mysip->AN = $result['variable']['AN'][0];

        $mysip->patron = $username;
        $mysip->patronpwd = $password;

        $in = $mysip->msgPatronStatusRequest();
        $msg_result = $mysip->get_message($in);

        // Make sure the response is 24 as expected
        if (!preg_match("/^24/", $msg_result)) {
            $mysip->disconnect();
            throw new VF_Exception_Auth('authentication_error_technical');
        }

        $result = $mysip->parsePatronStatusResponse($msg_result);
        $mysip->disconnect();
        if (($result['variable']['BL'][0] == 'Y')
            and ($result['variable']['CQ'][0] == 'Y')
        ) {
            // Success!!!
            $user = $this->processSIP2User($result, $username, $password);

            // Set login cookie for 1 hour
            $user->password = $password; // Need this for Metalib
        } else {
            throw new VF_Exception_Auth('authentication_error_invalid');
        }

        return $user;
    }

    /**
     * Process SIP2 User Account
     *
     * Based on code by Bob Wicksall <bwicksall@pls-net.org>.
     *
     * @param array  $info     An array of user information
     * @param string $username The user's ILS username
     * @param string $password The user's ILS password
     *
     * @throws VF_Exception_Auth
     * @return Zend_Db_Table_Row_Abstract Processed User object.
     */
    protected function processSIP2User($info, $username, $password)
    {
        $user = VuFind_Model_Db_User::getByUsername($info['variable']['AA'][0]);

        // This could potentially be different depending on the ILS.  Name could be
        // Bob Wicksall or Wicksall, Bob. This is currently assuming Wicksall, Bob
        $ae = $info['variable']['AE'][0];
        $user->firstname = trim(substr($ae, 1 + strripos($ae, ',')));
        $user->lastname = trim(substr($ae, 0, strripos($ae, ',')));
        // I'm inserting the sip username and password since the ILS is the source.
        // Should revisit this.
        $user->cat_username = $username;
        $user->cat_password = $password;

        // Update the user in the database, then return it to the caller:
        $user->save();
        return $user;
    }
}