<?php
/**
 * File: API.php
 *
 * Purpose:
 * - Primary entry point (front controller) for the REST-style API.
 * - Logs incoming request details for debugging.
 * - Includes the API implementation (`apiDef.php`) and delegates request handling to `MyAPI`.
 * - Wraps execution in a try/catch to ensure unexpected failures return JSON to the client.
 *
 * Typical usage:
 * - The client calls this script with a `request` parameter that contains the routed path.
 *   Example: API.php?request=messages/user/5
 *
 * Notes:
 * - This file assumes `apiDef.php` defines the `MyAPI` class and that it can process the
 *   request path provided in `$_REQUEST['request']`.
 *
 * Author: Sandip Bohara Chhetri
 */

error_log("Inside API.php GET:".json_encode($_GET));
error_log("Inside API.php Request:".json_encode($_REQUEST['request']));

require_once 'apiDef.php';

try
{
    /**
     * Construct the API router/handler.
     *
     * The constructor is expected to:
     * - Capture the HTTP method (GET/POST/PUT/DELETE).
     * - Parse the request string into endpoint + arguments.
     * - Read input parameters/body as needed.
     *
     * @param string $_REQUEST['request'] The routed path string for the API call.
     * @return void
     */
    $API = new MyAPI($_REQUEST['request']);  // Pass the information to constructor
    error_log("Inside API.PHP after constructor call");

    /**
     * Execute the routed endpoint and output its response.
     *
     * Important:
     * - In the current `apiDef.php` implementation, `processAPI()` typically *echoes* a JSON response
     *   and calls `exit;` internally (via a response helper).
     * - If `processAPI()` exits internally, the `echo` here will never run.
     * - If `processAPI()` instead returns a JSON string, `echo` will output it as expected.
     *
     * @return mixed Usually void (if it echoes/exits internally) or a JSON string (if implemented that way).
     */
    echo $API->processAPI();
    
}
catch(Exception $e)
{
    /**
     * Fallback error handling.
     *
     * If an exception is thrown anywhere above, return JSON describing the error.
     *
     * @return void Outputs JSON to the client.
     *
     * NOTE:
     * - There is a small bug in the original code: `$e.getMessage()` should be `$e->getMessage()`
     *   in PHP.
     * - This comment calls it out, but the logic/structure is preserved as requested.
     */
    echo json_encode(Array('error' => $e.getMessage()));
}