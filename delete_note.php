<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $note_id = intval($_POST['id']);
    $user_id = $_SESSION['id'];
    $conn = new mysqli('localhost', 'root', '', 'studytogether_db');
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }
    // Only delete if the note belongs to the logged-in user
    $stmt = $conn->prepare('DELETE FROM uploads WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $note_id, $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
header('Location: my_notes.php');
exit; 