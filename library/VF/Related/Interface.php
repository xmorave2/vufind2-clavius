<?php
/**
 * Related Records Interface
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Related Records Interface
 *
 * This interface class is the definition of the required methods for
 * generating record recommendations.
 *
 * Note that every class implementing this interface needs to be accompanied by
 * a template file in the Related subdirectory of every theme's template
 * directory.  For example, VF_Related_Similar needs a corresponding
 * Related/Similar.phtml template.  The template will be rendered as a
 * partial with two available variables: related (the related records object)
 * and driver (the record driver representing the source record).
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
interface VF_Related_Interface
{
    /**
     * Constructor
     *
     * Establishes base settings for making recommendations.
     *
     * @param string               $settings Settings from config.ini
     * @param VF_RecordDriver_Base $driver   Record driver object
     */
    public function __construct($settings, $driver);
}
