<?php
/**
 * VF_Log_Writer_Mail
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
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

/**
 * This class extends the Zend Logging towards Mail systems
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Log_Writer_Mail extends Zend_Log_Writer_Mail
{
    /**
     * Verbosity filter
     *
     * @var int
     */
    protected $verbosity;

    /**
     * Class constructor.
     *
     * Constructs the mail writer; requires a Zend_Mail instance, and takes an
     * optional Zend_Layout instance.  If Zend_Layout is being used,
     * $this->_layout->events will be set for use in the layout template.
     *
     * @param Zend_Mail   $mail   Mail instance
     * @param Zend_Layout $layout Layout instance; optional
     * @param int         $verb   Verbosity level (1-5)
     *
     * @return void
     */
    public function __construct(Zend_Mail $mail, Zend_Layout $layout=null, $verb=1)
    {
        $this->verbosity = $verb;
        parent::__construct($mail, $layout);
    }

    /**
     * Create a new instance of Zend_Log_Writer_Mail
     *
     * @param array|Zend_Config $config - the configuration object
     *
     * @return Zend_Log_Writer_Mail
     */
    static public function factory($config)
    {
        $writer = parent::factory($config);
        if (isset($config['verbosity'])) {
            $writer->setVerbosity($config['verbosity']);
        }
        return $writer;
    }

    /**
     * Set verbosity
     *
     * @param integer $verb - the verbosity value
     *
     * @return void
     */
    public function setVerbosity($verb)
    {
        $this->verbosity = $verb;
    }

    /**
     * Write a message to the log.
     *
     * @param array $event - event data
     *
     * @return void
     * @throws Zend_Log_Exception
     */
    protected function _write($event)
    {
        // Apply verbosity filter:
        if (is_array($event['message'])) {
            $event['message'] = $event['message'][$this->verbosity];
        }

        // Call parent method:
        return parent::_write($event);
    }
}