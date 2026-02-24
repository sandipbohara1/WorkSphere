<?php
/**
 * File: welcome.php
 *
 * Purpose:
 * - Serves as the main landing page after a successful login.
 * - Provides navigation links to:
 *   - User Management (userManage.php)
 *   - Role Management (roleManage.php)
 *   - Messages (external directMessage index.php)
 * - Displays the currently logged-in user's username.
 * - Provides a Logout button handled by code.js.
 *
 * Access control:
 * - Requires an authenticated session with `$_SESSION['username']` set.
 * - If the user is not logged in, redirects to login.php.
 *
 * Dependencies:
 * - styleWelcome.css (page styling)
 * - jQuery 3.6.3 (DOM + AJAX)
 * - code.js (logout handler and optional navigation handlers)
 *
 * Author: Sandip Bohara Chhetri
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) 
{
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Get the username
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Page</title>
    <link rel="stylesheet" href="styleWelcome.css">
    <!-- Link to jQuery amd Ajax library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"> </script>
    
    <!-- Link to Js file -->
    <script src="code.js"></script>

</head>
<body>
  <div class="container">
    <header>
      <h1>Main Page</h1>
    </header>
    
    <main>
      <div class="links">
        <!-- Navigation links to management sections -->
        <a href="userManage.php" id ="userManage">User Management</a>
        <a href="roleManage.php" id ="roleManage">Role Management</a>

        <!-- Messages link points to the Direct Messaging app entry page -->
        <a href="https://thor.cnt.sast.ca/~sandipt1241/WorkSphere/directMessage/index.php">Messages</a>
        
      </div>

      <!-- Logout button is wired in code.js (".logout-btn" click handler) -->
      <button class="logout-btn">Logout</button>
    </main>
    
    <footer>
      <p>Page Status : Welcome <?php echo ($username); ?>!</p>
    </footer>
  </div>
</body>
</html>