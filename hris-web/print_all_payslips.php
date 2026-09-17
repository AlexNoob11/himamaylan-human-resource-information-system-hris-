<?php
ob_start();
session_start();
require_once 'conn.php';

// protect
if (!isset($_SESSION['admin_id'])) {
    exit("Unauthorized access.");
}

// Filter conditions
$where = [];
$params = [];
$paramTypes = '';

// Employee Filter
if (!empty($_GET['employee_id'])) {
    $where[] = 'p.user_id = ?';
    $params[] = $_GET['employee_id'];
    $paramTypes .= 'i';
}

// Department Filter
if (!empty($_GET['department'])) {
    $where[] = 'u.department_id = ?';
    $params[] = $_GET['department'];
    $paramTypes .= 'i';
}

// Pay period start
if (!empty($_GET['start_date'])) {
    $where[] = 'p.pay_period_start >= ?';
    $params[] = $_GET['start_date'];
    $paramTypes .= 's';
}

// Pay period end
if (!empty($_GET['end_date'])) {
    $where[] = 'p.pay_period_end <= ?';
    $params[] = $_GET['end_date'];
    $paramTypes .= 's';
}

// Main Query
$query = "SELECT 
            p.id,
            p.pay_period_start,
            p.pay_period_end,
            p.gross_pay,
            p.net_pay,
            p.payment_date,
            u.first_name,
            u.middle_initial,
            u.last_name,
            d.department_name
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
$data = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Print Payslips</title>
<style>
body {
    font-family: Arial, sans-serif;
}
.payslip-box {
    border: 1px solid #444;
    padding: 15px;
    margin-bottom: 25px;
}
h3 {
    margin-bottom: 5px;
}
</style>
</head>
<body onload="window.print();">

<h2 style="text-align:center;">All Payslips</h2>
<hr>

<?php if ($data->num_rows > 0): ?>
    <?php while($row = $data->fetch_assoc()): ?>
    <div class="payslip-box">
        <h3>
            <?= htmlspecialchars($row['first_name'].' '.($row['middle_initial'] ?? '').' '.$row['last_name']) ?>
        </h3>

        <p><b>Department:</b> 
            <?= htmlspecialchars($row['department_name'] ?? 'N/A') ?>
        </p>

        <p><b>Pay Period:</b> 
            <?= htmlspecialchars($row['pay_period_start'].' — '.$row['pay_period_end']) ?>
        </p>

        <p><b>Gross Pay:</b> ₱<?= number_format($row['gross_pay'],2) ?></p>
        <p><b>Net Pay:</b> ₱<?= number_format($row['net_pay'],2) ?></p>
        <p><b>Payment Date:</b> <?= htmlspecialchars($row['payment_date']) ?></p>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>No payslips found.</p>
<?php endif; ?>

</body>
</html>
<?php ob_end_flush(); ?>
