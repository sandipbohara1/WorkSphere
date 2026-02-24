<?php
/**
 * File: server.php
 *
 * Purpose:
 * - Acts as the central server-side controller for WorkSphere.
 * - Receives AJAX POST requests from code.js and performs actions based on `$_POST['action']`.
 * - Supports:
 *   - Authentication: register, login, logout
 *   - Session validation: validateSession
 *   - User management: getUsers, addUser, deleteUser, updateUserRole
 *   - Role management: getRoles, addRole, updateRole, deleteRole
 *
 * Request format:
 * - Method: POST
 * - Required field: action (string)
 * - Additional fields depend on the action.
 *
 * Response format:
 * - Most actions return JSON.
 * - getRoles outputs HTML table rows (intended to be injected directly into a table body).
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
    mySQLConnection();

    if(isset($_POST['action']))
    {
        //clean inputs
        $cleanAction = stTrim($_POST['action']);

        /****************User Registration / Creation*****************/
        if($cleanAction == "register")
        {
            $cleanUser = stTrim($_POST['username']);
            $cleanPass = stTrim($_POST['password']);

            error_log("Inside Register part Username:".$cleanUser. " Password:".$cleanPass);

            /**
             * Registration flow:
             * - Reject if username already exists
             * - Hash password before storing
             * - Insert new user record
             */
            if(ExistUsername($cleanUser))
            {
                $data = [
                    'success' => false,
                    'message' => "Username already in use!"
                ];
            }
            else
            {
                $HashedPassword = password_hash($cleanPass, PASSWORD_DEFAULT);  //Hash the password
                if(RegisterUser($cleanUser, $HashedPassword))
                {
                    $data = [
                        'success' => true,
                        'message' => "Registration failure! Please try again."
                    ];
                }
                else
                {
                    $data = [
                        'success' => false,
                        'message' => "Registration successful! You can now log in."
                    ];
                }    
            }

            echo json_encode($data);
            die();
        }

        /****************User Authentication / Login*****************/
        if($cleanAction == "login")
        {
            $cleanUser = stTrim($_POST['username']);
    $cleanPass = stTrim($_POST['password']);
    error_log("Inside Login part Username:".$cleanUser. " Password:".$cleanPass);

    /**
     * Login flow:
     * - Validate credentials using LoginCheck()
     * - If valid:
     *   - Fetch user_id and role_id
     *   - Store session values for authorization checks in the application
     * - Always returns a JSON response with a success flag and message.
     */
    $loginResult = LoginCheck($cleanUser, $cleanPass);
    if ($loginResult['success'])
    {
        $query = "SELECT user_id, role_id FROM users WHERE username = '$cleanUser'";
        $result = mySelectQuery($query);

        if ($result && $result->num_rows > 0) 
        {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $cleanUser;
            $_SESSION['role_id'] = $user['role_id'];

            $data['success'] = true;
            $data['response'] = "Loginpassed";
            $data['message'] = "Login is passed.";
        }
        else 
        {
            $data['success'] = false;
            $data['response'] = "Loginfailed";
            $data['message'] = "Unable to fetch user ID.";
        }
    }
    else
    {
        $data['success'] = false;
        $data['response'] = "Loginfailed";
        $data['message'] = "Check your username and password.";
    }
            // Include the username in the response
            $data['username'] = $cleanUser;             
            //Call function to test credentials
            echo json_encode($data);
            die();
        }  
        
        /****************User Logout*****************/
        if($cleanAction == "logout")
        {
            /**
             * Logout flow:
             * - Clear session variables and destroy the session to invalidate authentication.
             */
            session_unset(); // Clear session variables
            session_destroy(); // Destroy the session
            $data['success'] = true; 
            $data['message'] = "Logout successfully.";
            echo json_encode($data);
            die();
        }

        /****************User Managemnt Table*****************/
        if($cleanAction == "getUsers")
        {
            /**
             * Access control:
             * - If no active session exists, return a JSON response indicating session expiry.
             * - If session exists, return an HTML table fragment inside JSON.
             */
            if (!isset($_SESSION['user_id'])) {
                echo json_encode(["success" => false, "isLoggedIn" => false, "message" => "Session expired."]);
                die();
            }
        
            // Fetch the user management table
            $html = getUsersTable(); // Generate the table HTML dynamically
            echo json_encode(["success" => true, "isLoggedIn" => true, "html" => $html]);
            die();
        }

        /**************** Validate Session *****************/
        if ($cleanAction == "validateSession") {
            /**
             * Session validation endpoint:
             * - Returns isLoggedIn=true if a user_id is stored in the session.
             * - Returns isLoggedIn=false otherwise.
             */
            if (isset($_SESSION['user_id'])) 
            {
                echo json_encode(["isLoggedIn" => true]);
            } 
            else 
            {
                echo json_encode(["isLoggedIn" => false]);
            }
            die();
        }

        
        /****************Add User from User Management*****************/
        if($cleanAction == "addUser")
        {
            /**
             * Adds a new user from the User Management page.
             *
             * Required POST fields:
             * - username (string)
             * - password (string)
             * - role (int) -> role_id
             *
             * Validation:
             * - role_id must exist in role_Info
             * - username must not already exist
             */
            $username = stTrim($_POST['username']);
            $password = stTrim($_POST['password']);
            $roleId = intval($_POST['role']); // role_id from dropdown

            // Validate role exists
            $roleCheck = mySelectQuery("SELECT role_id FROM role_Info WHERE role_id = {$roleId} LIMIT 1");
            if (!$roleCheck || $roleCheck->num_rows === 0) {
                echo json_encode(["success" => false, "message" => "Invalid role selected."]);
                die();
            }
    
            // Check if username already exists
            if (ExistUsername($username)) {
                echo json_encode(["success" => false, "message" => "Username already exists."]);
                die();
            }
    
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
            // Insert the user into the database
            $usernameEsc = addslashes($username);
            $passEsc = addslashes($hashedPassword);
            $query = "INSERT INTO users (username, `password`, role_id) VALUES ('$usernameEsc', '$passEsc', {$roleId})";
            $result = mySelectQuery($query);
    
            if ($result)
            {
                echo json_encode(["success" => true, "message" => "User added successfully."]);
            } 
            else
            {
                echo json_encode(["success" => false, "message" => "Failed to add user. Please try again."]);
            }
    
            die(); 
        }  
        
        /****************Delete User from User Management*****************/
        if($cleanAction == "deleteUser")
        {
            /**
             * Deletes a user by user_id.
             *
             * Required POST fields:
             * - user_id (int)
             *
             * Flow:
             * - Confirm user exists
             * - Perform delete
             * - Return JSON result
             */
            $userId = intval($_POST['user_id']); // Ensure user_id is an integer

            // Check if the user exists before attempting to delete
            $query = "SELECT * FROM users WHERE user_id = $userId";
            $result = mySelectQuery($query);

            if ($result && $result->num_rows > 0) 
            {
                // Proceed with deletion
                $delQuery = "DELETE FROM users WHERE user_id = $userId";
                $deleteResult = mySelectQuery($delQuery);
        
                if ($deleteResult) 
                {
                    echo json_encode(["success" => true, "message" => "User deleted successfully."]);
                } 
                else 
                {
                    echo json_encode(["success" => false, "message" => "Failed to delete user."]);
                }
            } 
            else 
            {
                echo json_encode(["success" => false, "message" => "User not found."]);
            }
        
            die();
        }

/**************** Update User Role from User Management *****************/
if ($cleanAction == "updateUserRole") {
    /**
     * Updates a user's role based on a rank/privilege check.
     *
     * Required POST fields:
     * - user_id (int)
     * - role_id (int) -> new role to assign
     *
     * Access control:
     * - Requires active session (user_id in session)
     *
     * Authorization rule:
     * - The current user can only assign roles with privilege <= their own privilege rank.
     */
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["success" => false, "message" => "Session expired. Please log in again."]);
        die();
    }

    $userId = intval($_POST['user_id']); // Ensure user_id is an integer
    $newRoleId = intval($_POST['role_id']);
    $currentUserId = $_SESSION['user_id']; // Current logged-in user's ID

    global $mySQL_Connection;

    // Step 1: Get the current user's role rank
    $currentUserQuery = $mySQL_Connection->prepare("
        SELECT r.role_value AS current_user_rank 
        FROM users u 
        INNER JOIN role_Info r ON u.role_id = r.role_id 
        WHERE u.user_id = ?");
    $currentUserQuery->bind_param("i", $currentUserId);
    $currentUserQuery->execute();
    $currentUserResult = $currentUserQuery->get_result();

    if (!$currentUserResult || $currentUserResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Unable to determine current user's role rank."]);
        die();
    }
    $currentUserRank = $currentUserResult->fetch_assoc()['current_user_rank'];

    // Step 2: Get the rank of the new role being assigned
    $newRoleQuery = $mySQL_Connection->prepare("
        SELECT role_value AS new_role_rank 
        FROM role_Info 
        WHERE role_id = ?");
    $newRoleQuery->bind_param("i", $newRoleId);
    $newRoleQuery->execute();
    $newRoleResult = $newRoleQuery->get_result();

    if (!$newRoleResult || $newRoleResult->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Invalid role selected."]);
        die();
    }
    $newRoleRank = $newRoleResult->fetch_assoc()['new_role_rank'];

    // Step 3: Validate that the current user can assign the new role
    if ($newRoleRank > $currentUserRank) {
        echo json_encode(["success" => false, "message" => "You cannot assign a role with a higher privilege level than your own."]);
        die();
    }

    // Step 4: Update the user's role
    $updateQuery = $mySQL_Connection->prepare("
        UPDATE users 
        SET role_id = ? 
        WHERE user_id = ?");
    $updateQuery->bind_param("ii", $newRoleId, $userId);

    if ($updateQuery->execute()) {
        if ($updateQuery->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "User role updated successfully."]);
        } else {
            echo json_encode(["success" => false, "message" => "No changes made to the user's role."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update user role."]);
    }

    $updateQuery->close();
    die();
}
                        
        /****************Displays Role Management Table*****************/
        if ($cleanAction == "getRoles") 
        {
            /**
             * Outputs HTML table rows for roleManage.php.
             * Intended response type: HTML (not JSON).
             */
            //ob_start();         //Start output buffering
            getRolesTable();
            die();
        }

        /****************Add New Role in Role Management Table*****************/
        if ($cleanAction == "addRole") 
        {
            /**
             * Adds a new role to role_Info.
             *
             * Required POST fields:
             * - role_name (string)
             * - privileges (int) -> role_value
             * - description (string) optional
             */
            $roleName = stTrim($_POST['role_name']);
            $privileges = intval($_POST['privileges']);
            $description = isset($_POST['description']) ? stTrim($_POST['description']) : "New role";

            // Check for existing role
            $query = "SELECT * FROM role_Info WHERE role_name = '$roleName'";
            $result = mySelectQuery($query);

            if ($result && $result->num_rows > 0) 
            {
                echo json_encode(["success" => false, "message" => "Role already exists."]);
                die();
            }

            // Insert new role
            $query = "INSERT INTO role_Info (role_name, `description`, role_value) VALUES ('$roleName', '$description', $privileges)";
            $insertResult = mySelectQuery($query);
            if ($insertResult) 
            {
                echo json_encode(["success" => true, "message" => "Role added successfully."]);
            } 
            else 
            {
                echo json_encode(["success" => false, "message" => "Failed to add role."]);
                // $("#status-message").text(response.message).show();
            }        
            die();
        }

        /**************************USING STORE PROCEDURE*************************/
        /****************Update Role in Role Management Table********************/
        if ($cleanAction == "updateRole") 
        {
            /**
             * Updates an existing role via the stored procedure UpdateRoleP().
             *
             * Required POST fields:
             * - role_id (int)
             * - role_name (string)
             * - privileges (int)
             * - description (string)
             */
            $roleId = intval($_POST['role_id']); // Ensure the role ID is an integer
            $roleName = stTrim($_POST['role_name']);
            $privileges = intval($_POST['privileges']);
            $description = stTrim($_POST['description']);

            global $mySQL_Connection;

            // Basic validation
            if ($roleId <= 0 || $roleName === '' || $description === '')
            {
                echo json_encode(["success" => false, "message" => "Invalid role data."]);
                die();
            }

            // Call the stored procedure
            $roleNameEsc = addslashes($roleName);
            $descEsc = addslashes($description);
            $query = "CALL UpdateRoleP({$roleId}, '{$roleNameEsc}', '{$descEsc}', {$privileges})";

            if (!($result = mySelectQuery($query))) 
            {
                echo json_encode(["success" => false, "message" => "Call to stored procedure failed or returned no results."]);
            } 
            else 
            {
                if ($mySQL_Connection->affected_rows > 0) {
                    echo json_encode(["success" => true, "message" => "Role updated successfully."]);
                } else {
                    echo json_encode(["success" => false, "message" => "No changes made to the role."]);
                }
            }            
            die();
        }

        /**************************USING STORE PROCEDURE*************************/
        /****************Delete Role in Role Management Table********************/
        if ($cleanAction == "deleteRole") 
        {
            /**
             * Deletes a role using the stored procedure DeleteRole().
             *
             * Required POST fields:
             * - role_id (int)
             *
             * Constraint:
             * - A role cannot be deleted if any users are assigned to it.
             *
             * Transaction flow:
             * - Begin transaction
             * - Call stored procedure
             * - Commit on success, rollback on failure/no change
             */
            $roleId = intval($_POST['role_id']); // Ensure the role ID is an integer

            // Constraint: role cannot be deleted if users are assigned
            $cntRes = mySelectQuery("SELECT COUNT(*) AS cnt FROM users WHERE role_id = {$roleId}");
            if ($cntRes && ($row = $cntRes->fetch_assoc()) && intval($row['cnt']) > 0)
            {
                echo json_encode(["success" => false, "message" => "Cannot delete this role because users are assigned to it."]);
                die();
            }

            try
            {
                global $mySQL_Connection;
                if(!$mySQL_Connection)
                {
                    throw new Exception("Failed to establish a database connection.");
                }

                // Begin transaction
                $mySQL_Connection->begin_transaction();

                // Call the stored procedure
                $query = "CALL DeleteRole({$roleId})";

                if (!($result = mySelectQuery($query))) 
                {
                    throw new Exception("Call to stored procedure failed or returned no results.");
                } 

                // Check if any rows were affected
                if ($mySQL_Connection->affected_rows > 0) 
                {
                    // Commit the transaction if successful
                    $mySQL_Connection->commit();
                    echo json_encode(["success" => true, "message" => "Role deleted successfully."]);
                }
                else 
                {
                    // Rollback if no rows were affected
                    $mySQL_Connection->rollback();
                    echo json_encode(["success" => false, "message" => "No changes made to the role."]);                
                } 
            }
            catch (Exception $e)
            {
                // Rollback transaction on error
                if (isset($mySQL_Connection)) 
                {
                    $mySQL_Connection->rollback();
                }
                echo json_encode(["success" => false, "message" => $e->getMessage()]);
            }
            finally 
            {
                // Close the database connection
                if (isset($mySQL_Connection)) 
                {
                    mySQLCloseConnection();
                }
            }
            die();
        }        
    }
    else
    {
        echo json_encode("Sorry my friend  you are not allowed"); //using JSOn in make ajax call
    }

    /**
     * Sanitizes a string value for basic safety.
     *
     * @param string $string Raw input value.
     * @return string Sanitized string with whitespace trimmed and HTML tags removed.
     */
    function stTrim($string)
    {
        return strip_tags(trim($string));
    }

    /**
     * Checks whether a username already exists in the users table.
     *
     * @param string $name Username to check.
     * @return bool True if username exists; otherwise false.
     */
    function ExistUsername($name)
    {
        $query = "SELECT username FROM users WHERE username = '$name'";
        $result = mySelectQuery($query);    
        return ($result && $result->num_rows > 0);
    }

    /**
     * Inserts a new user record using a default role_id value.
     *
     * @param string $name Username to create.
     * @param string $password Hashed password string.
     * @return mixed mysqli_result on success; false on failure.
     */
    function RegisterUser($name, $password)
    {
        $query = "INSERT INTO users(username, `password`, role_id) VALUES ('$name','$password', 5)";
        return mySelectQuery($query);
    }

    //LoginCheck will receive user and pass and return true/false
    /**
     * Validates login credentials for a username/password pair.
     *
     * Flow:
     * - Fetch the stored hashed password for the username.
     * - Compare with the provided password using password_verify().
     *
     * @param string $user Username to authenticate.
     * @param string $pass Plain-text password provided by the user.
     * @return array Result array with:
     *   - success (bool)
     *   - message (string)
     */
    function LoginCheck($user, $pass)
    {
        $query = "SELECT `password` from users WHERE username = '$user'";
        $result = mySelectQuery($query);
        if ($result && $result->num_rows > 0) 
        {
            $row = $result->fetch_assoc();
            if(password_verify($pass, $row['password']))
            {
                return ['success' => true, 'message' => 'Login successful.'];
            }
            else
            {
                return ['success' => false, 'message' => 'Incorrect password.'];
            }
        }
        else
        {
            return ['success' => false, 'message' => 'Username does not exist.'];
        }
    }

    /**
     * Builds the User Management table markup.
     *
     * Notes:
     * - Does not return password values.
     * - Generates a role dropdown per user using role_Info.
     *
     * @return string HTML markup containing the user table and footer, or a fallback message.
     */
    function getUsersTable() {
        // Query to fetch all users (do NOT return hashed passwords to the UI)
        $query = "SELECT u.user_id, u.username, u.role_id
                  FROM users u
                  INNER JOIN role_Info r ON r.role_id = u.role_id
                  ORDER BY u.user_id ASC";
        $result = mySelectQuery($query);
    
        if ($result && $result->num_rows > 0) {
            // Fetch all roles once
            $rolesQuery = "SELECT role_id, role_name FROM role_Info ORDER BY role_id ASC";
            $rolesResult = mySelectQuery($rolesQuery);
    
            $roles = [];
            if ($rolesResult && $rolesResult->num_rows > 0) {
                while ($role = $rolesResult->fetch_assoc()) {
                    $roles[$role['role_id']] = $role['role_name'];
                }
            }
    
            // Start building the HTML (card wrapper matches userManage.php)
            $html = '<div class="card card--table">';
            $html .= '  <div class="card-header">';
            $html .= '    <h2 class="card-title">Users</h2>';
            $html .= '  </div>';
            $html .= '  <table id="user-table">';
            $html .= '    <thead>';
            $html .= '      <tr><th>Actions</th><th>User ID</th><th>Username</th><th>Change Role</th></tr>';
            $html .= '    </thead>';
            $html .= '    <tbody>';
    
            // Populate the table rows
            while ($row = $result->fetch_assoc()) {
                $html .= '<tr>';
                $uid = intval($row['user_id']);
                $html .= "<td><a class='btn btn-danger btn-sm' href='deleteUser.php?user_id={$uid}' onclick=\"return confirm('Are you sure you want to delete this user?');\">Delete</a></td>";
                $html .= '<td>' . $row['user_id'] . '</td>';
                $html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
    
                // Dropdown for roles
                $html .= '<td><select class="role-select" data-id="' . $row['user_id'] . '">';
                foreach ($roles as $roleId => $roleName) {
                    $selected = $roleId == $row['role_id'] ? 'selected' : '';
                    $html .= "<option value='{$roleId}' {$selected}>{$roleName}</option>";
                }
                $html .= '</select></td>';
                $html .= '</tr>';
            }
    
            $html .= '    </tbody>';
            $html .= '  </table>';

            // Info row
            $html .= '  <div class="table-footer">Retrieved: ' . $result->num_rows . ' user records</div>';
            $html .= '</div>';

            return $html;
        } else {
            return '<p>No user data found.</p>';
        }
    }
    
    /**
     * Inserts a new user record using a default role_id value.
     *
     * @param string $name Username to create.
     * @param string $password Hashed password string.
     * @return mixed mysqli_result on success; false on failure.
     */
    function AddUser($name, $password)
    {
        $query = "INSERT INTO users(username, `password`, role_id) VALUES ('$name','$password', 5)";
        return mySelectQuery($query);
    }

    /**
     * Outputs role table rows for the Role Management page.
     *
     * Output:
     * - Echoes HTML <tr> rows directly.
     *
     * @return void
     */
    function getRolesTable() {
        $query = "SELECT * FROM role_Info ORDER BY role_id ASC";
        $result = mySelectQuery($query);
    
        if ($result && $result->num_rows > 0) 
        {
            while ($row = $result->fetch_assoc()) 
            {
                echo "<tr>";
                echo "<td>{$row['role_id']}</td>";
                echo "<td><input type='text' value='{$row['role_name']}' class='role-name' data-id='{$row['role_id']}'></td>";
                echo "<td><input type='number' value='{$row['role_value']}' class='role-privileges' data-id='{$row['role_id']}'></td>";
                echo "<td><input type='text' value='{$row['description']}' class='role-description' data-id='{$row['role_id']}'></td>";
                echo "<td>
                        <div class='action-buttons'>
                            <button class='update-role-btn' data-id='{$row['role_id']}'>Update</button>
                            <button class='delete-role-btn' data-id='{$row['role_id']}'>Delete</button>
                        </div>
                      </td>";
                echo "</tr>";
            }
            
        } 
        else 
        {
            echo "<tr><td colspan='4'>No roles found.</td></tr>";
        }
    }    
?>