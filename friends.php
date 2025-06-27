<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
$user_id = $_SESSION['id'];
$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// --- HANDLE FRIEND ACTIONS (POST REQUESTS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ACCEPT a friend request
    if (isset($_POST['accept_id'])) {
        $friend_to_accept = intval($_POST['accept_id']);
        // Update the existing 'pending' request to 'accepted'
        $stmt = $conn->prepare("UPDATE friends SET status='accepted' WHERE user_id=? AND friend_id=?");
        $stmt->bind_param('ii', $friend_to_accept, $user_id);
        $stmt->execute();
        // Create the reverse relationship so both are friends
        $stmt = $conn->prepare("INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
        $stmt->bind_param('ii', $user_id, $friend_to_accept);
        $stmt->execute();
        $stmt->close();
    }
    // DECLINE or CANCEL a friend request
    if (isset($_POST['decline_id'])) {
        $friend_to_decline = intval($_POST['decline_id']);
        $stmt = $conn->prepare("DELETE FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?) AND status='pending'");
        $stmt->bind_param('iiii', $friend_to_decline, $user_id, $user_id, $friend_to_decline);
        $stmt->execute();
        $stmt->close();
    }
    // REMOVE an existing friend
    if (isset($_POST['remove_id'])) {
        $friend_to_remove = intval($_POST['remove_id']);
        $stmt = $conn->prepare("DELETE FROM friends WHERE (user_id=? AND friend_id=?) OR (user_id=? AND friend_id=?)");
        $stmt->bind_param('iiii', $friend_to_remove, $user_id, $user_id, $friend_to_remove);
        $stmt->execute();
        $stmt->close();
    }
    // ADD a new friend (send a request)
    if (isset($_POST['add_id'])) {
        $friend_to_add = intval($_POST['add_id']);
        $stmt = $conn->prepare("INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param('ii', $user_id, $friend_to_add);
        $stmt->execute();
        $stmt->close();
    }
    // Redirect to the same page to prevent form resubmission
    header("Location: friends.php");
    exit;
}


// --- FETCH DATA FOR DISPLAY ---

// 1. Get "Your Friends" (status is 'accepted')
$friends = [];
$stmt = $conn->prepare("SELECT u.id, u.name, u.avatar, u.avatar_mime_type FROM users u JOIN friends f ON u.id = f.friend_id WHERE f.user_id = ? AND f.status = 'accepted'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $friends[] = $row;
}

// 2. Get "Friend Requests" (requests waiting for YOUR approval)
$requests = [];
$stmt = $conn->prepare("SELECT u.id, u.name, u.avatar, u.avatar_mime_type FROM users u JOIN friends f ON u.id = f.user_id WHERE f.friend_id = ? AND f.status = 'pending'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// 3. Get "Sent Requests" (requests YOU sent that are pending)
$sent_requests = [];
$stmt = $conn->prepare("SELECT u.id, u.name, u.avatar, u.avatar_mime_type FROM users u JOIN friends f ON u.id = f.friend_id WHERE f.user_id = ? AND f.status = 'pending'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $sent_requests[] = $row;
}

// 4. Get "Add New Friends" (all users who are not you, not friends, and have no pending requests either way)
$addable_users = [];
$res = $conn->query("
    SELECT id, name, avatar, avatar_mime_type
    FROM users
    WHERE id != $user_id AND id NOT IN (
        SELECT friend_id FROM friends WHERE user_id = $user_id
    ) AND id NOT IN (
        SELECT user_id FROM friends WHERE friend_id = $user_id
    )
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $addable_users[] = $row;
    }
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Friends - studytogether</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="friends.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;900&family=Inter:wght@400;600&family=Poppins:wght@700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="dashboard-main">
        <h1 class="friends-heading">Friends</h1>
        <div class="friends-description">Connect with your friends, send and accept requests, and manage your study buddies. Use the tabs below to view your friends, add new ones, or handle friend requests.</div>
        <div class="friends-nav-bar">
            <button class="tab-btn active" data-tab="friends-list-section">Friends</button>
            <button class="tab-btn" data-tab="add-friends-section">Add Friends</button>
            <button class="tab-btn" data-tab="friend-requests-section">Friend Requests</button>
        </div>
        <div id="friends-list-section" class="tab-section">
            <div class="search-bar-wrapper">
                <input type="text" class="search-bar" placeholder="Search Friends...">
                <button class="search-btn"><i class="fa fa-search"></i></button>
            </div>
            <div class="friends-list">
                <?php if (empty($friends)): ?>
                    <p class="empty-state">You have no friends yet. Add some below!</p>
                <?php else: foreach ($friends as $f): ?>
                    <div class="friend-card">
                        <a href="view_profile.php?id=<?php echo $f['id']; ?>" style="text-decoration: none; color: inherit; font-weight: 600;">
                            <?php if (!empty($f['avatar'])): ?>
                                <img src="data:<?php echo $f['avatar_mime_type']; ?>;base64,<?php echo base64_encode($f['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                            <?php else: ?>
                                <div class="friend-avatar-initial"><?php echo strtoupper(substr($f['name'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <div class="friend-name"><?php echo htmlspecialchars($f['name']); ?></div>
                        </a>
                        <div class="friend-actions">
                            <form method="POST" action="friends.php" style="display:inline;">
                                <input type="hidden" name="remove_id" value="<?php echo $f['id']; ?>">
                                <button type="submit" class="remove-btn">Remove</button>
                            </form>
                            <a href="view_profile.php?id=<?php echo $f['id']; ?>" class="profile-btn">Profile</a>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div id="add-friends-section" class="tab-section" style="display:none;">
            <div class="search-bar-wrapper">
                <input type="text" class="search-bar" placeholder="Search Users...">
                <button class="search-btn"><i class="fa fa-search"></i></button>
            </div>
            <div class="all-users-list">
                <?php if (empty($addable_users)): ?>
                    <p class="empty-state">No new users to add right now.</p>
                <?php else: foreach ($addable_users as $u): ?>
                    <div class="friend-card">
                        <span>
                            <?php if (!empty($u['avatar'])): ?>
                                <img src="data:<?php echo $u['avatar_mime_type']; ?>;base64,<?php echo base64_encode($u['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                            <?php else: ?>
                                <div class="friend-avatar-initial"><?php echo strtoupper(substr($u['name'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </span>
                        <div class="friend-actions">
                            <form method="POST" action="friends.php" style="display:inline;">
                                <input type="hidden" name="add_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="add-friend-btn">Add Friend</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div id="friend-requests-section" class="tab-section" style="display:none;">
            <div class="search-bar-wrapper">
                <input type="text" class="search-bar" placeholder="Search Requests...">
                <button class="search-btn"><i class="fa fa-search"></i></button>
            </div>
            <div class="requests-sections">
                <div class="requests-column">
                    <div class="friend-requests-heading">Friend Requests</div>
                    <div class="requests-list">
                        <?php if (empty($requests)): ?>
                            <p class="empty-state">No pending friend requests.</p>
                        <?php else: foreach ($requests as $r): ?>
                            <div class="friend-card">
                                <span>
                                    <?php if (!empty($r['avatar'])): ?>
                                        <img src="data:<?php echo $r['avatar_mime_type']; ?>;base64,<?php echo base64_encode($r['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                                    <?php else: ?>
                                        <div class="friend-avatar-initial"><?php echo strtoupper(substr($r['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($r['name']); ?> wants to be your friend.</span>
                                <div class="friend-actions">
                                    <form method="POST" action="friends.php" style="display:inline;">
                                        <input type="hidden" name="accept_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="accept-btn">Accept</button>
                                    </form>
                                    <form method="POST" action="friends.php" style="display:inline;">
                                        <input type="hidden" name="decline_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="decline-btn">Decline</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div class="requests-column">
                    <div class="sent-requests-heading">Sent Requests</div>
                    <?php if (empty($sent_requests)): ?>
                        <p class="empty-state">You have not sent any friend requests.</p>
                    <?php endif; ?>
                    <div class="requests-list">
                        <?php foreach ($sent_requests as $sr): ?>
                            <div class="friend-card sent-request-card">
                                <div class="sent-request-label">
                                    <?php if (!empty($sr['avatar'])): ?>
                                        <img src="data:<?php echo $sr['avatar_mime_type']; ?>;base64,<?php echo base64_encode($sr['avatar']); ?>" class="friend-avatar" alt="Profile Picture">
                                    <?php else: ?>
                                        <div class="friend-avatar-initial"><?php echo strtoupper(substr($sr['name'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    Request sent to <?php echo htmlspecialchars($sr['name']); ?>
                                </div>
                                <div class="friend-actions">
                                    <form method="POST" action="friends.php" style="display:inline;">
                                        <input type="hidden" name="decline_id" value="<?php echo $sr['id']; ?>">
                                        <button type="submit" class="cancel-request-btn">Cancel Request</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.querySelectorAll('.tab-section').forEach(sec => sec.style.display = 'none');
                document.getElementById(this.dataset.tab).style.display = '';
            });
        });
        // Search filtering
        document.querySelectorAll('.tab-section').forEach(section => {
            const search = section.querySelector('.search-bar');
            const btn = section.querySelector('.search-btn');
            function filter() {
                const val = search.value.toLowerCase();
                section.querySelectorAll('.friend-card').forEach(card => {
                    card.style.display = card.textContent.toLowerCase().includes(val) ? '' : 'none';
                });
            }
            if (search) {
                search.addEventListener('input', filter);
            }
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    filter();
                    search.focus();
                });
            }
        });
        </script>
    </main>
</div>
</body>
</html>