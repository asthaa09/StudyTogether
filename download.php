<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}

// Ensure the ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid request.');
}

// --- Correct Database Connection ---
$conn = new mysqli('localhost', 'root', '', 'studytogether_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = intval($_GET['id']);

// Fetch file from database
$stmt = $conn->prepare("SELECT file_name, file_data FROM uploads WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($file_name, $file_data);
    $stmt->fetch();

    // Set headers to trigger browser download
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"" . basename($file_name) . "\"");
    header("Content-Length: " . strlen($file_data));
    
    // Output the file data
    echo $file_data;

} else {
    echo "File not found.";
}

$stmt->close();
$conn->close();
exit; 