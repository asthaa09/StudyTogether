<?php
// Get the current page name
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch avatar for the sidebar
$sidebar_avatar = null;
$sidebar_avatar_mime = null;
if (isset($_SESSION['id'])) {
    $conn_sidebar = new mysqli('localhost', 'root', '', 'studytogether_db');
    if (!$conn_sidebar->connect_error) {
        $sidebar_user_id = $_SESSION['id'];
        $res_sidebar = $conn_sidebar->query("SELECT avatar, avatar_mime_type FROM users WHERE id = $sidebar_user_id");
        if ($res_sidebar && $row = $res_sidebar->fetch_assoc()) {
            $sidebar_avatar = $row['avatar'];
            $sidebar_avatar_mime = $row['avatar_mime_type'];
        }
        $conn_sidebar->close();
    }
}
?>
<aside class="sidebar modern-sidebar">
    <div class="sidebar-user-section">
        <div class="sidebar-avatar js-lightbox-trigger">
            <?php if (!empty($sidebar_avatar)): ?>
                <img src="data:<?php echo $sidebar_avatar_mime; ?>;base64,<?php echo base64_encode($sidebar_avatar); ?>" alt="Avatar">
            <?php else: ?>
                <span><?php echo strtoupper(substr($_SESSION['name'], 0, 1)); ?></span>
            <?php endif; ?>
        </div>
        <div class="sidebar-username"><?php echo htmlspecialchars($_SESSION['name']); ?></div>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">NOTES</div>
        <a href="dashboard.php" class="nav-item <?php if ($current_page == 'dashboard.php') echo 'active'; ?>"><i class="fa-solid fa-house"></i> Dashboard</a>
        <a href="my_notes.php" class="nav-item <?php if ($current_page == 'my_notes.php') echo 'active'; ?>"><i class="fa-solid fa-note-sticky"></i> My Notes</a>
        <a href="upload.php" class="nav-item <?php if ($current_page == 'upload.php') echo 'active'; ?>"><i class="fa-solid fa-upload"></i> Upload</a>
        <div class="sidebar-section-label">COMMUNITY</div>
        <a href="friends.php" class="nav-item <?php if ($current_page == 'friends.php') echo 'active'; ?>"><i class="fa-solid fa-user-group"></i> Friends</a>
        <a href="messages.php" class="nav-item <?php if ($current_page == 'messages.php') echo 'active'; ?>"><i class="fa-solid fa-comments"></i> Messages</a>
        <div class="sidebar-section-label">SETTINGS</div>
        <a href="profile.php" class="nav-item <?php if ($current_page == 'profile.php') echo 'active'; ?>"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="logout.php" class="nav-item logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </nav>
</aside> 