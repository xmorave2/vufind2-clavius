<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */
class VF_ILS_Connection
{
    /**
     * The object of the appropriate driver.
     *
     * @var    object
     */
    protected $driver;

    /**
     * Constructor
     *
     * @throws VF_Exception_ILS
     */
    public function __construct()
    {
        $config = VF_Config_Reader::getConfig();
        if (!isset($config->Catalog->driver)) {
            throw new VF_Exception_ILS('ILS driver setting missing.');
        }
        $class = 'VF_ILS_Driver_' . $config->Catalog->driver;
        if (!class_exists($class)) {
            // Don't throw VF_Exception_ILS here -- we don't want this to be
            // treated as a login problem; it's more serious than that!
            throw new Exception('ILS driver missing: ' . $class);
        }
        try {
            $this->driver = new $class;
        } catch (Exception $e) {
            // If the main driver failed, use the NoILS driver instead if
            // configured to do so:
            if (isset($config->Catalog->loadNoILSOnFailure)
                && $config->Catalog->loadNoILSOnFailure
            ) {
                $this->driver = new VF_ILS_Driver_NoILS();
            } else {
                throw new VF_Exception_ILS($e->getMessage());
            }
        }
    }

    /**
     * Check Function
     *
     * This is responsible for checking the driver configuration to determine
     * if the system supports a particular function.
     *
     * @param string $function The name of the function to check.
     *
     * @return mixed On success, an associative array with specific function keys
     * and values; on failure, false.
     */
    public function checkFunction($function)
    {
        // Extract the configuration from the driver if available:
        $functionConfig = method_exists($this->driver, 'getConfig')
            ? $this->driver->getConfig($function) : false;

        // See if we have a corresponding check method to analyze the response:
        $checkMethod = "checkMethod".$function;
        if (!method_exists($this, $checkMethod)) {
            return false;
        }

        // Send back the settings:
        return $this->$checkMethod($functionConfig);
    }

    /**
     * Check Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Holds.
     *
     * @param string $functionConfig The Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for placing holds via a form or a URL; on failure, false.
     */
    protected function checkMethodHolds($functionConfig)
    {
        $response = false;

        if ($this->getHoldsMode() != "none"
            && method_exists($this->driver, 'placeHold')
            && isset($functionConfig['HMACKeys'])
        ) {
            $response = array('function' => "placeHold");
            $response['HMACKeys'] = explode(":", $functionConfig['HMACKeys']);
            if (isset($functionConfig['defaultRequiredDate'])) {
                $response['defaultRequiredDate']
                    = $functionConfig['defaultRequiredDate'];
            }
            if (isset($functionConfig['extraHoldFields'])) {
                $response['extraHoldFields'] = $functionConfig['extraHoldFields'];
            }
        } else if (method_exists($this->driver, 'getHoldLink')) {
            $response = array('function' => "getHoldLink");
        }
        return $response;
    }

    /**
     * Check Cancel Holds
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Cancelling Holds.
     *
     * @param string $functionConfig The Cancel Hold configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for cancelling holds via a form or a URL;
     * on failure, false.
     */
    protected function checkMethodcancelHolds($functionConfig)
    {
        $response = false;
        $config = VF_Config_Reader::getConfig();

        if ($config->Catalog->cancel_holds_enabled == true
            && method_exists($this->driver, 'cancelHolds')
        ) {
            $response = array('function' => "cancelHolds");
        } else if ($config->Catalog->cancel_holds_enabled == true
            && method_exists($this->driver, 'getCancelHoldLink')
        ) {
            $response = array('function' => "getCancelHoldLink");
        }
        return $response;
    }

    /**
     * Check Renewals
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports Renewing Items.
     *
     * @param string $functionConfig The Renewal configuration values
     *
     * @return mixed On success, an associative array with specific function keys
     * and values either for renewing items via a form or a URL; on failure, false.
     */
    protected function checkMethodRenewals($functionConfig)
    {
        $response = false;
        $config = VF_Config_Reader::getConfig();

        if ($config->Catalog->renewals_enabled == true
            && method_exists($this->driver, 'renewMyItems')
        ) {
            $response = array('function' => "renewMyItems");
        } else if ($config->Catalog->renewals_enabled == true
            && method_exists($this->driver, 'renewMyItemsLink')
        ) {
            $response = array('function' => "renewMyItemsLink");
        }
        return $response;
    }

    /**
     * Check Request is Valid
     *
     * This is responsible for checking if a request is valid from hold.php
     *
     * @param string $id     A Bibliographic ID
     * @param array  $data   Collected Holds Data
     * @param array  $patron Patron related data
     *
     * @return mixed The result of the checkRequestIsValid function if it
     * exists, true if it does not
     */
    public function checkRequestIsValid($id, $data, $patron)
    {
        $method = array($this->driver, 'checkRequestIsValid');
        if (is_callable($method)) {
            return $this->driver->checkRequestIsValid($id, $data, $patron);
        }
        // If the driver has no checkRequestIsValid method, we will assume that
        // all requests are valid - failure can be handled later after the user
        // attempts to place an illegal hold
        return true;
    }

    /**
     * Get Holds Mode
     *
     * This is responsible for returning the holds mode
     *
     * @return string The Holds mode
     */
    public static function getHoldsMode()
    {
        $config = VF_Config_Reader::getConfig();
        return isset($config->Catalog->holds_mode)
            ? $config->Catalog->holds_mode : 'all';
    }

    /**
     * Get Offline Mode
     *
     * This is responsible for returning the offline mode
     *
     * @return string|bool "ils-offline" for systems where the main ILS is offline,
     * "ils-none" for systems which do not use an ILS, false for online systems.
     */
    public function getOfflineMode()
    {
        // Graceful degradation -- return false if no method supported.
        return method_exists($this->driver, 'getOfflineMode') ?
            $this->driver->getOfflineMode() : false;
    }

    /**
     * Get Title Holds Mode
     *
     * This is responsible for returning the Title holds mode
     *
     * @return string The Title Holds mode
     */
    public static function getTitleHoldsMode()
    {
        $config = VF_Config_Reader::getConfig();
        return isset($config->Catalog->title_level_holds_mode)
            ? $config->Catalog->title_level_holds_mode : 'disabled';
    }

    /**
     * Has Holdings
     *
     * Obtain information on whether or not the item has holdings
     *
     * @param string $id A bibliographic id
     *
     * @return bool true on success, false on failure
     */
    public function hasHoldings($id)
    {
        // Graceful degradation -- return true if no method supported.
        return method_exists($this->driver, 'hasHoldings') ?
            $this->driver->hasHoldings($id) : true;
    }

    /**
     * Get Hidden Login Mode
     *
     * This is responsible for indicating whether login should be hidden.
     *
     * @return bool true if the login should be hidden, false if not
     */
    public function loginIsHidden()
    {
        // Graceful degradation -- return false if no method supported.
        return method_exists($this->driver, 'loginIsHidden') ?
            $this->driver->loginIsHidden() : false;
    }

    /**
     * Default method -- pass along calls to the driver if available; return
     * false otherwise.  This allows custom functions to be implemented in
     * the driver without constant modification to the connection class.
     *
     * @param string $methodName The name of the called method.
     * @param array  $params     Array of passed parameters.
     *
     * @return mixed             Varies by method (false if undefined method)
     */
    public function __call($methodName, $params)
    {
        $method = array($this->driver, $methodName);
        if (is_callable($method)) {
            return call_user_func_array($method, $params);
        }
        throw new VF_Exception_ILS('Cannot call method: ' . $methodName);
    }
}
