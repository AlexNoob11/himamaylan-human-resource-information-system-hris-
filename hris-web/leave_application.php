<?php
session_start();
require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';


// Initialize variables
$error = '';
$success = '';
$leave_balances = [];

// Get employee's leave balances with error handling
$query = "
    SELECT lt.id, lt.name, lt.max_days_per_year, lt.allow_half_day, lb.balance 
    FROM leave_balances lb
    JOIN leave_types lt ON lb.leave_type_id = lt.id
    WHERE lb.employee_id = ? AND lb.year = YEAR(CURDATE()) AND lt.is_active = 1
";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    $error = "Database error: " . $conn->error;
} else {
    $stmt->bind_param("i", $_SESSION['employee_id']);
    if (!$stmt->execute()) {
        $error = "Error executing query: " . $stmt->error;
    } else {
        $result = $stmt->get_result();
        $leave_balances = $result->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type_id = (int)$_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $conn->real_escape_string($_POST['reason']);
    $half_day = isset($_POST['half_day']) ? 1 : 0;

    try {
        // Validate dates
        if (empty($start_date) || empty($end_date)) {
            throw new Exception("Please select both start and end dates");
        }

        // Calculate business days (excluding weekends)
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $interval = $start->diff($end);
        $days_requested = $half_day ? 0.5 : ($interval->days + 1); // +1 to include both dates

        // Check leave balance
        $balance_query = "
            SELECT balance FROM leave_balances 
            WHERE employee_id = ? AND leave_type_id = ? AND year = YEAR(CURDATE())
        ";
        $balance_stmt = $conn->prepare($balance_query);
        if ($balance_stmt === false) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $balance_stmt->bind_param("ii", $_SESSION['employee_id'], $leave_type_id);
        if (!$balance_stmt->execute()) {
            throw new Exception("Error checking balance: " . $balance_stmt->error);
        }
        
        $balance_result = $balance_stmt->get_result();
        $balance_row = $balance_result->fetch_assoc();
        $balance = $balance_row ? $balance_row['balance'] : 0;
        $balance_stmt->close();

        if ($days_requested > $balance) {
            throw new Exception("You don't have enough leave balance for this request");
        }

        // Insert leave application
        $insert_query = "
            INSERT INTO leave_applications 
            (employee_id, leave_type_id, start_date, end_date, days_requested, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')
        ";
        $insert_stmt = $conn->prepare($insert_query);
        if ($insert_stmt === false) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $insert_stmt->bind_param("iissds", 
            $_SESSION['employee_id'], 
            $leave_type_id, 
            $start_date, 
            $end_date, 
            $days_requested,
            $reason
        );
        
        if ($insert_stmt->execute()) {
            $_SESSION['success'] = "Leave application submitted successfully!";
            header("Location: leave_status.php");
            exit();
        } else {
            throw new Exception("Error submitting leave application: " . $insert_stmt->error);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
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

  <!-- =======================================================
  * Template Name: NiceAdmin
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Updated: Apr 20 2024 with Bootstrap v5.3.3
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<body>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Apply for Leave</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Leave</li>
                <li class="breadcrumb-item active">Apply</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">New Leave Application</h5>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form id="leaveForm" method="post" class="row g-3">
                            <div class="col-md-6">
                                <label for="leave_type" class="form-label">Leave Type</label>
                                <select name="leave_type" id="leave_type" class="form-select" required>
                                    <option value="">Select Leave Type</option>
                                    <?php foreach($leave_balances as $balance): ?>
                                    <option value="<?= htmlspecialchars($balance['id']) ?>"
                                            data-balance="<?= htmlspecialchars($balance['balance']) ?>"
                                            data-max-days="<?= htmlspecialchars($balance['max_days_per_year']) ?>"
                                            data-allow-half-day="<?= htmlspecialchars($balance['allow_half_day']) ?>">
                                        <?= htmlspecialchars($balance['name']) ?> 
                                        (<?= htmlspecialchars($balance['balance']) ?> of <?= htmlspecialchars($balance['max_days_per_year']) ?> days remaining)
                                    </option>
                                    <?php endforeach; ?>
                                    <!-- Adding Stress Leave option manually -->
                                    <option value="stress"
                                            data-balance="5"
                                            data-max-days="5"
                                            data-allow-half-day="1">
                                        Stress Leave (5 of 5 days remaining)
                                    </option>
                                </select>
                                <div class="form-text" id="balanceText">Please select a leave type</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Date Range</label>
                                <div class="input-group">
                                    <input type="date" name="start_date" id="start_date" class="form-control" required>
                                    <span class="input-group-text">to</span>
                                    <input type="date" name="end_date" id="end_date" class="form-control" required>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="half_day" id="half_day">
                                    <label class="form-check-label" for="half_day">
                                        Half Day Leave
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="reason" class="form-label">Reason for Leave</label>
                                <textarea name="reason" id="reason" class="form-control" rows="4" required></textarea>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">Submit Application</button>
                                <button type="reset" class="btn btn-secondary">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Leave Balances</h5>
                        <div class="list-group">
                            <?php foreach($leave_balances as $balance): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($balance['name']) ?>
                                <span class="badge bg-primary rounded-pill">
                                    <?= htmlspecialchars($balance['balance']) ?>/<?= htmlspecialchars($balance['max_days_per_year']) ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <!-- Adding Stress Leave balance manually -->
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Stress Leave
                                <span class="badge bg-primary rounded-pill">
                                    5/5
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Vendor JS Files -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/jquery/jquery.min.js"></script>

<script>
$(document).ready(function() {
    // Toggle half day checkbox based on leave type selection
    $('#leave_type').change(function() {
        const selected = $(this).find('option:selected');
        const allowHalfDay = selected.data('allow-half-day');
        
        $('#half_day').prop('disabled', !allowHalfDay);
        if (!allowHalfDay) {
            $('#half_day').prop('checked', false);
        }
        
        // Update balance text
        const balance = selected.data('balance');
        const maxDays = selected.data('max-days');
        $('#balanceText').text(`You have ${balance} of ${maxDays} days remaining`);
    });

    // Date validation
    $('#leaveForm').submit(function(e) {
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        const leaveType = $('#leave_type option:selected');
        const balance = parseFloat(leaveType.data('balance'));
        const isHalfDay = $('#half_day').is(':checked');
        
        if (startDate > endDate) {
            alert('End date must be after start date');
            e.preventDefault();
            return false;
        }
        
        // Calculate business days (excluding weekends)
        let businessDays = 0;
        const currentDate = new Date(startDate);
        while (currentDate <= endDate) {
            const dayOfWeek = currentDate.getDay();
            if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Skip Sunday and Saturday
                businessDays++;
            }
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        if (isHalfDay) {
            businessDays = 0.5;
        }
        
        if (businessDays > balance) {
            alert(`You only have ${balance} days remaining but are requesting ${businessDays} days.`);
            e.preventDefault();
            return false;
        }
        
        return true;
    });

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    $('#start_date').attr('min', today);
    $('#end_date').attr('min', today);
    
    // Update end date when start date changes
    $('#start_date').change(function() {
        $('#end_date').attr('min', $(this).val());
    });
});
</script>

</body>
</html>