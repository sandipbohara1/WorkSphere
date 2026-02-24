/**
 * File: code.js
 *
 * Purpose:
 * - Implements client-side behavior for authentication and administration workflows:
 *   - User registration
 *   - User login
 *   - User logout
 *   - User management (list/add/delete users, update user roles)
 *   - Role management (list/add/update/delete roles)
 *
 * Overview:
 * - Event handlers are registered on DOM ready.
 * - Most operations send an AJAX POST request to `server.php` with an `action` field.
 * - Responses are handled as JSON or HTML depending on the request.
 *
 * Dependencies:
 * - jQuery
 * - server.php (must implement the actions referenced in this file)
 *
 * Author: Sandip Bohara Chhetri
 */

// To handle on page onload stuff
$(document).ready(()=>{

    console.log("Good morning we are here");

    $("#register").on("click",()=>{
        console.log("Register button has been clicked.");
        let username = $("#username").val().trim();
        let password = $("#password").val().trim();

        // Reject empty input fields
        if(username == "" || password == "")
        {
            $("#message").text("Please fill all fields!");
            return;
        }

        // Enforce password policy
        if(!validatePassword(password))
        {
            $('#message').text("Password must be at least 8 characters, contain a number, a special character, and an uppercase letter.");
            return;
        }

        // Request payload for registration
        let data = {
                    action: "register",
                    username: username, 
                    password: password
                };
        console.log(data);

        //Make Ajax call
        MakeAjaxCall("server.php","POST", data, "JSON", 
            (response) =>{
                if(response.success)
                {
                    //window.location.href = 'login.php';  //Redirects to login page after successful registration
                    console.log("Registered successfully.");
                    $('#message').text("Registered successfully.");
                }
                else
                {
                    $('#message').text(response.message);   //Response message to server
                }
            }
            , errorHandler);
    });

    $("#login").on("click",()=>{
        console.log("Login button has been clicked.");
        let username = $("#username").val().trim();
        let password = $("#password").val().trim();

        // Reject empty input fields
        if(username == "" || password == "")
        {
            $("#message").text("Please fill all fields!");
            return;
        }

        // Enforce password policy
        if(!validatePassword(password))
        {
            $('#message').text("Password must be at least 8 characters, contain a number, a special character, and an uppercase letter.");
            return;
        }  
        
        // Request payload for login
        let data ={};
        data['action'] = "login";
        data['username'] = $("[name = 'username']").val();
        data['password'] = $("[name = 'password']").val();
        console.log(data);

        //Make Ajax call
        MakeAjaxCall("server.php","POST", data, "JSON", successLogin, errorHandler);
    });

    $(".logout-btn").on("click",()=>{
        console.log("Logout button has been clicked.");
        let data ={};
        data['action'] = "logout";

        /**
         * Performs a client-side redirect to the login page.
         * A redirect initiated before the AJAX call may prevent the request from completing.
         */
        window.location = "login.php";

        //Make Ajax call
        MakeAjaxCall("server.php","POST", data, "JSON", successLogOut, errorHandler);
    });

    // User Management link click handler
    $("#userManage").on("click", (e) => {
        e.preventDefault(); // Prevent default link behavior
        console.log("User Management link clicked.");

        // Action value defined for server-side user list retrieval
        let data ={};
        data['action'] = "getUsers";

        // Navigate to the user management page
        window.location ="userManage.php";
    });

    // Check if on userManage.php and dynamically load the table
    if (window.location.pathname.endsWith("userManage.php")) 
    {
        const data = { action: "validateSession" };

        /**
         * Validates that the current session is active before loading the management view.
         * If the session is invalid, the user is redirected to the login page.
         */
        MakeAjaxCall("server.php", "POST", data, "JSON",
            (response) => {
                if (!response.isLoggedIn) {
                    alert("Session expired. Redirecting to login page.");
                    window.location = "login.php"; // Redirect to login page
                } else {
                    loadUserTable(); // If session is valid, load the table
                }
            },
            (error) => {
                console.error("Error validating session:", error);
                alert("An error occurred while validating your session.");
                window.location = "login.php"; // Redirect in case of error
            }
            );
    }

    $("#addUserBtn").on("click", () => {
        console.log("Add User button has been clicked.");

        let username = $("#username").val().trim();
        let password = $("#password").val().trim();
        let role = $("#role").val();

        // Reject empty input fields
        if (username == "" || password == "" || role == "")
        {
            alert("Please fill in all fields.");
            return;
        }

        // Enforce password policy
        if (!validatePassword(password)) 
        {
            alert("Password must be at least 8 characters, contain a number, a special character, and an uppercase letter.");
            return;
        }

        // Request payload for add user
        let data = {};
        data['action'] = "addUser",
        data['username'] = username;
        data['password'] = password;
        data['role'] = role;

        console.log(data);

        // Make AJAX Call to add user
        MakeAjaxCall("server.php", "POST", data, "JSON",
            (response) => {
                if (response.success) 
                {
                    alert("User added successfully!");
                    $("#username").val(""); // Clear the input fields
                    $("#password").val("");
                    $("#role").prop('selectedIndex', 0); // Reset to first role
                    loadUserTable(); // Reload the user table
                }
                else 
                {
                    alert(response.message || "Failed to add user.");
                }
            }, errorHandler);
    });

    $(document).on("click", ".deleteBtn", function () {
        let userId = $(this).data("id");
        console.log("Delete button has been clicked.");
        
        if (confirm("Are you sure you want to delete this user?"))
        {
            // Request payload for delete user
            let data = {};
            data['action'] = "deleteUser",
            data['user_id'] = userId;    

            console.log(data);

            // Make AJAX Call to delete user
            MakeAjaxCall("server.php", "POST", data, "JSON",
                (response) => {
                    if (response.success)
                    {
                        alert("User deleted successfully!");
                        loadUserTable(); // Reload the table after deletion
                    } 
                    else 
                    {
                        alert(response.message || "Failed to delete user.");
                    }
                }, errorHandler );               
        }
    });

    $(document).on("change", ".role-select", function () {
        let userId = $(this).data("id");
        let newRoleId = $(this).val();
        console.log("Role has been changed.");

        // Current user role rank (used to enforce role assignment permissions)
        let currentUserRoleRank = parseInt($("#current-user-role-rank").val()); 
        
        // Rank for the selected role option
        let selectedRoleRank = parseInt($(`option[value='${newRoleId}']`, this).data("rank"));

        /**
         * Role assignment rule:
         * - A role with rank >= current user's rank cannot be assigned by the current user.
         * - On violation, the UI resets the selection to the previously stored role value.
         */
        if (selectedRoleRank >= currentUserRoleRank)
        {
            alert("You cannot assign a role with a rank greater than or equal to your own.");
            $(this).val($(this).data("current")); // Reset to previous value
            return;
        }

        if (confirm("Are you sure you want to change this user's role?"))
        {
            // Request payload for role update
            let data = {};
            data['action'] = "updateUserRole",
            data['user_id'] = userId;  
            data['role_id'] = newRoleId;  

            console.log(data);

            // Make AJAX Call to update role
            MakeAjaxCall("server.php", "POST", data, "JSON",
                (response) => {
                    if (response.success)
                    {
                        alert("Role updated successfully!");
                        $(this).data("current", newRoleId); // Persist the updated role as the current selection
                    } 
                    else 
                    {
                        alert(response.message || "Failed to update role.");
                        $(this).val($(this).data("current")); // Reset to previous value on failure
                    }
                }, errorHandler );               
        }
        else 
        {
            $(this).val($(this).data("current")); // Reset to previous value if cancelled
        }
    });

    // Automatically load the role table if on the Role Management page
    if (window.location.pathname.endsWith("roleManage.php")) 
    {
        loadRoleTable();
    }    

    $("#addRoleBtn").on("click", () => {
        console.log("Add Role button has been clicked.");
        let roleName = $("#role-name").val().trim();
        let rolePrivileges = $("#role-privileges").val();
        let roleDescription = $("#role-description").val() ? $("#role-description").val().trim() : "New role";

        // Reject empty required fields
        if (roleName == "" || rolePrivileges == "") 
        {
            alert("Please fill in all fields.");
            return;
        }

        // Request payload for add role
        let data = {};
        data['action'] = "addRole",
        data['role_name'] = roleName; 
        data['privileges'] = rolePrivileges; 
        data['description'] = roleDescription; 

        console.log(data);
        // Make AJAX Call to add role
        MakeAjaxCall("server.php", "POST", data, "JSON", 
            (response) => {
            if (response.success) {
                alert("Role added successfully!");
                $("#role-name").val(""); // Clear input fields
                $("#role-privileges").val("");
                $("#role-description").val("");
                loadRoleTable(); // Refresh the table
            } 
            else 
            {
                alert(response.message || "Failed to add role.");
            }
        }, errorHandler);
    });

    
    $(document).on("click", ".update-role-btn", function () {
        console.log("Update  Role button has been clicked.");

        let roleId = $(this).data("id"); // Get the role ID from the button's data attribute
        let roleName = $(`.role-name[data-id='${roleId}']`).val().trim();
        let rolePrivileges = $(`.role-privileges[data-id='${roleId}']`).val().trim();
        let roleDescription = $(`.role-description[data-id='${roleId}']`).val().trim(); // Updated to select the text input for description

        // Reject empty required fields
        if (roleName == "" || rolePrivileges == "" || roleDescription =="") 
        {
            alert("Please fill in all fields.");
            return;
        }

         // Enforce numeric privileges input
        if (isNaN(rolePrivileges)) 
        {
            alert("Privileges must be a valid number.");
            return;
        }

        // Request payload for update role
        let data = {};
        data['action'] = "updateRole",
        data['role_id'] = roleId;
        data['role_name'] = roleName; 
        data['privileges'] = rolePrivileges; 
        data['description'] = roleDescription; 
        console.log(data);

        // Make AJAX Call to update role
        MakeAjaxCall("server.php", "POST", data, "JSON", 
            (response) => {
            if (response.success) {
                alert("Role updated successfully!");
                loadRoleTable(); // Refresh the table
            } 
            else 
            {
                alert(response.message || "Failed to add role.");
            }
        }, errorHandler);
    });

    $(document).on("click", ".delete-role-btn", function () {
        console.log("Delete Role button has been clicked.");

        let roleId = $(this).data("id"); // Get the role ID from the button's data attribute
        if (confirm("Are you sure you want to delete this role?"))
        {
            // Request payload for delete role
            let data = {};
            data['action'] = "deleteRole",
            data['role_id'] = roleId;   
        
            console.log(data);

            // Make AJAX Call to delete role
            MakeAjaxCall("server.php", "POST", data, "JSON",
                (response) => {
                    if (response.success)
                    {
                        alert("Role deleted successfully!");
                        loadRoleTable(); // Reload the table after deletion
                    } 
                    else 
                    {
                        alert(response.message || "Failed to delete user.");
                    }
                }, errorHandler );               
        }    
    });
});


/**
 * Loads the user management table markup from the server and injects it into the page.
 *
 * Request:
 * - POST server.php with action=getUsers
 *
 * Expected response (JSON):
 * - success (boolean)
 * - isLoggedIn (boolean)
 * - html (string)   Markup for the management table/container
 * - message (string) Optional error message
 *
 * @returns {void}
 */
function loadUserTable() 
{
    console.log("Reloading user table.");

    let data = { action: "getUsers" };

    MakeAjaxCall("server.php", "POST", data, "JSON",
        (response) => {
            if (response.success && response.isLoggedIn) {
                $("#user-management-container").html(response.html);
                console.log("User management table loaded.");
            } else if (!response.isLoggedIn) {
                alert("Session expired. Redirecting to login page.");
                window.location = "login.php";
            } else {
                alert(response.message || "Failed to load user management table.");
            }
        },
        errorHandler
    );
}

/**
 * Loads the role management table rows from the server and injects them into #role-table tbody.
 *
 * Request:
 * - POST server.php with action=getRoles
 *
 * Expected response:
 * - HTML table rows as a string
 *
 * @returns {void}
 */
function loadRoleTable() 
{
    console.log("Loading role table...");

    // Data to send to the server
    let data = { action: "getRoles" };

    // Make AJAX Call to fetch roles
    MakeAjaxCall("server.php", "POST", data, "HTML",
        (response) => {
            // Update the table body within #role-table
            $("#role-table tbody").html(response);
            console.log("Role table loaded successfully.");
        },
        errorHandler // Use the generic error handler for errors
    );
}


/**
 * Validates a password against the required password policy.
 *
 * Policy:
 * - Minimum length: 8
 * - Must include at least one digit
 * - Must include at least one special character from: !@#$%^&*
 * - Must include at least one uppercase letter
 *
 * @param {string} pass Password string to validate.
 * @returns {boolean} true if the password meets requirements; otherwise false.
 */
function validatePassword(pass)
{
    let regex = /^(?=.*\d)(?=.*[!@#$%^&*])(?=.*[A-Z]).{8,}$/;
    return regex.test(pass);
}

//SuccessLogin is function to handle successful response for Login button request
/**
 * Handles the login success callback.
 *
 * @param {Object} serverData JSON returned by server.php.
 * @param {string} serverStatus jQuery status string.
 * @returns {void}
 */
function successLogin(serverData, serverStatus)
{
    console.log("Inside Success handler for Login button request");
    console.log(serverData);
    //Header(Location:)
    if(serverData.success)
    {
        console.log("Login successful");

        //Redirect the user to the new page
        window.location = "welcome.php";
        $("#message").text("Welcome");
    }
    else
    {
        $("#message").text("An error occurred. Please try again to login.");
    }
}

/**
 * Handles the logout success callback.
 *
 * @param {Object} serverData JSON returned by server.php.
 * @param {string} serverStatus jQuery status string.
 * @returns {void}
 */
function successLogOut(serverData, serverStatus)
{
    console.log("Inside Success handler for Logout button request");
    console.log(serverData);

    if(serverData.success)
    {
        console.log("Logout successful");

        //Redirect the user to the new page
        window.location = "login.php";
        $("#message").text("Welcome");
    }
    else
    {
        $("#message").text("An error occurred. Please try again to login.");
    }    
}

//errorHandler is funtion to handle errors
/**
 * Handles AJAX request failures.
 *
 * @param {jqXHR} ajaxReq jQuery XMLHttpRequest object.
 * @param {string} ajaxStatus jQuery status string.
 * @param {string} errorThrown Error description.
 * @returns {void}
 */
function errorHandler(ajaxReq, ajaxStatus, errorThrown)
{
    console.log("Inside Error Handler");
    console.log(ajaxReq + "Status" + ajaxStatus + "Error" + errorThrown);
    $("#message").text("An error occurred. Please try again.");
}

//MAkeAjaxCall is used to make ajax calls
/**
 * Executes an AJAX request using jQuery.
 *
 * @param {string} url Destination URL.
 * @param {string} reqMethod HTTP method ("GET" or "POST").
 * @param {Object} data Data object to send to the server.
 * @param {string} serverResponseType Expected response type ("HTML" or "JSON").
 * @param {Function} successHandler Success callback.
 * @param {Function} errorHandler Error callback.
 * @returns {void}
 */
function MakeAjaxCall(url, reqMethod, data, serverResponseType, successHandler,errorHandler)
{
    console.log("Inside MakeAjaxCall funtion");

    let ajaxOptions = {};
    ajaxOptions['url'] = url;   //Destination URL
    ajaxOptions['data'] = data; //Client data sent to server
    ajaxOptions['dataType'] = serverResponseType;   //HTML / JSON
    ajaxOptions['type'] = reqMethod;    //GET / POST
    ajaxOptions['success'] = successHandler; //funtion to call on successful attempt
    ajaxOptions['error'] = errorHandler;    //duntion to call in case of error

    //Actually making ajax call now 
    $.ajax(ajaxOptions);
}