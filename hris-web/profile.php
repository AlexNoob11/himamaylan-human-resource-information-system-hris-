<?php
ob_start();
session_start();
require_once 'conn.php';

// Redirect if not admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Fetch admin details
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ? LIMIT 1");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    die("Admin not found.");
}

// Prepare data
$profile_data = [
    'full_name'   => htmlspecialchars($admin['username']),
    'username'    => htmlspecialchars($admin['username']),
    'email'       => htmlspecialchars($admin['email']),
    'role'        => htmlspecialchars(ucfirst($admin['role'])),
    'status'      => $admin['is_active'] ? 'Active' : 'Inactive',
    'member_since'=> date('F j, Y', strtotime($admin['created_at'])),
    'employee_id' => 'ADM-' . str_pad($admin['admin_id'], 3, '0', STR_PAD_LEFT),
    'description' => 'Welcome to your UPC BioEnergy HRIS Admin Account.',
    'skills'      => ['System Management', 'User Administration', 'Secure Access Control']
];

$initial = strtoupper(substr($profile_data['full_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Admin Profile - BioEnergy HR System</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    body { background-color: #f6f8fa; font-family: 'Inter', sans-serif; }
    .profile-header {
      background: #008CBA; color: #fff; padding: 60px 0 80px;
      text-align: center; border-radius: 0 0 20px 20px; position: relative;
    }
    .profile-avatar {
      width: 110px; height: 110px; border-radius: 50%;
      background: #fff; color: #008CBA; font-size: 2.8rem;
      display: flex; align-items: center; justify-content: center;
      position: absolute; left: 50%; bottom: -55px;
      transform: translateX(-50%); border: 5px solid #f6f8fa;
      font-weight: bold;
    }
    .profile-card {
      background: #fff; border-radius: 10px; padding: 25px;
      margin-bottom: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .info-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
    .info-item:last-child { border-bottom: none; }
    .skill-tag {
      display: inline-block; background: #e8f5fa;
      color: #0077a9; padding: 6px 12px;
      border-radius: 20px; font-size: 0.85rem; margin: 4px 6px 0 0;
    }
    .stat-card { text-align: center; background: #f9fafb; border-radius: 10px; padding: 15px; }
    .stat-number { font-weight: 700; font-size: 1.3rem; color: #008CBA; }
    .back-btn { position: absolute; top: 15px; left: 20px; }
  </style>
</head>

<body>

<?php include 'theme/navbar.php'; ?>
<?php include 'theme/sidebar.php'; ?>

<main id="main" class="main">

  <div class="profile-header">
    <a href="index.php" class="btn btn-light back-btn"><i class="bi bi-arrow-left"></i></a>
    <h1 class="fw-bold mb-2">Admin Profile</h1>
    <p>Manage your admin account and system settings</p>
    <div class="profile-avatar"><?= $initial; ?></div>
  </div>

  <section class="section mt-5 pt-4">
    <div class="container">
      <div class="row">

        <!-- LEFT -->
        <div class="col-lg-8">
          <div class="profile-card text-center">
            <h3 class="fw-bold mb-1"><?= $profile_data['full_name']; ?></h3>
            <p class="text-muted mb-0"><?= $profile_data['role']; ?></p>
            <p class="text-muted">Admin ID: <?= $profile_data['employee_id']; ?></p>

            <div class="row mt-4">
              <div class="col-md-4 mb-3"><div class="stat-card"><div class="stat-number">42</div><div class="text-muted small">Employees</div></div></div>
              <div class="col-md-4 mb-3"><div class="stat-card"><div class="stat-number">112</div><div class="text-muted small">Login Records</div></div></div>
              <div class="col-md-4 mb-3"><div class="stat-card"><div class="stat-number">100%</div><div class="text-muted small">Access Level</div></div></div>
            </div>
          </div>

          <div class="profile-card">
            <h5 class="mb-3"><i class="bi bi-info-circle me-2"></i>Account Information</h5>
            <div class="info-item"><span>Username</span><span><?= $profile_data['username']; ?></span></div>
            <div class="info-item"><span>Email</span><span><?= $profile_data['email']; ?></span></div>
            <div class="info-item"><span>Role</span><span><?= $profile_data['role']; ?></span></div>
            <div class="info-item"><span>Status</span><span class="badge bg-success"><?= $profile_data['status']; ?></span></div>
            <div class="info-item"><span>Member Since</span><span><?= $profile_data['member_since']; ?></span></div>
          </div>

          <div class="profile-card">
            <h5 class="mb-3"><i class="bi bi-file-text me-2"></i>About</h5>
            <p><?= $profile_data['description']; ?></p>
          </div>

          <div class="profile-card">
            <h5 class="mb-3"><i class="bi bi-tools me-2"></i>Admin Skills</h5>
            <?php foreach ($profile_data['skills'] as $skill): ?>
              <span class="skill-tag"><?= $skill; ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- RIGHT -->
        <div class="col-lg-4">
          <div class="profile-card">
            <h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
            <div class="d-grid gap-2 mt-3">
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="bi bi-gear me-2"></i>Edit Profile</button>
              <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#changePasswordModal"><i class="bi bi-shield-lock me-2"></i>Change Password</button>
              <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a>
            </div>
          </div>

          <div class="profile-card">
            <h5><i class="bi bi-shield-check me-2"></i>Security Status</h5>
            <div class="info-item"><span>Two-Factor Auth</span><span class="badge bg-warning text-dark">Not Enabled</span></div>
            <div class="info-item"><span>Password Strength</span><span class="badge bg-success">Strong</span></div>
            <div class="info-item"><span>Last Password Change</span><span>Unknown</span></div>
          </div>
        </div>

      </div>
    </div>
  </section>
</main>

<footer id="footer" class="footer text-center py-3">
  &copy; <?= date('Y'); ?> <strong>BioEnergy</strong>. All Rights Reserved
</footer>

<!-- ===================================== -->
<!-- EDIT PROFILE MODAL -->
<!-- ===================================== -->
<div class="modal fade" id="editProfileModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">

    <form id="editProfileForm">
      <div class="modal-header"><h5>Edit Admin Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="editProfileAlert"></div>

        <div class="mb-3">
          <label>Username</label>
          <input name="username" type="text" class="form-control" value="<?= $admin['username']; ?>">
        </div>

        <div class="mb-3">
          <label>Email</label>
          <input name="email" type="email" class="form-control" value="<?= $admin['email']; ?>">
        </div>

        <div class="mb-3">
          <label>Role</label>
          <select name="role" class="form-select">
            <option value="admin" <?= $admin['role']=="admin"?"selected":"" ?>>Admin</option>
            <option value="moderator" <?= $admin['role']=="moderator"?"selected":"" ?>>Moderator</option>
          </select>
        </div>

        <div class="mb-3">
          <label>Status</label>
          <select name="is_active" class="form-select">
            <option value="1" <?= $admin['is_active']?"selected":"" ?>>Active</option>
            <option value="0" <?= !$admin['is_active']?"selected":"" ?>>Inactive</option>
          </select>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Save Changes</button>
      </div>
    </form>

  </div></div>
</div>

<!-- ===================================== -->
<!-- CHANGE PASSWORD MODAL -->
<!-- ===================================== -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">

    <form id="changePasswordForm">
      <div class="modal-header"><h5>Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div id="changePasswordAlert"></div>

        <div class="mb-3">
          <label>Old Password</label>
          <input type="password" name="old_password" class="form-control">
        </div>

        <div class="mb-3">
          <label>New Password</label>
          <input type="password" name="new_password" class="form-control">
        </div>

        <div class="mb-3">
          <label>Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control">
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary">Update Password</button>
      </div>

    </form>

  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ======================
// EDIT PROFILE AJAX
// ======================
document.getElementById("editProfileForm").onsubmit = function(e) {
    e.preventDefault();
    
    fetch("update_admin_profile.php", {
        method: "POST",
        body: new FormData(this)
    }).then(res => res.text()).then(data => {
        document.getElementById("editProfileAlert").innerHTML = data;
        if (data.includes("success")) setTimeout(() => location.reload(), 1000);
    });
};

// ======================
// CHANGE PASSWORD AJAX
// ======================
document.getElementById("changePasswordForm").onsubmit = function(e) {
    e.preventDefault();

    fetch("update_admin_password.php", {
        method: "POST",
        body: new FormData(this)
    }).then(res => res.text()).then(data => {
        document.getElementById("changePasswordAlert").innerHTML = data;
        if (data.includes("success")) this.reset();
    });
};
</script>

</body>
</html>
