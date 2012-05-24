<?php
/**
 * LDAP authentication class
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
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */

/**
 * LDAP authentication class
 *
 * @category VuFind
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class VF_Auth_LDAP extends VF_Auth_Base
{
    protected $username;
    protected $password;

    /**
     * Constructor
     *
     * @param object $config Optional configuration object to pass through (loads
     * default configuration if none specified).
     *
     * @throws VF_Exception_Auth
     */
    public function __construct($config = null)
    {
        parent::__construct($config);
        $this->validateConfiguration();
    }

    /**
     * Validate configuration parameters.
     *
     * @throws VF_Exception_Auth
     * @return void
     */
    protected function validateConfiguration()
    {
        // Check for missing parameters:
        $requiredParams = array('host', 'port', 'basedn', 'username');
        foreach ($requiredParams as $param) {
            if (!isset($this->config->LDAP->$param)
                || empty($this->config->LDAP->$param)
            ) {
                throw new VF_Exception_Auth(
                    "One or more LDAP parameter are missing. Check your config.ini!"
                );
            }
        }
    }

    /**
     * Get the requested configuration parameter (or blank string if unset).
     *
     * @param string $name Name of parameter to retrieve.
     *
     * @return string
     */
    protected function getConfig($name)
    {
        $value = isset($this->config->LDAP->$name)
            ? $this->config->LDAP->$name : '';

        // Normalize all values to lowercase except for potentially case-sensitive
        // bind and basedn credentials.
        $doNotLower = array('bind_username', 'bind_password', 'basedn');
        return (in_array($name, $doNotLower)) ? $value : strtolower($value);
    }

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
        $this->username = trim($request->getParam('username'));
        $this->password = trim($request->getParam('password'));
        if ($this->username == '' || $this->password == '') {
            throw new VF_Exception_Auth('authentication_error_blank');
        }
        return $this->bindUser();
    }

    /**
     * Communicate with LDAP and obtain user details.
     *
     * @throws VF_Exception_Auth
     * @return Zend_Db_Table_Row_Abstract Object representing logged-in user.
     */
    protected function bindUser()
    {
        // Try to connect to LDAP and die if we can't; note that some LDAP setups
        // will successfully return a resource from ldap_connect even if the server
        // is unavailable -- we need to check for bad return values again at search
        // time!
        $ldapConnection = @ldap_connect(
            $this->getConfig('host'), $this->getConfig('port')
        );
        if (!$ldapConnection) {
            throw new VF_Exception_Auth('authentication_error_technical');
        }

        // Set LDAP options -- use protocol version 3
        @ldap_set_option($ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);

        // if the host parameter is not specified as ldaps://
        // then we need to initiate TLS so we
        // can have a secure connection over the standard LDAP port.
        if (stripos($this->getConfig('host'), 'ldaps://') === false) {
            if (!@ldap_start_tls($ldapConnection)) {
                throw new VF_Exception_Auth('authentication_error_technical');
            }
        }

        // If bind_username and bind_password were supplied in the config file, use
        // them to access LDAP before proceeding.  In some LDAP setups, these
        // settings can be excluded in order to skip this step.
        if ($this->getConfig('bind_username') != ''
            && $this->getConfig('bind_password') != ''
        ) {
            $ldapBind = @ldap_bind(
                $ldapConnection, $this->getConfig('bind_username'),
                $this->getConfig('bind_password')
            );
            if (!$ldapBind) {
                throw new VF_Exception_Auth('authentication_error_technical');
            }
        }

        // Search for username
        $ldapFilter = $this->getConfig('username') . '=' . $this->username;
        $ldapSearch = @ldap_search(
            $ldapConnection, $this->getConfig('basedn'), $ldapFilter
        );
        if (!$ldapSearch) {
            throw new VF_Exception_Auth('authentication_error_technical');
        }

        $info = ldap_get_entries($ldapConnection, $ldapSearch);
        if ($info['count']) {
            // Validate the user credentials by attempting to bind to LDAP:
            $ldapBind = @ldap_bind(
                $ldapConnection, $info[0]['dn'], $this->password
            );
            if ($ldapBind) {
                // If the bind was successful, we can look up the full user info:
                $ldapSearch = ldap_search(
                    $ldapConnection, $this->getConfig('basedn'), $ldapFilter
                );
                $data = ldap_get_entries($ldapConnection, $ldapSearch);
                return $this->processLDAPUser($data);
            }
        }

        throw new VF_Exception_Auth('authentication_error_invalid');
    }

    /**
     * Build a User object from details obtained via LDAP.
     *
     * @param array $data Details from ldap_get_entries call.
     *
     * @return Zend_Db_Table_Row_Abstract Object representing logged-in user.
     */
    protected function processLDAPUser($data)
    {
        // Database fields that we may be able to load from LDAP:
        $fields = array(
            'firstname', 'lastname', 'email', 'cat_username', 'cat_password',
            'college', 'major'
        );

        // User object to populate from LDAP:
        $user = VuFind_Model_Db_User::getByUsername($this->username);

        // Loop through LDAP response and map fields to database object based
        // on configuration settings:
        for ($i = 0; $i < $data["count"]; $i++) {
            for ($j = 0; $j < $data[$i]["count"]; $j++) {
                foreach ($fields as $field) {
                    $configValue = $this->getConfig($field);
                    if ($data[$i][$j] == $configValue && !empty($configValue)) {
                        $user->$field = $data[$i][$data[$i][$j]][0];
                    }
                }
            }
        }

        // Update the user in the database, then return it to the caller:
        $user->save();
        return $user;
    }
}