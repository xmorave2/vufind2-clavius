<?php

// If the XHProf profiler is enabled, set it up now:
$xhprof = getenv('VUFIND_PROFILER_XHPROF');
if (!empty($xhprof) && extension_loaded('xhprof')) {
    xhprof_enable();
} else {
    $xhprof = false;
}

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('VUFIND_ENV') ? getenv('VUFIND_ENV') : 'production'));

// Define path to local override directory
defined('LOCAL_OVERRIDE_DIR')
    || define('LOCAL_OVERRIDE_DIR', (getenv('VUFIND_LOCAL_DIR') ? getenv('VUFIND_LOCAL_DIR') : ''));

// Ensure library/ is on include_path; use local library as first choice if
// configured.
$pathParts = array();
if (strlen(trim(LOCAL_OVERRIDE_DIR)) > 0) {
    $pathParts[] = LOCAL_OVERRIDE_DIR . '/library';
}
$pathParts[] = realpath(APPLICATION_PATH . '/../library');
$pathParts[] = get_include_path();
set_include_path(implode(PATH_SEPARATOR, $pathParts));

/** Zend_Application */
require_once 'Zend/Application.php';

// Create application, bootstrap, and run
$application = new Zend_Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);
$application->bootstrap()
            ->run();

// Handle final profiling details, if necessary:
if ($xhprof) {
    $xhprofData = xhprof_disable();
    include_once "xhprof_lib/utils/xhprof_lib.php";
    include_once "xhprof_lib/utils/xhprof_runs.php";
    $xhprofRuns = new XHProfRuns_Default();
    $suffix = 'vufind2';
    $xhprofRunId = $xhprofRuns->save_run($xhprofData, $suffix);
    $url = "$xhprof?run=$xhprofRunId&source=$suffix";
    echo "<a href='$url'>Profiler output</a>";
}