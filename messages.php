<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
$user_id = $_SESSION['id'];
$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

// --- Handle group creation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'], $_POST['members'])) {
    $group_name = trim($_POST['group_name']);
    $members = $_POST['members'];
    if ($group_name && is_array($members) && count($members) > 0) {
        $stmt = $conn->prepare("INSERT INTO groups (name, created_by) VALUES (?, ?)");
        $stmt->bind_param('si', $group_name, $user_id);
        if ($stmt->execute()) {
            $group_id = $stmt->insert_id;
            // Add creator as member
            $conn->query("INSERT INTO group_members (group_id, user_id) VALUES ($group_id, $user_id)");
            // Add selected members
            $gm_stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            foreach ($members as $mid) {
                $mid = intval($mid);
                if ($mid !== $user_id) {
                    $gm_stmt->bind_param('ii', $group_id, $mid);
                    $gm_stmt->execute();
                }
            }
            $gm_stmt->close();
            header("Location: messages.php?group=$group_id");
            exit;
        }
        $stmt->close();
    }
}

// --- Fetch user's groups ---
$user_groups = [];
$group_stmt = $conn->prepare("SELECT g.id, g.name FROM groups g JOIN group_members gm ON g.id = gm.group_id WHERE gm.user_id = ? ORDER BY g.name ASC");
$group_stmt->bind_param('i', $user_id);
$group_stmt->execute();
$group_result = $group_stmt->get_result();
while ($row = $group_result->fetch_assoc()) {
    $user_groups[] = $row;
}
$group_stmt->close();

// --- Handle group message send ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'], $_POST['group_message'])) {
    $group_id = intval($_POST['group_id']);
    $msg = trim($_POST['group_message']);
    if ($msg !== '') {
        $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $group_id, $user_id, $msg);
        $stmt->execute();
        $stmt->close();
        header("Location: messages.php?group=$group_id");
        exit;
    }
}

// --- Handle leave group ---
if (isset($_POST['leave_group_id'])) {
    $leave_group_id = intval($_POST['leave_group_id']);
    $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $leave_group_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: messages.php");
    exit;
}

// --- Get selected chat or group ---
$chat_id = isset($_GET['chat']) ? intval($_GET['chat']) : 0;
$group_id = isset($_GET['group']) ? intval($_GET['group']) : 0;

// --- Fetch messages ---
$messages = [];
$group_messages = [];
if ($chat_id) {
    $stmt = $conn->prepare("
        SELECT m.sender_id, m.message, m.sent_at, u.name, u.avatar, u.avatar_mime_type
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
        ORDER BY m.sent_at ASC
    ");
    $stmt->bind_param('iiii', $user_id, $chat_id, $chat_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($sender, $msg, $sent_at, $sender_name, $sender_avatar, $sender_avatar_mime);
    while ($stmt->fetch()) {
        $messages[] = [
            'sender' => $sender,
            'msg' => $msg,
            'sent_at' => $sent_at,
            'name' => $sender_name,
            'avatar' => $sender_avatar,
            'avatar_mime_type' => $sender_avatar_mime
        ];
    }
    $stmt->close();
}
if ($group_id) {
    $stmt = $conn->prepare("
        SELECT gm.sender_id, gm.message, gm.sent_at, u.name, u.avatar, u.avatar_mime_type
        FROM group_messages gm
        JOIN users u ON gm.sender_id = u.id
        WHERE gm.group_id=?
        ORDER BY gm.sent_at ASC
    ");
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $stmt->bind_result($sender, $msg, $sent_at, $sender_name, $sender_avatar, $sender_avatar_mime);
    while ($stmt->fetch()) {
        $group_messages[] = [
            'sender' => $sender,
            'msg' => $msg,
            'sent_at' => $sent_at,
            'name' => $sender_name,
            'avatar' => $sender_avatar,
            'avatar_mime_type' => $sender_avatar_mime
        ];
    }
    $stmt->close();
}

// --- Get Conversation Partners ---
// This query fetches a unique list of users the current user has exchanged messages with.
$conversations = [];
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.avatar, u.avatar_mime_type
    FROM users u
    JOIN (
        SELECT sender_id AS other_user_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT receiver_id AS other_user_id FROM messages WHERE sender_id = ?
    ) AS partners ON u.id = partners.other_user_id
    ORDER BY u.name ASC
");
if ($stmt) {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $conversations[] = $row;
    }
    $stmt->close();
}

// Handle send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receiver_id'], $_POST['message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $msg = trim($_POST['message']);
    if ($msg !== '') {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $user_id, $receiver_id, $msg);
        $stmt->execute();
        $stmt->close();
    }
}

// Get selected friend
$chat_id = isset($_GET['chat']) ? intval($_GET['chat']) : (count($conversations) ? $conversations[0]['id'] : 0);

// Get messages
$messages = [];
if ($chat_id) {
    $stmt = $conn->prepare("
        SELECT m.sender_id, m.message, m.sent_at, u.name, u.avatar, u.avatar_mime_type
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
        ORDER BY m.sent_at ASC
    ");
    $stmt->bind_param('iiii', $user_id, $chat_id, $chat_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($sender, $msg, $sent_at, $sender_name, $sender_avatar, $sender_avatar_mime);
    while ($stmt->fetch()) {
        $messages[] = [
            'sender' => $sender,
            'msg' => $msg,
            'sent_at' => $sent_at,
            'name' => $sender_name,
            'avatar' => $sender_avatar,
            'avatar_mime_type' => $sender_avatar_mime
        ];
    }
    $stmt->close();
}

// --- Fetch group members if group_id is set ---
$group_members = [];
if ($group_id) {
    $stmt = $conn->prepare("SELECT u.id, u.name, u.avatar, u.avatar_mime_type FROM users u JOIN group_members gm ON u.id = gm.user_id WHERE gm.group_id = ?");
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $group_members[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages - studytogether</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="messages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include 'sidebar.php'; // Use your sidebar code here ?>
    <main class="dashboard-main">
        <h1>Messages</h1>
        <div class="messages-container">
            <div class="side-panel">
                <div class="card-section">
                    <div class="section-title">Personal</div>
                    <div class="friends-list">
                        <?php foreach ($conversations as $f): ?>
                            <a href="messages.php?chat=<?php echo $f['id']; ?>" class="friend-link<?php if ($chat_id == $f['id']) echo ' active'; ?>">
                                <?php if (!empty($f['avatar'])): ?>
                                    <img src="data:<?php echo $f['avatar_mime_type']; ?>;base64,<?php echo base64_encode($f['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                                <?php else: ?>
                                    <div class="friend-avatar-initial"><?php echo strtoupper(substr($f['name'], 0, 1)); ?></div>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($f['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="group-section">
                    <div class="section-title" style="display:flex;align-items:center;justify-content:space-between;">
                        <span>Groups</span>
                        <button class="create-group-btn" onclick="openGroupModal()"><i class="fa fa-plus"></i> Create Group</button>
                    </div>
                    <div class="friends-list" id="groups-list">
                        <?php foreach ($user_groups as $g): ?>
                            <a href="messages.php?group=<?php echo $g['id']; ?>" class="group-link<?php if (isset($group_id) && $group_id == $g['id']) echo ' active'; ?>">
                                <i class="fa-solid fa-users"></i> <?php echo htmlspecialchars($g['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="chat-area">
                <?php if ($group_id): ?>
                <div class="chat-header">
                    <?php
                    $header_user = null;
                    foreach ($user_groups as $g) {
                        if ($g['id'] == $group_id) {
                            $header_user = $g;
                            break;
                        }
                    }
                    ?>
                    <i class="fa-solid fa-users"></i>
                    <span>
                        <?php echo htmlspecialchars($header_user['name'] ?? ''); ?>
                    </span>
                    <button class="participants-btn" onclick="openParticipantsModal()" style="margin-left:auto;background:none;border:none;color:#fff;font-size:1.2rem;cursor:pointer;">
                        <i class="fa fa-user-friends"></i> Participants
                    </button>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <?php if (!empty($group_messages)): ?>
                        <?php foreach ($group_messages as $m): ?>
                            <div class="chat-message<?php if ($m['sender'] == $user_id) echo ' me'; ?>">
                                <?php if ($m['sender'] != $user_id): ?>
                                    <?php if (!empty($m['avatar'])): ?>
                                        <img src="data:<?php echo $m['avatar_mime_type']; ?>;base64,<?php echo base64_encode($m['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                                    <?php else: ?>
                                        <div class="friend-avatar-initial"><?php echo strtoupper(substr($m['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="msg-bubble"><?php echo htmlspecialchars($m['msg']); ?></div>
                                <div class="msg-time"><?php echo date('H:i', strtotime($m['sent_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#888;">No messages yet. Say hi to your group!</p>
                    <?php endif; ?>
                </div>
                <form class="chat-form" method="POST" action="messages.php?group=<?php echo $group_id; ?>">
                    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
                    <input type="text" name="group_message" placeholder="Enter Your Message" autocomplete="off" required>
                    <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
                <?php else: ?>
                <?php if ($chat_id): ?>
                <div class="chat-header">
                    <?php
                    $header_user = null;
                    foreach ($conversations as $f) {
                        if ($f['id'] == $chat_id) {
                            $header_user = $f;
                            break;
                        }
                    }
                    ?>
                    <?php if (!empty($header_user['avatar'])): ?>
                        <img src="data:<?php echo $header_user['avatar_mime_type']; ?>;base64,<?php echo base64_encode($header_user['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                    <?php else: ?>
                        <div class="friend-avatar-initial"><?php echo strtoupper(substr($header_user['name'], 0, 1)); ?></div>
                    <?php endif; ?>
                    <span>
                        <?php echo htmlspecialchars($header_user['name'] ?? ''); ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="chat-messages" id="chat-messages">
                    <?php if ($chat_id && !empty($messages)): ?>
                        <?php foreach ($messages as $m): ?>
                            <div class="chat-message<?php if ($m['sender'] == $user_id) echo ' me'; ?>">
                                <?php if ($m['sender'] != $user_id): ?>
                                    <?php if (!empty($m['avatar'])): ?>
                                        <img src="data:<?php echo $m['avatar_mime_type']; ?>;base64,<?php echo base64_encode($m['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                                    <?php else: ?>
                                        <div class="friend-avatar-initial"><?php echo strtoupper(substr($m['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <div class="msg-bubble"><?php echo htmlspecialchars($m['msg']); ?></div>
                                <div class="msg-time"><?php echo date('H:i', strtotime($m['sent_at'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($chat_id): ?>
                        <p style="color:#888;">No messages yet. Say hi!</p>
                    <?php else: ?>
                        <p style="color:#888;">Select a friend to start chatting.</p>
                    <?php endif; ?>
                </div>
                <?php if ($chat_id): ?>
                <form class="chat-form" method="POST" action="messages.php?chat=<?php echo $chat_id; ?>">
                    <input type="hidden" name="receiver_id" value="<?php echo $chat_id; ?>">
                    <input type="text" name="message" placeholder="Enter Your Message" autocomplete="off" required>
                    <button type="submit"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<div class="modal-overlay" id="groupModalOverlay" onclick="closeGroupModal()"></div>
<div class="modal" id="groupModal">
    <h2>Create Group</h2>
    <form id="createGroupForm" method="POST" action="messages.php">
        <label for="group_name">Group Name</label>
        <input type="text" id="group_name" name="group_name" required>
        <label>Add Members</label>
        <div class="user-list">
            <?php foreach ($conversations as $f): ?>
                <label><input type="checkbox" name="members[]" value="<?php echo $f['id']; ?>"> <?php echo htmlspecialchars($f['name']); ?></label>
            <?php endforeach; ?>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn cancel" onclick="closeGroupModal()">Cancel</button>
            <button type="submit" class="btn">Create</button>
        </div>
    </form>
</div>
<div class="modal-overlay" id="participantsModalOverlay" onclick="closeParticipantsModal()"></div>
<div class="modal" id="participantsModal">
    <h2>Group Participants</h2>
    <ul style="list-style:none;padding:0;max-height:180px;overflow-y:auto;">
        <?php foreach ($group_members as $member): ?>
            <li style="padding:6px 0;display:flex;align-items:center;gap:10px;">
                <?php if (!empty($member['avatar'])): ?>
                    <img src="data:<?php echo $member['avatar_mime_type']; ?>;base64,<?php echo base64_encode($member['avatar']); ?>" class="friend-avatar" alt="Profile Picture" style="width:32px;height:32px;">
                <?php else: ?>
                    <div class="friend-avatar-initial" style="width:32px;height:32px;">
                        <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <?php echo htmlspecialchars($member['name']); ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="POST" action="messages.php?group=<?php echo $group_id; ?>" class="modal-actions" style="margin-top:18px;">
        <input type="hidden" name="leave_group_id" value="<?php echo $group_id; ?>">
        <button type="button" class="btn cancel" onclick="closeParticipantsModal()">Close</button>
        <button type="submit" class="btn" style="background:#e57373;">Leave Group</button>
    </form>
</div>
<script>
    // Auto-scroll to bottom of chat
    var chat = document.getElementById('chat-messages');
    if (chat) chat.scrollTop = chat.scrollHeight;
    function openGroupModal() {
        document.getElementById('groupModal').style.display = 'block';
        document.getElementById('groupModalOverlay').style.display = 'block';
    }
    function closeGroupModal() {
        document.getElementById('groupModal').style.display = 'none';
        document.getElementById('groupModalOverlay').style.display = 'none';
    }
    function openParticipantsModal() {
        document.getElementById('participantsModal').style.display = 'block';
        document.getElementById('participantsModalOverlay').style.display = 'block';
    }
    function closeParticipantsModal() {
        document.getElementById('participantsModal').style.display = 'none';
        document.getElementById('participantsModalOverlay').style.display = 'none';
    }
</script>
</body>
</html>