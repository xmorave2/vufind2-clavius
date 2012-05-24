<?php
/**
 * VuFind Action Helper - Followup
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
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Zend action helper to deal with login followup; responsible for remembering URLs
 * before login and then redirecting the user to the appropriate place afterwards.
 *
 * @category VuFind2
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VuFind_Action_Helper_Followup extends Zend_Controller_Action_Helper_Abstract
{
    protected $session;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->session = new Zend_Session_Namespace('Followup');
    }

    /**
     * Default method -- pass through to retrieve method.
     *
     * @return Zend_Session_Namespace
     */
    public function direct()
    {
        return $this->retrieve();
    }

    /**
     * Retrieve the stored followup information.
     *
     * @return Zend_Session_Namespace
     */
    public function retrieve()
    {
        return $this->session;
    }

    /**
     * Store the current URL (and optional additional information) in the session
     * for use following a successful login.
     *
     * @param array $extras Associative array of extra fields to store.
     *
     * @return void
     */
    public function store($extras = array())
    {
        // Store the current URL:
        $this->session->url = $this->getRequest()->getRequestUri();

        // Store the extra parameters:
        foreach ($extras as $key => $value) {
            $this->session->$key = $value;
        }
    }
}