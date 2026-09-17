<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Check if ID is present
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid user ID.";
    header("Location: manage_employess.php");
    exit();
}

$id = (int)$_GET['id'];

// Fetch user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: manage_users.php");
    exit();
}

$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = $_POST['first_name'];
    $middle = $_POST['middle_initial'];
    $last = $_POST['last_name'];
    $email = $_POST['email'];

    $update = "UPDATE users SET first_name = ?, middle_initial = ?, last_name = ?, email = ? WHERE id = ?";
    $stmt = $conn->prepare($update);
    $stmt->bind_param("ssssi", $first, $middle, $last, $email, $id);
    $stmt->execute();

    $_SESSION['success'] = "User updated successfully.";
    header("Location: manage_users.php");
    exit();
}
?>

<!-- Simple HTML Form -->
<!DOCTYPE html>
<html>
<head><title>Edit User</title></head>
<body>
    <h2>Edit User</h2>
    <form method="POST">
        First Name: <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required><br>
        Middle Initial: <input type="text" name="middle_initial" value="<?= htmlspecialchars($user['middle_initial']) ?>"><br>
        Last Name: <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required><br>
        Email: <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><br>
        <button type="submit">Save</button>
        <a href="manage_users.php">Cancel</a>
    </form>
</body>
</html>
