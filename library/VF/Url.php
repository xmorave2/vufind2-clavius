<?php
/**
 * VuFind URL routines.
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * VuFind URL routines.
 *
 * @category VuFind2
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Url
{
    /**
     * Get VuFind's base URL (protocol and hostname only -- no path).
     *
     * Adapted from code by aduyng found here:
     *     http://forums.zend.com/viewtopic.php?f=69&p=6134#p27864
     *
     * @return string
     */
    public static function getBaseUrl()
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        $scheme = $request->getScheme();
        $host = $request->getHttpHost();

        // If we can't figure out the settings from the request, use the config:
        if (empty($scheme) || empty($host)) {
            $config = VF_Config_Reader::getConfig();
            preg_match('/([^:]+):\/\/([^\/]+)(.*)/', $config->Site->url, $matches);
            $scheme = $matches[1];
            $host = $matches[2];
        }

        return $scheme . '://' . $host;
    }
}