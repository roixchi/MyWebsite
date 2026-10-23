<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if (!empty($name) && !empty($email)) {
        // Secure prepared statement to prevent SQL Injection
        $stmt = $pdo->prepare("INSERT INTO users (name, email) VALUES (?, ?)");
        if ($stmt->execute([$name, $email])) {
            header("Location: index.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Add New User</h2>
        <form method="POST" action="create.php">
            <input type="text" name="name" placeholder="Enter Full Name" required>
            <input type="email" name="email" placeholder="Enter Email Address" required>
            <button type="submit">Submit</button>
            <a href="index.php" class="btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>

