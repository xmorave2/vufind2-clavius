<?php
/**
 * Row Definition for tags
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
 * @category VuFind
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * Row Definition for tags
 *
 * @category VuFind
 * @package  DB_Models
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Model_Db_TagsRow extends Zend_Db_Table_Row_Abstract
{
    /**
     * Get all resources associated with the current tag.
     *
     * @param string $source Record source (optional limiter)
     * @param string $sort   Resource field to sort on (optional)
     *
     * @return array
     * @access public
     */
    public function getResources($source = null, $sort = null)
    {
        // Set up base query:
        $table = new VuFind_Model_Db_ResourceTags();
        $select = $table->select();
        $select->setIntegrityCheck(false)   // allow join
            ->distinct()
            ->from(array('r' => 'resource'), 'r.*')
            ->join(
                array('rt' => 'resource_tags'),
                'r.id = rt.resource_id',
                array()
            )
            ->where('rt.tag_id = ?', $this->id);

        if (!empty($source)) {
            $select->where('r.source = ?', $source);
        }

        if (!empty($sort)) {
            VuFind_Model_Db_Resource::applySort($select, $sort);
        }

        return $table->fetchAll($select);
    }
}
