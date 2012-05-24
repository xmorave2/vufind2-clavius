<?php
/**
 * Cover Controller
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
 * @link     http://www.vufind.org  Main Page
 */

/**
 * Generates covers for book entries
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class CoverController extends Zend_Controller_Action
{
    protected $loader;

    /**
     * init
     *
     * @return void
     */
    public function init()
    {
        // We don't want to use views or layouts in this controller since
        // it is responsible for generating images rather than HTML.
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        // Construct object for loading cover images:
        $this->loader = new VF_Cover_Loader();
    }

    /**
     * Send image data for display in the view
     *
     * @return void
     */
    public function showAction()
    {
        $this->loader->loadImage(
            $this->_request->getParam('isn'),
            $this->_request->getParam('size'),
            $this->_request->getParam('contenttype')
        );
        $this->displayImage();
    }

    /**
     * Return the default 'image not found' information
     *
     * @return void
     */
    public function unavailableAction()
    {
        $this->loader->loadUnavailable();
        $this->displayImage();
    }

    /**
     * Support method -- update the view to display the image currently found in the
     * VF_Cover_Loader.
     *
     * @return void
     */
    protected function displayImage()
    {
        $this->getResponse()->setHeader(
            'Content-type', $this->loader->getContentType()
        );
        $this->getResponse()->appendBody($this->loader->getImage());
    }
}

