<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "Invalid user ID.";
    exit;
}

$current_user_id = $_SESSION['id'];
$view_user_id = intval($_GET['id']);

// If user is viewing their own profile, redirect to the editable profile page
if ($current_user_id === $view_user_id) {
    header("location: profile.php");
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// --- Fetch User Details ---
$stmt = $conn->prepare('SELECT name, title, location, about, avatar, avatar_mime_type FROM users WHERE id = ?');
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    echo "User not found.";
    exit;
}

// --- Fetch User Stats ---
$res_followers = $conn->query("SELECT COUNT(*) as cnt FROM followers WHERE user_id = $view_user_id");
$followers = $res_followers ? $res_followers->fetch_assoc()['cnt'] : 0;
$res_following = $conn->query("SELECT COUNT(*) as cnt FROM followers WHERE follower_id = $view_user_id");
$following = $res_following ? $res_following->fetch_assoc()['cnt'] : 0;

// --- Check Follow Status ---
$is_following = false;
$stmt_follow = $conn->prepare("SELECT 1 FROM followers WHERE user_id = ? AND follower_id = ?");
$stmt_follow->bind_param('ii', $view_user_id, $current_user_id);
$stmt_follow->execute();
if ($stmt_follow->get_result()->num_rows > 0) {
    $is_following = true;
}

// --- Fetch User's Notes ---
$uploads = [];
$stmt_notes = $conn->prepare("SELECT id, file_name, description FROM uploads WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt_notes->bind_param('i', $view_user_id);
$stmt_notes->execute();
$result_notes = $stmt_notes->get_result();
while ($row = $result_notes->fetch_assoc()) {
    $uploads[] = $row;
}

$stmt->close();
$stmt_follow->close();
$stmt_notes->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($user['name']); ?>'s Profile</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="view_profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="dashboard-page">
<div class="dashboard-container">
    <?php include 'sidebar.php'; ?>
    <main class="dashboard-main">
        <div class="profile-header">
            <div class="profile-avatar-lg js-lightbox-trigger">
                <?php if (!empty($user['avatar'])): ?>
                    <img src="data:<?php echo $user['avatar_mime_type']; ?>;base64,<?php echo base64_encode($user['avatar']); ?>" alt="<?php echo htmlspecialchars($user['name']); ?>'s Avatar">
                <?php else: ?>
                    <span><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <div class="profile-name-lg"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="profile-title"><?php echo htmlspecialchars($user['title'] ?? 'No title'); ?></div>
                <div class="profile-stats">
                    <div class="profile-stat"><div class="profile-stat-num followers-count"><?php echo $followers; ?></div><div>Followers</div></div>
                    <div class="profile-stat"><div class="profile-stat-num"><?php echo $following; ?></div><div>Following</div></div>
                </div>
            </div>
            <div class="profile-actions">
                <button type="button" class="profile-action-btn follow-btn" data-user-id="<?php echo $view_user_id; ?>">
                    <i class="fa-solid fa-heart"></i>
                    <span class="follow-btn-text"><?php echo $is_following ? 'Unfollow' : 'Follow'; ?></span>
                </button>
                <a href="messages.php?chat=<?php echo $view_user_id; ?>" class="profile-action-btn">
                    <i class="fa-solid fa-paper-plane"></i> Message
                </a>
            </div>
        </div>

        <h2><?php echo htmlspecialchars($user['name']); ?>'s Notes</h2>
        <div class="notes-grid">
            <?php if (empty($uploads)): ?>
                <p><?php echo htmlspecialchars($user['name']); ?> has not uploaded any notes yet.</p>
            <?php else: foreach ($uploads as $note): ?>
                <?php
                // --- Fetch Like Data for this Note ---
                $conn_likes = new mysqli('localhost', 'root', '', 'studytogether_db');
                $note_id = $note['id'];
                
                // Get total likes for the note
                $like_count = 0;
                $res_likes = $conn_likes->query("SELECT COUNT(*) as cnt FROM likes WHERE upload_id = $note_id");
                if ($res_likes) {
                    $like_count = $res_likes->fetch_assoc()['cnt'];
                }

                // Check if the current user has liked this note
                $liked_by_user = false;
                $stmt_user_like = $conn_likes->prepare("SELECT 1 FROM likes WHERE upload_id = ? AND user_id = ?");
                $stmt_user_like->bind_param('ii', $note_id, $current_user_id);
                $stmt_user_like->execute();
                if ($stmt_user_like->get_result()->num_rows > 0) {
                    $liked_by_user = true;
                }
                $stmt_user_like->close();
                $conn_likes->close();
                ?>
                <div class="note-card">
                    <i class="fa-solid fa-file-lines"></i>
                    <div class="note-filename"><?php echo htmlspecialchars($note['file_name']); ?></div>
                    <div class="note-description"><?php echo htmlspecialchars($note['description'] ?? ''); ?></div>
                    <div class="note-actions">
                        <button class="like-btn profile-action-btn" 
                                data-upload-id="<?php echo $note['id']; ?>">
                            <i class="<?php echo $liked_by_user ? 'fa-solid' : 'fa-regular'; ?> fa-heart"></i>
                            <span class="like-count"><?php echo $like_count; ?></span>
                        </button>
                        <a href="download.php?id=<?php echo $note['id']; ?>" class="profile-action-btn download-btn">Download</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const followBtn = document.querySelector('.follow-btn');
    if (followBtn) {
        followBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.getAttribute('data-user-id');
            const btnText = this.querySelector('.follow-btn-text');
            const isFollowed = btnText.textContent.trim() === 'Unfollow';
            const action = isFollowed ? 'unfollow' : 'follow';
            const button = this;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'follow_user.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        const followerCountEl = document.querySelector('.followers-count');
                        if (followerCountEl) {
                            followerCountEl.textContent = res.follower_count;
                        }
                        if (action === 'follow') {
                            btnText.textContent = 'Unfollow';
                            button.style.background = '#aaa';
                        } else {
                            btnText.textContent = 'Follow';
                            button.style.background = '#e57373';
                        }
                    }
                }
            };
            xhr.send('user_id=' + encodeURIComponent(userId) + '&action=' + encodeURIComponent(action));
        });
    }

    // --- LIKE BUTTON LOGIC ---
    document.querySelectorAll('.like-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const uploadId = this.getAttribute('data-upload-id');
            const icon = this.querySelector('i');
            const likeCountSpan = this.querySelector('.like-count');
            const liked = icon.classList.contains('fa-solid');
            const action = liked ? 'unlike' : 'like';
            const button = this;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'like_note.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            likeCountSpan.textContent = res.like_count;
                            if (action === 'like') {
                                icon.classList.remove('fa-regular');
                                icon.classList.add('fa-solid');
                                button.style.color = '#e57373';
                                button.style.borderColor = '#e57373';
                            } else {
                                icon.classList.remove('fa-solid');
                                icon.classList.add('fa-regular');
                                button.style.color = '#333';
                                button.style.borderColor = '#ddd';
                            }
                        }
                    } catch (err) {
                        console.error("Error parsing like response:", xhr.responseText);
                    }
                }
            };
            xhr.send('upload_id=' + encodeURIComponent(uploadId) + '&action=' + encodeURIComponent(action));
        });
    });
});
</script>
<?php include 'lightbox.php'; ?>
</body>
</html> 