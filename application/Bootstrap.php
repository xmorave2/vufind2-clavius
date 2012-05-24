<?php
/**
 * Bootstrap
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
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
 
/**
 * Bootstrap extension to get everything initialized
 *
 * @category VuFind2
 * @package  Config
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

// @codingStandardsIgnoreStart
// protected functions with _ = no
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected $cli;

    /**
     * Constructor
     *
     * Ensure FrontController resource is registered
     *
     * @param  Zend_Application|Zend_Application_Bootstrap_Bootstrapper $application
     * @return void
     */
    public function __construct($application)
    {
        parent::__construct($application);

        // Are we running a command-line tool?  Note that we need a special check
        // for PHPUnit, since unit tests run from the command line but need to act
        // like web applications!
        $this->cli = (PHP_SAPI == 'cli' && !defined('VUFIND_PHPUNIT_RUNNING'));
    }

    /**
     * Initialize content type and encoding
     *
     * @return void
     */
    protected function _initViewSettings()
    {
        $this->bootstrap('view');

        $this->_view = $this->getResource('view');
        $this->_view->setEncoding('UTF-8');
        $this->_view->headMeta()->appendHttpEquiv(
            'Content-Type', 'text/html; charset=UTF-8'
        );
    }

    /**
     * Initialize Zend routes
     *
     * @return void
     */
    protected function _initRoutes()
    {
        $this->bootstrap('frontController');

        // If we're in command-line mode, use a custom router (needed for various
        // command-line utilities that share libraries with the web app):
        if ($this->cli) {
            // disable output buffering for instant gratification status messages:
            $this->frontController->setParam('disableOutputBuffering', true);
            $this->frontController->setRouter(new VF_Router_Cli());
            $this->frontController->setRequest(new Zend_Controller_Request_Simple());
            return;
        }

        $router = $this->frontController->getRouter();

        $route = new Zend_Controller_Router_Route(
            ':controller/:action',
            array(
                'controller' => 'index',
                'action' => 'Home'
            )
        );
        $router->addRoute('default', $route);

        $route = new Zend_Controller_Router_Route(
            'Record/:id/:action',
            array(
                'controller' => 'Record',
                'action' => 'Home'
            )
        );
        $router->addRoute('record', $route);

        $route = new Zend_Controller_Router_Route(
            'MissingRecord/:id/:action',
            array(
                'controller' => 'MissingRecord',
                'action' => 'Home'
            )
        );
        $router->addRoute('missingrecord', $route);

        $route = new Zend_Controller_Router_Route(
            'SummonRecord/:id/:action',
            array(
                'controller' => 'SummonRecord',
                'action' => 'Home'
            )
        );
        $router->addRoute('summonrecord', $route);

        $route = new Zend_Controller_Router_Route(
            'WorldCatRecord/:id/:action',
            array(
                'controller' => 'WorldCatRecord',
                'action' => 'Home'
            )
        );
        $router->addRoute('worldcatrecord', $route);

        $route = new Zend_Controller_Router_Route(
            'MyResearch/MyList/:id',
            array(
                'controller' => 'MyResearch',
                'action' => 'MyList'
            )
        );
        $router->addRoute('userList', $route);

        $route = new Zend_Controller_Router_Route(
            'MyResearch/EditList/:id',
            array(
                'controller' => 'MyResearch',
                'action' => 'EditList'
            )
        );
        $router->addRoute('editList', $route);
    }

    /**
     * Initialize Zend plugins
     *
     * @return void
     */
    protected function _initPlugins()
    {
        // Skip plugin initialization in command-line mode (it does not apply
        // and causes errors because it assumes an Http-based request object):
        if (!$this->cli) {
            $front = Zend_Controller_Front::getInstance();
            $front->registerPlugin(new VF_Plugin_Init());
        }
    }

    /**
     * Initialize database connection
     *
     * @return void
     */
    protected function _initDatabase()
    {
        $this->_db = VF_DB::connect();
        Zend_Db_Table::setDefaultAdapter($this->_db);
    }
    
    /**
     * Set up the mail based on config settings [Mail]
     *
     * @return void
     */
    protected function _initMail()
    {
        // Load settings from the config file into the object; we'll do the
        // actual creation of the mail object later since that will make error
        // detection easier to control.
        // build Zend_Mail

        $config = VF_Config_Reader::getConfig(); 
        
        $settings = array (
            'port' => $config->Mail->port
        );
        if(isset($config->Mail->username) && isset($config->Mail->password)) {
            $settings['auth'] = 'login';
            $settings['username'] = $config->Mail->username;
            $settings['password'] = $config->Mail->password;
        }
        $tr = new Zend_Mail_Transport_Smtp($config->Mail->host,$settings);
        Zend_Mail::setDefaultTransport($tr);
    }
    
    /**
     * Initialize Log
     *
     * @return void
     */    
    protected function _initLog()
    {
        // dependencies
        $this->bootstrap('database');
        $this->bootstrap('mail');
        
        if (!Zend_Registry::isRegistered('Log')) {
            $logger = new VF_Logger();
            Zend_Registry::set('Log', $logger);
        }
    }

    /**
     * Initialize autoloader model
     *
     * @return void
     */
    protected function _initResourceAutoloader()
    {
        $params = array(
            'basePath' => APPLICATION_PATH,
            'namespace' => 'VuFind'
        );
        $this->_resourceLoader = new Zend_Loader_Autoloader_Resource($params);
        $this->_resourceLoader->addResourceType('model', 'models/', 'Model');
    }

    /**
     * Zend/PHP Session
     *
     * @return void
     */
    protected function _initSession()
    {
        // Get session configuration:
        $config = VF_Config_Reader::getConfig();
        if (!isset($config->Session->type)) {
            throw new Exception('Cannot initialize session; configuration missing');
        }

        // Set up session handler (after manipulating the type setting for legacy
        // compatibility -- VuFind 1.x used MySQL instead of Database and had
        // "Session" as part of the configuration string):
        $type = ucwords(
            str_replace('session', '', strtolower($config->Session->type))
        );
        if ($type == 'Mysql') {
            $type = 'Database';
        }
        $class = 'VF_Session_' . $type;
        Zend_Session::setSaveHandler(new $class($config->Session));

        // Start up the session:
        Zend_Session::start();

        // According to the PHP manual, session_write_close should always be
        // registered as a shutdown function when using an object as a session
        // handler: http://us.php.net/manual/en/function.session-set-save-handler.php
        register_shutdown_function(array('Zend_Session', 'writeClose'));

        // Check user credentials:
        VF_Account_Manager::getInstance()->checkForExpiredCredentials();
    }

    /**
     * Initialize helpers
     *
     * @return void
     */
    protected function _initActionHelpers()
    {
        Zend_Controller_Action_HelperBroker::addPath(
            APPLICATION_PATH . '/controllers/helpers',
            'VuFind_Action_Helper'
        );
    }

    /**
     * Initializes locale and timezone values
     *
     * @return void
     */
    protected function _initLocaleAndTimeZone()
    {
        $config = VF_Config_Reader::getConfig();

        // Try to set the locale to UTF-8, but fail back to the exact string from
        // the config file if this doesn't work -- different systems may vary in
        // their behavior here.
        setlocale(
            LC_MONETARY,
            array($config->Site->locale . ".UTF-8", $config->Site->locale)
        );
        date_default_timezone_set($config->Site->timezone);
    }
}
// @codingStandardsIgnoreEnd

