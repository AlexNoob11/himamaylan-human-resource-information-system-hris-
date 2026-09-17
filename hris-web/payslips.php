<?php
ob_start();
session_start();
require_once 'conn.php';

// Redirect if not admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch employees for filter dropdown
$employeesResult = $conn->query("SELECT id, first_name, middle_initial, last_name FROM users ORDER BY first_name ASC");
$employees = [];
while ($row = $employeesResult->fetch_assoc()) {
    $employees[] = $row;
}

// Fetch departments for filter dropdown
$departmentsResult = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name ASC");
$departments = [];
while ($row = $departmentsResult->fetch_assoc()) {
    $departments[] = $row;
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM payslips WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Payslip deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete payslip: " . $stmt->error;
    }
    $stmt->close();
    header("Location: payslips.php");
    exit();
}

// Build query with filters
$where = [];
$params = [];
$paramTypes = '';

if (!empty($_GET['employee_id'])) {
    $where[] = 'p.user_id = ?';
    $params[] = $_GET['employee_id'];
    $paramTypes .= 'i';
}

if (!empty($_GET['department'])) {
    $where[] = 'u.department_id = ?';
    $params[] = $_GET['department'];
    $paramTypes .= 'i';
}

if (!empty($_GET['start_date'])) {
    $where[] = 'p.pay_period_start >= ?';
    $params[] = $_GET['start_date'];
    $paramTypes .= 's';
}

if (!empty($_GET['end_date'])) {
    $where[] = 'p.pay_period_end <= ?';
    $params[] = $_GET['end_date'];
    $paramTypes .= 's';
}

// Join users and departments to get department_name
$query = "SELECT p.id, u.first_name, u.middle_initial, u.last_name, d.department_name,
          p.pay_period_start, p.pay_period_end, p.gross_pay, p.net_pay, p.payment_date
          FROM payslips p
          JOIN users u ON p.user_id = u.id
          LEFT JOIN departments d ON u.department_id = d.id";

if ($where) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY p.payment_date DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payslips - HR System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include 'theme/navbar.php'; ?>
<?php include 'theme/sidebar.php'; ?>

<main id="main" class="main">
<div class="pagetitle">
<h1>Payslips</h1>
<nav>
<ol class="breadcrumb">
<li class="breadcrumb-item"><a href="index.php">Home</a></li>
<li class="breadcrumb-item active">Payslips</li>
</ol>
</nav>
</div>

<?php if (isset($_SESSION['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['success']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['success']); endif; ?>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['error']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['error']); endif; ?>

<section class="section">
<div class="card mb-4">
<div class="card-body">
<h5 class="card-title">Filter Payslips</h5>
<form method="GET" class="row g-3">
    <div class="col-md-3">
        <label class="form-label">Employee</label>
        <select name="employee_id" class="form-select">
            <option value="">All Employees</option>
            <?php foreach($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= (isset($_GET['employee_id']) && $_GET['employee_id']==$emp['id'])?'selected':'' ?>>
                <?= htmlspecialchars($emp['first_name'].' '.($emp['middle_initial'] ?? '').' '.$emp['last_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Department</label>
        <select name="department" class="form-select">
            <option value="">All Departments</option>
            <?php foreach($departments as $dept): ?>
            <option value="<?= $dept['id'] ?>" <?= (isset($_GET['department']) && $_GET['department']==$dept['id'])?'selected':'' ?>>
                <?= htmlspecialchars($dept['department_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= $_GET['start_date'] ?? '' ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label">End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= $_GET['end_date'] ?? '' ?>">
    </div>
    <div class="col-md-2 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="payslips.php" class="btn btn-secondary">Reset</a>
    </div>
</form>
</div>
</div>

<div class="card">
<div class="card-body">
<h5 class="card-title d-flex justify-content-between">
    Generated Payslips
    <a href="print_all_payslips.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-success">
        <i class="bi bi-printer"></i> Print All
    </a>
</h5>

<table class="table table-striped table-hover">
    <thead>
        <tr>
            <th>#</th>
            <th>Employee</th>
            <th>Department</th>
            <th>Pay Period</th>
            <th>Gross Pay</th>
            <th>Net Pay</th>
            <th>Payment Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($result && $result->num_rows > 0): ?>
        <?php $count = 1; while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= $count++ ?></td>
            <td><?= htmlspecialchars($row['first_name'].' '.($row['middle_initial'] ?? '').' '.$row['last_name']) ?></td>
            <td><?= htmlspecialchars($row['department_name'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($row['pay_period_start'].' to '.$row['pay_period_end']) ?></td>
            <td>₱<?= number_format($row['gross_pay'], 2) ?></td>
            <td>₱<?= number_format($row['net_pay'], 2) ?></td>
            <td><?= htmlspecialchars($row['payment_date']) ?></td>
            <td>
                <a href="view_payslip.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
            <td colspan="8" class="text-center">No payslips found.</td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

</div>
</div>
</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
