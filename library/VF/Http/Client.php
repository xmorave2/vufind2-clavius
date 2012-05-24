<?php
/**
 * Proxy Server Support for VuFind
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @link     http://vufind.org/wiki/system_classes Wiki
 */

/**
 * Proxy_Request Class
 *
 * This is a wrapper class around the Zend HTTP client which automatically
 * initializes proxy server support when requested by the configuration file.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes Wiki
 */
class VF_Http_Client extends Zend_Http_Client
{
    /**
     * Constructor
     *
     * @param string $url     - target url
     * @param string $options - options array (passed to Zend_Http_Client)
     */
    public function __construct($url = null, $options = array())
    {
        // If an adapter was not explicitly passed in, select a default based on
        // ini settings.
        if (!isset($options['adapter'])) {
            $config = VF_Config_Reader::getConfig();

            // Never proxy localhost traffic, even if configured to do so:
            $skipProxy = (strstr($url, '//localhost') !== false);

            // Proxy server settings
            if (isset($config->Proxy->host) && !$skipProxy) {
                $options['adapter'] = 'Zend_Http_Client_Adapter_Proxy';
                $options['proxy_host'] = $config->Proxy->host;
                if (isset($config->Proxy->port)) {
                    $options['proxy_port'] = $config->Proxy->port;
                }
            } else {
                // Default if no proxy settings found:
                $options['adapter'] = 'Zend_Http_Client_Adapter_Socket';
            }
        }

        // Send the request via the parent class
        parent::__construct($url, $options);
    }
}
