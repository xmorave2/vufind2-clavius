<?php
/**
 * ILS authentication module.
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

/**
 * ILS authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class VF_Auth_ILS extends VF_Auth_Base
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
        $username = trim($request->getParam('username'));
        $password = trim($request->getParam('password'));
        if ($username == '' || $password == '') {
            throw new VF_Exception_Auth('authentication_error_blank');
        }
        // Connect to catalog:
        try {
            $catalog = VF_Connection_Manager::connectToCatalog();
            $patron = $catalog->patronLogin($username, $password);
        } catch (Exception $e) {
            throw new VF_Exception_Auth('authentication_error_technical');
        }

        // Did the patron successfully log in?
        if ($patron) {
            return $this->processILSUser($patron);
        }

        // If we got this far, we have a problem:
        throw new VF_Exception_Auth('authentication_error_invalid');
    }

    /**
     * Update the database using details from the ILS, then return the User object.
     *
     * @param array $info User details returned by ILS driver.
     *
     * @throws VF_Exception_Auth
     * @return Zend_Db_Table_Row_Abstract Processed User object.
     */
    protected function processILSUser($info)
    {
        // Figure out which field of the response to use as an identifier; fail
        // if the expected field is missing or empty:
        $usernameField = isset($this->config->Authentication->ILS_username_field)
            ? $this->config->Authentication->ILS_username_field : 'cat_username';
        if (!isset($info[$usernameField]) || empty($info[$usernameField])) {
            throw new VF_Exception_Auth('authentication_error_technical');
        }

        // Check to see if we already have an account for this user:
        $user = VuFind_Model_Db_User::getByUsername($info[$usernameField]);

        // No need to store the ILS password in VuFind's main password field:
        $user->password = "";

        // Update user information based on ILS data:
        $user->firstname = $info['firstname'] == null ? " " : $info['firstname'];
        $user->lastname = $info['lastname'] == null ? " " : $info['lastname'];
        $user->cat_username = $info['cat_username'] == null
            ? " " : $info['cat_username'];
        $user->cat_password = $info['cat_password'] == null
            ? " " : $info['cat_password'];
        $user->email = $info['email'] == null ? " " : $info['email'];
        $user->major = $info['major'] == null ? " " : $info['major'];
        $user->college = $info['college'] == null ? " " : $info['college'];

        // Update the user in the database, then return it to the caller:
        $user->save();
        return $user;
    }
}