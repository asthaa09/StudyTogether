<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo "Invalid user.";
    exit;
}
$view_user_id = intval($_GET['user_id']);
$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$stmt = $conn->prepare('SELECT name, title, location, about FROM users WHERE id = ?');
$stmt->bind_param('i', $view_user_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo "User not found.";
    exit;
}
$stmt->bind_result($name, $title, $location, $about);
$stmt->fetch();
// Get followers and uploads count
$res = $conn->query("SELECT COUNT(*) as cnt FROM followers WHERE user_id = $view_user_id");
$followers = $res ? $res->fetch_assoc()['cnt'] : 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM followers WHERE follower_id = $view_user_id");
$following = $res ? $res->fetch_assoc()['cnt'] : 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM uploads WHERE user_id = $view_user_id");
$uploads = $res ? $res->fetch_assoc()['cnt'] : 0;
$conn->close();

$is_following = false;
if ($view_user_id != $_SESSION['id']) {
    $conn2 = new mysqli('localhost', 'root', 'Deyant@786', 'studytogether_db');
    $stmt = $conn2->prepare("SELECT 1 FROM followers WHERE user_id = ? AND follower_id = ?");
    $stmt->bind_param('ii', $view_user_id, $_SESSION['id']);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $is_following = true;
    }
    $stmt->close();
    $conn2->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - studytogether</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="profile_view.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <div class="profile-view-container">
        <div class="profile-avatar-lg">
            <span><?php echo strtoupper(substr($name, 0, 1)); ?></span>
        </div>
        <div class="profile-name-lg"><?php echo htmlspecialchars($name); ?></div>
        <div class="profile-title"><?php echo htmlspecialchars($title ?? ''); ?></div>
        <div class="profile-stats">
            <div class="profile-stat"><div class="profile-stat-num"><?php echo $followers; ?></div><div>Followers</div></div>
            <div class="profile-stat"><div class="profile-stat-num"><?php echo $following; ?></div><div>Following</div></div>
            <div class="profile-stat"><div class="profile-stat-num"><?php echo $uploads; ?></div><div>Uploads</div></div>
        </div>
        <div class="profile-location"><?php echo htmlspecialchars($location ?? ''); ?></div>
        <div class="profile-about"><?php echo htmlspecialchars($about ?? ''); ?></div>
        <div class="profile-actions">
            <form method="POST" action="#" style="display:inline;">
                <button type="button" class="profile-action-btn friend"><i class="fa-solid fa-user-plus"></i> Add Friend</button>
            </form>
            <form method="POST" action="#" style="display:inline;">
            <?php if ($view_user_id != $_SESSION['id']): ?>
            <button 
                type="button" 
                class="profile-action-btn follow-btn" 
                data-user-id="<?php echo $view_user_id; ?>"
                style="background: <?php echo $is_following ? '#4caf50' : '#e57373'; ?>;">
                <i class="fa-solid fa-heart"></i>
                <span class="follow-btn-text"><?php echo $is_following ? 'Followed' : 'Follow'; ?></span>
            </button>
            <?php endif; ?>
            </form>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.follow-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var userId = this.getAttribute('data-user-id');
            var btnText = this.querySelector('.follow-btn-text');
            var isFollowed = btnText.textContent.trim() === 'Followed';
            var action = isFollowed ? 'unfollow' : 'follow';
            var button = this;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'follow_user.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        if (action === 'follow') {
                            btnText.textContent = 'Followed';
                            button.style.background = '#4caf50';
                        } else {
                            btnText.textContent = 'Follow';
                            button.style.background = '#e57373';
                        }
                        // Optionally update follower count on the page
                        var followerStat = document.querySelector('.profile-stat-num');
                        if (followerStat && res.follower_count !== undefined) {
                            followerStat.textContent = res.follower_count;
                        }
                    }
                }
            };
            xhr.send('user_id=' + encodeURIComponent(userId) + '&action=' + encodeURIComponent(action));
        });
    });
});
</script>
</body>
</html> 