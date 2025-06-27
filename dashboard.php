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

// --- Fetch recent uploads from ALL users, along with their names ---
$uploads = [];
$res = $conn->query("
    SELECT 
        up.id, 
        up.file_name, 
        up.uploaded_at, 
        up.description,
        usr.id as user_id,      -- The ID of the user who uploaded
        usr.name as user_name   -- The name of the user who uploaded
    FROM uploads up
    JOIN users usr ON up.user_id = usr.id
    ORDER BY up.uploaded_at DESC 
    LIMIT 20
");
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
    <title>Dashboard - StudyTogether</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        <main class="dashboard-main">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h1>
            <p>Here are the latest notes from the community.</p>

            <div class="search-bar-wrapper">
                <input type="text" class="search-bar" id="dashboard-search" placeholder="Search notes...">
                <button id="dashboard-search-btn" class="search-btn"><i class="fa fa-search"></i></button>
            </div>
            <h2>Recent Uploads</h2>
            <?php if (empty($uploads)): ?>
                <p>No files have been uploaded by anyone yet.</p>
            <?php else: ?>
                <div class="notes-grid">
                    <?php foreach ($uploads as $file): ?>
                        <?php
                        // --- Fetch Like Data for this Note ---
                        $conn2 = new mysqli('localhost', 'root', '', 'studytogether_db');
                        $note_id = $file['id'];
                        
                        $like_count = 0;
                        $res2 = $conn2->query("SELECT COUNT(*) as cnt FROM likes WHERE upload_id = $note_id");
                        if ($res2) $like_count = $res2->fetch_assoc()['cnt'];

                        $liked_by_user = false;
                        $res3 = $conn2->query("SELECT 1 FROM likes WHERE upload_id = $note_id AND user_id = $user_id");
                        if ($res3 && $res3->num_rows > 0) $liked_by_user = true;
                        
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
                                <a href="view_profile.php?id=<?php echo $file['user_id']; ?>" title="View Profile">
                                    <i class="fa-regular fa-user"></i>
                                </a>
                                <a href="download.php?id=<?php echo $file['id']; ?>" title="Download">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <!-- Profile Modal (Right Side) -->
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
            const uploadId = this.getAttribute('data-upload-id');
            const icon = this.querySelector('i');
            const likeCountSpan = this.querySelector('.like-count');
            let currentLikes = parseInt(likeCountSpan.textContent);
            const button = this;

            const isLiked = button.classList.toggle('liked');

            if (isLiked) {
                likeCountSpan.textContent = currentLikes + 1;
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
            } else {
                likeCountSpan.textContent = currentLikes - 1;
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'like_note.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status !== 200) {
                    // Revert UI on error
                    button.classList.toggle('liked');
                    likeCountSpan.textContent = currentLikes; // Revert count
                    icon.classList.toggle('fa-solid');
                    icon.classList.toggle('fa-regular');
                }
            };
            xhr.send('upload_id=' + encodeURIComponent(uploadId));
        });
    });
});
</script>
<script>
function filterDashboardNotes() {
    const val = document.getElementById('dashboard-search').value.toLowerCase();
    document.querySelectorAll('.notes-grid .note-card').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
}
document.getElementById('dashboard-search').addEventListener('input', filterDashboardNotes);
document.getElementById('dashboard-search-btn').addEventListener('click', function(e) {
    e.preventDefault();
    filterDashboardNotes();
    document.getElementById('dashboard-search').focus();
});
</script>
</body>
</html> 