<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['admin_id'])) {
    exit("<div class='alert alert-danger'>Unauthorized access.</div>");
}

$admin_id = $_SESSION['admin_id'];

$username = trim($_POST['username']);
$email    = trim($_POST['email']);
$role     = $_POST['role'];
$active   = $_POST['is_active'];

if ($username === "" || $email === "") {
    exit("<div class='alert alert-danger'>All fields are required.</div>");
}

$stmt = $conn->prepare("UPDATE admin SET username=?, email=?, role=?, is_active=? WHERE admin_id=?");
$stmt->bind_param("sssii", $username, $email, $role, $active, $admin_id);

if ($stmt->execute()) {
    echo "<div class='alert alert-success'>Profile updated successfully!</div>";
} else {
    echo "<div class='alert alert-danger'>Failed to update profile.</div>";
}
