/**
 * File: messages.js
 *
 * Purpose:
 * - Provides the full client-side behavior for the "Messages" page (Messenger-style UI).
 * - Loads the user list (sidebar) from the REST API and allows selecting a user to start/view a conversation.
 * - Loads conversation history and renders message bubbles in the chat feed.
 * - Sends messages, supports editing previously sent messages, and supports "unsend" behavior.
 * - Implements polling to refresh the conversation periodically for near real-time updates.
 *
 * Dependencies:
 * - jQuery 3.6.x (loaded by the page)
 * - REST API endpoints (relative to the page):
 *     GET    ./REST/usernames            -> list all users (excluding me)
 *     GET    ./REST/usernames/{filter}   -> list users filtered by username
 *     GET    ./REST/messages/user/{id}   -> get conversation with user id
 *     POST   ./REST/messages             -> send new message (recipient_id, message)
 *     PUT    ./REST/messages/{id}        -> edit a message (message)
 *     DELETE ./REST/messages/{id}        -> unsend a message
 *
 * Author: Sandip Bohara Chhetri
 */

let selectedUserId = null;
let selectedUsername = null;
let myUserId = null;
let pollTimer = null;

let isEditing = false;
let editingMessageId = null;

$(document).ready(function () {

  $("#filter-button").on("click", () => {
    loadUsers($("#filter-input").val().trim());
  });

  $("#filter-input").on("input", debounce(function () {
    loadUsers($("#filter-input").val().trim());
  }, 250));

  $("#send-button").on("click", sendMessage);

  $("#message-input").on("keydown", function (e) {
    /**
     * If the user presses Enter while typing in the message input:
     * - prevent the default action (which might submit a form or add a newline, depending on context)
     * - send the message immediately
     */
    if (e.key === "Enter") {
      e.preventDefault();
      sendMessage();
    }
  });

  //Close open menus only when clicking truly outside
  $(document).on("click", function () {
    $(".msgDropdown").hide();
  });

  //Clicking inside dropdown should NOT close it
  $(document).on("click", ".msgDropdown", function (e) {
    e.stopPropagation();
  });

  //Clicking dropdown items should NOT bubble to document
  $(document).on("click", ".msgDropdown .dropdownItem", function (e) {
    e.stopPropagation();
  });

  //Toggle ⋯ menu + auto "dropup" based on space inside #chat-feed (not window)
$(document).on("click", ".msgMenuBtn", function (e) {
  e.preventDefault();
  e.stopPropagation();

  const wrapper = $(this).closest(".msgMenuOutside");
  const dd = wrapper.find(".msgDropdown");
  const feedEl = document.getElementById("chat-feed");

  const wasVisible = dd.is(":visible");

  // close other dropdowns
  $(".msgDropdown").hide();

  if (wasVisible) return;

  // show first so we can measure height
  dd.removeClass("dropup").show();

  // If feed doesn't exist (shouldn't happen), fallback to window behavior
  if (!feedEl) return;

  const btnRect = this.getBoundingClientRect();
  const feedRect = feedEl.getBoundingClientRect();
  const ddHeight = dd.outerHeight();

  /**
   * Complex positioning logic:
   * - The dropdown menu should appear below the button by default.
   * - If there isn't enough visible space inside the chat feed below the button,
   *   but there IS enough space above the button, we convert it to a "dropup".
   *
   * Why measure relative to #chat-feed?
   * - The chat feed is a scrollable container; using window space can be misleading because
   *   the feed might be smaller than the viewport and clip the menu.
   */
  const spaceBelowInFeed = feedRect.bottom - btnRect.bottom;
  const spaceAboveInFeed = btnRect.top - feedRect.top;

  // If not enough room below but enough room above, open upward
  if (spaceBelowInFeed < ddHeight + 12 && spaceAboveInFeed > ddHeight + 12) {
    dd.addClass("dropup");
  }
});

  // Edit from dropdown
  $(document).on("click", ".dropdownItem.editBtn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const messageId = $(this).data("id");
    const oldText = $(this).data("text");

    $(".msgDropdown").hide();
    startEdit(messageId, oldText);
  });

  // Unsend from dropdown
  $(document).on("click", ".dropdownItem.unsendBtn", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const messageId = $(this).data("id");

    $(".msgDropdown").hide();
    unsendMessage(messageId);
  });

  loadUsers("");
});

/**
 * Creates a debounced version of a function.
 *
 * Debouncing:
 * - Ensures the function only executes after the user stops triggering it for `wait` ms.
 * - Used here to prevent firing an API request on every keystroke instantly.
 *
 * @param {Function} fn The function to debounce.
 * @param {number} wait Delay in milliseconds before executing the function.
 * @returns {Function} A debounced wrapper function.
 */
function debounce(fn, wait) {
  let t = null;
  return function (...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), wait);
  };
}

/**
 * Loads the list of users from the server and renders them in the sidebar.
 *
 * - If `filter` is non-empty, calls: GET ./REST/usernames/{filter}
 * - Otherwise calls:               GET ./REST/usernames
 *
 * Also updates:
 * - `myUserId` from `res.me`
 * - the topbar badge (#meBadge) using `window.ME_USERNAME` when available
 * - the sidebar count pill (#user-count)
 *
 * @param {string} filter Username filter text (may be empty).
 * @returns {void}
 */
function loadUsers(filter) {
  const safeFilter = encodeURIComponent(filter || "");
  const url = filter ? `./REST/usernames/${safeFilter}` : "./REST/usernames";

  $.ajax({
    url,
    method: "GET",
    dataType: "json",
    success: (res) => {
      if (res.status !== 200) {
        $("#user-list").html(`<div class="hint">Failed to load users</div>`);
        return;
      }

      myUserId = res.me;

      // show username (from PHP) instead of user_id
      const meName = (window.ME_USERNAME && String(window.ME_USERNAME).trim() !== "")
        ? window.ME_USERNAME
        : `user_id: ${myUserId}`;

      $("#meBadge").text(`Logged in as ${meName}`);

      $("#user-count").text((res.data || []).length);
      renderUsers(res.data || []);
    }
  });
}

/**
 * Renders the sidebar user buttons.
 *
 * UI behavior:
 * - Creates a button per user.
 * - When a user is clicked:
 *   - sets `selectedUserId` and `selectedUsername`
 *   - updates the chat header
 *   - enables the composer input + send button
 *   - loads the conversation and starts polling
 *
 * List of users returned by the API.
 */
function renderUsers(users) {
  const list = $("#user-list").empty();

  if (!users.length) {
    list.append(`<div class="hint">No users found</div>`);
    return;
  }

  users.forEach(u => {
    const btn = $(`<button class="userItem" type="button"></button>`);
    btn.text(u.username);
    btn.data("id", Number(u.user_id));
    btn.data("name", String(u.username));

    if (selectedUserId && Number(u.user_id) === Number(selectedUserId)) {
      btn.addClass("active");
    }

    btn.on("click", () => {
      $(".userItem").removeClass("active");
      btn.addClass("active");

      selectedUserId = btn.data("id");
      selectedUsername = btn.data("name");

      $("#chat-username").text(selectedUsername);
      $("#chat-meta").text(`Direct message with @${selectedUsername}`);

      $("#message-input").prop("disabled", false).val("").focus();
      $("#send-button").prop("disabled", false);

      loadConversation(true);
      startPolling();
    });

    list.append(btn);
  });
}

/**
 * Returns the chat feed DOM element.
 *
 * @returns {HTMLElement|null} The feed element, or null if not found.
 */
function getFeed() {
  return document.getElementById("chat-feed");
}

/**
 * Determines whether the user is close to the bottom of the scrollable feed.
 *
 * This is used to decide whether to auto-scroll when new messages arrive:
 * - If the user is already near the bottom, keep them at the bottom.
 * - If the user has scrolled up to read earlier messages, do not force-scroll.
 *
 * The feed element.
 * The "near bottom" threshold in pixels.
 * true if near the bottom (or feed missing), otherwise false.
 */
function isNearBottom(feedEl, threshold = 90) {
  if (!feedEl) return true;
  return (feedEl.scrollHeight - feedEl.scrollTop - feedEl.clientHeight) < threshold;
}

/**
 * Loads the conversation between the logged-in user and the selected user.
 *
 * Important UI guardrails:
 * - If no user is selected, do nothing.
 * - If currently editing (`isEditing`), do not redraw the feed.
 *   This prevents overwriting the inline edit UI.
 * - If any dropdown menu is open, do not redraw the feed.
 *   This prevents the menu from disappearing due to re-render.
 *
 * If true, force scroll to bottom after render.
 */
function loadConversation(forceScrollBottom = false) {
  if (!selectedUserId) return;

  //don't redraw while editing OR while a menu is open (prevents "menu disappears")
  if (isEditing) return;
  if ($(".msgDropdown:visible").length) return;

  $.ajax({
    url: `./REST/messages/user/${selectedUserId}`,
    method: "GET",
    dataType: "json",
    success: (res) => {
      if (res.status !== 200) {
        $("#chat-feed").html(`<div class="hint">Failed to load messages</div>`);
        return;
      }

      myUserId = res.me ?? myUserId;
      renderConversation(res.data || [], forceScrollBottom);
    }
  });
}

/**
 * Renders the full conversation into the chat feed.
 *
 * Message rendering rules:
 * - Messages from me are aligned/styled as "mine".
 * - Messages from the other user are aligned/styled as "theirs".
 * - If a message text equals "Message unsent" (case-insensitive), render it with the unsent style
 *   and do not show edit/unsend actions.
 *
 * Auto-scroll behavior:
 * - If `forceScrollBottom` is true, scroll to the bottom.
 * - Otherwise, scroll only if the user was already near the bottom.
 *
 * Conversation messages.
 * Whether to force scroll to bottom after rendering.
 */
function renderConversation(messages, forceScrollBottom) {
  const feed = $("#chat-feed").empty();

  if (!messages.length) {
    feed.append(`<div class="hint">No messages yet. Say hi 👋</div>`);
    return;
  }

  messages.forEach(m => {
    const isMine = Number(m.sender_id) === Number(myUserId);
    const isUnsent = String(m.message || "").trim().toLowerCase() === "message unsent";

    // Row wrapper (holds dots + bubble)
    const row = $(`<div class="msgRow ${isMine ? "mineRow" : "theirRow"}"></div>`);

    // Bubble
    const bubble = $(`<article class="msg ${isMine ? "mine" : ""} ${isUnsent ? "unsent" : ""}"></article>`);

    const t = new Date(m.date_time);
    const header = $(`<div class="msgHeader"></div>`)
      .text(`${m.username} • ${t.toLocaleString()}`);

    const text = $(`<div class="msgText" id="msg-${m.message_id}"></div>`);
    if (isUnsent) {
      text.text("Message unsent");
    } else {
      text.text(m.message);
    }

    bubble.append(header, text);

    // Dots menu (only for my messages & not unsent)
    if (isMine && !isUnsent) {
      const menu = $(`
        <div class="msgMenuOutside">
          <button type="button" class="msgMenuBtn" aria-label="Message menu">⋯</button>
          <div class="msgDropdown" role="menu" aria-label="Message actions">
            <button type="button" class="dropdownItem editBtn" role="menuitem">Edit</button>
            <button type="button" class="dropdownItem unsendBtn" role="menuitem">Unsend</button>
          </div>
        </div>
      `);

      // Attach data used by click handlers
      menu.find(".editBtn").data("id", m.message_id).data("text", m.message);
      menu.find(".unsendBtn").data("id", m.message_id);

      // dots on LEFT for sent messages
      row.append(menu, bubble);
    } else {
      row.append(bubble);
    }

    feed.append(row);
  });

  const feedEl = feed[0];

  // If user was already near the bottom, keep them at bottom when new messages arrive.
  // If user scrolled up, don't force scroll.
  if (forceScrollBottom || isNearBottom(feedEl)) {
    feedEl.scrollTo({
      top: feedEl.scrollHeight,
      behavior: "smooth"
    });
  }
}

/**
 * Sends a new message to the currently selected user.
 *
 * Validations:
 * - Must have a selected user.
 * - Message text cannot be empty after trimming.
 *
 * UI behavior:
 * - Disables the Send button while the request is in-flight.
 * - On success (status 201), clears the input and reloads conversation (scroll to bottom).
 *
 * @returns {void}
 */
function sendMessage() {
  const msg = $("#message-input").val().trim();
  if (!selectedUserId) return;

  if (!msg) {
    $("#message-input").focus();
    return;
  }

  $("#send-button").prop("disabled", true);

  $.ajax({
    url: "./REST/messages",
    method: "POST",
    dataType: "json",
    data: { message: msg, recipient_id: selectedUserId },
    success: (res) => {
      if (res.status === 201) {
        $("#message-input").val("");
        loadConversation(true);
      } else {
        alert(res.message || "Send failed");
      }
    },
    complete: () => $("#send-button").prop("disabled", false)
  });
}

/**
 * Starts inline edit mode for a message.
 *
 * Behavior:
 * - Stops polling to prevent the feed from refreshing during edit.
 * - Replaces the message text with an input field + Save/Cancel buttons.
 * - On Save: validates non-empty and calls updateMessage().
 * - On Cancel: exits edit mode and re-renders the conversation.
 *
 * @param {number} messageId The message ID being edited.
 * @param {string} oldText The existing message text to prefill into the input.
 * @returns {void}
 */
function startEdit(messageId, oldText) {
  stopPolling(); // stop refresh during edit
  isEditing = true;
  editingMessageId = messageId;

  const container = $(`#msg-${messageId}`).empty();

  const input = $(`<input type="text" />`).val(oldText);
  input.css({
    width: "100%",
    padding: "10px 12px",
    borderRadius: "12px",
    border: "1px solid rgba(21,26,34,.14)",
    background: "rgba(243,240,230,0.70)",
    color: "inherit",
    outline: "none"
  });

  const row = $(`<div style="display:flex; gap:10px; margin-top:10px;"></div>`);

  const save = $(`<button type="button" class="btn small primary">Save</button>`);
  const cancel = $(`<button type="button" class="btn small">Cancel</button>`);

  save.on("click", () => {
    const newMsg = input.val().trim();
    if (!newMsg) return;
    updateMessage(messageId, newMsg);
  });

  cancel.on("click", () => {
    isEditing = false;
    editingMessageId = null;
    startPolling(); // resume
    loadConversation(false);
  });

  row.append(save, cancel);
  container.append(input, row);
  input.focus();
}

/**
 * Sends an update request to edit a message on the server.
 *
 * Endpoint:
 * - PUT ./REST/messages/{messageId}
 *
 * On success:
 * - Exit edit mode
 * - Restart polling
 * - Reload the conversation (without forcing scroll)
 *
 * @param {number} messageId The message ID to update.
 * @param {string} newMsg The new message text.
 * @returns {void}
 */
function updateMessage(messageId, newMsg) {
  $.ajax({
    url: `./REST/messages/${messageId}`,
    method: "PUT",
    dataType: "json",
    data: { message: newMsg },
    success: (res) => {
      if (res.status === 200) {
        isEditing = false;
        editingMessageId = null;
        startPolling(); // resume
        loadConversation(false);
      } else {
        alert(res.message || "Update failed");
      }
    }
  });
}

/**
 * Performs the "unsend" operation for a message.
 *
 * Endpoint:
 * - DELETE ./REST/messages/{messageId}
 *
 * UI behavior:
 * - Prompts the user for confirmation.
 * - On success, reloads the conversation.
 *
 * @param {number} messageId The message ID to unsend.
 * @returns {void}
 */
function unsendMessage(messageId) {
  if (!confirm("Unsend this message?")) return;

  $.ajax({
    url: `./REST/messages/${messageId}`,
    method: "DELETE",
    dataType: "json",
    success: (res) => {
      if (res.status === 200) {
        loadConversation(false);
      } else {
        alert(res.message || "Unsend failed");
      }
    }
  });
}

/**
 * Starts polling the server for conversation updates.
 *
 * Implementation:
 * - Clears any existing timer first (to avoid duplicates).
 * - Polls every 2500ms and refreshes the conversation without forcing scroll.
 *
 * @returns {void}
 */
function startPolling() {
  stopPolling();
  pollTimer = setInterval(() => loadConversation(false), 2500);
}

/**
 * Stops the polling timer if it exists.
 *
 * @returns {void}
 */
function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}