<?php
/**
 * VF_Log_Writer_Db
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
 * This class extends the Zend Logging towards DB
 *
 * @category VuFind2
 * @package  Error_Logging
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Log_Writer_Db extends Zend_Log_Writer_Db
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
     * @param Zend_Db_Adapter_Mysqli $db        The database object
     * @param string                 $table     Name of the database table
     * @param array                  $columnMap Association array between
     * event object values and data columns
     * @param int                    $verb      Verbosity level
     *
     * @return void
     */
    public function __construct($db, $table, $columnMap = null, $verb = 1)
    {
        parent::__construct($db, $table, $columnMap);
        $this->verbosity = $verb;
    }

    /**
     * Create a new instance of Zend_Log_Writer_Db
     *
     * @param array|Zend_Config $config - the configuration object
     * 
     * @return Zend_Log_Writer_Db
     */
    static public function factory($config)
    {
        $config = self::_parseConfig($config);
        $config = array_merge(
            array(
                'db'        => null,
                'table'     => null,
                'verbosity' => null,
                'columnMap' => null
            ),
            $config
        );

        if (isset($config['columnmap'])) {
            $config['columnMap'] = $config['columnmap'];
        }

        return new self(
            $config['db'],
            $config['table'],
            $config['verbosity'],
            $config['columnMap']
        );
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
        $event['ident'] = 'vufind2';

        // Apply verbosity filter:
        if (is_array($event['message'])) {
            $event['message'] = $event['message'][$this->verbosity];
        }

        // Call parent method:
        return parent::_write($event);
    }
}
