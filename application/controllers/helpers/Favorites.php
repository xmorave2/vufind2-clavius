<?php
/**
 * VuFind Action Helper - Favorites Support Methods
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
 * Zend action helper to perform favorites-related actions
 *
 * @category VuFind2
 * @package  Action_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class VuFind_Action_Helper_Favorites extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * Default method -- get access to the object so other methods may be called.
     *
     * @return VuFind_Action_Helper_Renewals
     */
    public function direct()
    {
        return $this;
    }

    /**
     * Save a group of records to the user's favorites.
     *
     * @param array             $params Array with some or all of these keys:
     *  <ul>
     *    <li>ids - Array of IDs in source|id format</li>
     *    <li>mytags - Unparsed tag string to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param Zend_Db_Table_Row $user   The user saving the record
     *
     * @return void
     */
    public function saveBulk($params, $user)
    {
        // Validate incoming parameters:
        if (!$user) {
            throw new VF_Exception_LoginRequired('You must be logged in first');
        }

        // Get or create a list object as needed:
        $listId = isset($params['list']) ? $params['list'] : '';
        if (empty($listId) || $listId == 'NEW') {
            $list = VuFind_Model_Db_UserList::getNew($user);
            $list->title = VF_Translator::translate('My Favorites');
            $list->save();
        } else {
            $list = VuFind_Model_Db_UserList::getExisting($listId);
            $list->rememberLastUsed(); // handled by save() in other case
        }

        // Loop through all the IDs and save them:
        foreach ($params['ids'] as $current) {
            // Break apart components of ID:
            list($source, $id) = explode('|', $current, 2);

            // Get or create a resource object as needed:
            $resourceTable = new VuFind_Model_Db_Resource();
            $resource = $resourceTable->findResource($id, $source);

            // Add the information to the user's account:
            $user->saveResource(
                $resource, $list,
                isset($params['mytags']) ? VF_Tags::parse(trim($params['mytags'])) : '',
                '', false
            );
        }
    }

    /**
     * Delete a group of favorites.
     *
     * @param array                      $ids    Array of IDs in source|id format.
     * @param mixed                      $listID ID of list to delete from (null for
     * all lists)
     * @param Zend_Db_Table_Row_Abstract $user   Logged in user
     *
     * @return void
     */
    public function delete($ids, $listID, $user)
    {
        // Sort $ids into useful array:
        $sorted = array();
        foreach ($ids as $current) {
            list($source, $id) = explode('|', $current, 2);
            if (!isset($sorted[$source])) {
                $sorted[$source] = array();
            }
            $sorted[$source][] = $id;
        }

        // Both user and list objects have identical removeResourcesById methods,
        // so we just need to pick an appropriate object based on $listID:
        $object = !empty($listID)
            ? VuFind_Model_Db_UserList::getExisting($listID)    // Specific list
            : $user;                                            // Current user

        // Delete favorites one source at a time:
        foreach ($sorted as $source => $ids) {
            $object->removeResourcesById($ids, $source);
        }
    }
}