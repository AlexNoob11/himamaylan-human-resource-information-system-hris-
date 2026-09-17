<?php
require_once 'conn.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid payslip ID.");
}

$id = $_GET['id'];

// Fetch payslip with user and department details
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.middle_initial, u.last_name, d.department_name
                        FROM payslips p
                        JOIN users u ON p.user_id = u.id
                        LEFT JOIN departments d ON u.department_id = d.id
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$payslip = $result->fetch_assoc();

if (!$payslip) die("Payslip not found.");

// Set default values for missing fields
$payslip['transportation_allowance'] = $payslip['transportation_allowance'] ?? 0;
$payslip['meal_allowance'] = $payslip['meal_allowance'] ?? 0;
$payslip['housing_allowance'] = $payslip['housing_allowance'] ?? 0;
$payslip['gross_pay'] = $payslip['gross_pay'] ?? 0;
$payslip['tax'] = $payslip['tax'] ?? 0;
$payslip['sss'] = $payslip['sss'] ?? 0;
$payslip['philhealth'] = $payslip['philhealth'] ?? 0;
$payslip['pagibig'] = $payslip['pagibig'] ?? 0;
$payslip['other_deductions'] = $payslip['other_deductions'] ?? 0;
$payslip['net_pay'] = $payslip['net_pay'] ?? 0;
$payslip['remarks'] = $payslip['remarks'] ?? '';
$payslip['days_worked'] = $payslip['days_worked'] ?? 0;
$payslip['daily_rate'] = $payslip['daily_rate'] ?? 0;
$payslip['overtime_hours'] = $payslip['overtime_hours'] ?? 0;
$payslip['overtime_rate'] = $payslip['overtime_rate'] ?? 0;
$payslip['basic_salary'] = $payslip['basic_salary'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Payslip - UPC BioEnergy HRIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial, sans-serif; margin: 30px; color: #333; }
header { text-align: center; margin-bottom: 30px; }
header img { max-height: 80px; margin-bottom: 5px; }
header h1 { margin: 5px 0 0 0; font-size: 28px; }
header h2 { margin: 5px 0 0 0; font-size: 18px; font-weight: normal; }
.table td, .table th { vertical-align: middle; }
.total { font-weight: bold; background-color: #f8f9fa; }
.flex-table { display: flex; justify-content: space-between; }
.flex-table .table { width: 48%; }
button.print-btn { margin-bottom: 20px; }
@media print { button { display: none; } }
</style>
</head>
<body>

<header>
    <img src="assets/img/upc12.png" alt="UPC BioEnergy Logo">
    <h1>UPC BioEnergy</h1>
    <h2>Human Resource Information System (HRIS)</h2>
    <p><strong>Payslip for the Period:</strong> <?= htmlspecialchars($payslip['pay_period_start']) ?> to <?= htmlspecialchars($payslip['pay_period_end']) ?></p>
</header>

<div class="mb-3">
    <button class="btn btn-primary print-btn" onclick="window.print()">🖨 Print Payslip</button>
</div>

<table class="table table-bordered">
<tr><th colspan="2" class="text-center">Employee Information</th></tr>
<tr><td>Employee Name</td><td><?= htmlspecialchars($payslip['first_name'] . ' ' . ($payslip['middle_initial'] ?? '') . ' ' . $payslip['last_name']) ?></td></tr>
<tr><td>Department</td><td><?= htmlspecialchars($payslip['department_name'] ?? 'N/A') ?></td></tr>
<tr><td>Payment Date</td><td><?= htmlspecialchars($payslip['payment_date']) ?></td></tr>
<tr><td>Remarks</td><td><?= htmlspecialchars($payslip['remarks']) ?></td></tr>
</table>

<div class="flex-table">
    <!-- Earnings Table -->
    <table class="table table-bordered">
    <thead class="table-light text-center"><tr><th colspan="2">Earnings</th></tr></thead>
    <tbody>
        <tr><td>Basic Salary</td><td>₱<?= number_format($payslip['basic_salary'],2) ?></td></tr>
        <tr><td>Days Worked</td><td><?= htmlspecialchars($payslip['days_worked']) ?></td></tr>
        <tr><td>Daily Rate</td><td>₱<?= number_format($payslip['daily_rate'],2) ?></td></tr>
        <tr><td>Overtime Hours</td><td><?= htmlspecialchars($payslip['overtime_hours']) ?></td></tr>
        <tr><td>Overtime Rate</td><td>₱<?= number_format($payslip['overtime_rate'],2) ?></td></tr>
        <tr><td>Overtime Pay</td><td>₱<?= number_format($payslip['overtime_hours'] * $payslip['overtime_rate'],2) ?></td></tr>
        <tr class="total"><td>Gross Pay</td><td>₱<?= number_format($payslip['gross_pay'],2) ?></td></tr>
    </tbody>
    </table>

    <!-- Deductions Table -->
    <table class="table table-bordered">
    <thead class="table-light text-center"><tr><th colspan="2">Deductions</th></tr></thead>
    <tbody>
        <tr><td>SSS</td><td>₱<?= number_format($payslip['sss'],2) ?></td></tr>
        <tr><td>PhilHealth</td><td>₱<?= number_format($payslip['philhealth'],2) ?></td></tr>
        <tr><td>Pag-IBIG</td><td>₱<?= number_format($payslip['pagibig'],2) ?></td></tr>
        <tr><td>Other Deductions</td><td>₱<?= number_format($payslip['other_deductions'],2) ?></td></tr>
        <tr class="total"><td>Net Pay</td><td>₱<?= number_format($payslip['net_pay'],2) ?></td></tr>
    </tbody>
    </table>
</div>

<div class="mt-4 d-flex justify-content-between">
    <div class="text-center" style="width:30%;"><p>Prepared By</p><hr></div>
    <div class="text-center" style="width:30%;"><p>Approved By</p><hr></div>
    <div class="text-center" style="width:30%;"><p>Employee</p><hr></div>
</div>

</body>
</html>
