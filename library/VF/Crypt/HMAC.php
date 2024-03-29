<?php
/**
 * HMAC hash generator
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
 * @category VuFind2
 * @package  Crypt
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */

/**
 * HMAC hash generator wrapper
 *
 * @category VuFind2
 * @package  Crypt
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class VF_Crypt_HMAC
{
    /**
     * Accepts $keysToHash, a list of array keys, and $keyValueArray, a keyed array
     *
     * @param array $keysToHash    A list of keys to hash
     * @param array $keyValueArray A keyed array
     *
     * @return sting A hash_hmac string using md5
     */
    public static function generate($keysToHash, $keyValueArray)
    {
        $config = VF_Config_Reader::getConfig();
        $str = '';
        foreach ($keysToHash as $key) {
            $value = isset($keyValueArray[$key]) ? $keyValueArray[$key] : '';
            $str .= $key . '=' . $value . '|';
        }
        return hash_hmac('md5', $str, $config->Security->HMACkey);
    }
}
