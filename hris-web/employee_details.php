<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'upc');

// Include navbar/sidebar if needed
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

$employee = null;
$error = '';

// Get employee ID from GET
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $error = "Invalid Employee ID.";
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $error = "Connection failed: " . $conn->connect_error;
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $employee = $result->fetch_assoc();
        } else {
            $error = "Employee not found.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Details - UPC BioEnergy HRIS</title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<main id="main" class="main">
  <div class="pagetitle">
    <h1>Employee Details</h1>
  </div>
  <section class="section">
    <div class="row">
      <div class="col-lg-8">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <a href="employees.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        <?php elseif ($employee): ?>
            <div class="card">
                <div class="card-header">
                    <h5><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['middle_initial'] . ' ' . $employee['last_name']); ?></h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr><th>Email</th><td><?php echo htmlspecialchars($employee['email']); ?></td></tr>
                        <tr><th>Phone Number</th><td><?php echo htmlspecialchars($employee['phone_number']); ?></td></tr>
                        <tr><th>Employee Type</th><td><?php echo htmlspecialchars($employee['employee_type']); ?></td></tr>
                        <tr><th>Department</th><td><?php echo htmlspecialchars($employee['department']); ?></td></tr>
                        <tr><th>Civil Status</th><td><?php echo htmlspecialchars($employee['civil_status']); ?></td></tr>
                        <tr><th>Date of Birth</th><td><?php echo htmlspecialchars($employee['date_of_birth']); ?></td></tr>
                        <tr><th>Age</th><td><?php echo htmlspecialchars($employee['age']); ?></td></tr>
                        <tr><th>Date Registered</th><td><?php echo htmlspecialchars($employee['date_registered']); ?></td></tr>
                        <tr><th>Address</th><td><?php echo htmlspecialchars($employee['address']); ?></td></tr>
                    </table>
                    <a href="employees.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
                </div>
            </div>
        <?php endif; ?>

      </div>
    </div>
  </section>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
