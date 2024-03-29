<?php
/**
 * jqueryValidation view helper
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
 * @package  View_Helpers
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org   Main Site
 * @link     http://www.jquery.com   jQuery Project Page
 */

/**
 * Print a formatted string so jquery metadata and validation plugins can understand.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Tuan Nguyen <tuan@yorku.ca>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org   Main Site
 * @link     http://www.jquery.com   jQuery Project Page
 */
class VuFind_Theme_Root_Helper_JqueryValidation extends Zend_View_Helper_Abstract
{
    /**
     * Print a formatted string so jquery metadata
     * and validation plugins can understand.
     *
     * @param array $params - rules to test jquery against
     *
     * @return string
     */
    public function jqueryValidation($params)
    {
        // jquery validation rules that this plugin currently supports
        $supported_rules = array('required', 'email', 'digits', 'equalTo',
            'phoneUS', 'mobileUK');
        $messages = array();
        $rules = array();
        foreach ($supported_rules as $rule) {
            if (isset($params[$rule])) {
                switch($rule) {
                case 'equalTo':
                    $rules[] = "equalTo:'" . $params['equalToField'] . "'";
                    $messages[$rule] = $params[$rule];
                    break;
                default:
                    $rules[] = "$rule:true";
                    $messages[$rule] = $params[$rule];
                    break;
                }
            }
        }

        // format the output
        $output = '{' . implode(',', $rules) . ',messages:{';
        $first = true;
        foreach ($messages as $rule => $message) {
            if (!$first) {
                $output .= ',';
            }
            $message = $this->view->translate($message);
            $output .= "$rule:'$message'";
            $first = false;
        }
        $output .= '}}';
        return $this->view->escape($output);
    }
}
