<?php
/**
* #############################################################################
*
* VACCINATOR CONFIGURATION INITIALISATION
* ===============================================
*
* This file contains the custom configuration followed by the defaults
*
* #############################################################################
*/

// standard database credentials
define('DBHOST', "127.0.0.1");
define('DBDATABASE', "vaccinator");
define('DBUSER', "vaccinator");
define('DBPASSWORD', "vaccinator");

// how many pids can get requested in one "get" or "delete" call:
define('MAX_GET_PID', 500); // default is 500

// how big is data allowed to be:
define('MAX_DATA_SIZE', 512); // in KB! Default is 512

// global requirements
$dir = __DIR__ .'/../';
define('ROOT_DIR', realpath($dir) . '/');

require_once(ROOT_DIR . '/lib/common.php');
require_once(ROOT_DIR . '/lib/utils.php');
require_once(ROOT_DIR . '/lib/plugins.php');

// include optional plugins
// require_once(ROOT_DIR . 'plugins/plugSearch.php');

// Enable debug (if needed)
// $DebugLevel = RF_LOG_VERB;

?>
