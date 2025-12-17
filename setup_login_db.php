<?php
include 'config.php';

// Create admin_users table
$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table 'admin_users' created or already exists.<br>";
} else {
    die("Error creating table: " . $conn->error);
}

// Check if admin user exists
$checkUser = "SELECT * FROM admin_users WHERE username = 'admin'";
$result = $conn->query($checkUser);

if ($result->num_rows == 0) {
    // Create default admin user
    $username = "admin";
    $password = "admin123"; // Default password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);

    if ($stmt->execute()) {
        echo "Default admin user created successfully.<br>";
        echo "Username: <strong>admin</strong><br>";
        echo "Password: <strong>admin123</strong><br>";
    } else {
        echo "Error creating admin user: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "Admin user already exists.<br>";
}

$conn->close();
?>