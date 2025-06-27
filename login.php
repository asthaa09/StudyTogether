<?php
session_start(); // Start the session

// Database configuration
$servername = "localhost";
$username = "root";
$password = '';
$dbname = "studytogether_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Form Submission Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $plain_password = $_POST['password'];

    if (empty($email) || empty($plain_password)) {
        echo "Please fill in all fields.";
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $name, $hashed_password);
            $stmt->fetch();

            // Verify the password
            if (password_verify($plain_password, $hashed_password)) {
                // Password is correct, set session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $id;
                $_SESSION['name'] = $name;

                // Redirect to the user dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Incorrect password
                echo "Invalid email or password.";
            }
        } else {
            // No user found with that email
            echo "Invalid email or password.";
        }

        $stmt->close();
    }
}

$conn->close();
?> 