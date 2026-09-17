<?php
require_once 'conn.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid payslip ID.");
}

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.middle_initial, u.last_name, u.department
                        FROM payslips p
                        JOIN users u ON p.user_id = u.id
                        WHERE p.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$payslip = $result->fetch_assoc();
if (!$payslip) die("Payslip not found.");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UPC BioEnergy HRIS - Payslip</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 40px;
    color: #333;
    line-height: 1.5;
}
header {
    text-align: center;
    margin-bottom: 30px;
}
header img {
    max-height: 80px;
    margin-bottom: 5px;
}
header h1 {
    margin: 5px 0 0 0;
    font-size: 28px;
}
header h2 {
    margin: 5px 0 0 0;
    font-size: 18px;
    font-weight: normal;
}
button.print-btn {
    display: inline-block;
    margin-bottom: 20px;
    padding: 8px 16px;
    font-size: 14px;
    cursor: pointer;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}
th, td {
    padding: 8px 12px;
    border: 1px solid #444;
    text-align: left;
}
th {
    background-color: #f0f0f0;
}
.section-title {
    background-color: #e0e0e0;
    font-weight: bold;
    text-align: center;
}
.total {
    font-weight: bold;
    background-color: #f7f7f7;
}
.flex-table {
    display: flex;
    justify-content: space-between;
}
.flex-table table {
    width: 48%;
}
.signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 40px;
}
.sign-box {
    text-align: center;
    width: 30%;
}
.sign-box hr {
    margin-top: 60px;
    border: 1px solid #000;
}
@media print {
    button { display: none; }
}
</style>
</head>
<body>

<header>
    <img src="assets/img/logo.png" alt="UPC BioEnergy Logo">
    <h1>UPC BioEnergy</h1>
    <h2>Human Resource Information System (HRIS)</h2>
    <p><strong>Payslip for the Period:</strong> <?= $payslip['pay_period_start'] ?> to <?= $payslip['pay_period_end'] ?></p>
</header>

<button class="print-btn" onclick="window.print()">🖨 Print Payslip</button>

<table>
<tr><th colspan="2" class="section-title">Employee Information</th></tr>
<tr><td>Employee Name</td><td><?= htmlspecialchars($payslip['first_name'] . ' ' . ($payslip['middle_initial'] ?? '') . ' ' . $payslip['last_name']) ?></td></tr>
<tr><td>Department</td><td><?= htmlspecialchars($payslip['department']) ?></td></tr>
<tr><td>Payment Date</td><td><?= $payslip['payment_date'] ?></td></tr>
<tr><td>Remarks</td><td><?= htmlspecialchars($payslip['remarks']) ?></td></tr>
</table>

<div class="flex-table">
    <!-- Earnings Table -->
    <table>
    <tr><th colspan="2" class="section-title">Earnings</th></tr>
    <tr><td>Basic Salary</td><td>₱<?= number_format($payslip['basic_salary'],2) ?></td></tr>
    <tr><td>Days Worked</td><td><?= $payslip['days_worked'] ?></td></tr>
    <tr><td>Daily Rate</td><td>₱<?= number_format($payslip['daily_rate'],2) ?></td></tr>
    <tr><td>Overtime Hours</td><td><?= $payslip['overtime_hours'] ?></td></tr>
    <tr><td>Overtime Rate</td><td>₱<?= number_format($payslip['overtime_rate'],2) ?></td></tr>
    <tr><td>Overtime Pay</td><td>₱<?= number_format($payslip['overtime_hours'] * $payslip['overtime_rate'],2) ?></td></tr>
    <tr><td>Transportation Allowance</td><td>₱<?= number_format($payslip['transportation_allowance'],2) ?></td></tr>
    <tr><td>Meal Allowance</td><td>₱<?= number_format($payslip['meal_allowance'],2) ?></td></tr>
    <tr><td>Housing Allowance</td><td>₱<?= number_format($payslip['housing_allowance'],2) ?></td></tr>
    <tr class="total"><td>Gross Pay</td><td>₱<?= number_format($payslip['gross_pay'],2) ?></td></tr>
    </table>

    <!-- Deductions Table -->
    <table>
    <tr><th colspan="2" class="section-title">Deductions</th></tr>
    <tr><td>Tax</td><td>₱<?= number_format($payslip['tax'],2) ?></td></tr>
    <tr><td>SSS</td><td>₱<?= number_format($payslip['sss'],2) ?></td></tr>
    <tr><td>PhilHealth</td><td>₱<?= number_format($payslip['philhealth'],2) ?></td></tr>
    <tr><td>Pag-IBIG</td><td>₱<?= number_format($payslip['pagibig'],2) ?></td></tr>
    <tr><td>Other Deductions</td><td>₱<?= number_format($payslip['other_deductions'],2) ?></td></tr>
    <tr class="total"><td>Net Pay</td><td>₱<?= number_format($payslip['net_pay'],2) ?></td></tr>
    </table>
</div>

<div class="signatures">
    <div class="sign-box">
        <p>Prepared By</p>
        <hr>
    </div>
    <div class="sign-box">
        <p>Approved By</p>
        <hr>
    </div>
    <div class="sign-box">
        <p>Employee</p>
        <hr>
    </div>
</div>

</body>
</html>
