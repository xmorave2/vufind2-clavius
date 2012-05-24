<?php
/**
 * VuFind Record Controller
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * VuFind Record Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VF_Controller_Record extends VF_Controller_Action
{
    protected $defaultTab = 'Holdings';
    protected $account;
    protected $searchClassId = 'Solr';
    protected $searchObject;
    protected $useResultScroller = true;

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        // Get user status:
        $this->account = VF_Account_Manager::getInstance();

        // Set up flash messages:
        $this->view->flashMessenger = $this->_helper->flashMessenger;

        // Set up search class ID-related settings:
        $this->searchObject = 'VF_Search_' . $this->searchClassId . '_Results';
        $this->view->searchClassId = $this->searchClassId;
    }

    /**
     * Magic method for handling undefined methods; this makes it easier to
     * add new tabs to the record view without modifying controllers.
     *
     * @param string $method Method name being called.
     * @param array  $params Parameters passed to method.
     *
     * @return void
     */
    public function __call($method, $params)
    {
        if (substr($method, -6) == 'Action') {
            return $this->showTab(substr($method, 0, strlen($method) - 6));
        }
        throw new Exception('Unsupported method: ' . $method);
    }

    /**
     * Add a comment
     *
     * @return void
     */
    public function addcommentAction()
    {
        // Force login:
        if (!($user = $this->account->isLoggedIn())) {
            // Remember comment since POST data will be lost:
            return $this->forceLogin(
                null, array('comment' => $this->_request->getParam('comment'))
            );
        }

        // Obtain the current record object:
        $this->loadRecord();

        // Save comment:
        $comment = $this->_request->getParam('comment');
        if (empty($comment)) {
            // No comment?  Try to restore from session:
            $session = $this->_helper->followup->retrieve();
            if (isset($session->comment)) {
                $comment = $session->comment;
                unset($session->comment);
            }
        }

        // At this point, we should have a comment to save; if we do not,
        // something has gone wrong (or user submitted blank form) and we
        // should do nothing:
        if (!empty($comment)) {
            $table = new VuFind_Model_Db_Resource();
            $resource = $table->findResource(
                $this->view->driver->getUniqueId(),
                $this->view->driver->getResourceSource(), true, $this->view->driver
            );
            $resource->addComment($comment, $user);
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage('add_comment_success');
        } else {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('add_comment_fail_blank');
        }

        return $this->redirectToRecord('', array('action' => 'UserComments'));
    }

    /**
     * Delete a comment
     *
     * @return void
     */
    public function deletecommentAction()
    {
        // Force login:
        if (!($user = $this->account->isLoggedIn())) {
            return $this->forceLogin();
        }
        $id = $this->_request->getParam('delete');
        $table = new VuFind_Model_Db_Comments();
        if (!is_null($id) && $table->deleteIfOwnedByUser($id, $user)) {
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage('delete_comment_success');
        } else {
            $this->_helper->flashMessenger->setNamespace('error')
                ->addMessage('delete_comment_failure');
        }
        return $this->redirectToRecord('', array('action' => 'UserComments'));
    }

    /**
     * Add a tag
     *
     * @return void
     */
    public function addtagAction()
    {
        // Force login:
        if (!($user = $this->account->isLoggedIn())) {
            return $this->forceLogin();
        }

        // Obtain the current record object:
        $this->loadRecord();

        // Save tags, if any:
        if ($this->_request->getParam('submit')) {
            $this->view->driver->addTags($user, $this->_request->getParam('tag'));
            return $this->redirectToRecord();
        }

        $this->render('record/addtag', null, true);
    }

    /**
     * Home (default) action -- forward to default tab.
     *
     * @return void
     */
    public function homeAction()
    {
        // Forward to default tab (first fixing it if it is invalid):
        $this->loadRecord();
        $tabs = $this->view->driver->getTabs();
        if (!isset($tabs[$this->defaultTab])) {
            $keys = array_keys($tabs);
            $this->defaultTab = $keys[0];
        }
        return $this->_forward($this->defaultTab);
    }

    /**
     * ProcessSave action -- store the results of the Save action.
     *
     * @return void
     */
    public function processsaveAction()
    {
        // Retrieve user object and force login if necessary:
        if (!($user = $this->account->isLoggedIn())) {
            return $this->forceLogin();
        }

        // Perform the save operation:
        $this->loadRecord();
        $this->view->driver->saveToFavorites($this->_request->getParams(), $user);

        // Grab the followup namespace so we know where to send the user next:
        $followup = new Zend_Session_Namespace($this->searchObject . 'SaveFollowup');
        if (isset($followup->url) && !empty($followup->url)) {
            // Display a success status message:
            $this->_helper->flashMessenger->setNamespace('info')
                ->addMessage('bulk_save_success');

            // Clear followup URL in session -- we're done with it now:
            $url = $followup->url;
            unset($followup->url);

            // Redirect!
            return $this->_redirect($url, array('prependBase' => false));
        }

        // No followup info found?  Send back to record view:
        return $this->redirectToRecord();
    }

    /**
     * Save action - Allows the save template to appear,
     *   passes containingLists & nonContainingLists
     *
     * @return void
     */
    public function saveAction()
    {
        // Process form submission:
        if ($this->_request->getParam('submit')) {
            return $this->_forward('ProcessSave');
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->account->isLoggedIn())) {
            return $this->forceLogin();
        }

        // If we got this far, we should save the referer for later use by the
        // ProcessSave action (to get back to where we came from after saving).
        // We only save if we don't already have a saved URL; otherwise we
        // might accidentally redirect to the "create new list" screen!
        $followup = new Zend_Session_Namespace($this->searchObject . 'SaveFollowup');
        $followup->url = (isset($followup->url) && !empty($followup->url))
            ? $followup->url : $this->_request->getServer('HTTP_REFERER');

        // Retrieve the record driver:
        $this->loadRecord();

        // Find out if the item is already part of any lists; save list info/IDs
        $listIds = array();
        $resources = $user->getSavedData(
            $this->view->driver->getUniqueId(), null,
            $this->view->driver->getResourceSource()
        );
        foreach ($resources as $userResource) {
            $listIds[] = $userResource->list_id;
        }

        // Loop through all user lists and sort out containing/non-containing lists
        $this->view->containingLists = $this->view->nonContainingLists = array();
        foreach ($user->getLists() as $list) {
            // Assign list to appropriate array based on whether or not we found
            // it earlier in the list of lists containing the selected record.
            if (in_array($list->id, $listIds)) {
                $this->view->containingLists[] = array(
                    'id' => $list->id, 'title' => $list->title
                );
            } else {
                $this->view->nonContainingLists[] = array(
                    'id' => $list->id, 'title' => $list->title
                );
            }
        }

        $this->render('record/save', null, true);
    }

    /**
     * Email action - Allows the email form to appear.
     *
     * @return void
     */
    public function emailAction()
    {
        // Retrieve the record driver:
        $this->loadRecord();

        // Process form submission:
        if ($this->_request->getParam('submit')) {
            // Send parameters back to view so form can be re-populated:
            $this->view->to = $this->_request->getParam('to');
            $this->view->from = $this->_request->getParam('from');
            $this->view->message = $this->_request->getParam('message');

            // Attempt to send the email and show an appropriate flash message:
            try {
                $mailer = new VF_Mailer();
                $mailer->sendRecord(
                    $this->view->to, $this->view->from, $this->view->message,
                    $this->view->driver, $this->view
                );
                $this->_helper->flashMessenger->setNamespace('info')
                    ->addMessage('email_success');
                return $this->redirectToRecord();
            } catch (VF_Exception_Mail $e) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }

        // Display the template:
        $this->render('record/email', null, true);
    }

    /**
     * SMS action - Allows the SMS form to appear.
     *
     * @return void
     */
    public function smsAction()
    {
        // Retrieve the record driver:
        $this->loadRecord();

        // Load the SMS carrier list:
        $mailer = new VF_Mailer_SMS();
        $this->view->carriers = $mailer->getCarriers();

        // Process form submission:
        if ($this->_request->getParam('submit')) {
            // Send parameters back to view so form can be re-populated:
            $this->view->to = $this->_request->getParam('to');
            $this->view->provider = $this->_request->getParam('provider');

            // Attempt to send the email and show an appropriate flash message:
            try {
                $mailer->textRecord(
                    $this->view->provider, $this->view->to, $this->view->driver,
                    $this->view
                );
                $this->_helper->flashMessenger->setNamespace('info')
                    ->addMessage('sms_success');
                return $this->redirectToRecord();
            } catch (VF_Exception_Mail $e) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage($e->getMessage());
            }
        }

        // Display the template:
        $this->render('record/sms', null, true);
    }

    /**
     * Show citations for the current record.
     *
     * @return void
     */
    public function citeAction()
    {
        $this->loadRecord();
        $this->render('record/cite', null, true);
    }

    /**
     * Export the record
     *
     * @return void
     */
    public function exportAction()
    {
        $this->loadRecord();
        $format = $this->_request->getParam('style');

        // Display export menu if missing/invalid option
        if (empty($format) || !$this->view->driver->supportsExport($format)) {
            if (!empty($format)) {
                $this->_helper->flashMessenger->setNamespace('error')
                    ->addMessage('export_invalid_format');
            }
            return $this->render('record/export-menu', null, true);
        }

        // If this is an export format that redirects to an external site, perform
        // the redirect now (unless we're being called back from that service!):
        if (VF_Export::needsRedirect($format)
            && !$this->_request->getParam('callback')
        ) {
            // Build callback URL:
            $callback = $this->view->fullUrl(
                $this->view->url() . '?callback=1&style=' . urlencode($format)
            );

            return $this->_redirect(VF_Export::getRedirectUrl($format, $callback));
        }

        // Send appropriate HTTP headers for requested format:
        VF_Export::setHeaders($format, $this->getResponse());

        // Turn off layouts and rendering -- we only want to display export data!
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        // Actually export the record
        $this->getResponse()->appendBody(
            $this->view->record($this->view->driver)->getExport($format)
        );
    }

    /**
     * Special action for RDF export
     *
     * @return void
     */
    public function rdfAction()
    {
        $this->_request->setParam('style', 'RDF');
        return $this->_forward('Export');
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @return void
     */
    protected function loadRecord()
    {
        // Only load the record if it has not already been loaded:
        if (!isset($this->view->driver)) {
            $this->view->driver = call_user_func(
                array($this->searchObject, 'getRecord'),
                $this->_request->getParam('id')
            );
        }
    }

    /**
     * Redirect the user to the main record view.
     *
     * @param string $params  Parameters to append to record URL.
     * @param array  $options Options to add to the array sent to the router
     *
     * @return void
     */
    protected function redirectToRecord($params = '', $options = array())
    {
        $this->loadRecord();
        $router = Zend_Controller_Front::getInstance()->getRouter();
        $target = $router->assemble(
            $options + array('id' => $this->view->driver->getUniqueId()),
            $this->view->driver->getRecordRoute(), true, false
        );
        return $this->_redirect($target . $params, array('prependBase' => false));
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab Name of tab to display
     *
     * @return void
     */
    protected function showTab($tab)
    {
        // Special case -- handle login request (currently needed for holdings
        // tab when driver-based holds mode is enabled, but may also be useful
        // in other circumstances):
        if ($this->_request->getParam('login', 'false') == 'true'
            && !VF_Account_Manager::getInstance()->isLoggedIn()
        ) {
            return $this->forceLogin(null);
        } else if ($this->_request->getParam('catalogLogin', 'false') == 'true'
            && !$this->catalogLogin()
        ) {
            return;
        }

        $this->loadRecord();
        $this->view->tab = $tab;
        // Set up next/previous record links (if appropriate)
        if ($this->useResultScroller) {
            $scroller = new VF_Search_ResultScroller();
            $this->view->scrollData
                = $scroller->getScrollData($this->_request->getParam('id'));
        }
        $this->render('record/view', null, true);
    }
}