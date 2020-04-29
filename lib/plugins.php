<?php
/*
 * DataVaccinator plugin handler
 * 
 * Based on a stack overflow post here:
 * https://stackoverflow.com/questions/42/best-way-to-allow-plugins-for-a-php-application
 */

/*
 * A plugin has to return a map array with these values:
 * boolean "skip"       -> if to skip the original function (only pre_ events)
 * integer "error"      -> error code if some error happened (EC_OK if not)
 * string  "errorDesc"  -> error description if error <> EC_OK
 * 
 * If "error" is != EC_OK, the protocol handler will stop immediately, sending
 * back an error to the client with the given error code and description.
 * 
 * A plugin may access global array $clientAnswer to see and manipulate the
 * prepared answer to the client (only available in post_ events).
 */

// global array holding event hooks
$listeners = array();

// global array holding names of loaded plugins
$plugins = array();

// Create an entry point for plugins
function hook() {
    global $listeners;

    $num_args = func_num_args();
    $args = func_get_args();

    if($num_args < 2) {
        trigger_error("Insufficient arguments", E_USER_ERROR);
    }

    // Hook name should always be first argument
    $hook_name = array_shift($args);

    if(!isset($listeners[$hook_name])) {
        return false; // No plugins have registered this hook
    }

    foreach($listeners[$hook_name] as $func) {
        $args = $func($args); 
    }
    return $args;
}

// Attach a function to a hook
function add_listener($hook, $function_name) {
    global $listeners;
    $listeners[$hook][] = $function_name;
}

/* Example

// Sample Plugin 
add_listener('a_b', 'my_plugin_func1');
add_listener('str', 'my_plugin_func2');

function my_plugin_func1($args) {
    return array(4, 5);
}

function my_plugin_func2($args) {
    return str_replace('sample', 'CRAZY', $args[0]);
}

// Sample Application
$a = 1;
$b = 2;

list($a, $b) = hook('a_b', $a, $b);

$str  = "This is my sample application\n";
$str .= "$a + $b = ".($a+$b)."\n";
$str .= "$a * $b = ".($a*$b)."\n";

$str = hook('str', $str);
echo $str;
*/