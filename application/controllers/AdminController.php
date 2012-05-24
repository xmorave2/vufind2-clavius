<?php
/**
 * Admin Controller
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
 * Class controls VuFind administration.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class AdminController extends VF_Controller_Action
{
    /**
     * preDispatch -- block access when appropriate.
     *
     * @return void
     */
    public function preDispatch()
    {
        // If we're using the "disabled" action, we don't need to do any further
        // checking to see if we are disabled!!
        if (strtolower($this->_request->getActionName()) == 'disabled') {
            return;
        }

        // Block access to everyone when module is disabled:
        $config = VF_Config_Reader::getConfig();
        if (!isset($config->Site->admin_enabled) || !$config->Site->admin_enabled) {
            return $this->_forward('Disabled');
        }

        // Block access by IP when IP checking is enabled:
        if (isset($config->AdminAuth->ipRegEx)) {
            $ipMatch = preg_match(
                $config->AdminAuth->ipRegEx,
                $this->_request->getServer('REMOTE_ADDR')
            );
            if (!$ipMatch) {
                throw new VF_Exception_Forbidden('Access denied.');
            }
        }

        // Block access by username when user whitelist is enabled:
        if (isset($config->AdminAuth->userWhitelist)) {
            $user = VF_Account_Manager::getInstance()->isLoggedIn();
            if ($user == false) {
                return $this->forceLogin();
            }
            $matchFound = false;
            foreach ($config->AdminAuth->userWhitelist as $check) {
                if ($check == $user->username) {
                    $matchFound = true;
                    break;
                }
            }
            if (!$matchFound) {
                throw new VF_Exception_Forbidden('Access denied.');
            }
        }
    }

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        $this->view->flashMessenger = $this->_helper->flashMessenger;
        $this->view->action = $this->_request->getActionName();

        // No search box in admin module:
        $this->view->layout()->searchbox = false;
    }

    /**
     * Display disabled message.
     *
     * @return void
     */
    public function disabledAction()
    {
    }

    /**
     * Admin home.
     *
     * @return void
     */
    public function homeAction()
    {
        $config = VF_Config_Reader::getConfig();
        $xml = false;
        if (isset($config->Index->url)) {
            $client = new VF_Http_Client($config->Index->url . '/admin/multicore');
            $response = $client->request('GET');
            $xml = $response->isError() ? false : $response->getBody();
        }
        $this->view->xml = $xml ? simplexml_load_string($xml) : false;
    }

    /**
     * Statistics reporting
     *
     * @return void
     */
    public function statisticsAction()
    {
    }

    /**
     * Configuration management
     *
     * @return void
     */
    public function configAction()
    {
        $this->view->baseConfigPath = VF_Config_Reader::getBaseConfigPath('');
        $conf = VF_Config_Reader::getConfig();
        $this->view->showInstallLink
            = isset($conf->System->autoConfigure) && $conf->System->autoConfigure;
    }

    /**
     * Support action for config -- attempt to enable auto configuration.
     *
     * @return void
     */
    public function enableautoconfigAction()
    {
        $configDir = LOCAL_OVERRIDE_DIR . '/application/configs';
        $configFile = $configDir . '/config.ini';
        $writer = new VF_Config_Writer($configFile);
        $writer->set('System', 'autoConfigure', 1);
        if (@$writer->save()) {
            $this->view->flashMessenger->setNamespace('info')
                ->addMessage('Auto-configuration enabled.');

            // Reload config now that it has been edited (otherwise, old setting
            // will persist in cache):
            VF_Config_Reader::getConfig(null, true);
        } else {
            $this->view->flashMessenger->setNamespace('error')
                ->addMessage(
                    'Could not enable auto-configuration; check permissions on '
                    . $configFile . '.'
                );
        }
        return $this->_forward('Config');
    }

    /**
     * System maintenance
     *
     * @return void
     */
    public function maintenanceAction()
    {
    }

    /**
     * Support action for maintenance -- delete expired searches.
     *
     * @return void
     */
    public function deleteexpiredsearchesAction()
    {
        $daysOld = intval($this->_request->getParam('daysOld', 2));
        if ($daysOld < 2) {
            $this->view->flashMessenger->setNamespace('error')
                ->addMessage(
                    'Expiration age must be at least two days.'
                );
        } else {
            // Delete the expired searches--this cleans up any junk left in the
            // database from old search histories that were not caught by the
            // session garbage collector.
            $search = new VuFind_Model_Db_Search();
            $expired = $search->getExpiredSearches($daysOld);
            if (count($expired) == 0) {
                $msg = "No expired searches to delete.";
            } else {
                $count = count($expired);
                foreach ($expired as $oldSearch) {
                    $oldSearch->delete();
                }
                echo $msg = "{$count} expired searches deleted.";
            }
            $this->view->flashMessenger->setNamespace('info')->addMessage($msg);
        }
        return $this->_forward('Maintenance');
    }
}

