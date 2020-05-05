<?php
/*-------------------------------------------------------+
| DataVaccinator Service Provider System
| Copyright (C) Volker Schmid
| https://www.datavaccinator.com/
+--------------------------------------------------------+
| Filename: utils.php
| Author: Data Vaccinator Development Team
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/

if (file_exists(ROOT_DIR . '/lib/version.php')) {
    include_once(ROOT_DIR . '/lib/version.php');
}
if (!defined('VACCINATOR_VERSION')) {
    define('VACCINATOR_VERSION', 'dev');
}

// global constants

// TODO store in separate include
define('EC_OK', 0); # Success
define('EC_MISSING_PARAMETERS', 1); # Missing parameters.
define('EC_WRONG_PROTOCOL', 2); # Wrong protocol.
define('EC_OUTDATED_CLIENT', 3); # Outdated client software.
define('EC_USER_LOCKED', 4); # The account was locked due to possible misuse.
define('EC_INVALID_CREDENTIALS', 5); # used credentials invalid
define('EC_INVALID_ENCODING', 6); # expected json, hex or b64, but it is not
define('EC_NOT_FOUND', 7); # if vaccinator internal error occured (a bad thing)
define('EC_INVALID_PARTNER', 8); # not the correct partner
define('EC_INVALID_SIZE', 9); # some parameter size invalid (> or <)
define('EC_PLUGIN_INVALID', 20); # invalid call, returned by plugin
define('EC_PLUGIN_MISSING_PARAMETERS', 21); # Missing parameters, returned by plugin
define('EC_INTERNAL_ERROR', 99); # if vaccinator internal error ocured (a bad thing)


define('LOG_TYPE_ADD', 0);
define('LOG_TYPE_GET', 1);
define('LOG_TYPE_UPDATE', 2);
define('LOG_TYPE_DELETE', 3);
define('LOG_TYPE_ERROR', 9);

function DoLog($logType, $provId, $message) {
    $sql = "INSERT INTO log 
              SET logtype=?, logdate=NOW(), providerid=?, logcomment=?";
    ExecuteSQL($sql, array($logType, $provId, $message));
}

/**
 * Generates PID suitable for this vaccinator system.
 * Actually, it is a 128 bit random number in hex encoding.
 */
function GeneratePID() {
    // Hint: 16Byte = 128 bit = 32 characters

    // try to get unique PID max 3 times
    for ($i = 1; $i <= 3; $i++) {
        if (function_exists('random_bytes')) {
            // php 7 or newer
            $pid = bin2hex(random_bytes(16));
        } else {
            // php pre 7
            $pid = bin2hex(openssl_random_pseudo_bytes(16));
        }
        $sql = "SELECT PID FROM data WHERE PID=?";
        $ret = GetOneSQLValue($sql, array($pid));
        if ($ret === 0) {
            return $pid;
        }
    }
    // Always collisions or SQL errors?
    return false;
}

?>