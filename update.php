
This script uses a target URL parameter ID to retrieve single-row parameters (SELECT), fills out a sticky form element matching that record, and updates the target row upon form submission.

<?php
require 'db.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];

// Fetch user data to populate the form fields
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: index.php");
    exit;
}

// Process update request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);

    if (!empty($name) && !empty($email)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, $id])) {
            header("Location: index.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Update User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>Modify User Record</h2>
        <form method="POST">
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            <button type="submit">Update Record</button>
            <a href="index.php" class="btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
