<?php
require_once 'config.php';
require_once 'theme/header.php';
require_once 'theme/sidebar.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$current_month = date('Y-m');
$filter_month = isset($_GET['month']) ? $_GET['month'] : $current_month;
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>My Attendance</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>

    <section class="section dashboard">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Attendance for <?= date('F Y', strtotime($filter_month)) ?></h5>
                        
                        <!-- Month Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-3">
                                <input type="month" class="form-control" name="month" value="<?= $filter_month ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </div>
                        </form>

                        <!-- Time In/Out Button -->
                        <div class="text-center mb-4">
                            <?php
                            $today = date('Y-m-d');
                            $stmt = $conn->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
                            $stmt->bind_param("is", $employee_id, $today);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $today_attendance = $result->fetch_assoc();
                            
                            if (!$today_attendance) {
                                // No record for today
                                echo '<button id="timeInBtn" class="btn btn-success btn-lg"><i class="bi bi-clock"></i> Time In</button>';
                            } elseif ($today_attendance['time_in'] && !$today_attendance['time_out']) {
                                // Time in but not time out
                                echo '<button id="timeOutBtn" class="btn btn-danger btn-lg" data-id="'.$today_attendance['id'].'">
                                        <i class="bi bi-clock"></i> Time Out
                                      </button>';
                            } else {
                                echo '<div class="alert alert-info">Attendance for today is already completed</div>';
                            }
                            ?>
                        </div>

                        <!-- Attendance Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Status</th>
                                        <th>Working Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $start_date = date('Y-m-01', strtotime($filter_month));
                                    $end_date = date('Y-m-t', strtotime($filter_month));
                                    
                                    $stmt = $conn->prepare("
                                        SELECT * FROM attendance 
                                        WHERE employee_id = ? 
                                        AND date BETWEEN ? AND ?
                                        ORDER BY date DESC
                                    ");
                                    $stmt->bind_param("iss", $employee_id, $start_date, $end_date);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    while ($row = $result->fetch_assoc()):
                                        // Calculate working hours
                                        $hours = '-';
                                        if ($row['time_in'] && $row['time_out']) {
                                            $diff = strtotime($row['time_out']) - strtotime($row['time_in']);
                                            $hours = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                        <td><?= $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-' ?></td>
                                        <td><?= $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-' ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $row['status'] == 'Present' ? 'success' : 
                                                ($row['status'] == 'Late' ? 'warning' : 
                                                ($row['status'] == 'Absent' ? 'danger' : 'info')) 
                                            ?>">
                                                <?= $row['status'] ?>
                                            </span>
                                        </td>
                                        <td><?= $hours ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
$(document).ready(function() {
    // Time In Button
    $('#timeInBtn').click(function() {
        $.post('time_action.php', { action: 'time_in' }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Failed to record time in');
            }
        }, 'json');
    });
    
    // Time Out Button
    $('#timeOutBtn').click(function() {
        $.post('time_action.php', { 
            action: 'time_out',
            attendance_id: $(this).data('id')
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message || 'Failed to record time out');
            }
        }, 'json');
    });
});
</script>

<?php 
require_once 'theme/footer.php';
?>