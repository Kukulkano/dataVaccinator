<?php
/*-------------------------------------------------------+
| DataVaccinator Service Provider System
| Copyright (C) Volker Schmid
| https://www.datavaccinator.com/
+--------------------------------------------------------+
| Filename: vaccinator_test.php
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

/**
 * Comandline test-tool for vaccinator service protocol.
 * 
 * Call with vaccinator service URL as parameter.
 */

$serviceProviderID  = 1;
$serviceProviderPwd = "vaccinator";

if (count($argv) < 2) {
    print("Please provide URL for vaccinator service (like http://vaccinator.com)\n");
    exit();
}

require_once(__DIR__ . '/../lib/init.php'); // include functions 

$url = $argv[1] . "/index.php";

$r = array();
$r["sid"] = $serviceProviderID;
$r["spwd"] = $serviceProviderPwd;
$remove = array(); // will have a list of PIDs to remove at the end
$supportsSearch = false; // default
$pass = "- pass\n";
$someKey = "OAm6_Q%Xk*08";

while (true) {
    /**
     * *******************************************
     * Get version and check availability
     * *******************************************
     */
    print("\nGet version and check availability:\n");
    
    $r["op"] = "check";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for op 'check', got [".$j["status"]."] instead.\n";
        break;
    }
    $p = getFromHash($j, "plugins", array());
    foreach ($p as $plugin) {
      if ($plugin['name'] == "search") {
          $supportsSearch = true;
          break;
      }
    }
    print($pass);
    if ($supportsSearch) {
        print("\nNOTE: Server supports 'search' module. We will test this, too.\n");
    }
    
    /**
     * *******************************************
     * Tests that should fail (eg authentication)
     * *******************************************
     */
    print("\nTesting invalid requests (wrong data, missing data etc):\n");

    // no json at all
    $j = _parseVaccinatorResult("");
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for missing json param, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    // invalid op params
    $r["op"] = "addr";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for invalid op, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    // invalid login params
    $r["op"] = "add";
    $r["sid"] = -1;
    $r["data"] = "cbc-aes-256:7f:75os3i1zome41tkuunp1fjoauw:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for invalid sid, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd . "invalid";
    $r["data"] = "cbc-aes-256:7f:75os3i1zome41tkuunp1fjoauw:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for invalid spwd, got [".$j["status"]."] instead.\n";
        break;
    }
    // missing some data
    print($pass);

    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["data"] = "";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for missing data field, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    // Invalid hex encoding for IV in data
    print($pass);
    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["data"] = "cbc-aes-256:7f:75os3i1!#1tkuunp1fjoauw:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for wrong hex encoding, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    /**
     * *******************************************
     * Test adding data (must have success)
     * *******************************************
     */
    print("\nTesting to add data:\n");

    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["op"] = "add";
    $r["data"] = "chacha20:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $r["uid"] = 12345;
    if ($supportsSearch) {
      $r["words"] = array(_generateSearchHash("Klaus", true), 
                          _generateSearchHash("Müller", true));
    }
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for 'add' operation, got [".$j["status"]."] instead.\n";
        break;
    }
    // got some valid pid?
    $pid = getFromHash($j, "pid");
    if (strlen($pid) < 16) {
        print "Expected some valid pid as result from 'add', got [$pid] instead.\n";
        break;
    }
    print "NOTE: New user PID: $pid\n";
    array_push($remove, $pid); // for later deletion

    // did I get the uid value back?
    $uid = getFromHash($j, "uid");
    if ($uid != 12345) {
        print "Expected returning the same uid as sent (12345), got [$uid] instead (add).\n";
        break;
    }
    print($pass);

    /**
     * *******************************************
     * Test modifying data (must have success)
     * *******************************************
     */
    print("\nTests updating data:\n");

    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["op"] = "update";
    $r["data"] = "chacha20:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $r["uid"] = 12345;
    $r["pid"] = $pid; // update generated entry
    if ($supportsSearch) {
      $r["words"] = array(_generateSearchHash("Klaus", true), 
                          _generateSearchHash("Meier", true));
    }
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for 'update' operation, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    // with unknown PID
    $r["data"] = "chacha20:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $r["uid"] = 12345;
    $r["pid"] = "2ff18992cfc290d3d648aea5bdea38b1"; // some unknown PID
    unset($r["words"]);
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for unknown 'update' pid, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    // with invalid PID (no hex)
    $r["data"] = "cbc-aes-256:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $r["uid"] = 12345;
    $r["pid"] = "Im definitely not hex encoded"; // some invalid PID
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for invalid 'update' pid, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    /**
     * *******************************************
     * Test retrieving modified data (must have success)
     * *******************************************
     */
    print("\nTests retrieving data:\n");

    // retrieve generated pid
    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["op"] = "get";
    $r["uid"] = 12345;
    $r["pid"] = $pid;
    unset($r["data"]);
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for 'update' operation, got [".$j["status"]."] instead.\n";
        break;
    }
    if ($j["data"][$pid]["data"] != "chacha20:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6") {
        print "Expected other payload, got [".$j["data"]."] instead.\n";
        break;
    }
    print($pass);

    // retrieve generated pid and inknown pid
    $r["pid"] = $pid . " 2ff18992cfc290d3d648aea5bdea38b1";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for 'update' operation, got [".$j["status"]."] instead.\n";
        break;
    }
    if ($j["data"][$pid]["status"] != "OK" || 
        $j["data"]["2ff18992cfc290d3d648aea5bdea38b1"]["status"] != "NOTFOUND") {
        print "Expected status OK for valid PID and NOTFOUND for invalid. Got others.\n";
        break; 
    }
    print($pass);
    
    // retrieve some PID using the search function on modified value "Meier"
    if ($supportsSearch) {
      print("\nTesting 'search' plugin function:\n");
      $r["op"] = "search";
      $r["words"] = _generateSearchHash("Meier", false);
      unset($r["pid"]);
      unset($r["data"]);
      
      $j = _parseVaccinatorResult(json_encode($r));
      if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
      if ($j["status"] != "OK") {
          print "Expected status OK for 'search' operation, got [".$j["status"]."] instead.\n";
          break;
      }
      if (getFromHash($j["pids"], 0) != $pid) {
          print "Expected pid {$pid} as search result but got ".print_r($j["pids"], true)."instead.\n";
          break;
      }
      print($pass);
    }

    break; // leave endless while () loop
}

/**
 * *******************************************
 * Cleanup any entries created during testing
 * *******************************************
 */
print("\nCleanup pid's created:\n");
foreach($remove as $toRem) {
    print("Removing pid [$toRem]... ");
    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["op"] = "delete";
    $r["pid"] = $toRem . " " . "4532432434324324321";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); }
    if ($j["status"] != "OK") {
        print("Expected status OK for 'delete' operation, got [".$j["status"]."] instead.\n");
    } else {
        print("OK\n");
    }
}

print "\nDone\n";

/**
 * *******************************************
 * HELPING FUNCTIONS BELOW
 * *******************************************
 */

/**
 * Call DataVaccinator and decode result.
 * 
 * @param string $json
 * @return array
 */
function _parseVaccinatorResult($json) {
    global $url;
    $data = array();
    $data["json"] = $json;
    $error = "";
    // print "URL: $url\n";
    $res =  DoRequest($url, $data, null, "", $error, 8, 0, '', false);
    $j = json_decode($res, true);
    // print_r($res);
    // print_r($j);
    // print_r($error);
    return $j;
}

function _generateSearchHash($word, $withRandom = false) {
    global $someKey;
    $searchHash = "";
    $h = "f1748e9819664b324ae079a9ef22e33e9014ffce302561b9bf71a37916c1d2a3"; // init, see docs
    $letters = str_split($word);
    foreach($letters as $l) {
        $c = strtolower($l);
        $h = hash("sha256", $c . $h . $someKey);
        $searchHash .= substr($h, 0, 2); // concat SearchHash
    }
    if ($withRandom) {
        $c = rand(0, 5);
        for ($i = 1; $i <= $c; $i++) {
            $v = rand(0, 255);
            $searchHash .= str_pad(dechex($v), 2, "0");
        }
    }
    return $searchHash;
}
?>
