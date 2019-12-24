<?php
require_once('../lib/init.php'); // include functions 

$jsonString = getFromHash($_REQUEST, "json");
if ($jsonString == "") {
    _generateError(array(), EC_MISSING_PARAMETERS, "Nothing to see here.");
    exit();
}

$request = json_decode($jsonString, true);

$providerId = 0; // init, is set in _validateParams() later

$op = getFromHash($request, "op");
switch ($op) {
    case "add":
        _add($request);
        break;
    case "update":
        _update($request);
        break;
    case "get":
        _get($request);
        break;
    case "delete":
        _delete($request);
        break;
    case "check":
        _generateResult($request, array());
        break;
    default:
        _generateError($request, EC_MISSING_PARAMETERS, 
                       "Invalid operation");
        exit();
}

/**
 * Handle ADD functionality
 * 
 * @param type $request json array
 */
function _add(array $request) {
    if (!_validateParams($request, array("data"))) {
        return;
    };
    $pid = GeneratePID();
    if ($pid === false) {
        // failure pid generation
        _generateError($request, EC_INTERNAL_ERROR, 
            "Failed pid generation, please contact vaccinator support");
        return;
    }

    $sql = "INSERT INTO data (PID, PAYLOAD, PROVIDERID, CREATIONDATE)
                          VALUES (?, ?, ?, NOW())";
    $ret = ExecuteSQL($sql, array($pid, 
                                  $request["data"], 
                                  $request["sid"]));
    if ($ret == false) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }

    DoLog(LOG_TYPE_ADD, $request["sid"], $pid);

    $j = array("pid" => $pid);
    _generateResult($request, $j);
}

/**
 * Handle DELETE functionality
 * 
 * @param type $request json array
 */
function _delete(array $request) {
    if (!_validateParams($request, array("pid"))) {
        return;
    };

    $pid = explode(" ", $request["pid"]);
    if (count($pid) > MAX_GET_PID) {
        // failure!
        _generateError($request, EC_INVALID_SIZE, 
            "Maximum ".MAX_GET_PID." pids allowed per request");
        return;
    }

    $pids = str_repeat("?, ", count($pid) - 1) . "?";
    $sqlPid = array_merge($pid, array($request["sid"])); // last ? is sid

    $sql = "DELETE FROM data WHERE PID IN($pids) AND PROVIDERID=?";
    $ret = ExecuteSQL($sql, $sqlPid);
    if ($ret == false) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }

    DoLog(LOG_TYPE_DELETE, $request["sid"], $request["pid"]);

    _generateResult($request, array());
}

/**
 * Handle UPDATE functionality
 * 
 * @param type $request json array
 */
function _update(array $request) {
    if (!_validateParams($request, array("data", "pid"))) {
        return;
    };

    $pid = $request["pid"];

    // Verify if dataset exists for this service provider
    $sql = "SELECT PROVIDERID FROM data WHERE PID=? AND PROVIDERID=?";
    $ret = GetOneSQLValue($sql, array($pid, $request["sid"]));
    if ($ret == 0) {
        // failure!
        _generateError($request, EC_NOT_FOUND, 
            "Entry with this pid not found");
        return;
    }

    // Update dataset
    $sql = "UPDATE data SET PAYLOAD=? WHERE PID=?";
    $ret = ExecuteSQL($sql, array($request["data"], $pid));
    if ($ret == false) {
        // failure!
        _generateError($request, EC_INTERNAL_ERROR, 
            "Check your values and please contact vaccinator support");
        return;
    }

    DoLog(LOG_TYPE_UPDATE, $request["sid"], $pid);

    _generateResult($request, array());
}

/**
 * Handle GET functionality
 * 
 * @param type $request json array
 */
function _get(array $request) {
    if (!_validateParams($request, array("pid"))) {
        return;
    };

    $pid = explode(" ", $request["pid"]);
    if (count($pid) > MAX_GET_PID) {
        // failure!
        _generateError($request, EC_INVALID_SIZE, 
            "Maximum ".MAX_GET_PID." pids allowed per request");
        return;
    }

    $pids = str_repeat("?, ", count($pid) - 1) . "?";

    // Verify if dataset exists for this service provider
    $sql = "SELECT PID, PAYLOAD FROM data 
              WHERE PID IN($pids) AND PROVIDERID=?";
    $payload = array();
    $sqlPid = array_merge($pid, array($request["sid"])); // last ? is sid
    $db = new classOpenDB($sql, $sqlPid);
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
        // remove each found entry from $pid list
        $pid = array_diff($pid, array($row["PID"]));
    }
    foreach ($pid as $invalid) {
        // compose the return values for invalid pids
        $j = array("status" => "NOTFOUND",
                   "data" => false);
                   $payload[$invalid] = $j;
    }

    DoLog(LOG_TYPE_GET, $request["sid"], implode(" ", $pid));

    $j = array("data" => $payload);
    _generateResult($request, $j);
}

/**
 * Test given $request for that all mandatory values are set
 * and confirming to encoding guides. Also checks validity of
 * pkey and pid values (if used).
 * 
 * Provide mandatory value names in array $needed. Only
 * values named here are validated!
 * 
 * @param array $request
 * @param array $needed
 */
function _validateParams(array $request, array $needed) {
    global $KeyPublic, $KeyEncoding, $KeyRevoked;

    // add login credentials to be checked always:
    // sid (service provider id)
    // spwd (service provider password)
    array_push($needed, "sid", "spwd");

    // Check if all needed values are existing in the request
    foreach($needed as $param) {
        if (!array_key_exists($param, $request)) {
            _generateError($request, EC_MISSING_PARAMETERS, 
              "Missing mandatory value $param");
            return false;
        }
    }

    // validate encrypted data if needed
    // must be receipt:iv:data, where iv must be hex encoded
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
              "Invalid data encoding (expecting some payload)");
            return false;
        }
    }

    // validate pkey value if needed 
    // (only for future ECC implementation)
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

    // validate pid value(s) if needed
    if (in_array("pid", $needed)) {
        $pids = explode(" ", $request["pid"]);
        foreach ($pids as $pid) {
            if (!validateHEX($pid)) {
                _generateError($request, EC_INVALID_ENCODING, 
                  "Invalid pid value (expecting pid to be hex)");
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
    if (array_key_exists("uid", $request)) {
        // return any uid if given
        $j["uid"] = $request["uid"];
    }
    $ret = array("status" => "OK",
                 "version" => VACCINATOR_VERSION);
    $ret = array_merge($ret, $j);
    echo json_encode($ret)."\n";
}

/**
 * Generates a JSON encoded error response. It will set the status field
 * depending on the error code. Most codes will generate INVALID while some
 * will generate ERROR
 *
 * @global array $errorCodes holds the error codes that cause the status to be
 *         ERROR
 * @param type $errorCode the error code to print
 */
function _generateError(array $request, $errorCode, $desc = "") {
    global $LogLevelStrings;
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

    $provId = getFromHash($request, "sid");

    DoLog(LOG_TYPE_ERROR, $provId, "Returned error $errorCode [$desc]");
    // error_log("$errorCode [$desc]");
    return;
}
?>
