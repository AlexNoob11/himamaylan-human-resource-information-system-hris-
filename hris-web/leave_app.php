<?php
// --- secure session start ---
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => false,
    'cookie_samesite' => 'Strict'
]);

if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!$conn instanceof mysqli || $conn->connect_error) {
    error_log("DB connection failed: " . ($conn->connect_error ?? 'no object'));
    $_SESSION['error'] = "Database connection failed.";
    $conn = null;
}

// --- handle approve/decline ---
if (
    $conn &&
    isset($_POST['action'], $_POST['leave_id'], $_POST['csrf_token']) &&
    hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $leave_id = (int)$_POST['leave_id'];

    // map action string to a status value in your DB
    if ($_POST['action'] === 'approve') {
        $status = 'Approved';
    } elseif ($_POST['action'] === 'decline') {
        $status = 'Rejected';
    } else {
        $status = 'Pending';
    }

    $sql = "UPDATE leave_requests SET status=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("si", $status, $leave_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['success'] = "Leave request has been $status.";
        } else {
            $_SESSION['error'] = "No rows updated. Check the ID / column names.";
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Database error: " . $conn->error;
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// --- fetch all leave requests ---
$leaveRows = [];
if ($conn) {
    $sql = "
        SELECT lr.*, u.first_name, u.middle_initial, u.last_name, u.email
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        ORDER BY lr.date_filed DESC";
    $res = $conn->query($sql);
    if ($res) $leaveRows = $res->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Manage Leave Requests</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<main id="main" class="main">
  <div class="pagetitle">
    <h1>Manage Leave Requests</h1>
    <nav>
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active">Leave Requests</li>
      </ol>
    </nav>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-body pt-4">

        <?php if (isset($_SESSION['error'])): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
          <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <table class="table datatable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Employee</th>
              <th>Email</th>
              <th>Leave Type</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Comments</th>
              <th>Status</th>
              <th>Date Filed</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($leaveRows as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['id']) ?></td>
              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['middle_initial'] . ' ' . $row['last_name']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><?= htmlspecialchars($row['leave_type']) ?></td>
              <td><?= htmlspecialchars($row['start_date']) ?></td>
              <td><?= htmlspecialchars($row['end_date']) ?></td>
              <td><?= htmlspecialchars($row['comments']) ?></td>
              <td><?= htmlspecialchars($row['status']) ?></td>
              <td><?= htmlspecialchars($row['date_filed']) ?></td>
              <td>
  <?php if ($row['status'] === 'Pending'): ?>
    <!-- Approve Form -->
    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" style="display:inline-block;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
      <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"
        onclick="return confirm('Approve this leave request?')">Approve</button>
    </form>

    <!-- Decline Form -->
    <form method="post" action="<?= $_SERVER['PHP_SELF'] ?>" style="display:inline-block;">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="leave_id" value="<?= $row['id'] ?>">
      <button type="submit" name="action" value="decline" class="btn btn-danger btn-sm"
        onclick="return confirm('Decline this leave request?')">Decline</button>
    </form>
  <?php elseif ($row['status'] === 'Approved'): ?>
    <span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Approved</span>
  <?php elseif ($row['status'] === 'Rejected'): ?>
    <span class="text-danger fw-bold"><i class="bi bi-x-circle-fill"></i> Rejected</span>
  <?php endif; ?>

  <!-- Print Button (always visible) -->
  <a href="print_leave.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-secondary btn-sm">
    <i class="bi bi-printer"></i> Print
  </a>
</td>

            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script>
  new simpleDatatables.DataTable(".datatable");
</script>
</body>
</html>
