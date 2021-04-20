<?php
/**
 * Emulating some vaccinator service provider.
 * 
 * Mainly forwarding all vaccinator protocol requests to the DataVaccinator Vault.
 * Adds uid and upwd parameters for vaccinator protocol to verify as provider.
 */

$sid        = 1; // service provider ID
$spwd       = "vaccinator"; // service provider password
$vaccinatorUrl   = "http://127.0.0.1:8080"; // service provider main URL
$vaccinatorUrl2  = "http://127.0.0.1:8080"; // service provider fallback URL

define('EC_MISSING_PARAMETERS', 1);  // Missing parameters.
define('EC_INVALID_ENCODING',   6);  // expected json, hex or b64, but it is not
define('EC_INTERNAL_ERROR',     99); // if vaccinator internal error ocured (a bad thing)

if (!isset($_POST["json"])) {
    $ret = array("status" => "INVALID",
                 "code" => EC_MISSING_PARAMETERS,
                 "desc" => "Missing json post parameter");
    echo json_encode($ret)."\n";
    exit();
}

// decode incoming request
$request = $_POST["json"];
$j = json_decode($request, true);
if ($j === NULL) {
    $ret = array("status" => "INVALID",
                 "code" => EC_INVALID_ENCODING,
                 "desc" => "Invalid JSON in json post parameter");
    echo json_encode($ret)."\n";
    exit();
}

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// verify validity of the request (eg VID assignemnt to calling user)
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

if ($j["op"] == "update" || $j["op"] == "get") {
    // check VID(s) against your local database
}

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

// enrich request by service provider credentials
$j["sid"] = $sid;
$j["spwd"] = $spwd;
$request = json_encode($j);

// url-ify the data for the POST
$fields_string = http_build_query(['json' => $request]);
$ch = curl_init(); // open curl connection
curl_setopt($ch, CURLOPT_URL, $vaccinatorUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 

// execute post to DataVaccinator Vault
$result = curl_exec($ch);
if ($result === false) {
    // call failed, try fallback URL
    error_log("Main DataVaccinator URL failed!");
    curl_setopt($ch, CURLOPT_URL, $vaccinatorUrl2);
    $result = curl_exec($ch);
    if ($result === false) {
        // fallback also failed
        $ret = array("status" => "ERROR",
                     "code" => EC_INTERNAL_ERROR,
                     "desc" => "DataVaccinator Vault offline/error");
        echo json_encode($ret)."\n";
        exit();
    }
}
// return the vaccinator result back to the caller
echo $result;

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%
// potentially update service provider database with results
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

if ($j["op"] == "update") {
    // special case, clients need to wipe their cache
    // for at least the affected VID from $j["vid"] or
    // even the whole cache.
    // Please refer to the vaccinatorJSClient function wipeCache() 
    // for additional documentation and information.
}

// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

?>
