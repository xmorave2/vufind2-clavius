<?php
/**
 * Database session handler
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
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */

/**
 * Database session handler
 *
 * @category VuFind2
 * @package  Session_Handlers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/creating_a_session_handler Wiki
 */
class VF_Session_Database extends VF_Session_Base
{
    protected $table;

    /**
     * Constructor.
     *
     * @param Zend_Config $config Session configuration ([Session] section of
     * config.ini)
     */
    public function __construct($config)
    {
        // Create database connection:
        $this->table = new VuFind_Model_Db_Session();

        // Call standard session initialization from this point.
        parent::__construct($config);
    }

    /**
     * Read function must return string value always to make save handler work as
     * expected. Return empty string if there is no data to read.
     *
     * @param string $sess_id The session ID to read
     *
     * @return string
     */
    public function read($sess_id)
    {
        // Try to read the session, but destroy it if it has expired:
        try {
            return $this->table->readSession($sess_id, $this->lifetime);
        } catch (VF_Exception_SessionExpired $e) {
            $this->destroy($sess_id);
            return;
        }
    }

    /**
     * Write function that is called when session data is to be saved.
     *
     * @param string $sess_id The current session ID
     * @param string $data    The session data to write
     *
     * @return void
     */
    public function write($sess_id, $data)
    {
        $this->table->writeSession($sess_id, $data);
    }

    /**
     * The destroy handler, this is executed when a session is destroyed with
     * session_destroy() and takes the session id as its only parameter.
     *
     * @param string $sess_id The session ID to destroy
     *
     * @return void
     */
    public function destroy($sess_id)
    {
        // Perform standard actions required by all session methods:
        parent::destroy($sess_id);

        // Now do database-specific destruction:
        $this->table->destroySession($sess_id);
    }

    /**
     * The garbage collector, this is executed when the session garbage collector
     * is executed and takes the max session lifetime as its only parameter.
     *
     * @param int $sess_maxlifetime Maximum session lifetime.
     *
     * @return void
     */
    public function gc($sess_maxlifetime)
    {
        $this->table->garbageCollect($sess_maxlifetime);
    }
}