<!--
  File: roleManage.php

  Purpose:
  - Provides the Role Management user interface.
  - Displays the existing roles in a table.
  - Provides a form to add a new role (name, privileges, description).

  Behavior:
  - Roles are loaded dynamically into the table body (#role-table tbody) by code.js
    when the page path ends with "roleManage.php".
  - The "Add Role" button (#addRoleBtn) triggers an AJAX request in code.js to
    create a new role on the server, then refreshes the table.

  Dependencies:
  - roleManage.css (page styling)
  - jQuery 3.6.3 (DOM + AJAX)
  - code.js (client-side handlers and AJAX calls to server.php)

  Key Elements:
  - #role-table: Table used to display roles returned by the server
  - #role-name / #role-privileges / #role-description: Inputs used when creating a role
  - #addRoleBtn: Submits the add-role request through code.js

  Author: Sandip Bohara Chhetri
-->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management</title>
    <link rel="stylesheet" href="roleManage.css">

    <!-- Link to jQuery amd Ajax library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"> </script>
    <script src="code.js"></script>
    <!-- Link to Js file -->

</head>

<body>
    <div class="container">
        <h1>Role Management</h1>

        <!-- Display All Roles -->
        <div id="roles-container">
            <h2>Existing Roles</h2>

            <!--
              Role table:
              - Rows are injected dynamically into <tbody> by loadRoleTable() in code.js.
              - Each row typically includes Update/Delete buttons wired by delegated handlers in code.js.
            -->
            <table id="role-table">
                <thead>
                    <tr>
                        <th>Role ID</th>
                        <th>Role Name</th>
                        <th>Privileges</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Roles will be dynamically loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Add Role Section -->
        <div id="add-role-container">
            <h2>Add New Role</h2>

            <!-- Add role form inputs (used by #addRoleBtn handler in code.js) -->
            <label for="role-name">Role Name:</label>
            <input type="text" id="role-name" placeholder="Enter role name">

            <label for="role-privileges">Privileges:</label>
            <input type="number" id="role-privileges" placeholder="Enter privilege level">

            <label for="role-description">Description:</label>
            <input type="text" id="role-description" placeholder="Enter description">

            <!-- Triggers the add-role workflow in code.js -->
            <button id="addRoleBtn">Add Role</button><br>
            
        </div>
        <!-- <div id="status-message" style="display: none;"></div> -->
    </div>
</body>

</html>