<?php
/**
 * Records Controller
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
 * Records Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class RecordsController extends VF_Controller_Search
{
    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $this->searchClassId = 'MixedList';
        $this->useResultScroller = false;
        parent::init();
    }

    /**
     * Home action -- call standard results action
     *
     * @return void
     */
    public function homeAction()
    {
        // If there is exactly one record, send the user directly there:
        $ids = $this->_request->getParam('id', array());
        if (count($ids) == 1) {
            list($source, $id) = explode('|', $ids[0], 2);
            $driver = VF_Record::load($id, $source);
            $router = Zend_Controller_Front::getInstance()->getRouter();
            $target = $router->assemble(
                array('id' => $driver->getUniqueId()), $driver->getRecordRoute(),
                true, false
            );
            // forward print param, if necessary:
            $print = $this->_request->getParam('print');
            $params = empty($print) ? '' : '?print=' . urlencode($print);
            return $this->_redirect(
                $target . $params,
                array('prependBase' => false)
            );
        }

        // Not exactly one record -- show search results:
        $this->resultsAction();
    }
}

