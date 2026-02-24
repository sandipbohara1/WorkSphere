<?php
/**
 * File: dbUtil.php
 *
 * Purpose:
 * - Provides reusable database utility functions for connecting to and interacting with a MySQL database.
 * - Centralizes database connection logic using the mysqli extension.
 * - Exposes helper functions for:
 *      - Establishing a database connection.
 *      - Executing SELECT queries.
 *      - Executing non-SELECT (DML) queries such as INSERT, UPDATE, DELETE.
 *
 * Global Variables:
 * - $mysql_connection : Holds the active mysqli connection object.
 * - $mysql_response   : Placeholder array for database responses (not actively used in this file).
 * - $mysql_status     : Status string for error or state reporting.
 *
 * Author: Sandip Bohara Chhetri
 */

    error_log("In DB Util file");

    // Database connection
    $mysql_connection = null;

    // Response from DB 
    $mysql_response=array();

    // Status string that will be appended to
    $mysql_status = "";


    //funtion to create database connection

    /**
     * Establishes a connection to the MySQL database using mysqli.
     *
     * Uses hard-coded credentials to connect to:
     * - Server
     * - Username
     * - Password
     * - Database name
     *
     * @return bool
     *      true  - If the connection is successfully established.
     *      false - If connection fails.
     */
    function mySQLConnection()
    {
        //grab hold on to connection variables first
        global  $mysql_connection, $mysql_response, $mysql_status;

        //Try to connect to DB
        /*mysqli() - it will take 4 parameters
        1. DB Name
        2. User Name
        3. Password
        4. Server
        */                              //Server     Username    Password       DB name
        $mysql_connection = new mysqli("localhost", "Username", "Password", "sandipt1241_phpDatabase"); //these credentials are dummy credentintials (not Valid credentials)
        
        /**
         * Conditional Logic Explanation:
         * - connect_errno will be non-zero if the connection fails.
         * - If there is an error, return false immediately.
         * - Otherwise, log success and return true.
         */
        if($mysql_connection->connect_errno)
        {
            echo"Error while establishing connection";
            return false;
        }
        //If we are here, it means connection is successful
        error_log("Connected to Library/DB.");
        return true;
    }

    // Funtion to handle all select queries
    // Parameters: Input - query string
    //             Output :Data or false if there is any issue

    /**
     * Executes a SELECT query against the active MySQL connection.
     *
     * @param string $myquery The SQL SELECT query string to execute.
     *
     * @return mysqli_result|bool
     *      mysqli_result - If query executes successfully.
     *      false         - If connection is invalid or query fails.
     */
    function mySelectQuery($myquery)
    {
        //grab hold on to connection variables first
        global  $mysql_connection, $mysql_response, $mysql_status;

        $result = false;

        /**
         * Conditional Logic Explanation:
         * - If $mysql_connection is null, no connection was established.
         * - In that case, log an error and return false.
         */
        if($mysql_connection == null)
        {
            error_log("No active connection.");
            $mysql_status = "No active connection or you are missing connection";
            return $result;
        }
        else
        {
            /**
             * Attempt to execute the query.
             * - If query() returns false, it indicates a SQL error.
             * - Otherwise, it returns a mysqli_result object.
             */
            if (!($result = $mysql_connection ->query($myquery)))
            {
                // No result - error
                error_log("Error while running the query.");
                return $result;
                //die();
            }
            //if we are here it means all good return your result back to user
            return $result;
        }        
    }

    /**
     * Executes a non-SELECT (DML) query such as:
     * - INSERT
     * - UPDATE
     * - DELETE
     *
     * @param string $dmlquery The SQL DML query string to execute.
     *
     * @return int|string
     *      int    - Number of affected rows on success.
     *      string - Error message if connection fails or query execution fails.
     */
    function mySQLNonSelectQuery($dmlquery)
    {
        // grab hold on to connection variables first
        global $mysql_connection, $mysql_response, $mysql_status;

        $result = false;

        /**
         * Conditional Logic Explanation:
         * - If connection is null, return a descriptive error message.
         * - Otherwise, attempt to execute the query.
         */
        if ($mysql_connection == null)
        {
            error_log("No active connection");
            return "No active connection Make sure you are still part of organizatio";
        } 
        else
        {
            /**
             * Execute the DML query.
             * - If query() fails, log error and return error string.
             * - If successful, return number of affected rows.
             */
            if (!($mysql_connection->query($dmlquery)))
            {                
                error_log("Error while performing DML opeartion");
                return "Error while performing DML opeartion";
            }
            else
            {
                return $mysql_connection->affected_rows; 
            }
        }
    }

?>