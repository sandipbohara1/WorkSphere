<?php
/**
 * File: apiDef.php
 * 
 * Purpose:
 * - Implements a lightweight REST-style API router for the direct messaging feature in WorkSphere.
 * - Parses the incoming request path (via `?request=...`), determines the endpoint, and dispatches to the
 *   matching method on `MyAPI`.
 * - Supports authentication using PHP sessions (`$_SESSION['user_id']`).
 * - Provides endpoints for:
 *   - `/usernames` (and optional `/usernames/{filter}`) to list users for the sidebar.
 *   - `/messages` (GET/POST/PUT/DELETE routing) to fetch, send, edit, and "unsend" messages.
 *
 * Response format:
 * - JSON responses with an HTTP status code and a payload containing at minimum `status` and `message` or `data`.
 *
 * Dependencies:
 * - dbUtil.php (for `mySQLConnection()` and `$mysql_connection`)
 * 
 * Author: Sandip Bohara Chhetri
 */

require_once 'dbUtil.php';
session_start();

header("Content-Type: application/json; charset=utf-8");

class MyAPI
{
    /** @var string HTTP method for this request (GET/POST/PUT/DELETE) */
    private string $method;

    /** @var string Resolved API endpoint name (first segment of the request path) */
    private string $endpoint = '';

    /** @var array Remaining URL path segments after the endpoint */
    private array $args = [];

    /** @var array Parsed request data (query params, form fields, or parsed body depending on method) */
    private array $data = [];

    /**
     * Constructor.
     *
     * Reads the HTTP method, parses the URL-style request string into endpoint + args,
     * and loads request input data into `$this->data`.
     *
     * @param string $request The API request path, typically passed in via `$_GET['request']`
     *                        (example: "messages/user/5" or "usernames/admin_007").
     * @return void
     */
    public function __construct(string $request)
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->parseRequest($request);
        $this->data = $this->readInput();
    }

    /**
     * Splits the incoming request path into:
     * - `$this->endpoint`: first segment
     * - `$this->args`: remaining segments
     *
     * Example:
     * - "messages/user/5" => endpoint="messages", args=["user","5"]
     *
     * @param string $request The API request path string.
     * @return void
     */
    private function parseRequest(string $request): void
    {
        $parts = explode('/', trim($request, '/'));
        $this->endpoint = $parts[0] ?? '';
        $this->args = array_slice($parts, 1);
    }

    /**
     * Reads request input and normalizes it into an associative array.
     *
     * Method-specific behavior:
     * - POST: uses `$_POST` (form-encoded)
     * - PUT/DELETE: reads raw body and parses it using `parse_str()` (expects form-encoded body)
     * - GET: uses `$_GET`
     *
     * After loading input, all string values are trimmed.
     *
     * @return array Associative array of input parameters (possibly empty).
     */
    private function readInput(): array
    {
        $input = [];
        $raw = file_get_contents("php://input") ?: '';

        if ($this->method === 'POST') {
            $input = $_POST;
        } elseif (in_array($this->method, ['PUT', 'DELETE'], true)) {
            // For PUT/DELETE, PHP does not populate $_POST automatically.
            // We parse the raw body assuming it is `application/x-www-form-urlencoded`.
            parse_str($raw, $parsed);
            if (is_array($parsed)) $input = $parsed;
        } elseif ($this->method === 'GET') {
            $input = $_GET;
        }

        // Normalize whitespace: trim any string fields to prevent accidental leading/trailing spaces
        // from affecting validation or stored message content.
        foreach ($input as $k => $v) {
            if (is_string($v)) $input[$k] = trim($v);
        }

        return $input;
    }

    /**
     * Sends a JSON response and terminates the request.
     *
     * @param array $payload JSON-serializable array to return to the client.
     * @param int $status HTTP status code to send (default 200).
     * @return void This method exits the script.
     */
    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload);
        exit;
    }

    /**
     * Ensures the user is authenticated.
     *
     * This API relies on the login flow to set:
     * - `$_SESSION['user_id']` as the authenticated user's primary key (users.user_id)
     *
     * If missing, this method responds with 401 and exits.
     *
     * @return int The authenticated user's ID (users.user_id).
     */
    private function requireAuth(): int
    {
        // your login must set $_SESSION['user_id'] to match users.user_id
        if (!isset($_SESSION['user_id'])) {
            $this->respond(['status' => 401, 'message' => 'Unauthorized'], 401);
        }
        return (int)$_SESSION['user_id'];
    }

    /**
     * Routes the request to the appropriate endpoint method.
     *
     * Rules:
     * - The first URL segment becomes `$this->endpoint`.
     * - If the endpoint method does not exist, respond 404.
     * - Otherwise call `$this->{$this->endpoint}()`.
     *
     * @return void
     */
    public function processAPI(): void
    {
        if ($this->endpoint === '' || !method_exists($this, $this->endpoint)) {
            $this->respond(['status' => 404, 'message' => 'Endpoint not found'], 404);
        }
        $this->{$this->endpoint}();
    }

    // ----------------------------
    // GET /usernames OR /usernames/{filter}
    // returns: {status, me, data:[{user_id,username}]}
    /**
     * Lists users for the conversation sidebar.
     *
     * Endpoint:
     * - GET /usernames
     * - GET /usernames/{filter}
     *
     * Behavior:
     * - Requires session authentication.
     * - Excludes the current user (`user_id <> me`).
     * - If a filter is provided, applies `username LIKE %filter%`.
     *
     * Response payload:
     * - status: 200
     * - me: current user id
     * - data: array of users [{user_id, username}]
     *
     * @return void
     */
    private function usernames(): void
    {
        $me = $this->requireAuth();

        if (!mySQLConnection()) {
            $this->respond(['status' => 500, 'message' => 'DB connection failed'], 500);
        }

        global $mysql_connection;

        $filter = $this->args[0] ?? '';
        $filter = is_string($filter) ? trim($filter) : '';

        if ($filter !== '') {
            $like = "%{$filter}%";
            $stmt = $mysql_connection->prepare(
                "SELECT user_id, username
                 FROM users
                 WHERE user_id <> ? AND username LIKE ?
                 ORDER BY username"
            );
            $stmt->bind_param("is", $me, $like);
        } else {
            $stmt = $mysql_connection->prepare(
                "SELECT user_id, username
                 FROM users
                 WHERE user_id <> ?
                 ORDER BY username"
            );
            $stmt->bind_param("i", $me);
        }

        if (!$stmt->execute()) {
            $this->respond(['status' => 500, 'message' => 'Query failed'], 500);
        }

        $res = $stmt->get_result();
        $users = [];
        // Collect all rows into an array for JSON serialization.
        // This loop continues until fetch_assoc() returns null/false (no more rows).
        while ($row = $res->fetch_assoc()) $users[] = $row;

        $this->respond(['status' => 200, 'me' => $me, 'data' => $users], 200);
    }

    // ----------------------------
    // /messages endpoint (GET/POST/PUT/DELETE)
    /**
     * Routes the /messages endpoint to a method based on HTTP verb.
     *
     * Supported methods:
     * - GET    -> getConversation()
     * - POST   -> sendMessage()
     * - PUT    -> updateMessage()
     * - DELETE -> deleteMessage()
     *
     * If the HTTP method is not one of the above, respond 405.
     *
     * @return void
     */
    private function messages(): void
    {
        switch ($this->method) {
            case 'GET':    $this->getConversation(); break;
            case 'POST':   $this->sendMessage(); break;
            case 'PUT':    $this->updateMessage(); break;
            case 'DELETE': $this->deleteMessage(); break;
            default:
                // Any other verb is not supported for this endpoint.
                $this->respond(['status' => 405, 'message' => 'Method Not Allowed'], 405);
        }
    }

    // GET /messages/user/{otherUserId}
    // returns messages between session user and other user
    /**
     * Fetches the message history between the authenticated user and another user.
     *
     * Endpoint:
     * - GET /messages/user/{otherUserId}
     *
     * Validation rules:
     * - First arg must be the literal string "user".
     * - otherUserId must be a positive integer.
     *
     * Query behavior:
     * - Returns messages where (me -> other) OR (other -> me).
     * - Joins users table to include the sender username.
     * - Orders results chronologically ascending.
     *
     * Response payload:
     * - status: 200
     * - me: current user id
     * - data: array of messages
     *   [{message_id, sender_id, username, message, date_time}]
     *
     * @return void
     */
    private function getConversation(): void
    {
        $me = $this->requireAuth();

        if (!mySQLConnection()) {
            $this->respond(['status' => 500, 'message' => 'DB connection failed'], 500);
        }
        global $mysql_connection;

        $mode = $this->args[0] ?? '';
        $other = (int)($this->args[1] ?? 0);

        if ($mode !== 'user' || $other <= 0) {
            // The client must call /messages/user/{id}; anything else is rejected.
            $this->respond(['status' => 400, 'message' => 'Invalid user target'], 400);
        }

        // messages table: id, sender_id, recipient_id, message, timestamp
        // users table: user_id, username
        $stmt = $mysql_connection->prepare(
            "SELECT m.id AS message_id,
                    m.sender_id,
                    u.username,
                    m.message,
                    m.`timestamp` AS date_time
             FROM messages m
             JOIN users u ON u.user_id = m.sender_id
             WHERE (m.sender_id = ? AND m.recipient_id = ?)
                OR (m.sender_id = ? AND m.recipient_id = ?)
             ORDER BY m.`timestamp` ASC"
        );

        $stmt->bind_param("iiii", $me, $other, $other, $me);

        if (!$stmt->execute()) {
            $this->respond(['status' => 500, 'message' => 'Query failed'], 500);
        }

        $res = $stmt->get_result();
        $msgs = [];
        // Collect all rows from the result set in order.
        while ($row = $res->fetch_assoc()) $msgs[] = $row;

        $this->respond(['status' => 200, 'me' => $me, 'data' => $msgs], 200);
    }

    // POST /messages  (recipient_id, message)
    /**
     * Sends a new direct message from the authenticated user to a recipient.
     *
     * Endpoint:
     * - POST /messages
     *
     * Expected input (form-encoded):
     * - recipient_id (int)
     * - message (string)
     *
     * Validation:
     * - recipient_id must be > 0
     * - message must be non-empty after trimming
     * - message length must be <= 2000 characters
     *
     * Database:
     * - Inserts into `messages` with `timestamp = NOW()`.
     *
     * Response payload:
     * - status: 201
     * - me: current user id
     * - message: "Message sent"
     *
     * @return void
     */
    private function sendMessage(): void
    {
        $me = $this->requireAuth();

        if (!mySQLConnection()) {
            $this->respond(['status' => 500, 'message' => 'DB connection failed'], 500);
        }
        global $mysql_connection;

        $recipient = (int)($this->data['recipient_id'] ?? 0);
        $message = $this->data['message'] ?? '';

        if ($recipient <= 0 || !is_string($message) || trim($message) === '') {
            // Reject missing/invalid recipient or empty messages to prevent inserting useless rows.
            $this->respond(['status' => 400, 'message' => 'Invalid input'], 400);
        }

        $message = trim($message);
        if (mb_strlen($message) > 2000) {
            // Use 413 (Payload Too Large) to clearly indicate the message exceeded allowed size.
            $this->respond(['status' => 413, 'message' => 'Message too long'], 413);
        }

        // Insert message (timestamp = NOW())
        $stmt = $mysql_connection->prepare(
            "INSERT INTO messages (sender_id, recipient_id, message, `timestamp`)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("iis", $me, $recipient, $message);

        if (!$stmt->execute()) {
            $this->respond(['status' => 500, 'message' => 'Insert failed'], 500);
        }

        $this->respond(['status' => 201, 'me' => $me, 'message' => 'Message sent'], 201);
    }

    // PUT /messages/{message_id}  (message)
    /**
     * Updates an existing message (edit functionality).
     *
     * Endpoint:
     * - PUT /messages/{message_id}
     *
     * Expected input (form-encoded body):
     * - message (string)
     *
     * Authorization:
     * - Only the original sender can edit a message.
     *   This is enforced by the WHERE clause: `id = ? AND sender_id = ?`.
     *
     * Validation:
     * - message_id must be > 0
     * - message must be non-empty after trimming
     * - message length must be <= 2000 characters
     *
     * Response:
     * - 200 on success
     * - 403 if not permitted or message not found
     *
     * @return void
     */
    private function updateMessage(): void
    {
        $me = $this->requireAuth();

        if (!mySQLConnection()) {
            $this->respond(['status' => 500, 'message' => 'DB connection failed'], 500);
        }
        global $mysql_connection;

        $messageId = (int)($this->args[0] ?? 0);
        $newMessage = $this->data['message'] ?? '';

        if ($messageId <= 0 || !is_string($newMessage) || trim($newMessage) === '') {
            $this->respond(['status' => 400, 'message' => 'Invalid input'], 400);
        }

        $newMessage = trim($newMessage);
        if (mb_strlen($newMessage) > 2000) {
            $this->respond(['status' => 413, 'message' => 'Message too long'], 413);
        }

        // Only sender can edit
        $stmt = $mysql_connection->prepare(
            "UPDATE messages
             SET message = ?
             WHERE id = ? AND sender_id = ?"
        );
        $stmt->bind_param("sii", $newMessage, $messageId, $me);

        if (!$stmt->execute()) {
            $this->respond(['status' => 500, 'message' => 'Update failed'], 500);
        }

        if ($stmt->affected_rows <= 0) {
            // If no rows were affected, either:
            // - the message_id does not exist, OR
            // - the message exists but does not belong to the current user (not permitted).
            $this->respond(['status' => 403, 'message' => 'Not permitted or not found'], 403);
        }

        $this->respond(['status' => 200, 'me' => $me, 'message' => 'Message updated'], 200);
    }

    // DELETE /messages/{message_id}
// "Unsend": keep row but replace message text
/**
 * "Unsend" behavior for messages.
 *
 * Endpoint:
 * - DELETE /messages/{message_id}
 *
 * Design choice:
 * - Instead of deleting the row, the message text is replaced with a placeholder ("Message unsent").
 *   This preserves conversation integrity and timestamps while hiding the original content.
 *
 * Authorization:
 * - Only the original sender can unsend their message.
 *
 * Response:
 * - 200 on success
 * - 403 if not permitted or message not found
 *
 * @return void
 */
private function deleteMessage(): void
{
    $me = $this->requireAuth();

    if (!mySQLConnection()) {
        $this->respond(['status' => 500, 'message' => 'DB connection failed'], 500);
    }
    global $mysql_connection;

    $messageId = (int)($this->args[0] ?? 0);
    if ($messageId <= 0) {
        $this->respond(['status' => 400, 'message' => 'Invalid ID'], 400);
    }

    // Only sender can unsend
    $unsent = "Message unsent";
    $stmt = $mysql_connection->prepare(
        "UPDATE messages
         SET message = ?
         WHERE id = ? AND sender_id = ?"
    );
    $stmt->bind_param("sii", $unsent, $messageId, $me);

    if (!$stmt->execute()) {
        $this->respond(['status' => 500, 'message' => 'Unsend failed'], 500);
    }

    if ($stmt->affected_rows <= 0) {
        // If no rows changed, the message either doesn't exist or doesn't belong to the current user.
        $this->respond(['status' => 403, 'message' => 'Not permitted or not found'], 403);
    }

    $this->respond(['status' => 200, 'me' => $me, 'message' => 'Message unsent'], 200);
}
}

// ----------------------------
// ENTRY POINT
/**
 * Entry point for the API.
 *
 * Expects a `request` query parameter that contains the URL-style endpoint path.
 * Example:
 * - /REST/api.php?request=messages/user/5
 *
 * If missing, returns a 400-style JSON payload and exits.
 */
$request = $_GET['request'] ?? '';
if (!is_string($request) || $request === '') {
    echo json_encode(['status' => 400, 'message' => 'No request given']);
    exit;
}

$api = new MyAPI($request);
$api->processAPI();