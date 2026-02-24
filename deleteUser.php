<?php
/**
 * File: deleteUser.php
 *
 * Purpose:
 * - Deletes a user record from the `users` table.
 * - This script is intended to be called from the user management workflow.
 *
 * Access control:
 * - Requires an active authenticated session (`$_SESSION['user_id']`).
 * - If no session is present, the request is redirected to login.php.
 *
 * Inputs:
 * - GET user_id (integer): The ID of the user to delete.
 *
 * Outputs:
 * - Redirects back to userManage.php with a query parameter indicating the result:
 *   - delete=success   : user deleted
 *   - delete=failed    : delete failed (DB error or query failure)
 *   - delete=notfound  : user_id does not exist
 *
 * Dependencies:
 * - dbUtil.php:
 *   - mySQLConnection()
 *   - mySelectQuery()
 *   - mySQLCloseConnection()
 *
 * Author: Sandip Bohara Chhetri
 */

session_start();
require_once 'dbUtil.php';

// Protect endpoint
if (!isset($_SESSION['user_id']))
{
    header('Location: login.php');
    exit;
}

/**
 * Reads the target user ID from the query string and validates it.
 * - Missing/invalid values are treated as failure and redirected back to userManage.php.
 */
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($userId <= 0)
{
    header('Location: userManage.php?delete=failed');
    exit;
}

try
{
    mySQLConnection();

    /**
     * Existence check:
     * - Verifies the user exists before attempting deletion.
     * - Prevents reporting "success" for a non-existent ID.
     */
    $check = mySelectQuery("SELECT user_id FROM users WHERE user_id = {$userId} LIMIT 1");
    if (!$check || $check->num_rows === 0)
    {
        header('Location: userManage.php?delete=notfound');
        exit;
    }

    /**
     * Delete operation:
     * - Executes a DELETE statement for the provided user_id.
     * - On success, redirect with delete=success.
     * - On failure, redirect with delete=failed.
     */
    $del = mySelectQuery("DELETE FROM users WHERE user_id = {$userId}");
    if ($del)
    {
        header('Location: userManage.php?delete=success');
        exit;
    }
    header('Location: userManage.php?delete=failed');
    exit;
}
catch (Throwable $e)
{
    /**
     * Error handling:
     * - Logs details to the server error log for debugging.
     * - Redirects to userManage.php with a failure status.
     */
    error_log('Delete user error: ' . $e->getMessage());
    header('Location: userManage.php?delete=failed');
    exit;
}
finally
{
    /**
     * Cleanup:
     * - Ensures the database connection is closed even if an exception occurs.
     */
    mySQLCloseConnection();
}