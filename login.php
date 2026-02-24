<!--
  File: index.html

  Purpose:
  - Provides the main entry page for WorkSphere authentication.
  - Displays a simple UI that allows a user to register or log in.
  - Loads the required CSS for styling and JavaScript for client-side behavior.

  Behavior:
  - The Register and Login buttons are wired up in code.js.
  - User input is collected from the Username and Password fields and sent to the server via AJAX.

  Dependencies:
  - style.css (page styling)
  - jQuery 3.6.3 (DOM helpers + AJAX support)
  - code.js (client-side event handlers and AJAX wrapper)

  Author: Sandip Bohara Chhetri
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkSphere</title>
    <link rel="stylesheet" href="style.css">

    <!-- Link to jQuery and Ajax library -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"> </script>
    <script src="code.js"></script>
</head>
<body>
    <header>
        WorkSphere - Register/Login
    </header>

    <div class="container">
        <div id="form">
            <h1>Login/Register</h1>
            
            <!-- Username input field (used by code.js for login/register requests) -->
            <div class="input-row">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" placeholder="Enter your username" required>
            </div>
            
            <!-- Password input field (used by code.js for login/register requests) -->
            <div class="input-row">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" placeholder="Enter your password" required>
            </div>

            <!-- Action buttons:
                 - #register triggers the registration workflow
                 - #login triggers the login workflow -->
            <div class="button-container">
                <button id="register">Register</button>
                <button id="login">Login</button>
            </div>
            
            <!-- Message area for status/errors returned by client-side logic -->
            <div id="message"></div>
        </div>
    </div>
</body>
</html>