<?php
/**
 * Database utility class.
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * Database utility class.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_DB
{
    /**
     * Obtain a Zend_DB connection using standard VuFind configuration.
     *
     * @param string $overrideUser Username override (leave null to use username
     * from config.ini)
     * @param string $overridePass Password override (leave null to use password
     * from config.ini)
     *
     * @return object
     */
    public static function connect($overrideUser = null, $overridePass = null)
    {
        // Parse details from connection string:
        $config = VF_Config_Reader::getConfig();
        list($type, $details) = explode('://', $config->Database->database);
        preg_match('/(.+)@([^@]+)\/(.+)/', $details, $matches);
        $credentials = isset($matches[1]) ? $matches[1] : null;
        $host = isset($matches[2]) ? $matches[2] : null;
        $dbName = isset($matches[3]) ? $matches[3] : null;
        if (strstr($credentials, ':')) {
            list($username, $password) = explode(':', $credentials, 2);
        } else {
            $username = $credentials;
            $password = null;
        }
        $username = !is_null($overrideUser) ? $overrideUser : $username;
        $password = !is_null($overridePass) ? $overridePass : $password;

        // Translate database type for compatibility with legacy config files:
        switch (strtolower($type)) {
        case 'mysql':
            $type = 'mysqli';
            break;
        }

        // Set up parameters:
        $options = array(
            Zend_Db::AUTO_QUOTE_IDENTIFIERS => true
        );
        $params = array(
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'dbname' => $dbName,
            'options' => $options
        );

        // Connect to database:
        return Zend_Db::factory($type, $params);
    }
}
