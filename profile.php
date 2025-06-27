<?php
session_start();

// If the user is not logged in, redirect them to the login page
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}

$user_id = $_SESSION['id'];
$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle form submission for profile, avatar upload, or avatar removal
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- Handle Avatar Removal ---
    if (isset($_POST['remove_avatar'])) {
        $stmt_remove = $conn->prepare("UPDATE users SET avatar = NULL, avatar_mime_type = NULL WHERE id = ?");
        $stmt_remove->bind_param("i", $user_id);
        $stmt_remove->execute();
        $stmt_remove->close();
    }
    // --- Handle Profile Update and Avatar Upload ---
    else {
        // Update Text Fields
        $new_name = trim($_POST['name']);
        $new_title = trim($_POST['title']);
        $new_location = trim($_POST['location']);
        $new_about = trim($_POST['about']);
        $stmt = $conn->prepare('UPDATE users SET name=?, title=?, location=?, about=? WHERE id=?');
        $stmt->bind_param('ssssi', $new_name, $new_title, $new_location, $new_about, $user_id);
        if ($stmt->execute()) {
            $_SESSION['name'] = $new_name;
            $update_message = 'Profile updated successfully!';
        } else {
            $update_message = 'Error updating profile.';
        }
        $stmt->close();
        
        // Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $image_tmp_path = $_FILES['avatar']['tmp_name'];
            $image_mime_type = mime_content_type($image_tmp_path);
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($image_mime_type, $allowed_types)) {
                $avatar_data = file_get_contents($image_tmp_path);
                $stmt_avatar = $conn->prepare("UPDATE users SET avatar = ?, avatar_mime_type = ? WHERE id = ?");
                $stmt_avatar->bind_param("ssi", $avatar_data, $image_mime_type, $user_id);
                $stmt_avatar->execute();
                $stmt_avatar->close();
            }
        }
    }
    
    // Refresh the page to show all changes
    header("Location: profile.php");
    exit;
}

// Fetch user details, including the new avatar fields
$result = $conn->query("SELECT name, email, title, location, about, avatar, avatar_mime_type FROM users WHERE id = $user_id");
$user = $result->fetch_assoc();

// Get followers count
$res = $conn->query("SELECT COUNT(*) as cnt FROM followers WHERE user_id = $user_id");
$followers = $res ? $res->fetch_assoc()['cnt'] : 0;

// Get following count
$res = $conn->query("SELECT COUNT(*) as cnt FROM followers WHERE follower_id = $user_id");
$following = $res ? $res->fetch_assoc()['cnt'] : 0;

// Get uploads count
$res = $conn->query("SELECT COUNT(*) as cnt FROM uploads WHERE user_id = $user_id");
$uploads = $res ? $res->fetch_assoc()['cnt'] : 0;

$conn->close();
$is_following = false;
if ($user_id != $_SESSION['id']) {
    $conn2 = new mysqli('localhost', 'root', 'Deyant@786', 'studytogether_db');
    $stmt = $conn2->prepare("SELECT 1 FROM followers WHERE user_id = ? AND follower_id = ?");
    $stmt->bind_param('ii', $user_id, $_SESSION['id']);
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
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script>
    function resetProfileForm() {
        document.getElementById('name').value = '<?php echo htmlspecialchars($user['name']); ?>';
        document.getElementById('title').value = '<?php echo htmlspecialchars($user['title'] ?? ''); ?>';
        document.getElementById('location').value = '<?php echo htmlspecialchars($user['location'] ?? ''); ?>';
        document.getElementById('about').value = '<?php echo htmlspecialchars($user['about'] ?? ''); ?>';
    }
    </script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        <main class="profile-main-container">
            <form class="profile-main" method="POST" enctype="multipart/form-data">
                <div class="profile-left">
                    <div class="profile-avatar-lg js-lightbox-trigger" id="avatar-preview-container">
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="data:<?php echo $user['avatar_mime_type']; ?>;base64,<?php echo base64_encode($user['avatar']); ?>" alt="Avatar" id="avatar-preview-image">
                        <?php else: ?>
                            <span id="avatar-preview-initials"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name-lg"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="profile-title"><?php echo htmlspecialchars($user['title'] ?? ''); ?></div>
                    <div class="profile-stats">
                        <div class="profile-stat"><div class="profile-stat-num"><?php echo $followers; ?></div><div>Followers</div></div>
                        <div class="profile-stat"><div class="profile-stat-num"><?php echo $following; ?></div><div>Following</div></div>
                        <div class="profile-stat"><div class="profile-stat-num"><?php echo $uploads; ?></div><div>Uploads</div></div>
                    </div>
                    <label for="avatar-upload" class="profile-upload-btn">Upload new avatar</label>
                    <input type="file" id="avatar-upload" name="avatar" style="display: none;" accept="image/*">
                    <?php if (!empty($user['avatar'])): ?>
                        <button type="submit" name="remove_avatar" class="profile-remove-btn">Remove picture</button>
                    <?php endif; ?>
                    <div class="profile-location"><?php echo htmlspecialchars($user['location'] ?? ''); ?></div>
                    <div class="profile-about"><?php echo htmlspecialchars($user['about'] ?? ''); ?></div>
                </div>
                <div class="profile-right">
                    <?php if ($update_message): ?>
                        <div class="profile-update-message"><?php echo $update_message; ?></div>
                    <?php endif; ?>
                    <div class="profile-form-group">
                        <label class="profile-form-label" for="name">Name</label>
                        <input type="text" class="profile-form-input" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>">
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-form-label" for="title">Title</label>
                        <input type="text" class="profile-form-input" id="title" name="title" value="<?php echo htmlspecialchars($user['title'] ?? ''); ?>">
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-form-label" for="location">Location</label>
                        <input type="text" class="profile-form-input" id="location" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-form-label" for="about">About Me</label>
                        <textarea class="profile-form-textarea" id="about" name="about"><?php echo htmlspecialchars($user['about'] ?? ''); ?></textarea>
                    </div>
                    <div class="profile-form-group">
                        <label class="profile-form-label" for="email">Email</label>
                        <input type="email" class="profile-form-input" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                    <div class="profile-form-actions">
                        <button type="button" class="profile-form-btn cancel" onclick="resetProfileForm()">Cancel</button>
                        <button type="submit" class="profile-form-btn">Save</button>
                    </div>
                </div>
            </form>
        </main>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const avatarUploadInput = document.getElementById('avatar-upload');
    const avatarPreviewContainer = document.getElementById('avatar-preview-container');

    avatarUploadInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Clear the container (removes initial span)
                avatarPreviewContainer.innerHTML = '';
                // Create and append the new image preview
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = 'Avatar Preview';
                img.id = 'avatar-preview-image';
                avatarPreviewContainer.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    });

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
<?php include 'lightbox.php'; ?>
</body>
</html> 