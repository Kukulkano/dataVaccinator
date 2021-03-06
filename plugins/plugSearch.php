<?php
/*-------------------------------------------------------+
| DataVaccinator Vault Provider System
| Copyright (C) Volker Schmid
| https://www.datavaccinator.com/
+--------------------------------------------------------+
| Filename: plugSearch.php
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

/*
 * Search plugin for the data vaccinator.
 * It uses the SearchHash functionality to
 * use and generate searchable tokens.
 * 
 * The license and terms are the same as for
 * the main DataVaccinator project.
 */

// //////////////////////////////////
// Initialisation
// //////////////////////////////////

// add this plugin name to the list of plugins
array_push($plugins, array("name"=>"search", 
                           "vendor"=>"DataVaccinator",
                           "license"=>"various"
                          ));

// //////////////////////////////////
// "search" protocol handler
// //////////////////////////////////

// register protocol hook
add_listener('operation', 'pl_search_op');

/**
 * Add the "search" protocol functionality.
 * 
 * @param array $args
 * @return boolean|string
 */
function pl_search_op(array $args) {
  // generic return is "all good"
  $r = array();
  $r["error"] = EC_OK;
  $r["skip"] = false;
  
  $op = getFromHash($args[0], "op");
  if ($op != "search") {
      return $r; // will do as if no plugin is here
  }
  
  // handle search functionality
  $words = getFromHash($args[0], "words", "");
  if ($words == "") {
      $r["error"]     = EC_MISSING_PARAMETERS;
      $r["errorDesc"] = "Missing 'words' parameter.";
      return $r;
  }
  
  $sql = "SELECT t1.PID FROM search t1\n";
  $where = "";
  $filter = array();
  $aWords = explode(" ", $words);
  $aWords = array_unique($aWords); // remove duplicates
  
  $c = 1;
  foreach ($aWords as $w) {
      if ($c > 1) {
          $sql .= "INNER JOIN search t{$c} ON (t1.PID = t{$c}.PID)\n";
      }
      $where .= "t{$c}.WORD LIKE ? AND ";
      array_push($filter, $w . "%");
      $c = $c + 1;
  }
  $where = substr($where, 0, -4); // remove last "AND "
  $sql .= " WHERE " . $where; // concat with where conditions

  // Filter provider association by putting results in a sub-query 
  // which filters for provider id (sub-query seems more efficient here).
  // This avoids later confusion while requesting all vids found.
  $provId = intval($args[0]["sid"]);
  array_push($filter, $provId);
  $sql = "SELECT PID FROM data WHERE PID IN({$sql}) AND PROVIDERID=?";
  
  // execute the sql query now to get all requested pids
  $db = new classOpenDB($sql, $filter);
  if ($db->hasError()) {
      // failure!
      $r["error"]     = EC_INTERNAL_ERROR;
      $r["errorDesc"] = "Failed searching! Contact DataVaccinator support!";
      return $r;
  }
  $res = array();
  while ($row = $db->fetchArray()) {
      array_push($res, $row["PID"]);
  }
  
  if ($args[0]["version"] === 1) {
    // return both 'vids' and 'pids' (for compatibility to older JS clients)
    _generateResult($args[0], array("vids" => $res, "pids" => $res));
  } else {
    _generateResult($args[0], array("vids" => $res));
  }
  
  $r["skip"] = true; // for "operation" hook this is like "all okay"
  return $r;
}

// //////////////////////////////////
// "add" protocol handler
// //////////////////////////////////

// register post_add hook
add_listener('post_add', 'pl_search_add');

/**
 * Adding search hashes to database after protocol was already handled.
 * 
 * @param array $args
 * @param string $vid
 * @return array
 */
function pl_search_add(array $args, string $vid = null) {
  global $clientAnswer; // contains last answer to client (may get enhanced)
  // generic return is "all good"
  $r = array();
  $r["error"] = EC_OK;
  $r["skip"]  = false;
  
  $w = getFromHash($args[0], "words", array());
  if (count($w) < 1) {
      return $r; // empty searchwords, ignore this call.
  }
  $w = array_unique($w); // remove duplicates
  
  if ($vid === null) {
      $vid = getFromHash($clientAnswer, "vid", "");
  }
  if (!validateHEX($vid) || $vid == "") {
      // Returned vid seems missing. For this, we simply
      // ignore such calls.
      return $r;
  }
  
  $values = "";
  $fields = array();
  foreach ($w as $word) {
      $values .= "('{$vid}',?),"; // generate PDO string
      array_push($fields, $word);
  }
  $values = substr($values, 0, -1); // remove last comma
  
  $sql = "INSERT INTO search(PID, WORD) VALUES" . $values;
  $ret = ExecuteSQL($sql, $fields);
  if (!$ret) {
      $r["error"]     = EC_INTERNAL_ERROR;
      $r["errorDesc"] = "Failed search table insert! Contact DataVaccinator support!";
      return $r;
  }
  
  return $r;
}

// //////////////////////////////////
// "update" protocol handler
// //////////////////////////////////

// register post_update hook
add_listener('post_update', 'pl_search_update');

/**
 * Updating search hashes in database.
 * 
 * @param array $args
 * @return array
 */
function pl_search_update(array $args) {
  // generic return is "all good"
  $r = array();
  $r["error"] = EC_OK;
  $r["skip"]  = false;
  
  $vid = getFromHash($args[0], "vid", "");
  if ($vid == "") {
      // vid is mandatory. Seems something wrong here. Skip.
      return $r;
  }
  
  // first, delete all entries for this vid using existing function
  $r = pl_search_delete($args);
  if (getFromHash($r, "error", EC_OK) !== EC_OK) {
      return $r;
  }
  
  // now add new keywords using existing function for known VID
  $r = pl_search_add($args, $vid);
  
  return $r;
}

// //////////////////////////////////
// "delete" protocol handler
// //////////////////////////////////

// register post_delete hook
add_listener('post_delete', 'pl_search_delete');

/**
 * Deleting search hashes in database.
 * 
 * @param array $args
 * @return array
 */
function pl_search_delete(array $args) {
  // generic return is "all good"
  $r = array();
  $r["error"] = EC_OK;
  $r["skip"]  = false;
  
  $vid = getFromHash($args[0], "vid", "");
  $vidIds = explode(" ", $vid); // array of VIDs
  // generate PDO string
  $sVid = str_repeat("?,", count($vidIds));
  $sVid = substr($sVid, 0, -1); // remove last comma
  
  $sql = "DELETE FROM search WHERE PID IN({$sVid})";
  $ret = ExecuteSQL($sql, $vidIds);
  if (!$ret) {
      $r["error"]     = EC_INTERNAL_ERROR;
      $r["errorDesc"] = "Failed search table delete! Contact DataVaccinator support!";
      return $r;
  }
  
  return $r;
}
?>