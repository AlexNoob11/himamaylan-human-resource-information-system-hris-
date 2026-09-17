<?php
ob_start();
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'upc');

require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

$departmentList = [];
$message = '';
$error = '';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);

    /**********************************************
     * ADD DEPARTMENT
     **********************************************/
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Add new department
        if (isset($_POST['add_department'])) {
            $name = trim($_POST['department_name']);

            if ($name === '') {
                $error = "Department name cannot be empty.";
            } else {
                $stmt = $conn->prepare("INSERT INTO departments (department_name) VALUES (?)");
                $stmt->bind_param("s", $name);

                if ($stmt->execute()) {
                    $message = "✔️ Department added successfully!";
                } else {
                    $error = "❌ Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }

        // Toggle Status
        if (isset($_POST['toggle_id'])) {
            $id = intval($_POST['toggle_id']);
            $current = intval($_POST['current_status']);

            $newStatus = ($current == 1) ? 0 : 1;

            $stmt = $conn->prepare("UPDATE departments SET status = ? WHERE id = ?");
            $stmt->bind_param("ii", $newStatus, $id);
            $stmt->execute();
            $stmt->close();

            $message = ($newStatus == 1) ? "✔️ Department enabled again." : "✔️ Department disabled.";
        }
    }

    /**********************************************
     * FETCH DEPARTMENTS
     **********************************************/
    $result = $conn->query("SELECT * FROM departments ORDER BY department_name ASC");
    while ($row = $result->fetch_assoc()) {
        $departmentList[] = $row;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
} finally {
    if ($conn instanceof mysqli && $conn->ping()) $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Departments Management - HRIS</title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

<main id="main" class="main">

  <div class="pagetitle">
      <h1>Departments Management</h1>
  </div>

  <section class="section">

    <!-- Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body d-flex justify-content-between">
        <h5><i class="bi bi-building"></i> Department List</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
          <i class="bi bi-plus-circle"></i> Add Department
        </button>
      </div>
    </div>

    <!-- Table -->
    <?php if ($departmentList): ?>
    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th width="60">ID</th>
          <th>Department Name</th>
          <th>Status</th>
          <th width="160">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($departmentList as $row): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['department_name']) ?></td>

          <!-- Status Column -->
          <td>
              <?php if ($row['status'] == 1): ?>
                  <span class="badge bg-success">Active</span>
              <?php else: ?>
                  <span class="badge bg-secondary">Disabled</span>
              <?php endif; ?>
          </td>

          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="toggle_id" value="<?= $row['id'] ?>">
              <input type="hidden" name="current_status" value="<?= $row['status'] ?>">

              <?php if ($row['status'] == 1): ?>
                  <button type="submit" class="btn btn-warning btn-sm">
                    <i class="bi bi-slash-circle"></i> Disable
                  </button>
              <?php else: ?>
                  <button type="submit" class="btn btn-success btn-sm">
                    <i class="bi bi-check-circle"></i> Enable
                  </button>
              <?php endif; ?>
            </form>
          </td>

        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="alert alert-info">No departments found.</div>
    <?php endif; ?>

  </section>
</main>

<!-- Add Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="bi bi-building-add"></i> Add Department
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <label class="form-label">Department Name</label>
          <input type="text" name="department_name" class="form-control" required>
        </div>

        <div class="modal-footer">
          <button type="submit" name="add_department" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php ob_end_flush(); ?>
