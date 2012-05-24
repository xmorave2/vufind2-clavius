<?php
/**
 * VF_Logger
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
 * This class wraps the Zend_Log class to allow for log verbosity
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

class VF_Log_Writer_Firebug extends Zend_Log_Writer_Firebug
{
    /**
     * Verbosity filter
     *
     * @var Zend_Db_Adapter
     */
    protected $verbosity = 1;

    /**
     * Class constructor
     *
     * @param integer $verb - the verbosity value
     *
     * @return void
     */
    public function __construct($verb = 1)
    {
        $this->verbosity = $verb;
        parent::__construct();
    }

    /**
     * Create a new instance of Zend_Log_Writer_Firebug
     *
     * @param array|Zend_Config $config - the configuration object
     *
     * @return Zend_Log_Writer_Firebug
     */
    static public function factory($config)
    {
        return new self($config['verbosity']);
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
     * Log a message to the Firebug Console.
     *
     * @param array $event The event data
     *
     * @return void
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
