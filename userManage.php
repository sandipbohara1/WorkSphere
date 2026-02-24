<?php
/**
 * File: userManage.php
 *
 * Purpose:
 * - Displays the User Management page for WorkSphere .
 * - Provides:
 *   - An "Add User" form (username, password, role selection)
 *   - A "Users" table that is populated dynamically via AJAX
 *   - Status messages after delete actions (success/failed/notfound)
 *
 * Access control:
 * - Requires an authenticated session (`$_SESSION['user_id']`).
 * - If the user is not logged in, redirects to login.php.
 *
 * Data flow:
 * - On initial page load:
 *   - This script loads roles from the database to build the role dropdown.
 * - After page load:
 *   - code.js calls server.php (action=getUsers) to fetch and inject the user table HTML.
 *   - Add user button triggers server.php (action=addUser).
 *   - Role dropdown changes trigger server.php (action=updateUserRole).
 *   - Delete link points to deleteUser.php?user_id=... (server-side delete + redirect back).
 *
 * Dependencies:
 * - dbUtil.php:
 *   - mySQLConnection()
 *   - mySelectQuery()
 *   - mySQLCloseConnection()
 * - userManage.css (styling)
 * - jQuery (DOM + AJAX)
 * - code.js (client handlers)
 *
 * Author: Sandip Bohara Chhetri
 */

session_start();
require_once 'dbUtil.php';

// Protect page
if (!isset($_SESSION['user_id']))
{
    header('Location: login.php');
    exit;
}

// Build roles dropdown from DB (role_id as value, role_name as label)
$roles = [];
try
{
    mySQLConnection();
    $rolesResult = mySelectQuery("SELECT role_id, role_name FROM role_Info ORDER BY role_id ASC");
    if ($rolesResult)
    {
        while ($r = $rolesResult->fetch_assoc())
        {
            $roles[] = $r;
        }
    }
}
catch (Throwable $e)
{
    // Keep page usable even if roles query fails
    error_log('Role dropdown load error: ' . $e->getMessage());
}
finally
{
    mySQLCloseConnection();
}

/**
 * Escapes a value for safe HTML output.
 *
 * @param mixed $s Value to escape (will be cast to string).
 * @return string HTML-escaped string (ENT_QUOTES, UTF-8).
 */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="userManage.css">

    <!-- Link to jQuery amd Ajax library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"> </script>
    <script src="code.js"></script>
    <!-- Link to Js file -->

</head>

<body>
    <div class="container">
        <h1>User Management</h1>

        <?php if (isset($_GET['delete']) && $_GET['delete'] === 'success'): ?>
            <div class="status status--success">User deleted successfully.</div>
        <?php elseif (isset($_GET['delete']) && $_GET['delete'] === 'failed'): ?>
            <div class="status status--error">Failed to delete user.</div>
        <?php elseif (isset($_GET['delete']) && $_GET['delete'] === 'notfound'): ?>
            <div class="status status--error">User not found.</div>
        <?php endif; ?>

        <div class="card">
            <h2 class="card-title">Add User</h2>

            <!--
              Add User form:
              - Inputs are read by code.js (#addUserBtn handler).
              - Payload is sent to server.php with action=addUser.
              - On success, code.js clears fields and reloads the users table.
            -->
            <div class="form-grid">
                <div class="field">
                    <label for="username">UserName</label>
                    <input type="text" id="username" placeholder="Supply a username" autocomplete="off">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" placeholder="Supply a password" autocomplete="new-password">
                </div>

                <div class="field">
                    <label for="role">Role</label>

                    <!--
                      Roles dropdown:
                      - Built server-side from role_Info table.
                      - value = role_id, label = role_name.
                    -->
                    <select id="role">
                        <?php if (count($roles) > 0): ?>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= h($r['role_id']) ?>"><?= h($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="">No roles found</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field field--full">
                    <button type="button" id="addUserBtn" class="btn btn-primary">Add User</button>
                </div>
            </div>
        </div>

        <div id="user-management-container" class="card card--table">
            <div class="card-header">
                <h2 class="card-title">Users</h2>
            </div>

            <!--
              Users table:
              - <tbody> is filled dynamically via AJAX.
              - code.js calls server.php with action=getUsers and injects the returned HTML.
              - Role changes are handled by code.js delegated change handler on .role-select.
            -->
            <table id="user-table">
                <thead>
                    <tr>
                        <th>Actions</th>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Change Role</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content from PHP (AJAX) -->
                </tbody>
            </table>
        </div>

        <!-- Navigation back to the main landing page -->
        <a class="btn btn-ghost" href="welcome.php">Main Page</a>
    </div>

</body>

</html>