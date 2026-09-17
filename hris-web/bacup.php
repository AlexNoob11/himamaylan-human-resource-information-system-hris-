<?php
ob_start();
session_start();
require_once 'conn.php';

// Redirect if not admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch users with department name and daily rate (basic_salary is now daily rate)
$usersQuery = $conn->query("
    SELECT u.id, u.first_name, u.middle_initial, u.last_name, 
           u.basic_salary as daily_rate, 
           d.department_name 
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.employee_type = 'Regular' OR u.employee_type = 'Contractual'
    ORDER BY u.first_name ASC
");
$users = [];
while ($row = $usersQuery->fetch_assoc()) {
    $users[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['employee_id'];
    $pay_period_start = $_POST['pay_period_start'];
    $pay_period_end = $_POST['pay_period_end'];
    
    // Get user's daily rate directly (basic_salary is now daily rate)
    $userQuery = $conn->query("
        SELECT basic_salary as daily_rate 
        FROM users 
        WHERE id = $user_id
    ");
    $userData = $userQuery->fetch_assoc();
    $daily_rate = floatval($userData['daily_rate']);
    $hourly_rate = $daily_rate / 8;
    $basic_salary = $daily_rate; // Since basic_salary = daily_rate now
    
    // Calculate total days in the period
    $start = new DateTime($pay_period_start);
    $end = new DateTime($pay_period_end);
    $interval = $start->diff($end);
    $days_in_period = $interval->days + 1; // Including start day
    
    // Calculate total hours from attendance for the selected period
    $attendanceQuery = $conn->query("
        SELECT date, total_hours, status
        FROM attendance 
        WHERE user_id = $user_id 
        AND date BETWEEN '$pay_period_start' AND '$pay_period_end'
        ORDER BY date ASC
    ");
    
    $total_hours_decimal = 0;
    $days_worked = 0;
    
    // Create array for all dates in period
    $all_dates = [];
    $current_date = clone $start;
    while ($current_date <= $end) {
        $date_str = $current_date->format('Y-m-d');
        $all_dates[$date_str] = [
            'hours' => '00:00',
            'decimal_hours' => 0,
            'status' => 'Absent',
            'has_record' => false
        ];
        $current_date->modify('+1 day');
    }
    
    // Fill with actual attendance data
    while ($row = $attendanceQuery->fetch_assoc()) {
        $date_str = $row['date'];
        
        // Skip absent days for hour calculation
        if ($row['status'] == 'Absent') {
            $all_dates[$date_str] = [
                'hours' => '00:00',
                'decimal_hours' => 0,
                'status' => $row['status'],
                'has_record' => true
            ];
            continue;
        }
        
        // Parse total_hours (format: HH:MM)
        $time_parts = explode(':', $row['total_hours']);
        if (count($time_parts) == 2) {
            $hours = (int)$time_parts[0];
            $minutes = (int)$time_parts[1];
            
            // Convert to decimal hours
            $decimal_hours = $hours + ($minutes / 60);
            $total_hours_decimal += $decimal_hours;
            
            $all_dates[$date_str] = [
                'hours' => $row['total_hours'],
                'decimal_hours' => $decimal_hours,
                'status' => $row['status'],
                'has_record' => true
            ];
        }
    }
    
    // Calculate days worked (days with hours > 0 or Present/Late status)
    foreach ($all_dates as $date) {
        if ($date['has_record'] && 
            ($date['decimal_hours'] > 0 || in_array($date['status'], ['Present', 'Late']))) {
            $days_worked++;
        }
    }
    
    $total_hours = $total_hours_decimal;
    
    // Calculate regular and overtime hours PER DAY
    $regular_hours_per_day = 8;
    
    // Calculate regular and overtime by checking each day
    $total_regular_hours = 0;
    $total_overtime_hours = 0;
    
    foreach ($all_dates as $date) {
        if ($date['decimal_hours'] > 0 && in_array($date['status'], ['Present', 'Late'])) {
            $daily_hours = $date['decimal_hours'];
            $daily_regular = min($daily_hours, $regular_hours_per_day);
            $daily_overtime = max(0, $daily_hours - $regular_hours_per_day);
            
            $total_regular_hours += $daily_regular;
            $total_overtime_hours += $daily_overtime;
        }
    }
    
    $regular_hours = $total_regular_hours;
    $overtime_hours = $total_overtime_hours;
    
    // Calculate actual basic pay
    $actual_basic = $regular_hours * $hourly_rate;
    
    // Overtime calculation (time and a half for overtime)
    $overtime_multiplier = floatval($_POST['overtime_multiplier']);
    $overtime_rate = $hourly_rate * $overtime_multiplier;
    $overtime_pay = $overtime_hours * $overtime_rate;
    
    // Allowances
    $transportation = floatval($_POST['transportation_allowance']);
    $meal = floatval($_POST['meal_allowance']);
    $housing = floatval($_POST['housing_allowance']);
    $total_allowances = $transportation + $meal + $housing;
    
    // Gross pay
    $gross_pay = $actual_basic + $overtime_pay + $total_allowances;
    
    // Deductions
    $tax = floatval($_POST['tax']);
    $sss = floatval($_POST['sss']);
    $philhealth = floatval($_POST['philhealth']);
    $pagibig = floatval($_POST['pagibig']);
    $other_deductions = floatval($_POST['other_deductions']);
    $govt_deductions = $sss + $philhealth + $pagibig;
    $total_deductions = $tax + $govt_deductions + $other_deductions;
    
    // Net pay
    $net_pay = $gross_pay - $total_deductions;
    
    $payment_date = $_POST['payment_date'];
    $remarks = $_POST['remarks'];
    $created_by = $_SESSION['admin_id'];
    
    // Format total_hours_display for storage (HH:MM format)
    $total_hours_int = floor($total_hours);
    $total_minutes = round(($total_hours - $total_hours_int) * 60);
    $total_hours_display = sprintf("%02d:%02d", $total_hours_int, $total_minutes);
    
    // Format regular_hours_display
    $regular_hours_int = floor($regular_hours);
    $regular_minutes = round(($regular_hours - $regular_hours_int) * 60);
    $regular_hours_display = sprintf("%02d:%02d", $regular_hours_int, $regular_minutes);
    
    // Format overtime_hours_display
    $overtime_hours_int = floor($overtime_hours);
    $overtime_minutes = round(($overtime_hours - $overtime_hours_int) * 60);
    $overtime_hours_display = sprintf("%02d:%02d", $overtime_hours_int, $overtime_minutes);

          // Insert into payslips
    $stmt = $conn->prepare("INSERT INTO payslips 
        (user_id, pay_period_start, pay_period_end, basic_salary, days_worked, daily_rate, total_hours_worked, total_hours_display, days_in_period, regular_hours, regular_hours_display, overtime_hours, overtime_hours_display, hourly_rate, overtime_multiplier, transportation_allowance, meal_allowance, housing_allowance, tax, overtime_rate, sss, philhealth, pagibig, other_deductions, gross_pay, net_pay, payment_date, remarks, attendance_details, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $attendance_details_json = json_encode($all_dates);
    
    $stmt->bind_param(
    "issdidsidsdsddddddddddddddsssi", 
    $user_id,                         
    $pay_period_start,                
    $pay_period_end,                  
    $basic_salary,                    
    $days_worked,                     
    $daily_rate,                      
    $total_hours,                     
    $total_hours_display,             
    $days_in_period,                  
    $regular_hours,                   
    $regular_hours_display,           
    $overtime_hours,                 
    $overtime_hours_display,          
    $hourly_rate,                    
    $overtime_multiplier,             
    $transportation,                 
    $meal,                            
    $housing,                         
    $tax,                             
    $overtime_rate,                   
    $sss,                             
    $philhealth,                     
    $pagibig,                         
    $other_deductions,                
    $gross_pay,                      
    $net_pay,                         
    $payment_date,                    
    $remarks,                        
    $attendance_details_json,         
    $created_by                       
);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Payslip generated successfully.";
    } else {
        $_SESSION['error'] = "Failed to generate payslip: " . $stmt->error;
    }
    $stmt->close();
    header("Location: payslips.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Process Payroll - HR System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
.payroll-card { background-color: #fff; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
.calculation-box, .preview-box { background-color: #f8f9fa; border-radius: 5px; padding: 15px; margin-top: 10px; }
.total-row { background-color: #e9ecef; font-weight: bold; padding: 10px; border-radius: 5px; }
.required-field::after { content: "*"; color: red; margin-left: 4px; }
.info-box { background-color: #e7f3ff; border-left: 4px solid #0d6efd; padding: 10px; margin: 10px 0; border-radius: 4px; }
</style>
</head>
<body>
<?php include 'theme/navbar.php'; ?>
<?php include 'theme/sidebar.php'; ?>

<main id="main" class="main">
<div class="pagetitle">
<h1>Process Payroll</h1>
<nav>
<ol class="breadcrumb">
<li class="breadcrumb-item"><a href="index.php">Home</a></li>
<li class="breadcrumb-item"><a href="payslips.php">Payroll</a></li>
<li class="breadcrumb-item active">Process Payroll</li>
</ol>
</nav>
</div>

<?php if (isset($_SESSION['error'])): ?>
<div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php unset($_SESSION['error']); ?>
<?php endif; ?>

<section class="section">
<div class="row">
<div class="col-lg-12">
<div class="card payroll-card">
<div class="card-body">
<h5 class="card-title">Payroll Information</h5>

<form class="row g-3" method="POST" id="payrollForm">
    <!-- Employee -->
    <div class="col-md-6">
        <label class="form-label required-field">Employee</label>
        <select class="form-select" name="employee_id" id="employee_id" required>
            <option value="">Select Employee</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" data-daily-rate="<?= $user['daily_rate'] ?? 0 ?>">
                    <?= htmlspecialchars($user['first_name'] . ' ' . ($user['middle_initial'] ?? '') . ' ' . $user['last_name']) ?>
                    (<?= htmlspecialchars($user['department_name'] ?? 'N/A') ?>)
                    - ₱<?= number_format($user['daily_rate'], 2) ?>/day
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Pay Period -->
    <div class="col-md-3">
        <label class="form-label required-field">Pay Period Start</label>
        <input type="date" class="form-control" name="pay_period_start" id="pay_period_start" required>
    </div>
    <div class="col-md-3">
        <label class="form-label required-field">Pay Period End</label>
        <input type="date" class="form-control" name="pay_period_end" id="pay_period_end" required>
    </div>

    <!-- Salary Information (Read-only) -->
    <div class="col-12">
        <div class="info-box">
            <h6>Payroll Information (Automatically Calculated)</h6>
            <div class="row mt-2">
                <div class="col-md-3">
                    <label class="form-label">Daily Rate</label>
                    <input type="text" class="form-control bg-light" id="daily_rate" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hourly Rate</label>
                    <input type="text" class="form-control bg-light" id="hourly_rate" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Total Hours</label>
                    <input type="text" class="form-control bg-light" id="total_hours" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Days in Period</label>
                    <input type="text" class="form-control bg-light" id="days_in_period" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Days Worked</label>
                    <input type="text" class="form-control bg-light" id="days_worked" readonly>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-md-6">
                    <label class="form-label">Regular Hours</label>
                    <input type="text" class="form-control bg-light" id="regular_hours" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Overtime Hours</label>
                    <input type="text" class="form-control bg-light" id="overtime_hours" readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- Overtime Rate (Can be adjusted) -->
    <div class="col-md-6">
        <label class="form-label">Overtime Rate Multiplier</label>
        <input type="number" step="0.1" class="form-control" name="overtime_multiplier" id="overtime_multiplier" value="1.5" min="1.0">
        <small class="text-muted">Standard is 1.5x (time and a half)</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Calculated Overtime Rate</label>
        <input type="text" class="form-control bg-light" id="calculated_overtime_rate" readonly>
    </div>

    <!-- Allowances -->
    <div class="col-12"><h6 class="mt-3 mb-3">Allowances</h6></div>
    <div class="col-md-4">
        <label class="form-label">Transportation Allowance</label>
        <input type="number" step="0.01" class="form-control" name="transportation_allowance" id="transportation_allowance" value="0">
    </div>
    <div class="col-md-4">
        <label class="form-label">Meal Allowance</label>
        <input type="number" step="0.01" class="form-control" name="meal_allowance" id="meal_allowance" value="0">
    </div>
    <div class="col-md-4">
        <label class="form-label">Housing Allowance</label>
        <input type="number" step="0.01" class="form-control" name="housing_allowance" id="housing_allowance" value="0">
    </div>

    <!-- Deductions -->
    <div class="col-12"><h6 class="mt-3 mb-3">Deductions</h6></div>
    <div class="col-md-4">
        <label class="form-label">Tax</label>
        <input type="number" step="0.01" class="form-control" name="tax" id="tax" value="0">
    </div>
    <div class="col-md-4">
        <label class="form-label">SSS</label>
        <input type="number" step="0.01" class="form-control" name="sss" id="sss" value="0">
    </div>
    <div class="col-md-4">
        <label class="form-label">PhilHealth</label>
        <input type="number" step="0.01" class="form-control" name="philhealth" id="philhealth" value="0">
    </div>
    <div class="col-md-6">
        <label class="form-label">Pag-IBIG</label>
        <input type="number" step="0.01" class="form-control" name="pagibig" id="pagibig" value="0">
    </div>
    <div class="col-md-6">
        <label class="form-label">Other Deductions</label>
        <input type="number" step="0.01" class="form-control" name="other_deductions" id="other_deductions" value="0">
    </div>

    <!-- Payment -->
    <div class="col-md-6">
        <label class="form-label required-field">Payment Date</label>
        <input type="date" class="form-control" name="payment_date" id="payment_date" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Remarks</label>
        <textarea class="form-control" name="remarks" id="remarks" rows="1"></textarea>
    </div>

    <!-- Preview -->
    <div class="col-12 mt-4">
        <div class="preview-box">
            <h6 class="mb-3">Payroll Summary</h6>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Basic Pay (Regular Hours):</strong> ₱<span id="preview_basic">0.00</span></p>
                    <p><strong>Overtime Pay:</strong> ₱<span id="preview_overtime">0.00</span></p>
                    <p><strong>Total Allowances:</strong> ₱<span id="preview_allowances">0.00</span></p>
                    <p class="total-row"><strong>Gross Pay:</strong> ₱<span id="preview_gross">0.00</span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Tax:</strong> ₱<span id="preview_tax">0.00</span></p>
                    <p><strong>Government Deductions:</strong> ₱<span id="preview_govt_deductions">0.00</span></p>
                    <p><strong>Other Deductions:</strong> ₱<span id="preview_other_deductions">0.00</span></p>
                    <p class="total-row"><strong>Net Pay:</strong> ₱<span id="preview_net">0.00</span></p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Process Payroll & Generate Payslip</button>
        <button type="reset" class="btn btn-secondary" onclick="resetForm()">Reset</button>
    </div>
</form>

</div>
</div>
</div>
</div>
</section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('payrollForm');
    
    // Set default dates
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    document.getElementById('pay_period_start').valueAsDate = firstDay;
    document.getElementById('pay_period_end').valueAsDate = lastDay;
    document.getElementById('payment_date').valueAsDate = today;

    async function fetchAttendanceData(userId, startDate, endDate) {
        if (!userId || !startDate || !endDate) return null;
        
        try {
            const response = await fetch(`ajax/get_attendance_data.php?user_id=${userId}&start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching attendance data:', error);
            return null;
        }
    }

    async function calculatePayroll() {
        const userId = document.getElementById('employee_id').value;
        const startDate = document.getElementById('pay_period_start').value;
        const endDate = document.getElementById('pay_period_end').value;
        const selectedEmployee = document.getElementById('employee_id').selectedOptions[0];
        const dailyRate = parseFloat(selectedEmployee.getAttribute('data-daily-rate') || 0);
        
        if (!userId || !startDate || !endDate || dailyRate <= 0) {
            resetCalculations();
            return;
        }
        
        // Calculate days in period
        const start = new Date(startDate);
        const end = new Date(endDate);
        const timeDiff = end.getTime() - start.getTime();
        const daysInPeriod = Math.floor(timeDiff / (1000 * 3600 * 24)) + 1;
        
        // Fetch attendance data
        const attendanceData = await fetchAttendanceData(userId, startDate, endDate);
        const totalHours = attendanceData?.total_hours || 0;
        const daysWorked = attendanceData?.days_worked || 0;
        
        // Calculate hourly rate
        const hourlyRate = dailyRate / 8;
        
        // Calculate regular and overtime hours PER DAY
        const regularHoursPerDay = 8;
        
        // Calculate regular and overtime by checking each day from the API
        let totalRegularHours = 0;
        let totalOvertimeHours = 0;
        
        if (attendanceData?.attendance_details) {
            const details = attendanceData.attendance_details;
            for (const date in details) {
                const day = details[date];
                if (day.decimal_hours > 0 && ['Present', 'Late'].includes(day.status)) {
                    const dailyHours = day.decimal_hours;
                    const dailyRegular = Math.min(dailyHours, regularHoursPerDay);
                    const dailyOvertime = Math.max(0, dailyHours - regularHoursPerDay);
                    
                    totalRegularHours += dailyRegular;
                    totalOvertimeHours += dailyOvertime;
                }
            }
        } else {
            // Fallback calculation
            const maxRegularHours = daysWorked * regularHoursPerDay;
            totalRegularHours = Math.min(totalHours, maxRegularHours);
            totalOvertimeHours = Math.max(0, totalHours - maxRegularHours);
        }
        
        const regularHours = totalRegularHours;
        const overtimeHours = totalOvertimeHours;
        
        // Calculate overtime rate
        const overtimeMultiplier = parseFloat(document.getElementById('overtime_multiplier').value) || 1.5;
        const overtimeRate = hourlyRate * overtimeMultiplier;
        
        // Update form fields
        document.getElementById('daily_rate').value = dailyRate.toFixed(2);
        document.getElementById('hourly_rate').value = hourlyRate.toFixed(2);
        document.getElementById('total_hours').value = totalHours.toFixed(2);
        document.getElementById('days_in_period').value = daysInPeriod;
        document.getElementById('days_worked').value = daysWorked;
        document.getElementById('regular_hours').value = regularHours.toFixed(2);
        document.getElementById('overtime_hours').value = overtimeHours.toFixed(2);
        document.getElementById('calculated_overtime_rate').value = overtimeRate.toFixed(2);
        
        // Update preview
        updatePreview(dailyRate, hourlyRate, regularHours, overtimeHours, overtimeRate);
    }

    function updatePreview(dailyRate, hourlyRate, regularHours, overtimeHours, overtimeRate) {
        // Calculate basic pay
        const basicPay = regularHours * hourlyRate;
        const overtimePay = overtimeHours * overtimeRate;
        
        // Get allowances
        const transportation = parseFloat(document.getElementById('transportation_allowance').value) || 0;
        const meal = parseFloat(document.getElementById('meal_allowance').value) || 0;
        const housing = parseFloat(document.getElementById('housing_allowance').value) || 0;
        const totalAllowances = transportation + meal + housing;
        
        // Get deductions
        const tax = parseFloat(document.getElementById('tax').value) || 0;
        const sss = parseFloat(document.getElementById('sss').value) || 0;
        const philhealth = parseFloat(document.getElementById('philhealth').value) || 0;
        const pagibig = parseFloat(document.getElementById('pagibig').value) || 0;
        const otherDeductions = parseFloat(document.getElementById('other_deductions').value) || 0;
        
        const govtDeductions = sss + philhealth + pagibig;
        const totalDeductions = tax + govtDeductions + otherDeductions;
        
        // Calculate totals
        const grossPay = basicPay + overtimePay + totalAllowances;
        const netPay = grossPay - totalDeductions;
        
        // Update preview
        document.getElementById('preview_basic').textContent = basicPay.toFixed(2);
        document.getElementById('preview_overtime').textContent = overtimePay.toFixed(2);
        document.getElementById('preview_allowances').textContent = totalAllowances.toFixed(2);
        document.getElementById('preview_gross').textContent = grossPay.toFixed(2);
        document.getElementById('preview_tax').textContent = tax.toFixed(2);
        document.getElementById('preview_govt_deductions').textContent = govtDeductions.toFixed(2);
        document.getElementById('preview_other_deductions').textContent = otherDeductions.toFixed(2);
        document.getElementById('preview_net').textContent = netPay.toFixed(2);
    }

    function resetCalculations() {
        document.getElementById('daily_rate').value = '';
        document.getElementById('hourly_rate').value = '';
        document.getElementById('total_hours').value = '';
        document.getElementById('days_in_period').value = '';
        document.getElementById('days_worked').value = '';
        document.getElementById('regular_hours').value = '';
        document.getElementById('overtime_hours').value = '';
        document.getElementById('calculated_overtime_rate').value = '';
        
        document.getElementById('preview_basic').textContent = '0.00';
        document.getElementById('preview_overtime').textContent = '0.00';
        document.getElementById('preview_allowances').textContent = '0.00';
        document.getElementById('preview_gross').textContent = '0.00';
        document.getElementById('preview_tax').textContent = '0.00';
        document.getElementById('preview_govt_deductions').textContent = '0.00';
        document.getElementById('preview_other_deductions').textContent = '0.00';
        document.getElementById('preview_net').textContent = '0.00';
    }

    function resetForm() {
        resetCalculations();
        form.reset();
        
        // Reset dates to defaults
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        document.getElementById('pay_period_start').valueAsDate = firstDay;
        document.getElementById('pay_period_end').valueAsDate = lastDay;
        document.getElementById('payment_date').valueAsDate = today;
        document.getElementById('overtime_multiplier').value = '1.5';
    }

    // Event Listeners
    document.getElementById('employee_id').addEventListener('change', calculatePayroll);
    document.getElementById('pay_period_start').addEventListener('change', calculatePayroll);
    document.getElementById('pay_period_end').addEventListener('change', calculatePayroll);
    document.getElementById('overtime_multiplier').addEventListener('input', calculatePayroll);
    
    // Listen to allowance and deduction inputs
    const inputFields = ['transportation_allowance', 'meal_allowance', 'housing_allowance', 
                         'tax', 'sss', 'philhealth', 'pagibig', 'other_deductions'];
    
    inputFields.forEach(field => {
        document.getElementById(field).addEventListener('input', () => {
            const selectedEmployee = document.getElementById('employee_id').selectedOptions[0];
            const dailyRate = parseFloat(selectedEmployee.getAttribute('data-daily-rate') || 0);
            
            if (dailyRate > 0) {
                const hourlyRate = dailyRate / 8;
                const regularHours = parseFloat(document.getElementById('regular_hours').value) || 0;
                const overtimeHours = parseFloat(document.getElementById('overtime_hours').value) || 0;
                const overtimeMultiplier = parseFloat(document.getElementById('overtime_multiplier').value) || 1.5;
                const overtimeRate = hourlyRate * overtimeMultiplier;
                
                updatePreview(dailyRate, hourlyRate, regularHours, overtimeHours, overtimeRate);
            }
        });
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>