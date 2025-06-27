<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$follower_id = $_SESSION['id'];
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($user_id === 0 || $user_id === $follower_id) {
    echo json_encode(['error' => 'Invalid user']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($action === 'follow') {
    $stmt = $conn->prepare("INSERT IGNORE INTO followers (user_id, follower_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $user_id, $follower_id);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'unfollow') {
    $stmt = $conn->prepare("DELETE FROM followers WHERE user_id = ? AND follower_id = ?");
    $stmt->bind_param('ii', $user_id, $follower_id);
    $stmt->execute();
    $stmt->close();
}

// Get updated follower count
$stmt = $conn->prepare("SELECT COUNT(*) FROM followers WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($follower_count);
$stmt->fetch();
$stmt->close();

$conn->close();

echo json_encode(['success' => true, 'follower_count' => $follower_count]);
?>