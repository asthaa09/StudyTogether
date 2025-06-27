<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['tmp_name']) {
    $user_id = $_SESSION['id'];
    $file_name = basename($_FILES['file']['name']);
    $description = trim($_POST['description'] ?? '');
    $file_data = file_get_contents($_FILES['file']['tmp_name']);
    $conn = new mysqli('localhost', 'root', '', 'studytogether_db');
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }
    $stmt = $conn->prepare('INSERT INTO uploads (user_id, file_name, description, file_data) VALUES (?, ?, ?, ?)');
    $null = NULL;
    $stmt->bind_param('issb', $user_id, $file_name, $description, $null);
    $stmt->send_long_data(3, $file_data);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'File uploaded successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload failed.']);
    }
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
} 