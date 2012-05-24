<?php
/**
 * Export support class
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
 * Export support class
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Export
{
    /**
     * Get bulk export options.
     *
     * @return array
     */
    public static function getBulkOptions()
    {
        static $options = false;

        if ($options === false) {
            $options = array();
            $config = VF_Config_Reader::getConfig();
            if (isset($config->BulkExport->enabled)
                && isset($config->BulkExport->options)
                && $config->BulkExport->enabled
            ) {
                foreach (explode(':', $config->BulkExport->options) as $option) {
                    if (isset($config->Export->$option)
                        && $config->Export->$option == true
                    ) {
                            $options[] = $option;
                    }
                }
            }
        }

        return $options;
    }

    /**
     * Get the URL for bulk export.
     *
     * @param Zend_View $view   View object (needed for URL generation)
     * @param string    $format Export format being used
     * @param array     $ids    Array of IDs to export (in source|id format)
     *
     * @return string
     */
    public static function getBulkUrl($view, $format, $ids)
    {
        $params = array();
        $params[] = 'f=' . urlencode($format);
        foreach ($ids as $id) {
            $params[] = urlencode('i[]') . '=' . urlencode($id);
        }
        $url = $view->fullUrl(
            $view->url(
                array('controller' => 'Cart', 'action' => 'doExport'), 'default',
                true
            )
        );
        $url .= '?' . implode('&', $params);

        return self::needsRedirect($format)
            ? self::getRedirectUrl($format, $url) : $url;
    }

    /**
     * Build callback URL for export.
     *
     * @param string $format   Export format being used
     * @param string $callback Callback URL for retrieving record(s)
     *
     * @return string
     */
    public static function getRedirectUrl($format, $callback)
    {
        // Grab configuration, since we may need it to fill in template:
        $config = VF_Config_Reader::getConfig();

        // Fill in special tokens in template:/*
        $exportConfig = VF_Config_Reader::getConfig('export');
        $template = $exportConfig->$format->redirectUrl;
        preg_match_all('/\{([^}]+)\}/', $template, $matches);
        foreach ($matches[1] as $current) {
            $parts = explode('|', $current);
            switch ($parts[0]) {
                
            case 'config':
            case 'encodedConfig':
                if (isset($config->{$parts[1]}->{$parts[2]})) {
                    $value = $config->{$parts[1]}->{$parts[2]};
                } else {
                    $value = $parts[3];
                }
                if ($parts[0] == 'encodedConfig') {
                    $value = urlencode($value);
                }
                $template = str_replace('{' . $current . '}', $value, $template);
                break;
            case 'encodedCallback':
                $template = str_replace(
                    '{' . $current . '}', urlencode($callback), $template
                );
                break;
            }
        }
        return $template;
    }

    /**
     * Does the requested format require a redirect?
     *
     * @param string $format Format to check
     *
     * @return bool
     */
    public static function needsRedirect($format)
    {
        $exportConfig = VF_Config_Reader::getConfig('export');
        return isset($exportConfig->$format->redirectUrl);
    }

    /**
     * Convert an array of individual records into a single string for display.
     *
     * @param string $format Format of records to process
     * @param array  $parts  Multiple records to process
     *
     * @return string
     */
    public static function processGroup($format, $parts)
    {
        // Load export configuration:
        $exportConfig = VF_Config_Reader::getConfig('export');

        // If we're in XML mode, we need to do some special processing:
        if (isset($exportConfig->$format->combineXpath)) {
            $ns = array();
            if (isset($exportConfig->$format->combineNamespaces)) {
                foreach ($exportConfig->$format->combineNamespaces as $current) {
                    $ns[] = explode('|', $current, 2);
                }
            }
            foreach ($parts as $part) {
                // Convert text into XML object:
                $current = simplexml_load_string($part);

                // The first record gets kept as-is; subsequent records get merged
                // in based on the configured XPath (currently only one level is
                // supported)...
                if (!isset($retVal)) {
                    $retVal = $current;
                } else {
                    foreach ($ns as $n) {
                        $current->registerXPathNamespace($n[0], $n[1]);
                    }
                    $matches = $current->xpath($exportConfig->$format->combineXpath);
                    foreach ($matches as $match) {
                        VF_SimpleXML::appendElement($retVal, $match);
                    }
                }
            }
            return $retVal->asXML();
        } else {
            // Not in XML mode -- just concatenate everything together:
            return implode('', $parts);
        }
    }

    /**
     * Set headers for the requested format.
     *
     * @param string                            $format   Selected export format
     * @param Zend_Controller_Response_Abstract $response Response object to modify
     *
     * @return void
     */
    public static function setHeaders($format, $response)
    {
        $exportConfig = VF_Config_Reader::getConfig('export');
        if (isset($exportConfig->$format->headers)) {
            $headerTypes = array();
            foreach ($exportConfig->$format->headers as $header) {
                // Keep track of which header keys we have already seen -- we
                // want to allow duplicate values, so if we've previously encountered
                // a particular key, we should set the "replace" parameter of the
                // setHeader() method to false:
                $parts = explode(':', $header, 2);
                $key = strtolower($parts[0]);
                $response->setHeader(
                    trim($parts[0]), trim($parts[1]), !isset($headerTypes[$key])
                );
                $headerTypes[$key] = 1;
            }
        }
    }
}
