<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch current admin info
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    die("Admin not found.");
}

$success = $error = "";

// Update profile
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_role = $_POST['role'];
    $new_status = $_POST['is_active'];

    if ($new_username === "" || $new_email === "") {
        $error = "All fields are required.";
    } else {

        // Update statement
        $update = $conn->prepare("
            UPDATE admin 
            SET username = ?, email = ?, role = ?, is_active = ?
            WHERE admin_id = ?
        ");
        $update->bind_param("sssii", $new_username, $new_email, $new_role, $new_status, $admin_id);

        if ($update->execute()) {
            $success = "Profile updated successfully!";
            $_SESSION['admin_username'] = $new_username; // Optional update for UI
        } else {
            $error = "Error updating profile.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow p-4">

        <h3 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Profile</h3>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Username</label>
                <input name="username" type="text" class="form-control" value="<?= $admin['username']; ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" value="<?= $admin['email']; ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                    <option value="admin" <?= $admin['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="moderator" <?= $admin['role'] == 'moderator' ? 'selected' : '' ?>>Moderator</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="form-label">Account Status</label>
                <select name="is_active" class="form-select">
                    <option value="1" <?= $admin['is_active'] ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= !$admin['is_active'] ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <button class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
            <a href="profile.php" class="btn btn-secondary">Cancel</a>

        </form>

    </div>
</div>

</body>
</html>
