<?php
// Start session with secure settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false, // Set to true in production with HTTPS
    'cookie_samesite' => 'Strict'
]);

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'conn.php';

// Verify database connection
if (!$conn instanceof mysqli || $conn->connect_error) {
    error_log("Database connection failed: " . ($conn->connect_error ?? 'No connection object'));
    $_SESSION['error'] = "Unable to connect to the database. Please contact the administrator.";
    $conn = null; // Prevent further queries
}

// Validate and sanitize date input
$current_date = date('Y-m-d');
$filter_date = $current_date;

if (isset($_GET['date']) && !empty($_GET['date'])) {
    if (DateTime::createFromFormat('Y-m-d', $_GET['date']) !== false) {
        $filter_date = $_GET['date'];
    } else {
        error_log("Invalid date format attempted: " . $_GET['date']);
        $_SESSION['error'] = "Invalid date format. Showing today's attendance.";
    }
}

// Define computeHours function here (outside the loop)
function computeHours($start, $end) {
    if (!$start || !$end) return 0;
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    return round(($endTime - $startTime) / 3600, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <link rel="icon" type="image/png" href="assets/img/upc12.png">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Employee Attendance</title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Attendance Tracker</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>

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

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Attendance for <?= htmlspecialchars(date('F j, Y', strtotime($filter_date))) ?></h5>

                        <!-- Date Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($filter_date) ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary"><i class="bi bi-search"></i> Filter</button>
                            </div>
                            <div class="col-md-2">
                                <a href="attendance.php?date=<?= date('Y-m-d') ?>" class="btn btn-outline-primary">Today</a>
                            </div>
                        </form>

                        <!-- Attendance Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>AM In</th>
                                        <th>AM Out</th>
                                        <th>PM In</th>
                                        <th>PM Out</th>
                                        <th>Night In</th>
                                        <th>Night Out</th>
                                        <th>Total Hours</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                if ($conn) {
                                    $sql = "
                                        SELECT 
                                            a.id AS attendance_id,
                                            a.user_id,
                                            a.date,
                                            a.am_in,
                                            a.am_out,
                                            a.pm_in,
                                            a.pm_out,
                                            a.night_in,
                                            a.night_out,
                                            a.status,
                                            a.notes,
                                            CONCAT(u.first_name, ' ', u.last_name) AS full_name
                                        FROM attendance a
                                        JOIN users u ON a.user_id = u.id
                                        WHERE a.date = ?
                                        ORDER BY u.first_name
                                    ";

                                    $stmt = $conn->prepare($sql);
                                    if ($stmt) {
                                        $stmt->bind_param("s", $filter_date);
                                        if ($stmt->execute()) {
                                            $result = $stmt->get_result();
                                            if ($result->num_rows === 0) {
                                                echo '<tr><td colspan="10" class="text-center">No attendance records found for this date</td></tr>';
                                            } else {
                                                while ($row = $result->fetch_assoc()):
                                                    $full_name = htmlspecialchars($row['full_name']);
                                                    $date = htmlspecialchars($row['date']);
                                                    $am_in = $row['am_in'] ? date('h:i A', strtotime($row['am_in'])) : '-';
                                                    $am_out = $row['am_out'] ? date('h:i A', strtotime($row['am_out'])) : '-';
                                                    $pm_in = $row['pm_in'] ? date('h:i A', strtotime($row['pm_in'])) : '-';
                                                    $pm_out = $row['pm_out'] ? date('h:i A', strtotime($row['pm_out'])) : '-';
                                                    $night_in = $row['night_in'] ? date('h:i A', strtotime($row['night_in'])) : '-';
                                                    $night_out = $row['night_out'] ? date('h:i A', strtotime($row['night_out'])) : '-';

                                                    // Calculate total hours
                                                    $am_hours = computeHours($row['am_in'], $row['am_out']);
                                                    $pm_hours = computeHours($row['pm_in'], $row['pm_out']);
                                                    $night_hours = computeHours($row['night_in'], $row['night_out']);
                                                    $total_hours = $am_hours + $pm_hours + $night_hours;
                                ?>
                                <tr>
                                    <td><?= $full_name ?></td>
                                    <td><?= date('F j, Y', strtotime($date)) ?></td>
                                    <td><?= $am_in ?></td>
                                    <td><?= $am_out ?></td>
                                    <td><?= $pm_in ?></td>
                                    <td><?= $pm_out ?></td>
                                    <td><?= $night_in ?></td>
                                    <td><?= $night_out ?></td>
                                    <td><?= $total_hours ?> hrs</td>
                                    
                                </tr>
                                <?php
                                                endwhile;
                                            }
                                        }
                                        $stmt->close();
                                    }
                                } else {
                                    echo '<tr><td colspan="10" class="text-center">Database connection error</td></tr>';
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editAttendanceForm" method="POST" action="update_attendance.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                        <input type="hidden" name="attendance_id" id="attendance_id">

                        <div class="mb-3">
                            <label class="form-label">Employee</label>
                            <input type="text" class="form-control" id="employee_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" name="date" id="attendance_date" readonly>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">AM In</label>
                                <input type="time" class="form-control" name="am_in" id="am_in">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">AM Out</label>
                                <input type="time" class="form-control" name="am_out" id="am_out">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">PM In</label>
                                <input type="time" class="form-control" name="pm_in" id="pm_in">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PM Out</label>
                                <input type="time" class="form-control" name="pm_out" id="pm_out">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Night In</label>
                                <input type="time" class="form-control" name="night_in" id="night_in">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Night Out</label>
                                <input type="time" class="form-control" name="night_out" id="night_out">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="attendance_status" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Late">Late</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" id="attendance_notes" rows="3" maxlength="500"></textarea>
                        </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS Scripts -->
<script src="assets/vendor/jquery/jquery.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
<script src="assets/js/main.js"></script>
<script>
$(document).ready(function() {
    // Initialize datatable
    new simpleDatatables.DataTable(".datatable");

    // Handle edit modal show
    $(document).on('click', '.edit-attendance', function() {
        let btn = $(this);
        $('#attendance_id').val(btn.data('attendance-id'));
        $('#employee_name').val(btn.data('employee'));
        $('#attendance_date').val(btn.data('date'));
        $('#am_in').val(btn.data('am-in') ? btn.data('am-in').substring(0,5) : '');
        $('#am_out').val(btn.data('am-out') ? btn.data('am-out').substring(0,5) : '');
        $('#pm_in').val(btn.data('pm-in') ? btn.data('pm-in').substring(0,5) : '');
        $('#pm_out').val(btn.data('pm-out') ? btn.data('pm-out').substring(0,5) : '');
        $('#night_in').val(btn.data('night-in') ? btn.data('night-in').substring(0,5) : '');
        $('#night_out').val(btn.data('night-out') ? btn.data('night-out').substring(0,5) : '');
        $('#attendance_status').val(btn.data('status'));
        $('#attendance_notes').val(btn.data('notes'));
        $('#editAttendanceModal').modal('show');
    });
});
</script>

</body>
</html>
