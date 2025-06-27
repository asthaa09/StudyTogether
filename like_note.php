<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['id'];
$upload_id = isset($_POST['upload_id']) ? intval($_POST['upload_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($action === 'like') {
    $stmt = $conn->prepare("INSERT IGNORE INTO likes (user_id, upload_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $user_id, $upload_id);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'unlike') {
    $stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND upload_id = ?");
    $stmt->bind_param('ii', $user_id, $upload_id);
    $stmt->execute();
    $stmt->close();
}

// Get updated like count
$stmt = $conn->prepare("SELECT COUNT(*) FROM likes WHERE upload_id = ?");
$stmt->bind_param('i', $upload_id);
$stmt->execute();
$stmt->bind_result($like_count);
$stmt->fetch();
$stmt->close();
$conn->close();
echo json_encode(['success' => true, 'like_count' => $like_count]);
?>