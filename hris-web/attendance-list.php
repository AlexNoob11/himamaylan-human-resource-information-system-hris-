<?php
// Include DB connection
$host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "upc";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch attendance records (latest 30)
$att_query = "SELECT a.*, u.first_name, u.last_name 
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              ORDER BY a.date DESC, a.am_in ASC
              LIMIT 30";
$att_result = $conn->query($att_query);

// Fetch leave requests (latest 30)
$leave_query = "SELECT l.*, u.first_name, u.last_name 
                FROM leave_requests l
                JOIN users u ON l.user_id = u.id
                ORDER BY l.date_filed DESC
                LIMIT 30";
$leave_result = $conn->query($leave_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance & Leave List - Admin</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">
    <h2>Attendance Records</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Date</th>
                <th>AM In</th>
                <th>AM Out</th>
                <th>PM In</th>
                <th>PM Out</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if($att_result->num_rows > 0){
                $i = 1;
                while($row = $att_result->fetch_assoc()){
                    echo "<tr>";
                    echo "<td>".$i++."</td>";
                    echo "<td>".$row['first_name']." ".$row['last_name']."</td>";
                    echo "<td>".$row['date']."</td>";
                    echo "<td>".$row['am_in']."</td>";
                    echo "<td>".$row['am_out']."</td>";
                    echo "<td>".$row['pm_in']."</td>";
                    echo "<td>".$row['pm_out']."</td>";
                    // Highlight Late/Absent
                    $status_class = '';
                    if($row['status'] == 'Late') $status_class = 'text-warning';
                    if($row['status'] == 'Absent') $status_class = 'text-danger';
                    echo "<td class='$status_class'>".$row['status']."</td>";
                    echo "<td>".$row['notes']."</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='9' class='text-center'>No attendance records found.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <h2 class="mt-5">Leave Requests</h2>
    <table class="table table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Status</th>
                <th>Comments</th>
                <th>Date Filed</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if($leave_result->num_rows > 0){
                $i = 1;
                while($row = $leave_result->fetch_assoc()){
                    echo "<tr>";
                    echo "<td>".$i++."</td>";
                    echo "<td>".$row['first_name']." ".$row['last_name']."</td>";
                    echo "<td>".$row['leave_type']."</td>";
                    echo "<td>".$row['start_date']."</td>";
                    echo "<td>".$row['end_date']."</td>";
                    // Highlight Pending leave
                    $status_class = '';
                    if($row['status'] == 'Pending') $status_class = 'text-warning';
                    if($row['status'] == 'Approved') $status_class = 'text-success';
                    if($row['status'] == 'Rejected') $status_class = 'text-danger';
                    echo "<td class='$status_class'>".$row['status']."</td>";
                    echo "<td>".$row['comments']."</td>";
                    echo "<td>".$row['date_filed']."</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' class='text-center'>No leave requests found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
