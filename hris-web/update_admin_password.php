<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['admin_id'])) {
    exit("<div class='alert alert-danger'>Unauthorized access.</div>");
}

$admin_id = $_SESSION['admin_id'];

$old_pass = $_POST['old_password'];
$new_pass = $_POST['new_password'];
$confirm_pass = $_POST['confirm_password'];

$stmt = $conn->prepare("SELECT password_hash FROM admin WHERE admin_id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!password_verify($old_pass, $data['password_hash'])) {
    exit("<div class='alert alert-danger'>Old password is incorrect.</div>");
}

if (strlen($new_pass) < 8) {
    exit("<div class='alert alert-danger'>New password must be at least 8 characters.</div>");
}

if ($new_pass !== $confirm_pass) {
    exit("<div class='alert alert-danger'>Passwords do not match.</div>");
}

$new_hash = password_hash($new_pass, PASSWORD_BCRYPT);

$update = $conn->prepare("UPDATE admin SET password_hash=? WHERE admin_id=?");
$update->bind_param("si", $new_hash, $admin_id);

if ($update->execute()) {
    echo "<div class='alert alert-success'>Password updated successfully!</div>";
} else {
    echo "<div class='alert alert-danger'>Failed to update password.</div>";
}
?>
