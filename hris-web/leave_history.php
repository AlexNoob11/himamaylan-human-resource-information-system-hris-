<?php
session_start();
require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$user_id = isset($_GET['employee']) ? intval($_GET['employee']) : 0;

// Fetch employee info
$user_query = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

if (!$user_result) {
    echo "Employee not found.";
    exit;
}

// Fetch leave records
$leave_query = $conn->prepare("
    SELECT leave_type, start_date, end_date, comments, status, date_filed 
    FROM leave_requests 
    WHERE user_id = ? AND YEAR(start_date) = ?
    ORDER BY start_date DESC
");
$leave_query->bind_param("ii", $user_id, $current_year);
$leave_query->execute();
$leaves = $leave_query->get_result();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>Dashboard - Human Resource Information System of UPC BioRnergy</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Leave History</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="leave_monitoring.php">Leave Monitoring</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($user_result['first_name'] . ' ' . $user_result['last_name']) ?></li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body pt-4">
                <h5>Employee: <?= htmlspecialchars($user_result['first_name'] . ' ' . $user_result['last_name']) ?> (<?= htmlspecialchars($user_result['email']) ?>)</h5>
                <h6>Year: <?= $current_year ?></h6>
                <table class="table datatable mt-3">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Total Days</th>
                            <th>Comments</th>
                            <th>Status</th>
                            <th>Date Filed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($leave = $leaves->fetch_assoc()) { 
                            $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60*60*24) + 1;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($leave['leave_type']) ?></td>
                            <td><?= htmlspecialchars($leave['start_date']) ?></td>
                            <td><?= htmlspecialchars($leave['end_date']) ?></td>
                            <td class="text-center"><?= $days ?></td>
                            <td><?= htmlspecialchars($leave['comments']) ?></td>
                            <td>
                                <?php if ($leave['status'] == 'Approved') { ?>
                                    <span class="badge bg-success"><?= $leave['status'] ?></span>
                                <?php } elseif ($leave['status'] == 'Pending') { ?>
                                    <span class="badge bg-warning"><?= $leave['status'] ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-danger"><?= $leave['status'] ?></span>
                                <?php } ?>
                            </td>
                            <td><?= htmlspecialchars($leave['date_filed']) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <a href="leave_monitoring.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left"></i> Back to Leave Monitoring</a>
            </div>
        </div>
    </section>
</main>

<!-- DataTables JS -->
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script>
  new simpleDatatables.DataTable(".datatable");
</script>

<!-- Bootstrap validation (if needed for forms/modals later) -->
<script>
(function () {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
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
