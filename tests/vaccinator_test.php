<?php
/*-------------------------------------------------------+
| DataVaccinator Vault Provider System
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
 * Call with DataVaccinator Vault URL as parameter.
 */

$serviceProviderID  = 1;
$serviceProviderPwd = "vaccinator";

if (count($argv) < 2) {
    print("Please provide URL for DataVaccinator Vault (like https://service.vaccinator.com)\n");
    exit();
}

require_once(__DIR__ . '/../lib/init.php'); // include functions 

$url = $argv[1] . "/index.php";

$r = array();
$r["sid"] = $serviceProviderID;
$r["spwd"] = $serviceProviderPwd;
$remove = array(); // will have a list of VIDs to remove at the end
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
    $r["version"] = 2;
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

    // Invalid hex encoding for IV in data (removed for golang version)
    /*
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
    */

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
    // got some valid vid?
    $vid = getFromHash($j, "vid");
    if (strlen($vid) < 16) {
        print "Expected some valid vid as result from 'add', got [$vid] instead.\n";
        break;
    }
    print "NOTE: New user VID: $vid\n";
    array_push($remove, $vid); // for later deletion

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
    $r["vid"] = $vid; // update generated entry
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

    // with unknown VID
    $r["data"] = "chacha20:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $r["uid"] = 12345;
    $r["vid"] = "2ff18992cfc290d3d648aea5bdea38b1"; // some unknown VID
    unset($r["words"]);
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for unknown 'update' vid, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    // with invalid VID (no hex)
    $r["data"] = "cbc-aes-256:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6";
    $r["uid"] = 12345;
    $r["vid"] = "Im definitely not hex encoded"; // some invalid VID
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "INVALID") {
        print "Expected status INVALID for invalid 'update' vid, got [".$j["status"]."] instead.\n";
        break;
    }
    print($pass);

    /**
     * *******************************************
     * Test retrieving modified data (must have success)
     * *******************************************
     */
    print("\nTests retrieving data:\n");

    // retrieve generated vid
    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["op"] = "get";
    $r["uid"] = 12345;
    $r["vid"] = $vid;
    unset($r["data"]);
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for 'get' operation, got [".$j["status"]."] instead.\n";
        break;
    }
    if ($j["data"][$vid]["data"] != "chacha20:7f:29a1c8b68d8a:btewwyzox3i3fe4cg6a1qzi8pqoqa55orzf4bcxtjfcf5chep998sj6") {
        print "Expected other payload, got [".$j["data"]."] instead.\n";
        break;
    }
    print($pass);

    // retrieve generated vid and unknown vid
    $r["vid"] = $vid . " 2ff18992cfc290d3d648aea5bdea38b1";
    $j = _parseVaccinatorResult(json_encode($r));
    if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
    if ($j["status"] != "OK") {
        print "Expected status OK for 'get' operation, got [".$j["status"]."] instead.\n";
        break;
    }
    if ($j["data"][$vid]["status"] != "OK" || 
        $j["data"]["2ff18992cfc290d3d648aea5bdea38b1"]["status"] != "NOTFOUND") {
        print "Expected status OK for valid VID and NOTFOUND for invalid. Got others.\n";
        break; 
    }
    print($pass);
    
    // retrieve some VID using the search function on modified value "Meier"
    if ($supportsSearch) {
      print("\nTesting 'search' plugin function:\n");
      // search one word
      $r["op"] = "search";
      $r["words"] = _generateSearchHash("Meier", false); // modified by update before!
      unset($r["vid"]);
      unset($r["data"]);
      
      $j = _parseVaccinatorResult(json_encode($r));
      if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
      if ($j["status"] != "OK") {
          print "Expected status OK for 'search' operation, got [".$j["status"]."] instead.\n";
          break;
      }
      if (getFromHash($j["vids"], 0) != $vid) {
          print "Expected vid {$vid} as search result but got ".print_r($j["vids"], true)."instead.\n";
          break;
      }
      print($pass);

      // search two words
      $r["words"] = _generateSearchHash("Meier", false); // modified by update before!
      $r["words"] .= " " . _generateSearchHash("Klaus", false);
      $j = _parseVaccinatorResult(json_encode($r));
      if ($j === NULL || $j === false) { print("unexpected result (no json)\n"); break; }
      if ($j["status"] != "OK") {
          print "Expected status OK for 'search' operation, got [".$j["status"]."] instead.\n";
          break;
      }
      if (getFromHash($j["vids"], 0) != $vid) {
          print "Expected vid {$vid} as search result but got ".print_r($j["vids"], true)."instead.\n";
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
print("\nCleanup vid's created:\n");
foreach($remove as $toRem) {
    print("Removing vid [$toRem]... ");
    $r["sid"] = $serviceProviderID;
    $r["spwd"] = $serviceProviderPwd;
    $r["op"] = "delete";
    $r["version"] = 2;
    $r["vid"] = $toRem . " " . "e6ec07c19fbadbd062028cedbe4ab7e5";
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
 * Call DataVaccinator Vault and decode result.
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
