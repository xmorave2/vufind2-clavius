<?php
/**
 * WorldCat Controller
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
 
/**
 * WorldCat Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class WorldcatController extends VF_Controller_Search
{
    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $this->searchClassId = 'WorldCat';
        $this->useResultScroller = false;
        parent::init();
    }

    /**
     * Home action
     *
     * @return void
     */
    public function homeAction()
    {
        // Do nothing -- just display template
    }

    /**
     * Search action -- call standard results action
     *
     * @return void
     */
    public function searchAction()
    {
        $this->resultsAction();
    }

    /**
     * Forward unrecognized actions to record controller for legacy URL
     * compatibility.
     *
     * @param string $method Method name being called.
     * @param array  $params Parameters passed to method.
     *
     * @return void
     */
    public function __call($method, $params)
    {
        if (substr($method, -6) == 'Action') {
            $action = substr($method, 0, strlen($method) - 6);
            // Special case for default record action:
            if ($action == 'record') {
                $action = 'home';
            }
            return $this->_forward($action, 'WorldCatRecord');
        }
        throw new Exception('Unsupported method: ' . $method);
    }
}

