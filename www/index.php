<?php
/*-------------------------------------------------------+
| DataVaccinator Service Provider System
| Copyright (C) Volker Schmid
| https://www.datavaccinator.com/
+--------------------------------------------------------+
| Filename: index.php
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
require_once('../lib/init.php'); // include functions 

$providerId = 0; // init, is set in _validateParams() later
$clientAnswer = array(); // init, is set by _generateError() and _generateResult()

$jsonString = getFromHash($_REQUEST, "json");
if ($jsonString == "") {
    _generateError(array(), EC_MISSING_PARAMETERS, 
                   "Nothing to see here.", false);
    exit();
}

$request = json_decode($jsonString, true);
if ($request === NULL) {
    _generateError(array(), EC_INVALID_ENCODING, 
                   "Invalid JSON.", false);
    exit();
}

if (!_validateParams($request)) {
    _generateError($request, EC_MISSING_PARAMETERS, 
                           "Missing some mandatory parameter(s)");
    exit();
}

if (!_manageHook('pre_globalProtocol', $request)) {

    $op = getFromHash($request, "op");
    switch ($op) {
        case "add":
            if (!_manageHook('pre_add', $request)) {
                _add($request);
            }
            _manageHook('post_add', $request);
            break;
        case "update":
            if (!_manageHook('pre_update', $request)) {
                _update($request);
            }
            _manageHook('post_update', $request);
            break;
        case "get":
            if (!_manageHook('pre_get', $request)) {
                _get($request);
            }
            _manageHook('post_get', $request);
            break;
        case "delete":
            if (!_manageHook('pre_delete', $request)) {
                _delete($request);
            }
            _manageHook('post_delete', $request);
            break;
        case "check":
            if (!_manageHook('pre_check', $request)) {
                _check($request);
            }
            _manageHook('post_check', $request);
            break;
        default:
            if (_manageHook('operation', $request)) {
                // in plugins, return 'skip' = true in case of success top prevent
                // any error message here.
                break;
            }
            _generateError($request, EC_MISSING_PARAMETERS, 
                           "Invalid operation");
            break;
    }
}

_manageHook('post_globalProtocol', $request);

// returned prepared answers to client
echo json_encode($clientAnswer)."\n";

exit();

/**
 * Handle ADD functionality
 * 
 * @param array $request json array
 * @return void
 */
function _add(array $request) {
    if (!_validateParams($request, array("data"))) {
        return;
    }
    $vid = GenerateVID();
    if ($vid === false) {
        // failure vid generation
        _generateError($request, EC_INTERNAL_ERROR, 
            "Failed vid generation, please contact vaccinator support");
        return;
    }

    $sql = "INSERT INTO data (PID, PAYLOAD, PROVIDERID, CREATIONDATE)
                          VALUES (?, ?, ?, NOW())";
    $ret = ExecuteSQL($sql, array($vid, 
                                  $request["data"], 
                                  $request["sid"]));
    if ($ret == false) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }

    DoLog(LOG_TYPE_ADD, $request["sid"], $vid);

    if ($request["version"] === 1) {
      // return both 'vid' and 'pid' (for compatibility to older JS clients)
      $j = array("pid" => $vid, "vid" => $vid);
    } else  {
      $j = array("vid" => $vid);
    }
    _generateResult($request, $j);
}

/**
 * Handle DELETE functionality
 * 
 * @param array $request json array
 * @return void
 */
function _delete(array $request) {
    if (!_validateParams($request, array("vid"))) {
        return;
    }

    $vid = explode(" ", $request["vid"]);
    if (count($vid) > MAX_GET_PID) {
        // failure!
        _generateError($request, EC_INVALID_SIZE, 
            "Maximum ".MAX_GET_PID." vids allowed per request");
        return;
    }

    $vids = str_repeat("?, ", count($vid) - 1) . "?";
    array_push($vid, $request["sid"]); // value for last ? is sid

    $sql = "DELETE FROM data WHERE PID IN($vids) AND PROVIDERID=?";
    $ret = ExecuteSQL($sql, $vid);
    if ($ret == false) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }

    DoLog(LOG_TYPE_DELETE, $request["sid"], $request["vid"]);

    _generateResult($request, array());
}

/**
 * Handle UPDATE functionality
 * 
 * @param array $request json array
 * @return void
 */
function _update(array $request) {
    if (!_validateParams($request, array("data", "vid"))) {
        return;
    }

    $vid = $request["vid"];

    // Verify if dataset exists for this service provider
    $sql = "SELECT PROVIDERID FROM data WHERE PID=? AND PROVIDERID=?";
    $ret = GetOneSQLValue($sql, array($vid, $request["sid"]));
    if ($ret == 0) {
        // failure!
        _generateError($request, EC_NOT_FOUND, 
            "Entry with this vid not found");
        return;
    }

    // Update dataset
    $sql = "UPDATE data SET PAYLOAD=? WHERE PID=?";
    $ret = ExecuteSQL($sql, array($request["data"], $vid));
    if ($ret == false) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }

    DoLog(LOG_TYPE_UPDATE, $request["sid"], $vid);

    _generateResult($request, array());
}

/**
 * Handle GET functionality
 * 
 * @param array $request json array
 * @return void
 */
function _get(array $request) {
    if (!_validateParams($request, array("vid"))) {
        return;
    }

    $vid = explode(" ", $request["vid"]);
    if (count($vid) > MAX_GET_PID) {
        // failure!
        _generateError($request, EC_INVALID_SIZE, 
            "Maximum ".MAX_GET_PID." vids allowed per request");
        return;
    }

    $vids = str_repeat("?, ", count($vid) - 1) . "?";

    // Verify if dataset exists for this service provider
    $sql = "SELECT PID, PAYLOAD FROM data 
              WHERE PID IN($vids) AND PROVIDERID=?";
    $payload = array();
    $sqlVid = array_merge($vid, array($request["sid"])); // last ? is sid
    $db = new classOpenDB($sql, $sqlVid);
    if ($db->hasError()) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }
    while ($row = $db->fetchArray()) {
        // compose whatever was found in the database
        $j = array("status" => "OK",
                   "data" => $row["PAYLOAD"]);
                   $payload[$row["PID"]] = $j;
        // remove each found entry from $vid list
        $vid = array_diff($vid, array($row["PID"]));
    }
    foreach ($vid as $invalid) {
        // compose the return values for invalid vids
        $j = array("status" => "NOTFOUND",
                   "data" => false);
                   $payload[$invalid] = $j;
    }

    DoLog(LOG_TYPE_GET, $request["sid"], implode(" ", $vid));

    $j = array("data" => $payload);
    _generateResult($request, $j);
}

/**
 * Handle the CHECK functionality
 * 
 * @param array $request
 */
function _check(array $request) {
    global $plugins;
    $j = array("time" => date("Y-m-d H:i:s"),
               "plugins" => $plugins);
    _generateResult($request, $j);
}

/**
 * Test given $request for that all mandatory values are set
 * and confirming to encoding guides. Also checks validity of
 * pkey and vid values (if used).
 * 
 * Provide mandatory value names in array $needed. Only
 * values named here are validated!
 * 
 * HINT: If request has a 'pid' but no 'vid' parameter,
 * we're talking to some outdated JS client. The function
 * will duplicate the existing 'pid' value as 'vid'.
 * 
 * @param array $request
 * @param array $needed
 * @return boolean valid
 */
function _validateParams(array &$request, array $needed = array()) {
    global $KeyPublic, $KeyEncoding, $KeyRevoked;

    if (getFromHash($request, "op") === "check") {
      // check function does not require any validation
      return true;
    }
    
    // add login credentials to be checked always:
    // sid (service provider id)
    // spwd (service provider password)
    array_push($needed, "sid", "spwd");
    
    if (!array_key_exists("version", $request)) {
        // compatibility mode for protocol version 1
        $request["version"] = 1;
        if (array_key_exists("pid", $request)) {
            // for outdated 'pid' parameter (new is 'vid')
            $request["vid"] = $request["pid"];
        }
    }

    // Check if all needed values are existing in the request
    foreach($needed as $param) {
        if (!array_key_exists($param, $request)) {
            _generateError($request, EC_MISSING_PARAMETERS, 
              "Missing mandatory value $param");
            return false;
        }
    }

    // validate encrypted data if needed
    // must be receipt:cs:iv:payload, where iv must be hex encoded and cs
    // must be 2 characters
    // maximum length of all is 15MB
    if (in_array("data", $needed)) {
        if (strlen($request["data"]) > MAX_DATA_SIZE * 1024) {
            _generateError($request, EC_INVALID_SIZE, 
              "Size of data > ".MAX_DATA_SIZE."KB");
            return false;
        }
        $data = explode(":", $request["data"]);
        if (count($data) != 4) {
            _generateError($request, EC_INVALID_ENCODING, 
              "Invalid data encoding (expecting 4 parts receipt:cs:iv:payload)");
            return false;
        }
        if (strlen($data[0]) < 4) {
            _generateError($request, EC_INVALID_ENCODING, 
              "Invalid recipt (expecting 4 characters minimum)");
            return false;
        }
        if (strlen($data[1]) != 2) {
            _generateError($request, EC_INVALID_ENCODING, 
              "Invalid checksum (expecting 2 characters)");
            return false;
        }
        if (!validateHEX($data[2])) { // hint: empty string is also not hex!
            _generateError($request, EC_INVALID_ENCODING, 
              "Invalid data encoding (expecting hex iv)");
            return false;
        }
        if (strlen($data[3]) < 4) {
            _generateError($request, EC_INVALID_ENCODING, 
              "Invalid data encoding (expecting some payload > 4 characters)");
            return false;
        }
    }

    // validate pkey value if needed 
    // (only for future ECC implementation)
    /*
    if (in_array("pkey", $needed)) {
        $pkey = $request["pkey"];
        if (!array_key_exists($pkey, $KeyPublic)) {
            _generateError($request, EC_INVALID_ENCODING, 
              "Invalid pkey value");
            return false;
        }
        if ($KeyRevoked[$pkey] == TRUE) {
            _generateError($request, EC_INVALID_ENCODING, 
              "Revoked pkey");
            return false;
        }
    }
    */

    // validate vid value(s) if needed
    if (in_array("vid", $needed)) {
        $vids = explode(" ", $request["vid"]);
        foreach ($vids as $vid) {
            if (!validateHEX($vid)) {
                _generateError($request, EC_INVALID_ENCODING, 
                  "Invalid vid value (expecting vid to be hex)");
                return false;
            }
        }
    }
    
    // validate login
    $sql = "SELECT password FROM provider WHERE providerid=?";
    $res = GetOneSQLValue($sql, array($request["sid"]));
    if ($res == 0) {
        _generateError($request, EC_INVALID_CREDENTIALS, 
            "Invalid sid (service provider id)");
        return false;
    }
    $pwd = $res["password"];
    if ($pwd != $request["spwd"]) {
        _generateError($request, EC_INVALID_CREDENTIALS, 
            "Invalid spwd (service provider password)");
        return false;
    }

    return true;
}

/**
 * Generate some successful result
 *
 * @param array $request
 * @param array $j
 * @return void
 */
function _generateResult(array $request, array $j) {
    global $clientAnswer;
    if (array_key_exists("uid", $request)) {
        // return any uid if given
        $j["uid"] = $request["uid"];
    }
    $ret = array("status" => "OK",
                 "version" => VACCINATOR_VERSION);
    $ret = array_merge($ret, $j);
    
    $clientAnswer = $ret; // keep for later plugin processing and sending to client
}

/**
 * Generate some error result.
 * 
 * @param array $request
 * @param type $errorCode EC_
 * @param string $desc
 * @param boolean $toLog
 * @return void
 */
function _generateError(array $request, $errorCode, $desc = "", $toLog = true) {
    $status = "INVALID";
    $errors = array(EC_INTERNAL_ERROR); // these will be status ERROR
    if (in_array($errorCode, $errors)) {
        $status = "ERROR";
    }
    $ret = array("status" => $status,
                 "code" => $errorCode,
                 "desc" => $desc,
                 "version" => VACCINATOR_VERSION);
    echo json_encode($ret)."\n";

    if ($toLog) {
        $provId = getFromHash($request, "sid");
        DoLog(LOG_TYPE_ERROR, $provId, "Returned error $errorCode [$desc]");
    }
    
    exit();
}

/**
 * Manage plugin hook functionality.
 * Returns true if the original function should get skipped (only used for "pre_").
 * 
 * @param string $hookName
 * @param type $request
 * @return boolean skip original function
 */
function _manageHook($hookName, &$request) {
  $r = hook($hookName, $request);
  if ($r === false) {
    return false; // hook not in use
  }
  $e = getFromHash($r, "error", EC_OK);
  if ($e != EC_OK) {
    _generateError($request, $e, getFromHash($r, "errorDesc", ""));
    exit();
  }
  return getFromHash($r, "skip", false);
}
?>
