<?php
/*-------------------------------------------------------+
| DataVaccinator Service Provider System
| Copyright (C) Volker Schmid
| https://www.datavaccinator.com/
+--------------------------------------------------------+
| Filename: common.php
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
 * General routines used by the vaccinator service.
 * File is generated automatically from regify GmbH sources.
 * This will become generic at a later stage.
 */
###############################################################################
#    Output
###############################################################################

if (function_exists("so") == FALSE) {
    /**
    * Return a string in a way that it can get displayed in a secure way
    * "so" means "secure output".
    *
    * @param string $Output
    * @return string
    */
    function so($Output) {
        $Output = htmlspecialchars($Output, ENT_COMPAT, "UTF-8", FALSE);
        return $Output;
    }
}

/**
 * Returns $key from $hash or $default if either don't exist. Does so without
 * triggering a E_NOTICE event.
 *
 * @param array  $hash    array to find key in
 * @param string $key   key to look up
 * @param string $default default value to return when key is not found
 * @return string value or default
 */
function getFromHash($hash, $key, $default = "") {
    return isset($hash[$key])?$hash[$key]:$default;
}


###############################################################################
#    Debug functionality
###############################################################################

define('RF_LOG_NONE', 0);
define('RF_LOG_CRIT', 1);
define('RF_LOG_WARN', 2);
define('RF_LOG_INFO', 3);
define('RF_LOG_VERB', 4);

$LogLevelStrings = array(RF_LOG_NONE => 'NONE',
                         RF_LOG_CRIT => 'CRIT',
                         RF_LOG_WARN => 'WARN',
                         RF_LOG_INFO => 'INFO',
                         RF_LOG_VERB => 'VERB');

if (!isset($DebugLevel)) $DebugLevel = RF_LOG_INFO;

/**
 * Log a debug entry into the php.log. Following levels are possible:
 * RF_LOG_NONE (0)
 * RF_LOG_CRIT (1)
 * RF_LOG_WARN (2)
 * RF_LOG_INFO (3)
 * RF_LOG_VERB (4)
 *
 * @param mixed $level
 * @param mixed $msg
 * @param mixed $Origin stacktrace if called from another log wrapper else one
 *              will get generated here pointing to the caller of this function.
 *
 * RF_LOG_CRIT is always logged because it is critical, right?
 */
function DbgLog($level, $msg, $Origin = "") {
    global $DebugLevel, $LogLevelStrings;
    
    if ($DebugLevel == RF_LOG_NONE && $level != RF_LOG_CRIT) { return; }
    if ($level > $DebugLevel && $level != RF_LOG_CRIT) { return; }
    // debug-mode is on!
    if ($Origin == "") {
        $Origin = getFormattedBt(debug_backtrace(), 'DbgLog');
    }
    $tid = '';
    if (is_array($_SERVER)) {
        $tid = sprintf("%08x ", abs(crc32(getFromHash($_SERVER, 'REMOTE_ADDR') .
                    getFromHash($_SERVER, 'REQUEST_TIME') .
                    getFromHash($_SERVER, 'REMOTE_PORT'))));
    }
    // flatten logs to make them filterable
    $msg = preg_replace('/\\s*\\n\\s*/', ' ', $msg);
    // allow filtering like
    # tail /var/log/php.log | sed -re 's/- - - .*//'
    error_log($tid.$LogLevelStrings[$level].": $msg - - - - -$Origin");
}

/**
 * Pretty formats a debug_backtrace() to be dropped into a log entry or db
 * @param array $Debug the debug_backtrace()
 * @param string $logFunc function name of the caller without ()
 * @return formatted debug back trace
 */
function getFormattedBt($Debug, $logFunc) {
    $Index = count($Debug) - 1;
    $Origin = "[";
    for($i=$Index;$i>=0;$i--){
        $class = getFromHash($Debug[$i], "class", "");
        if ($class != "") { $class = "$class."; }
        $function = getFromHash($Debug[$i], "function", "") . "()";
        if ($function == "$logFunc()") { $function = "line"; }
        $line = ":".getFromHash($Debug[$i], "line", "");
        $Origin = $Origin . @basename($Debug[$i]["file"]) . "->"
                . $class . $function . $line . " ";
    }
    return $Origin . "]";
}


/**
* Check, if a string is correctly HEX encoded.
* Returns false on wrong chars and on empty string!
*
* @param mixed $Hex String to check
* @return bool true, if it is valid hex encoded and not empty
*/
function validateHEX($Hex) {
    return ctype_xdigit($Hex);
}


/**
* Post the given data array to url and returns the response without the headers
* or FALSE in case of an error.
*
* Do not mix a data value string (body) with files array!
*
* @param string $url the url to post the data to
* @param mixed $data the data to post (array for form fields or string for body)
* @param array $files filenames of files to add
* @param string $proxy enables proxy usage
* @param string &$error where the error is written to in case of failure
* @param int $TimeoutSec function timeout in seconds
* @param int $logLevel RF_LOG_XXXX log level whether do fill &$error with debug info
* @param string $CaCertFile path to the ca cert file or directory
*               if SSL & $sslVerify = TRUE
* @param bool $sslVerify whether to check SSL stuff
* @param string $cookieFile where to read cookies from and write them to
* @param array $addHeaders to add additional headers to the request
* @return the body of the response without the headers.
*
* Example call:
* $postdata = Array("content-type" => "text/html", "x-test" => "some test");
* $files = Array("file" => "/var/opt/filename.dat");
* Result = PostRequest("sign.regify.com/sign.php", $postdata, $files);
*/
function DoRequest($url, $data, $files = null, $proxy = "", &$error = "",
                   $TimeoutSec = 8, $logLevel = RF_LOG_NONE, $CaCertFile = '',
                   $sslVerify = TRUE, $cookieFile = null, $addHeaders = null) {
    $h = curl_init();
    $error = '';
    If ($h == 0) {
        $error .= "Error calling curl_init. Check your cURL setup.";
        return False;
    }
    $isSSL = strtolower(substr($url, 0 ,5)) == "https";
    $ret = false;

    do {
        If ($logLevel >= RF_LOG_VERB) {
            // set debugging options to curl
            If (! curl_setopt($h, CURLOPT_VERBOSE, 1)) {
                $error .= "Error setting CURLOPT_VERBOSE. Curl ec:".curl_error($h);
            }

            If (! curl_setopt($h, CURLINFO_HEADER_OUT, true)) {
                $error .= "Error setting CURLINFO_HEADER_OUT. Curl ec:".curl_error($h);
            }
        }

        // Using with a proxy
        If ($proxy != "") {
            // activate proxy

            // establish a proxy tunnel only for https calls (not http)
            If (! curl_setopt($h, CURLOPT_HTTPPROXYTUNNEL, $isSSL)) {
                $error .= "Error setting CURLOPT_HTTPPROXYTUNNEL. Curl ec:"
                        . curl_error($h);
                break;
            }

            If (! curl_setopt($h, CURLOPT_PROXY, $proxy)) {
                $error .= "Error setting CURLOPT_PROXY. Curl ec:".curl_error($h);
                break;
            }


        } else {
            // deactivate proxy
            If (! curl_setopt($h, CURLOPT_HTTPPROXYTUNNEL, false)) {
                $error .= "Error deactivating CURLOPT_HTTPPROXYTUNNEL. Curl ec:".
                        curl_error($h);
                break;
            }
        }

        // setup SSL here
        if ($isSSL) {

            if ($sslVerify == false) {
                # debug mode
                $verifyPeer = false;
                $verifyHost = 0;
            } else {
                If (is_dir($CaCertFile)) {
                    $ret = curl_setopt($h, CURLOPT_CAPATH, $CaCertFile);
                } else {
                    $ret = curl_setopt($h, CURLOPT_CAINFO, $CaCertFile);
                }

                If (!$ret) {
                    $error .= "Cannot initialize $CaCertFile. Curl error ".
                                curl_error($h);
                    break;
                }

                $verifyPeer = true;
                $verifyHost = 2;
            }

            # force to verify SSL hosts! 1=verify peer certificate
            If (! curl_setopt($h, CURLOPT_SSL_VERIFYPEER, $verifyPeer)) {
                $error .= "Error setting CURLOPT_SSL_VERIFYPEER. Curl ec:".
                            curl_error($h);
                break;
            }

            # 2=validate hostname of peer-certificate
            If (! curl_setopt($h, CURLOPT_SSL_VERIFYHOST, $verifyHost)) {
                $error .= "Error setting CURLOPT_SSL_VERIFYHOST. Curl ec:".
                            curl_error($h);
                break;
            }
        }

        if ($cookieFile) {
            # do da cookie dance
            if (is_file($cookieFile)) {
                If (! curl_setopt($h, CURLOPT_COOKIEFILE, $cookieFile)) {
                    $error .= "Error setting CURLOPT_COOKIEFILE. Curl ec:". curl_error($h);
                    break;
                }
            }
            if (is_dir(dirname(CURLOPT_COOKIEJAR))) {
                If (! curl_setopt($h, CURLOPT_COOKIEJAR, $cookieFile)) {
                    $error .= "Error setting CURLOPT_COOKIEJAR. Curl ec:". curl_error($h);
                    break;
                }
            }
        }
        
        if ($addHeaders) {
            If (! curl_setopt($h, CURLOPT_HTTPHEADER, $addHeaders)) {
                $error .= "Error setting CURLOPT_HTTPHEADER. Curl ec:". curl_error($h);
                break;
            }
        }
        
        # dont output header in result
        If (! curl_setopt($h, CURLOPT_HEADER, false)) {
            $error .= "Error setting CURLOPT_HEADER. Curl ec:". curl_error($h);
            break;
        }

        # set connection url
        If (! curl_setopt($h, CURLOPT_URL, $url)) {
            $error .= "Error setting URL. Curl ec:" . curl_error($h);
            break;
        }

        $fpost = Array();
        if (is_array($files) == TRUE && count($files) > 0) {
            // add files to post data
            DbgLog($logLevel, "Add " . count($files) . " files to cURL post.");
            // prepare array to set filenames with beginning @
            foreach ($files as $field => $filename) {
                if (file_exists($filename) == TRUE) {
                    $fpost[$field] = "@" . $filename;
                } else {
                    DbgLog(RF_LOG_CRIT, "Did not add file $filename to cURL " .
                            "request, because it does not exist!");
                }
            }
            // merge files data to $data array
            if (! is_array($data)) { $data = Array(); }
            $data = $data + $fpost; // valid way to merge two arrays with preserved keys
        }

        if (is_array($data) == TRUE && count($data) > 0) {
            // set post data only, if array contains values
            DbgLog(RF_LOG_VERB, "Set cURL postfields with " .
                                count($data) . " values.");
            if (! curl_setopt($h, CURLOPT_POSTFIELDS, $data)) {
                $error .= "Error setting POSTFIELDS. Curl ec:" . curl_error($h);
                break;
            }
        } else {
            if (is_string($data) == true && strlen($data) > 0) {
                DbgLog(RF_LOG_VERB, "Set cURL post-body with string.");
                if (! curl_setopt($h, CURLOPT_POSTFIELDS, $data)) {
                    $error .= "Error setting POSTFIELDS. Curl ec:" . curl_error($h);
                    break;
                }
            } else {
                DbgLog(RF_LOG_VERB, "Dont set cURL POST data, because data-array is empty.");
            }
        }

        # return result with exec
        If (! curl_setopt($h, CURLOPT_RETURNTRANSFER, true)) {
            $error .= "Error setting CURLOPT_RETURNTRANSFER. Curl ec:". curl_error($h);
            break;
        }

        # set timeout for this function
        If (! curl_setopt($h, CURLOPT_CONNECTTIMEOUT, $TimeoutSec)) {
            $error .= "Error setting CURLOPT_RETURNTRANSFER. Curl ec:". curl_error($h);
            break;
        }
        
        # only lookup IPv4 address here
        If (! curl_setopt($h, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4)) {
            $error .= "Error setting CURLOPT_IPRESOLVE. Curl ec:". curl_error($h);
            break;
        }

        $ret = @curl_exec($h);
        $code = curl_getinfo($h, CURLINFO_HTTP_CODE);
        if ($ret === FALSE) {
            $error .= "Error executing curl call ($code). Error:".curl_error($h);
        } else {
            DbgLog(RF_LOG_VERB, "cURL result ($code): '$ret'.");
        }

        If ($logLevel == RF_LOG_VERB) {
            $Message = curl_getinfo($h, CURLINFO_HEADER_OUT);
            DbgLog(RF_LOG_VERB, "cURL SENT HEADER: " . trim($Message));
            $Message = curl_getinfo($h, CURLINFO_SSL_VERIFYRESULT);
            DbgLog(RF_LOG_VERB, "cURL SSL VERIFY RESULT: $Message");
        }

    } while (false);

    curl_close($h);

    return $ret;

}

/**
 * Returns a valid DB connector which already points to DBDATABASE.
 * Speeds up by re-using db connection inside a session.
 *
 * In high load environment, please use ClearDBConnector() at the
 * end of the PHP session to free ressources! Otherwise, PHP needs
 * to close mysql connections but this may need time and the number
 * of connectors is somewhat limited.
 *
 * If $close is TRUE, the existing connector is only closed and no
 * connector is returnd!
 *
 * @param mixed $close
 * @return DBConnectionHandle
 */
function GetDBConnector($close = false) {
    
    static $dbConn = null;
    if (isValidConnection($dbConn)) {
        return $dbConn;
    }

    if ($close == true) {
        $dbConn = null;
        return;
    }

    try {
        if (defined('PDO::MYSQL_ATTR_MAX_BUFFER_SIZE')) {
            $options = array(PDO::ATTR_EMULATE_PREPARES => false,
                             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                             PDO::MYSQL_ATTR_MAX_BUFFER_SIZE => 1024*1024*16,
                             PDO::ATTR_STRINGIFY_FETCHES => true);
        } else {
            $options = array(PDO::ATTR_EMULATE_PREPARES => false,
                             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                             PDO::ATTR_STRINGIFY_FETCHES => true);
        }
        $dbConn = new PDO("mysql:host=" . DBHOST . ";dbname=" . DBDATABASE . "", DBUSER, DBPASSWORD, $options);
    } catch (PDOException $e) {

        DbgLog(RF_LOG_CRIT, "DATABASE: Can not open database " . DBHOST . "." . DBDATABASE . " for execute.");
        DbgLog(RF_LOG_CRIT, "DATABASE: error-details: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }

    return $dbConn;
}

/**
 * Clears a database connection created by GetDBConnector()!
 *
 * IMPORTANT: Do not call if some other DB request will follow!!!!
 *            Every DB request after calling this function will fail!
 */
function ClearDBConnector() {
    GetDBConnector(true);
}

/**
 * Class for simple database-access.
 * @package regify
 * <code>
 * $db = new classOpenDB("SELECT COUNT(*) AS ANZ FROM tbluser");
 * $row = $db->fetchArray();
 * echo $row["ANZ"]
 * </code>
 * WARNING: Return Field-Values are case-sensitive (["Anz"] will not work!)
 *
 * ===============================================================================================
 * It is desired to use prepared statments to prevent SQL injuction then provide a SQL statment
 * without any arguments and pass the arguments as an array as the example here below demonstrates
 * ===============================================================================================
 * @example
 * <code>
 *   $instance = new classOpenDB("SELECT * from tbluser where userid > ? limit ?", array(3, 10));
 *   while($row = $instance->fetchArray()) {
 *     print_r($row);
 *   }
 * </code>
 * @Note Please note that the variable order in the $fields array is importnat.
 *
 */
class classOpenDB {

    private $connection = NULL;     // Pointer to the database connection.
    private $result     = NULL;     // Pointer to the result.
    private $hasError   = FALSE;    // Stores errors, if any occures during database operations.
    public $ResultCount = 0;        // Counts the results fetched from the database.

    /**
     * Connects to the database and executes a query
     *
     * @param string $SQL
     * @return classOpenDB
     */
    public function __construct($SQL = "", $fields = array())  {

        if ($this->connect()) {
            $this->hasError = FALSE;
            if (!empty($SQL)) {
                $this->query($SQL, $fields);
            }
        } else {
            $this->hasError = TRUE;
        }
    }

    /**
     * Closes the connection to the database server when the object reaches its life cycle.
     * Please note that: There is no need to close the connections as they will be closed automatically at the
     * end of the script's execution.
     */
    public function __destruct() {
        $this->disconnect(); // Close the connection by setting the pointer to null.
        // The garbage collector will handle the reste.
    }

    /**
     * Checks if this instance is already connected to the database.
     * @return bool
     */
    public function hasConnection() {
        return isValidConnection($this->connection);
    }

    /**
     * Checks if an the last execute SQL command has failed.
     * @return bool
     */
    public function hasError() {
        return $this->hasError;
    }

    /**
     * Connects to the database. If the server is already connect to the database then it closes the connection first.
     * If it cannot connect to the database then it will write to the log file and executes an exist(); to stop the execution
     * as the provider should not be running if no database is available.
     *
     * @return bool
     */
    public function connect() {
        try {
            $this->connection = GetDBConnector();
            return TRUE;
        } catch (Exception $e) {
            return false;
        }
    }

    /***
     * Disconnects from the database server by closing the connection.
     * PDO closes the connection when the pointer is set to null.
     */
    public function disconnect() {
        $this->connection = null;
    }

    /***
     * Clean the result if not already cleaned.
     */
    function CleanResult() {
        if (isValidStatment($this->result)) {
            $this->result->closeCursor();
            $this->result = null;
        }
    }

    /**
     * Queries the database. In order to fetch the record either of fetchArray() or fetchObject
     * need to be called.
     *
     * ===============================================================================================
     * It is desired to use prepared statments to prevent SQL injuction then provide a SQL statment
     * without any arguments and pass the arguments as an array as the example here below demonstrates
     * ===============================================================================================
     * @example
     * <code>
     *   $instance = new classOpenDB();
     *   $instance->query("SELECT * from tbluser where userid > ? limit ?", array(3, 10));
     *   while($row = $instance->fetchArray()) {
     *     print_r($row);
     *   }
     * </code>
     * @Note Please note that the variable order in the $fields array is importnat.
     *
     * @param string $query
     */
    public function query($query, $fields = array()) {

        // Check the connection
        try {
            if ($this->connection == null) {
                $this->connection = GetDBConnector();
            }
        } catch (Exception $e) {
            $this->hasError = true;
            return false;
        }

        $this->CleanResult(); // Clean the result if not already cleaned.
        $this->ResultCount = 0; // Reset the result count.
        $this->result = null; // Reset the result set.

        try {

            if (empty($fields)) { // Executes without native PDO protection against SQL injection.
                DbgLog(RF_LOG_VERB, __FUNCTION__.": $query");
                $this->result = $this->connection->query($query); // Run the query
            } else { // Native protection against SQL injection
                DbgLog(RF_LOG_VERB, __FUNCTION__.":[$query] vars ["
                                .print_r($fields, TRUE).']');
                $this->result = $this->connection->prepare($query);
                $this->result->execute($fields);
            }

            if (strpos($query, 'SQL_CALC_FOUND_ROWS') !== false) {
                $this->ResultCount = $this->connection->query("select found_rows()")->fetchColumn(); // Set the count
            } else {
                $this->ResultCount = $this->result->rowCount(); // Set the count
            }
            
            $this->hasError = false; // If everything is OK then set hasError to false.
        } catch (Exception $e) {
            DbgLog(RF_LOG_CRIT, "DATABASE: error in query: $query");
            DbgLog(RF_LOG_CRIT, "DATABASE: error-details: " . $e->getMessage() .
                " / " . $this->connection->errorCode());
            $this->hasError = true;
        }
    }

    /**
     * Returns the data retrieved by query row by row in an array format.
     * @return array.
     */
    public function fetchArray() {
        // WARNING: Return Field-Values are case-sensitive!
        return $this->dataFetcher(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the data retrieved by query row by row in an object format.
     * @return STD object.
     */
    public function fetchObject() {
        return $this->dataFetcher(PDO::FETCH_OBJ);
    }

    /**
     * Encapsulates the shared function of the two functions fetchArray() and fetchObject().
     * @param constant $type : PDO::FETCH_ASSOC|PDO::FETCH_OBJ
     */
    protected function dataFetcher($type) {

        // WARNING: Return Field-Values are case-sensitive!
        if (!isValidStatment($this->result)) {
            return false;
        }

        $result = $this->result->fetch($type);
        if (is_array($result) or is_object($result)) {

            return $result;
        }

        $this->CleanResult(); // Done, free any allocated resources.
        return false;
    }

    /**
     * Starts a connection by turning off the auto commit.
     * @param PDO $connection
     */
    public function TransactionStart() {
        TransactionStart($this->connection);
    }

    /**
     * Commits transactions done since TransactionStart() has been called . Restores the auto-commit.
     * @param PDO $connection
     */
    public function TransactionCommit() {
        TransactionCommit($this->connection);
    }

    /**
     * Rolls back the changes to the status before the TransactionStart() was called. Restores the auto-commit.
     * @param PDO $connection
     */
    public function TransactionRollback() {
        TransactionRollback($this->connection);
    }
}

/**
 * Checks if the result of PDO::query is a valid PDOStatment.
 * @param boolean $result
 */
function isValidStatment($result) {
    return $result instanceof PDOStatement;
}

/**
 * Starts a connection by turning off the auto commit.
 * @param PDO $connection
 */
function TransactionStart($connection) {
    $connection->beginTransaction();
}

/**
 * Commits transactions done since TransactionStart() has been called . Restores the auto-commit.
 * @param PDO $connection
 */
function TransactionCommit($connection) {
    $connection->commit();
}

/**
 * Rolls back the changes to the status before the TransactionStart() was called. Restores the auto-commit.
 * @param PDO $connection
 */
function TransactionRollback($connection) {
    $connection->rollback();
}

/**
 * Checks if the current connection is valid by making sure that it's a PDO instance.
 * @param mixed $connection
 * @return boolean
 */
function isValidConnection($connection) {
    return $connection instanceOf PDO;
}

/**
 * Does a SQL-Execute to the database.
 * Returns true in case of success, false in case of failure.
 *
 * ===============================================================================================
 * It is desired to use prepared statments to prevent SQL injuction then provide a SQL statment
 * without any arguments and pass the arguments as an array as the example here below demonstrates
 * ===============================================================================================
 * @example
 * <code>
 *  ExecuteSQL("UPDATE `tbldomains` SET `GROUPID`=? WHERE  `DOMAINID`>?;",  array(9, 3));
 *  $totalModified = $_SESSION["db_LastAffectedRows"];
 * </code>
 *
 * @param mixed $SQL
 * @param array $fields
 *
 * @return boolean success
 */
function ExecuteSQL($SQL, $fields = array()) {
    
    $_SESSION["db_LastAffectedRows"] = 0;
    

    // Connect to the database
    try {
        $DBCONN = GetDBConnector();
    } catch (Exception $e) {
        return false; // Return false to indicate that something went wrong.
    }

    // Query the database
    try {
        $_SESSION["db_LastAffectedRows"] = 0;

        if (empty($fields)) {
            // Execute a query native PDO protection against SQL injection.
            
            DbgLog(RF_LOG_VERB, __FUNCTION__.": $SQL");
            $_SESSION["db_LastAffectedRows"] = $DBCONN->exec($SQL);
        } else { // With native protection against SQL injection.
            
            DbgLog(RF_LOG_VERB, __FUNCTION__.":[$SQL] vars ["
                                .print_r($fields, TRUE).']');
            $statement = $DBCONN->prepare($SQL);
            $statement->execute($fields);
            $_SESSION["db_LastAffectedRows"] = $statement->rowCount();
        }

        return true;
    } catch (Exception $e) {

        DbgLog(RF_LOG_CRIT, "DATABASE: Error while executing " . $SQL);
        DbgLog(RF_LOG_CRIT, "DATABASE: error-details: " . $e->getMessage()
                . " / " . $DBCONN->errorCode());
        return false;
    }
}

/**
 * Does a SQL-Insert to the database. Returns the new AutoInc. value!
 * Dont forget to enter the table-name in $TableName!
 * Returns 0 (zero) in case of failure.
 * @deprecated use @insertWithLastId instead
 * ===============================================================================================
 * It is desired to use prepared statments to prevent SQL injuction then provide a SQL statment
 * without any arguments and pass the arguments as an array as the example here below demonstrates
 * ===============================================================================================
 * @example
 * <code>
 * $id = InsertWithResult("INSERT INTO `tbldomains` (`DOMAINNAME`, `SUBPROVIDERID`, `GROUPID`) VALUES (?, ?, ?)",
 *                        null,          // Table name is no longer used
 *                        array('regify.fr', 2, 15));
 * debug($id);
 * </code>
 *
 * @param string $SQL
 * @param string $TableName : not used anymore to retrieve the lastinsertid. Kept for compatibility reasons
 * @param array $fields
 *
 * @return integer: integer ID of the new entry
 */
function InsertWithResult($SQL, $TableName, $fields = array()) {
    return insertWithLastId($SQL, $fields);
}

/**
 * Does a SQL-Insert to the database. Returns the new AutoInc. value!
 * Returns 0 (zero) in case of failure.
 *
 * ===============================================================================================
 * It is desired to use prepared statments to prevent SQL injuction then provide a SQL statment
 * without any arguments and pass the arguments as an array as the example here below demonstrates
 * ===============================================================================================
 * @example
 * <code>
 * $id = insertWithLastId("INSERT INTO `tbldomains` (`DOMAINNAME`, `SUBPROVIDERID`, `GROUPID`) VALUES (?, ?, ?)",
 *                        array('regify.fr', 2, 15));
 * debug($id);
 * </code>
 *
 * @param string $SQL
 * @param array $fields
 *
 * @return integer: integer ID of the new entry
 */
function insertWithLastId($SQL, $fields = array()) {
    
    // do not log inserts to blob fields of tblfiles (binary stuff makes trouble in logfile)
    $doLog = (substr($SQL, 0, 20) != "INSERT INTO tblfiles");

    // Connect to the database
    try {
        $DBCONN = GetDBConnector();
    } catch (Exception $e) {
        return false; // Return false to indicate that something went wrong.
    }

    // Insert data into the database
    try {

        if (empty($fields)) {
            // Execute a query native PDO protection against SQL injuction.
            if ($doLog) {
                DbgLog(RF_LOG_VERB, __FUNCTION__.": $SQL");
            } else {
                DbgLog(RF_LOG_VERB, __FUNCTION__.
                        ": [not shown because of binary data to tblfiles]");
            }
            $DBCONN->exec($SQL);
        } else { // With native protection against SQL injuction.

            if ($doLog) {
                DbgLog(RF_LOG_VERB, __FUNCTION__.": [$SQL] vars [".
                                    print_r($fields, TRUE).']');
            } else {
                DbgLog(RF_LOG_VERB, __FUNCTION__.
                        ": [not shown because of binary data to tblfiles]");
            }
            $statement = $DBCONN->prepare($SQL);
            $statement->execute($fields);
        }
    } catch (Exception $e) {
        if (function_exists("debug") == TRUE) {
            debug($e->getMessage());
        }
        DbgLog(RF_LOG_CRIT, "DATABASE: Error inserting with " . $SQL);
        DbgLog(RF_LOG_CRIT, "DATABASE: error-details: " . $e->getMessage()
                            . " / " . $DBCONN->errorCode());
        return 0; // Failed return 0.
    }

    return $DBCONN->lastInsertId(); // Return the id of the last inserted row
}

/**
 * Returns the first row of the result of a sql-query. Result is an Array.
 *
 * <code>
 * $Result = GetOneSQLValue("SELECT Username,Password FROM tbluser WHERE UserID=41");
 * echo $Result["USERNAME"];
 * echo $Result["PASSWORD"];
 * </code>
 *
 * WARNING: Returned Field-Values are case-sensitive!
 * Returns 0 in case of no result!
 * Returns FALSE in case of an error!
 *
 * ATTENTION:
 * Do not compare Result === FALSE as this is wrong if no result.
 * Better use Result == 0, which catches up both no result and error.
 *
 * ===============================================================================================
 * It is desired to use prepared statments to prevent SQL injuction then provide a SQL statment
 * without any arguments and pass the arguments as an array as the example here below demonstrates
 * ===============================================================================================
 * @example
 * <code>
 * $result = GetOneSQLValue("SELECT * from tbluser where userid=? AND username=?", array(55, "John.Doe"));
 * </code>
 * @Note Please note that the variable order in the $fields array is importnat.
 *
 * @param string $SQL
 * @param array $fields
 * @return array
 */
function GetOneSQLValue($SQL, $fields = array()) {

    // Connect to the database
    try {
        $DBCONN = GetDBConnector();
    } catch (Exception $e) {
        return false; // Return false to indicate that something went wrong.
    }

    // Query the database
    $statement = null;
    try {

        if (empty($fields)) { // Execute a query native PDO protection against SQL injuction.
            DbgLog(RF_LOG_VERB, __FUNCTION__.": $SQL");
            $statement = $DBCONN->query($SQL);
        } else {
            DbgLog(RF_LOG_VERB, __FUNCTION__.": [$SQL] vars ["
                                . print_r($fields, TRUE) . ']');
            $statement = $DBCONN->prepare($SQL);
            $statement->execute($fields);
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return is_array($result) ? $result : 0;
    } catch (PDOException $e) { // Bad query. Log the error
        DbgLog(RF_LOG_CRIT, "Bad Query: " . $SQL . " error-details: " . $e->getMessage());
        return FALSE; // Return false to indicate that something went wrong.
    }
}

/**
 * Returns multiple results as a nicely ordered array
 *
 * @param string $query : valid SQL query
 * @returns Array
 */
function GetMultipleResults($query = "", $fields = array()) {

    $result = array();                      // Declare an empty array. This function will return this array in any case.
    $db = new classOpenDB($query, $fields); // Create a new instance of the database and pass it the query.

    while ($row = $db->fetchArray())        // Loop though the result of the query and save the each result in the result array.
        $result[] = $row;
    return $result;                         // Return the result array at the end of the function.
}
 
/**
 * Insert into table with BLOB. Sql statement must be in this form:
 * ONLY A PLACEHOLDER. NOT NEEDED FOR MYSQL BUT ONLY FOR ORACLE!!!!!
 *
 * @return boolean FALSE
 */
function InsertWithResultBLOB($SQL, $TableName, $VariableName, $BinaryData) {
    DbgLog(RF_LOG_CRIT, "Calling InsertWithResultBLOB() on MySQL system. This is not allowed!");
    return FALSE;
}

?>
