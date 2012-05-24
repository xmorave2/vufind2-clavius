<?php
/**
 * Record driver view helper
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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */

/**
 * Record driver view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
class VuFind_Theme_Root_Helper_Record extends Zend_View_Helper_Abstract
{
    protected $driver;

    /**
     * Render a template within a record driver folder.
     *
     * @param string $name    Template name to render
     * @param mixed  $context Context for rendering template (default = record
     * driver only).
     *
     * @return string
     */
    protected function renderTemplate($name, $context = null)
    {
        // Set default context if none provided:
        if (is_null($context)) {
            $context = array('driver' => $this->driver);
        }

        // Get the current record driver's class name, then start a loop
        // in case we need to use a parent class' name to find the appropriate
        // template.
        $className = get_class($this->driver);
        while (true) {
            // Guess the template name for the current class:
            $classParts = explode('_', $className);
            $template = 'RecordDriver/' . array_pop($classParts) . '/' . $name;
            try {
                // Try to render the template....
                return $this->view->partial($template, $context);
            } catch (Zend_View_Exception $e) {
                // If the template doesn't exist, let's see if we can inherit a
                // template from a parent class:
                $className = get_parent_class($className);
                if (empty($className)) {
                    // No more parent classes left to try?  Throw an exception!
                    throw new Zend_View_Exception(
                        'Cannot find ' . $name . ' template for record driver: ' .
                        get_class($this->driver)
                    );
                }
            }
        }
    }

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param VF_RecordDriver_Base $driver Record driver object.
     *
     * @return VuFind_Theme_Root_Helper_Record
     */
    public function record($driver)
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Render the core metadata area of the record view.
     *
     * @return string
     */
    public function getCoreMetadata()
    {
        return $this->renderTemplate('core.phtml');
    }

    /**
     * Export the record in the requested format.  For legal values, see
     * getExportFormats().
     *
     * @param string $format Export format to display
     *
     * @return string        Exported data
     */
    public function getExport($format)
    {
        $format = strtolower($format);
        return $this->renderTemplate(
            'export-' . $format . '.phtml', array('driver' => $this->driver)
        );
    }

    /**
     * Get an array of strings representing formats in which this record's 
     * data may be exported (empty if none).  Legal values: "RefWorks", 
     * "EndNote", "MARC", "RDF".
     *
     * @return array Strings representing export formats.
     * @access public
     */
    public function getExportFormats()
    {
        $config = VF_Config_Reader::getConfig();
        $exportConfig = VF_Config_Reader::getConfig('export');

        // Get an array of enabled export formats (from config, or use defaults
        // if nothing in config array).
        $active = isset($config->Export)
            ? $config->Export->toArray()
            : array('RefWorks' => true, 'EndNote' => true);

        // Loop through all possible formats:
        $formats = array();
        foreach ($exportConfig as $format => $details) {
            if (isset($active[$format]) && $active[$format]
                && $this->driver->supportsExport($format)
            ) {
                $formats[] = $format;
            }
        }

        // Send back the results:
        return $formats;
    }

    /**
     * Get the CSS class used to properly render a format.  (Note that this may
     * not be used by every theme).
     *
     * @param string $format Format text to convert into CSS class
     *
     * @return string
     */
    public function getFormatClass($format)
    {
        return $this->renderTemplate(
            'format-class.phtml', array('format' => $format)
        );
    }

    /**
     * Render a list of record formats.
     *
     * @return string
     */
    public function getFormatList()
    {
        return $this->renderTemplate('format-list.phtml');
    }

    /**
     * Render an entry in a favorite list.
     *
     * @param VuFind_Model_Db_UserListRow $list Currently selected list (null for
     * combined favorites)
     * @param VuFind_Model_Db_UserRow     $user Current logged in user (false if
     * none)
     *
     * @return string
     */
    public function getListEntry($list = null, $user = false)
    {
        // Get list of lists containing this entry
        $lists = null;
        if ($user) {
            $lists = $this->driver->getContainingLists($user->id);
        }
        return $this->renderTemplate(
            'list-entry.phtml',
            array(
                'driver' => $this->driver,
                'list' => $list,
                'user' => $user,
                'lists' => $lists
            )
        );
    }

    /**
     * Render previews of the item if configured.
     *
     * @return string
     */
    public function getPreviews()
    {
        $config = VF_Config_Reader::getConfig();
        return $this->renderTemplate(
            'preview.phtml',
            array('driver' => $this->driver, 'config' => $config)
        );
    }

    /**
     * Get the name of the controller used by the record route.
     *
     * @return string
     */
    public function getController()
    {
        $router = Zend_Controller_Front::getInstance()->getRouter();
        $route = $router->getRoute($this->driver->getRecordRoute());
        return $this->view->escape($route->getDefault('controller'));
    }

    /**
     * Render the link of the specified type.
     *
     * @param string $type    Link type
     * @param string $lookfor String to search for at link
     *
     * @return string
     */
    public function getLink($type, $lookfor)
    {
        return $this->renderTemplate(
            'link-' . $type . '.phtml', array('lookfor' => $lookfor)
        );
    }

    /**
     * Render the contents of the specified record tab.
     *
     * @param string $tab Tab to display
     *
     * @return string
     */
    public function getTab($tab)
    {
        // Maintain full view context rather than default driver/data-only context:
        return $this->renderTemplate('tab-' . $tab . '.phtml', $this->view);
    }

    /**
     * Render a search result for the specified view mode.
     *
     * @param string $view View mode to use.
     *
     * @return string
     */
    public function getSearchResult($view)
    {
        return $this->renderTemplate('result-' . $view . '.phtml');
    }

    /**
     * Render an HTML checkbox control for the current record.
     *
     * @param string $idPrefix Prefix for checkbox HTML ids
     *
     * @return string
     */
    public function getCheckbox($idPrefix = '')
    {
        static $checkboxCount = 0;
        $id = $this->driver->getResourceSource() . '|'
            . $this->driver->getUniqueId();
        return $this->view->partial(
            'record/checkbox.phtml',
            array('id' => $id, 'count' => $checkboxCount++, 'prefix' => $idPrefix)
        );
    }
}