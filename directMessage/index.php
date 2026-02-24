<?php
session_start();

require_once __DIR__ . "/REST/dbUtil.php";

// default fallback (in case DB fails)
$meUsername = "user_id: " . ($_SESSION['user_id'] ?? "Unknown");

if (isset($_SESSION['user_id']) && mySQLConnection()) {
    global $mysql_connection;

    $uid = (int)$_SESSION['user_id'];
    $stmt = $mysql_connection->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $meUsername = $row["username"];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Messages</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>

<main class="app" role="application" aria-label="Messaging app">

  <header class="topbar">
    <div class="brand">
      <div class="brandDot" aria-hidden="true"></div>
      <div>
        <div class="brandTitle">Messages</div>
        <div class="brandSub">Direct messages</div>
      </div>
    </div>

    <!-- We'll fill this from JS too, but give it a correct initial value -->
    <div class="me" id="meBadge" aria-live="polite">Logged in as <?= htmlspecialchars($meUsername) ?></div>
  </header>

  <section class="layout">

    <!-- Sidebar -->
    <aside class="sidebar" aria-label="Users sidebar">
      <div class="sidebarHeader">
        <label class="searchBox" aria-label="Search users">
          <span class="searchIcon" aria-hidden="true">⌕</span>
          <input id="filter-input" type="search" placeholder="Search users…" autocomplete="off" />
        </label>

        <button id="filter-button" class="btn ghost" type="button">Search</button>
      </div>

      <div class="sidebarBody">
        <div class="sidebarTitleRow">
          <div class="sidebarTitle">Users</div>
          <div class="pill" id="user-count">0</div>
        </div>

        <div id="user-list" class="userList" role="list"></div>
      </div>
    </aside>

    <!-- Chat -->
    <section class="chat" aria-label="Chat panel">
      <header class="chatHeader">
        <div class="chatTitleWrap">
          <div class="chatTitle" id="chat-username">Select a user</div>
          <div class="chatMeta" id="chat-meta">Start a direct message</div>
        </div>
      </header>

      <div id="chat-feed" class="chatFeed" aria-live="polite" aria-busy="false"></div>

      <footer class="composerWrap">
        <div class="composer">
          <input id="message-input" type="text" placeholder="Type a message…" autocomplete="off" />
          <button id="send-button" class="btn primary" type="button">Send</button>
        </div>
        <div class="hint" id="hint"></div>
      </footer>
    </section>

  </section>

</main>

<!-- expose username to JS -->
<script>
  window.ME_USERNAME = <?= json_encode($meUsername) ?>;
</script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="messages.js"></script>
</body>
</html>