<?php
/**
 * Shibboleth authentication module.
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
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Shibboleth authentication module.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VF_Auth_Shibboleth extends VF_Auth_Base
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
        // Throw an exception if the required username setting is missing.
        $shib = $this->config->Shibboleth;
        if (!isset($shib->username) || empty($shib->username)) {
            throw new VF_Exception_Auth(
                "Shibboleth username is missing in your configuration file."
            );
        }

        // Check if username is set.
        $username = $request->getServer($shib->username);
        if (empty($username)) {
            throw new VF_Exception_Auth('authentication_error_admin');
        }

        // Check if required attributes match up:
        foreach ($this->getRequiredAttributes() as $key => $value) {
            if (!preg_match('/'. $value .'/', $request->getServer($key))) {
                throw new VF_Exception_Auth('authentication_error_denied');
            }
        }

        // If we made it this far, we should log in the user!
        $user = VuFind_Model_Db_User::getByUsername($username);

        // Has the user configured attributes to use for populating the user table?
        $attribsToCheck = array(
            "cat_username", "email", "lastname", "firstname", "college", "major",
            "home_library"
        );
        foreach ($attribsToCheck as $attribute) {
            if (isset($shib->$attribute)) {
                $user->$attribute = $request->getServer($shib->$attribute);
            }
        }

        // Save and return the user object:
        $user->save();
        return $user;
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @return bool|string
     */
    public function getSessionInitiator()
    {
        if (!isset($this->config->Shibboleth->login)) {
            throw new VF_Exception_Auth(
                'Shibboleth login configuration parameter is not set.'
            );
        }

        if (isset($this->config->Shibboleth->target)) {
            $shibTarget = $this->config->Shibboleth->target;
        } else {
            $myRes = isset($this->config->Site->defaultLoggedInModule)
                ? $this->config->Site->defaultLoggedInModule : 'MyResearch';
            $urlOptions = array('controller' => $myRes, 'action' => 'Home');
            $router = Zend_Controller_Front::getInstance()->getRouter();
            $shibTarget = VF_Url::getBaseUrl()
                . $router->assemble($urlOptions, 'default', false, false);
        }
        $sessionInitiator = $this->config->Shibboleth->login
            . '?target=' . urlencode($shibTarget);

        if (isset($this->config->Shibboleth->provider_id)) {
            $sessionInitiator = $sessionInitiator . '&providerId=' .
                urlencode($this->config->Shibboleth->provider_id);
        }

        return $sessionInitiator;
    }

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired()
    {
        if (isset($this->config->Shibboleth->username)
            && isset($this->config->Shibboleth->logout)
        ) {
            // It would be more proper to call getServer on a Zend request
            // object... except that the request object doesn't exist yet when
            // this routine gets called.
            $username = isset($_SERVER[$this->config->Shibboleth->username])
                ? $_SERVER[$this->config->Shibboleth->username] : null;
            return empty($username);
        }
        return false;
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @return string     Redirect URL (usually same as $url, but modified in
     * some authentication modules).
     */
    public function logout($url)
    {
        // If single log-out is enabled, use a special URL:
        if (isset($this->config->Shibboleth->logout)
            && !empty($this->config->Shibboleth->logout)
        ) {
            $url = $this->config->Shibboleth->logout . '?return=' . urlencode($url);
        }

        // Send back the redirect URL (possibly modified):
        return $url;
    }

    /**
     * Extract required user attributes from the configuration.
     *
     * @return array      Only username and attribute-related values
     */
    protected function getRequiredAttributes()
    {
        // Special case -- store username as-is to establish return array:
        $sortedUserAttributes = array();

        // Now extract user attribute values:
        $shib = $this->config->Shibboleth;
        foreach ($shib as $key => $value) {
            if (preg_match("/userattribute_[0-9]{1,}/", $key)) {
                $valueKey = 'userattribute_value_' . substr($key, 14);
                $sortedUserAttributes[$value] = isset($shib->$valueKey)
                    ? $shib->$valueKey : null;

                // Throw an exception if attributes are missing/empty.
                if (empty($sortedUserAttributes[$value])) {
                    throw new VF_Exception_Auth(
                        "User attribute value of " . $value. " is missing!"
                    );
                }
            }
        }

        return $sortedUserAttributes;
    }
}