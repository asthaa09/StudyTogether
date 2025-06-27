<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
$user_id = $_SESSION['id'];
$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
$uploads = [];
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$res = $conn->query("SELECT id, file_name, uploaded_at, description FROM uploads WHERE user_id = $user_id ORDER BY uploaded_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $uploads[] = $row;
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notes - StudyTogether</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="my_notes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        <main class="dashboard-main">
            <h1>My Notes</h1>
            <div class="my-notes-description">View, manage, and organize all your uploaded notes here. You can like, download, or delete your notes anytime.</div>
            <?php if (empty($uploads)): ?>
                <p>You have not uploaded any files yet.</p>
            <?php else: ?>
                <div class="notes-grid">
                    <?php foreach ($uploads as $file): ?>
                        <?php
                        $note_id = $file['id'];
                        $like_count = 0;
                        $liked_by_user = false;
                        $conn2 = new mysqli('localhost', 'root', '', 'studytogether_db');
                        $res2 = $conn2->query("SELECT COUNT(*) as cnt FROM likes WHERE upload_id = $note_id");
                        if ($res2) {
                            $like_count = $res2->fetch_assoc()['cnt'];
                        }
                        $res3 = $conn2->query("SELECT 1 FROM likes WHERE upload_id = $note_id AND user_id = $user_id");
                        if ($res3 && $res3->num_rows > 0) {
                            $liked_by_user = true;
                        }
                        $conn2->close();
                        ?>
                        <div class="note-card">
                            <div class="note-card-content">
                                <i class="fa-solid fa-file-lines note-icon"></i>
                                <div class="note-filename"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                <div class="note-description"><?php echo htmlspecialchars($file['description'] ?? ''); ?></div>
                            </div>

                            <div class="note-actions">
                                <button class="like-btn <?php echo $liked_by_user ? 'liked' : ''; ?>" data-upload-id="<?php echo $file['id']; ?>">
                                    <i class="<?php echo $liked_by_user ? 'fa-solid' : 'fa-regular'; ?> fa-heart"></i>
                                    <span class="like-count"><?php echo $like_count; ?></span>
                                </button>
                                <a href="download.php?id=<?php echo $file['id']; ?>" title="Download">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                                <form method="POST" action="delete_note.php" style="display: contents;" onsubmit="return confirm('Are you sure you want to delete this note?');">
                                    <input type="hidden" name="note_id" value="<?php echo $file['id']; ?>">
                                    <button type="submit" title="Delete" class="delete-btn">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <!-- Profile Modal -->
    <div id="profileModalOverlay" onclick="closeProfileModal()"></div>
    <div id="profileModal">
        <button onclick="closeProfileModal()">&times;</button>
        <div id="profileModalBody">
            Loading...
        </div>
    </div>
    <script>
    function openProfileModal(userId) {
        document.getElementById('profileModal').style.display = 'block';
        document.getElementById('profileModalOverlay').style.display = 'block';
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'profile_view.php?user_id=' + encodeURIComponent(userId), true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                document.getElementById('profileModalBody').innerHTML = xhr.responseText;
            } else {
                document.getElementById('profileModalBody').innerHTML = 'Error loading profile.';
            }
        };
        xhr.send();
    }
    function closeProfileModal() {
        document.getElementById('profileModal').style.display = 'none';
        document.getElementById('profileModalOverlay').style.display = 'none';
    }
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.open-profile-modal').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                openProfileModal(this.getAttribute('data-user-id'));
            });
        });
    });
    </script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.like-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var uploadId = this.getAttribute('data-upload-id');
            var icon = this.querySelector('i');
            var likeCountSpan = this.querySelector('.like-count');
            var liked = icon.classList.contains('fa-solid');
            var action = liked ? 'unlike' : 'like';
            var button = this;

            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'like_note.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        if (action === 'like') {
                            icon.classList.remove('fa-regular');
                            icon.classList.add('fa-solid');
                            button.style.color = '#e57373';
                        } else {
                            icon.classList.remove('fa-solid');
                            icon.classList.add('fa-regular');
                            button.style.color = '#888';
                        }
                        likeCountSpan.textContent = res.like_count;

                        // Update all like buttons for this note on the page (dashboard & my_notes)
                        document.querySelectorAll('.like-btn[data-upload-id="' + uploadId + '"]').forEach(function(otherBtn) {
                            if (otherBtn !== button) {
                                var otherIcon = otherBtn.querySelector('i');
                                var otherLikeCountSpan = otherBtn.querySelector('.like-count');
                                if (action === 'like') {
                                    otherIcon.classList.remove('fa-regular');
                                    otherIcon.classList.add('fa-solid');
                                    otherBtn.style.color = '#e57373';
                                } else {
                                    otherIcon.classList.remove('fa-solid');
                                    otherIcon.classList.add('fa-regular');
                                    otherBtn.style.color = '#888';
                                }
                                otherLikeCountSpan.textContent = res.like_count;
                            }
                        });
                    }
                }
            };
            xhr.send('upload_id=' + encodeURIComponent(uploadId) + '&action=' + encodeURIComponent(action));
        });
    });
});
</script>
</body>
</html> 