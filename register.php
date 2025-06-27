<?php
// Database configuration
$servername = "localhost";
$username = "root"; // Default XAMPP username
$password = ''; // Default XAMPP password
$dbname = "studytogether_db";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql_create_db = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql_create_db) === TRUE) {
    // Select the database
    $conn->select_db($dbname);
} else {
    die("Error creating database: " . $conn->error);
}

// Create table if it doesn't exist
$sql_create_table = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($sql_create_table)) {
    die("Error creating table: " . $conn->error);
}

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $plain_password = $_POST['password'];

    // Validate input
    if (empty($name) || empty($email) || empty($plain_password)) {
        die("Please fill all fields.");
    }

    // Step 1: Check if email already exists
    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        // Email already exists
        echo "This email address is already registered. Please <a href='login.html'>login</a>.";
        $stmt_check->close();
        $conn->close();
        exit(); // Stop the script
    }
    $stmt_check->close();

    // Step 2: If email doesn't exist, proceed with insertion
    // Hash the password for security
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

    // Prepare and bind statement to prevent SQL injection
    $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt_insert->bind_param("sss", $name, $email, $hashed_password);

    // Execute the statement
    if ($stmt_insert->execute()) {
        // Registration successful, redirect to login page
        header("Location: login.html?registration=success");
        exit();
    } else {
        echo "Error during registration: " . $stmt_insert->error;
    }

    $stmt_insert->close();
}

$conn->close();
?> 