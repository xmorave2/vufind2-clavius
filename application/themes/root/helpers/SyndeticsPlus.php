<?php
/**
 * SyndeticsPlus view helper
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * SyndeticsPlus view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VuFind_Theme_Root_Helper_SyndeticsPlus extends Zend_View_Helper_Abstract
{
    protected $config;

    /**
     * Provides access to other class methods.
     *
     * @return VuFind_Theme_Root_Helper_SyndeticsPlus
     */
    public function syndeticsPlus()
    {
        $this->config = VF_Config_Reader::getConfig();
        return $this;
    }

    /**
     * Is SyndeticsPlus active?
     *
     * @return bool
     */
    public function isActive()
    {
        return isset($this->config->Syndetics->plus)
            ? $this->config->Syndetics->plus : false;
    }

    /**
     * Get the SyndeticsPlus Javascript loader.
     *
     * @return string
     */
    public function getScript()
    {
        // Determine whether to include script tag for syndetics plus
        if (isset($this->config->Syndetics->plus_id)) {
            return "http://plus.syndetics.com/widget.php?id="
                . urlencode($this->config->Syndetics->plus_id);
        }

        return null;
    }
}