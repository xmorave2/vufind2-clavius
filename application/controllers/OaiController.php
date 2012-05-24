<?php
/**
 * OAI Module Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */

/**
 * OAIController Class
 *
 * Controls the OAI server
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/alphabetical_heading_browse Wiki
 */
class OaiController extends Zend_Controller_Action
{
    /**
     * Display OAI server form.
     *
     * @return void
     */
    public function homeAction()
    {
        // no action needed
    }

    /**
     * Standard OAI server.
     *
     * @return void
     */
    public function authserverAction()
    {
        $this->handleOAI('VF_OAI_Server_Auth');
    }

    /**
     * Standard OAI server.
     *
     * @return void
     */
    public function serverAction()
    {
        $this->handleOAI('VF_OAI_Server');
    }

    /**
     * Shared OAI logic.
     *
     * @param string $serverClass Class to load for handling OAI requests.
     *
     * @return void
     */
    protected function handleOAI($serverClass)
    {
        // We don't want to use views or layouts in this action since
        // it is responsible for generating XML responses rather than HTML.
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        // Check if the OAI Server is enabled before continuing
        $config = VF_Config_Reader::getConfig();
        if (!isset($config->OAI)) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->getResponse()->appendBody('OAI Server Not Configured.');
            return;
        }

        // Collect relevant parameters for OAI server:
        $baseURL = $this->view->fullUrl($this->view->url());
        $params = $this->_request->getParams();

        // Don't pass VuFind-specific parameters down to OAI server:
        unset($params['controller']);
        unset($params['action']);

        // Build OAI response or die trying:
        try {
            $server = new $serverClass($baseURL, $params);
            $xml = $server->getResponse();
        } catch (Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
            $this->getResponse()->appendBody($e->getMessage());
            return;
        }

        // Return response:
        $this->getResponse()->setHeader('Content-type', 'text/xml');
        $this->getResponse()->appendBody($xml);
    }
}