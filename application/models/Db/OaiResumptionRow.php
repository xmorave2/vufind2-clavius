<?php
/**
 * Row Definition for oai_resumption
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
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * Row Definition for oai_resumption
 *
 * @category VuFind
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_OaiResumptionRow extends Zend_Db_Table_Row_Abstract
{
    /**
     * Extract an array of parameters from the object.
     *
     * @return array Original saved parameters.
     */
    public function restoreParams()
    {
        $parts = explode('&', $this->params);
        $params = array();
        foreach ($parts as $part) {
            list($key, $value) = explode('=', $part);
            $key = urldecode($key);
            $value = urldecode($value);
            $params[$key] = $value;
        }
        return $params;
    }

    /**
     * Encode an array of parameters into the object.
     *
     * @param array $params Parameters to save.
     *
     * @return void
     */
    public function saveParams($params)
    {
        ksort($params);
        $processedParams = array();
        foreach ($params as $key => $value) {
            $processedParams[] = urlencode($key) . '=' . urlencode($value);
        }
        $this->params = implode('&', $processedParams);
    }
}
