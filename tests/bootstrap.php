<?php
ob_start();

// Set flag that we're in test mode (needed for loading correct router)
define('VUFIND_PHPUNIT_RUNNING', 1);

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'testing'));

// Set up local override directory
defined('LOCAL_OVERRIDE_DIR')
    || define('LOCAL_OVERRIDE_DIR', realpath(dirname(__FILE__) . '/../local'));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    get_include_path(),
)));

require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance();
