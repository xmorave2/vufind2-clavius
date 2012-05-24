<?php
/**
 * Router for Command Line Interface
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
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki Wiki
 */

/**
 * Router for Command Line Interface
 *
 * @category VuFind
 * @package  Support_Classes
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/system_classes#index_interface Wiki
 */
class VF_Router_Cli extends Zend_Controller_Router_Abstract
{
    /**
     * Processes a request and sets its controller and action.  If
     * no route was possible, an exception is thrown.
     *
     * @param Zend_Controller_Request_Abstract $dispatcher Dispatcher object
     *
     * @throws Zend_Controller_Router_Exception
     * @return Zend_Controller_Request_Abstract|boolean
     */
    public function route(Zend_Controller_Request_Abstract $dispatcher)
    {
        $dispatcher->setControllerName('cli');
        $action = str_replace('.php', '', basename($_SERVER['argv'][0]));
        $dispatcher->setActionName($action);
        return $dispatcher;
    }

    /**
     * Generates a URL path that can be used in URL creation, redirection, etc.
     *
     * May be passed user params to override ones from URI, Request or even defaults.
     * If passed parameter has a value of null, it's URL variable will be reset to
     * default.
     *
     * If null is passed as a route name assemble will use the current Route or
     * 'default' if current is not yet set.
     *
     * Reset is used to signal that all parameters should be reset to it's defaults.
     * Ignoring all URL specified values. User specified params still get precedence.
     *
     * Encode tells to url encode resulting path parts.
     *
     * @param array $userParams Options passed by a user used to override parameters
     * @param mixed $name       The name of a Route to use
     * @param bool  $reset      Whether to reset to the route defaults ignoring URL
     * params
     * @param bool  $encode     Tells to encode URL parts on output
     *
     * @throws Zend_Controller_Router_Exception
     * @return string Resulting URL path
     */
    public function assemble($userParams, $name = null, $reset = false,
        $encode = true
    ) {
        throw new Zend_Controller_Router_Exception("Not implemented");
    }
}
