<?php
session_start();

// Redirect if admin not logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

$current_year = date('Y');
$max_annual_filing = 15; // maximum leave filings per year

// Handle filters
$filter_employee = $_GET['employee'] ?? '';
$filter_email = $_GET['email'] ?? '';
$filter_year = $_GET['year'] ?? $current_year;

// Fetch filtered users
$where = [];
$params = [];
$paramTypes = '';

if ($filter_employee !== '') {
    $where[] = 'first_name LIKE ? OR last_name LIKE ?';
    $params[] = "%$filter_employee%";
    $params[] = "%$filter_employee%";
    $paramTypes .= 'ss';
}

if ($filter_email !== '') {
    $where[] = 'email LIKE ?';
    $params[] = "%$filter_email%";
    $paramTypes .= 's';
}

$query = "SELECT * FROM users";
if ($where) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY date_registered DESC";

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
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Employee Leave Monitoring</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Employee Leave Monitoring</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Leave Monitoring</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Filter Card -->
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Filter Employees</h5>
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee Name</label>
                        <input type="text" name="employee" class="form-control" placeholder="Enter name" value="<?= htmlspecialchars($filter_employee) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="text" name="email" class="form-control" placeholder="Enter email" value="<?= htmlspecialchars($filter_email) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <input type="number" name="year" class="form-control" value="<?= htmlspecialchars($filter_year) ?>" min="2000" max="<?= date('Y') ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">Filter</button>
                        <a href="leave_monitoring.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Employee Leave Table -->
        <div class="card">
            <div class="card-body pt-4">
                <table class="table datatable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee Name</th>
                            <th>Email</th>
                            <th>Leave Filings Allowed</th>
                            <th>Leave Filed</th>
                            <th>Remaining Filings</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = $result->fetch_assoc()) { 
                        $user_id = $row['id'];

                        // Count number of leave requests filed for selected year
                        $filings_query = $conn->prepare("
                            SELECT COUNT(*) AS filed_count
                            FROM leave_requests
                            WHERE user_id = ? AND YEAR(start_date) = ?
                        ");
                        $filings_query->bind_param("ii", $user_id, $filter_year);
                        $filings_query->execute();
                        $filings_result = $filings_query->get_result()->fetch_assoc();
                        $filed = (int)($filings_result['filed_count'] ?? 0);

                        // Remaining filings
                        $remaining = max($max_annual_filing - $filed, 0);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td class="text-center"><?= $max_annual_filing ?></td>
                            <td class="text-center"><?= $filed ?></td>
                            <td class="text-center"><?= $remaining ?></td>
                            <td>
                                <a href="leave_history.php?employee=<?= $user_id ?>&year=<?= $filter_year ?>" 
                                   class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-clock-history"></i> View History
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script>
  new simpleDatatables.DataTable(".datatable");
</script>
</body>
</html>
