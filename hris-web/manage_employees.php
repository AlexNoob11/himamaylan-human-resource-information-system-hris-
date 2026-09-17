<?php
// --- secure session start ---
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false, // set true if using HTTPS
    'cookie_samesite' => 'Strict'
]);

require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

// Redirect if admin not logged in
if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- fetch all users ---
$users = [];
if ($conn) {
    $res = $conn->query("SELECT * FROM users ORDER BY date_registered DESC");
    if ($res) $users = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Manage Employees - HRIS UPC BioRnergy</title>

  <!-- Bootstrap & vendor CSS -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">

  <!-- Bootstrap JS -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<main id="main" class="main">
  <div class="pagetitle">
    <h1>Manage Employees</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Manage Employees</li>
      </ol>
    </nav>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-body pt-4">

        <table class="table datatable">
          <thead>
            <tr>
              <th>ID</th>
              <th>First Name</th>
              <th>Middle Initial</th>
              <th>Last Name</th>
              <th>Email</th>
              <th>Date Registered</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['first_name']) ?></td>
                <td><?= htmlspecialchars($row['middle_initial']) ?></td>
                <td><?= htmlspecialchars($row['last_name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['date_registered']) ?></td>
                <td>
                  <!-- Edit Modal trigger -->
                  <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>">
                    Edit
                  </button>
                  <a href="delete_user.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                </td>
              </tr>

              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $row['id'] ?>" aria-hidden="true">
                <div class="modal-dialog">
                  <form method="post" action="manage_users.php" class="needs-validation" novalidate>
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?= $row['id'] ?>">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="user_id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                          <label for="firstName<?= $row['id'] ?>" class="form-label">First Name</label>
                          <input type="text" name="first_name" id="firstName<?= $row['id'] ?>" class="form-control" value="<?= htmlspecialchars($row['first_name']) ?>" required>
                          <div class="invalid-feedback">First name is required.</div>
                        </div>
                        <div class="mb-3">
                          <label for="middleInitial<?= $row['id'] ?>" class="form-label">Middle Initial</label>
                          <input type="text" name="middle_initial" id="middleInitial<?= $row['id'] ?>" class="form-control" value="<?= htmlspecialchars($row['middle_initial']) ?>">
                        </div>
                        <div class="mb-3">
                          <label for="lastName<?= $row['id'] ?>" class="form-label">Last Name</label>
                          <input type="text" name="last_name" id="lastName<?= $row['id'] ?>" class="form-control" value="<?= htmlspecialchars($row['last_name']) ?>" required>
                          <div class="invalid-feedback">Last name is required.</div>
                        </div>
                        <div class="mb-3">
                          <label for="email<?= $row['id'] ?>" class="form-label">Email</label>
                          <input type="email" name="email" id="email<?= $row['id'] ?>" class="form-control" value="<?= htmlspecialchars($row['email']) ?>" required>
                          <div class="invalid-feedback">Please enter a valid email.</div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="submit" name="update_user" class="btn btn-primary" onclick="return confirm('Save changes?')">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                      </div>
                    </div>
                  </form>
                </div>
              </div>

            <?php endforeach; ?>
          </tbody>
        </table>

      </div>
    </div>
  </section>
</main>

<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script>
  new simpleDatatables.DataTable(".datatable");

  // Bootstrap form validation
  (function () {
    'use strict'
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
      form.addEventListener('submit', event => {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  })();
</script>
</body>
</html>
