<?php
// ========================
// DATABASE CONFIG
// ========================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'upc');

require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

$conn = null;
$error = '';
$users = [];
$attendanceRecords = [];

try {
    // CONNECT
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) throw new Exception("Connection failed: " . $conn->connect_error);

    // ========================
    // GET ALL USERS for filter
    // ========================
    $resultUsers = $conn->query("SELECT id, first_name, middle_initial, last_name FROM users ORDER BY last_name ASC");
    while ($row = $resultUsers->fetch_assoc()) {
        $users[] = $row;
    }

    // ========================
    // FILTER INPUT
    // ========================
    $selectedUserId = $_GET['user_id'] ?? '';
    $startDate      = $_GET['start_date'] ?? '';
    $endDate        = $_GET['end_date'] ?? '';

    // ========================
    // ATTENDANCE QUERY
    // ========================
    $query = "SELECT 
                    a.*,
                    u.first_name,
                    u.middle_initial,
                    u.last_name
              FROM attendance a
              INNER JOIN users u ON a.user_id = u.id
              WHERE 1";

    $types  = "";
    $params = [];

    if (!empty($selectedUserId)) {
        $query .= " AND a.user_id = ?";
        $types  .= "i";
        $params[] = (int)$selectedUserId;
    }

    if (!empty($startDate)) {
        $query .= " AND a.date >= ?";
        $types  .= "s";
        $params[] = $startDate;
    }

    if (!empty($endDate)) {
        $query .= " AND a.date <= ?";
        $types  .= "s";
        $params[] = $endDate;
    }

    $query .= " ORDER BY u.last_name ASC, a.date ASC";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    // ========================
    // GROUP BY USER
    // ========================
    while ($row = $res->fetch_assoc()) {
        $uid = $row['user_id'];

        $attendanceRecords[$uid]['user'] = [
            'full_name' => $row['first_name'] . ' ' . $row['middle_initial'] . ' ' . $row['last_name']
        ];

        $attendanceRecords[$uid]['records'][] = $row;
    }

    $stmt->close();

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
    <title>Attendance Records - UPC BioEnergy HRIS</title>

    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">

<style>
@media print {
    body * { visibility: hidden; }
    #print-area, #print-area * { visibility: visible; }
    #print-area {
        margin: 0;
        position: absolute;
        width: 100%;
        left: 0;
        top: 0;
    }
}
</style>

<script>
function printRecords() {
    window.print();
}
</script>
</head>

<body>
<main id="main" class="main">

<!-- PAGE TITLE -->
<div class="pagetitle"><h1>Attendance Records</h1></div>
<section class="section">
<div class="row">
<div class="col-lg-12">

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<!-- ========================
      FILTER FORM
======================== -->
<form method="GET" class="row g-3 mb-4">
  <div class="col-md-4">
    <label class="form-label">Employee</label>
    <select class="form-select" name="user_id">
      <option value="">All Employees</option>
      <?php foreach ($users as $u): ?>
      <option value="<?= $u['id']; ?>" <?= ($selectedUserId==$u['id'])?'selected':'' ?>>
        <?= $u['first_name'].' '.$u['middle_initial'].' '.$u['last_name']; ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label">Start Date</label>
    <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">End Date</label>
    <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
  </div>

  <div class="col-md-2 d-flex align-items-end gap-2">
    <button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
    <a href="attendance_user.php" class="btn btn-secondary">Reset</a>
  </div>
</form>

<!-- ========================
      PRINT CONTENT
======================== -->
<div id="print-area">

<?php if (!empty($attendanceRecords)): ?>

<?php
// =================================
// CALCULATE SUMMARY PER EMPLOYEE
// =================================
$summaries = [];

foreach ($attendanceRecords as $uid => $data) {
    $summaries[$uid] = [
        'total_days'  => 0,
        'total_hours' => 0,
        'absent_days' => 0,
        'late_count'  => 0,
    ];

    foreach ($data['records'] as $r) {
        $hours = 0;

        if ($r['am_in'] && $r['am_out'])
            $hours += (strtotime($r['am_out']) - strtotime($r['am_in'])) / 3600;

        if ($r['pm_in'] && $r['pm_out'])
            $hours += (strtotime($r['pm_out']) - strtotime($r['pm_in'])) / 3600;

        $summaries[$uid]['total_hours'] += $hours;
        $summaries[$uid]['total_days']++;

        if ($r['status'] === 'Absent') $summaries[$uid]['absent_days']++;
        if ($r['status'] === 'Late')   $summaries[$uid]['late_count']++;
    }
}
?>

<!-- ========================
      DISPLAY PER USER
======================== -->
<?php foreach ($attendanceRecords as $uid => $data): ?>
    <div class="card mb-4">
    <div class="card-body">

        <!-- SUMMARY HEADER -->
        <h3><strong>Employee:</strong> <?= htmlspecialchars($data['user']['full_name']) ?></h3>

        <p><strong>Total Days Worked:</strong> <?= $summaries[$uid]['total_days'] ?></p>
        <p><strong>Total Hours Worked:</strong> <?= number_format($summaries[$uid]['total_hours'], 2) ?> hrs</p>

        <hr>

        <!-- ATTENDANCE TABLE -->
        <table class="table table-bordered table-striped mt-3">
            <thead class="table-dark">
              <tr>
                <th>Date</th>
                <th>AM IN</th><th>AM OUT</th>
                <th>PM IN</th><th>PM OUT</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Total Hours</th>
              </tr>
            </thead>

            <tbody>
            <?php foreach ($data['records'] as $r):

                $hours = 0;
                if ($r['am_in'] && $r['am_out'])
                    $hours += (strtotime($r['am_out'])-strtotime($r['am_in']))/3600;

                if ($r['pm_in'] && $r['pm_out'])
                    $hours += (strtotime($r['pm_out'])-strtotime($r['pm_in']))/3600;
            ?>
              <tr>
                <td><?= $r['date']; ?></td>
                <td><?= $r['am_in']; ?></td>
                <td><?= $r['am_out']; ?></td>
                <td><?= $r['pm_in']; ?></td>
                <td><?= $r['pm_out']; ?></td>
                <td><?= $r['status']; ?></td>
                <td><?= htmlspecialchars($r['notes']); ?></td>
                <td><?= number_format($hours, 2); ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>

        </table>

    </div>
    </div>
<?php endforeach; ?>

<?php else: ?>
<div class="alert alert-info">No attendance records found.</div>
<?php endif; ?>

</div>

<!-- PRINT BUTTON -->
<button class="btn btn-success mb-4" onclick="printRecords()">
  <i class="bi bi-printer"></i> Print Attendance
</button>

</div>
</div>
</section>
</main>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
