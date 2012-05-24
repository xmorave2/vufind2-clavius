<?php
/**
 * VF_Log_Writer_Mock
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
 * Mock error messaging
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Log_Writer_Mock extends Zend_Log_Writer_Mock
{
    /**
     * Verbosity filter
     *
     * @var Zend_Db_Adapter
     */
    protected $verbosity = 1;
    
    /**
     * Constructor
     *
     * @param int $verb Verbosity level
     */
    public function __construct($verb = 1)
    {
        $this->verbosity = $verb;
    }
    
    /**
     * Write a message to the log.
     *
     * @param array $event - event data
     *
     * @return void
     */
    public function _write($event)
    {
        // Apply verbosity filter:
        if (is_array($event['message'])) {
            $event['message'] = $event['message'][$this->verbosity];
        }

        // Call parent method:
        return parent::_write($event);
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
     * Create a new instance of Zend_Log_Writer_Mock
     *
     * @param array|Zend_Config $config - configuration array/object
     *
     * @return Zend_Log_Writer_Mock
     */
    static public function factory($config)
    {
        return new self($config['verbosity']);
    }
}
