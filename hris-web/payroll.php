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
           u.employee_type,
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
    
    // Get user's daily rate, employee type and department
    $userQuery = $conn->query("
        SELECT u.basic_salary as daily_rate, u.employee_type, d.department_name 
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = $user_id
    ");
    $userData = $userQuery->fetch_assoc();
    $daily_rate = floatval($userData['daily_rate']);
    $employee_type = $userData['employee_type'];
    $department_name = $userData['department_name'];
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
    
    // Calculate regular hours (no overtime)
    $regular_hours_per_day = 8;
    $regular_hours = min($total_hours, $days_worked * $regular_hours_per_day);
    
    // Calculate actual basic pay (based on regular hours only)
    $actual_basic = $regular_hours * $hourly_rate;
    
    // Calculate allowances and deductions based on monthly rates
    $days_in_month = 30; // Standard month
    
    // Monthly rates
    $monthly_sss = 500;
    $monthly_philhealth = 500;
    $monthly_pagibig = 500;
    $monthly_tax_other = 100;
    $monthly_communication_allowance = 1500;
    $monthly_laundry_allowance = 600; // For Regular employees only
    $monthly_rice_subsidy = 2000; // For Regular employees only
    
    // Calculate pro-rated amounts based on days in pay period
    $pro_rate_factor = $days_in_period / $days_in_month;
    
    // Deductions (pro-rated)
    $sss = $monthly_sss * $pro_rate_factor;
    $philhealth = $monthly_philhealth * $pro_rate_factor;
    $pagibig = $monthly_pagibig * $pro_rate_factor;
    $other_deductions = $monthly_tax_other * $pro_rate_factor;
    $govt_deductions = $sss + $philhealth + $pagibig;
    
    // Allowances (pro-rated)
    $communication_allowance = $monthly_communication_allowance * $pro_rate_factor;
    
    // Laundry allowance only for Regular employees
    $laundry_allowance = 0;
    if ($employee_type == 'Regular') {
        $laundry_allowance = $monthly_laundry_allowance * $pro_rate_factor;
    }
    
    // Rice subsidy only for Regular employees
    $rice_subsidy = 0;
    if ($employee_type == 'Regular') {
        $rice_subsidy = $monthly_rice_subsidy * $pro_rate_factor;
    }
    
    // MEAL ALLOWANCE: ₱100 per day worked (only for Drivers)
    $meal_allowance = 0;
    if ($department_name == 'Driver') {
        $meal_allowance_per_day = 100;
        $meal_allowance = $days_worked * $meal_allowance_per_day;
    }
    
    // Total allowances
    $total_allowances = $meal_allowance + $rice_subsidy + $laundry_allowance + $communication_allowance;
    
    // Gross pay (basic + allowances only, no overtime)
    $gross_pay = $actual_basic + $total_allowances;
    
    // Net pay
    $net_pay = $gross_pay - $govt_deductions - $other_deductions;
    
    $payment_date = date('Y-m-d'); // Auto-set to today
    $remarks = $_POST['remarks'] ?? '';
    $created_by = $_SESSION['admin_id'];
    
    // Format total_hours_display for storage (HH:MM format)
    $total_hours_int = floor($total_hours);
    $total_minutes = round(($total_hours - $total_hours_int) * 60);
    $total_hours_display = sprintf("%02d:%02d", $total_hours_int, $total_minutes);
    
    // Format regular_hours_display
    $regular_hours_int = floor($regular_hours);
    $regular_minutes = round(($regular_hours - $regular_hours_int) * 60);
    $regular_hours_display = sprintf("%02d:%02d", $regular_hours_int, $regular_minutes);
    
    // Overtime fields are set to 0 since there's no overtime
    $overtime_hours = 0;
    $overtime_hours_display = '00:00';
    $overtime_multiplier = 0;
    $overtime_rate = 0;

    // Insert into payslips
    $stmt = $conn->prepare("INSERT INTO payslips 
        (user_id, pay_period_start, pay_period_end, basic_salary, days_worked, daily_rate, 
         total_hours_worked, total_hours_display, days_in_period, regular_hours, 
         regular_hours_display, overtime_hours, overtime_hours_display, hourly_rate, 
         overtime_multiplier, overtime_rate, meal_allowance, rice_subsidy, laundry_allowance, 
         communication_allowance, sss, philhealth, pagibig, other_deductions, 
         gross_pay, net_pay, payment_date, remarks, attendance_details, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $attendance_details_json = json_encode($all_dates);
    
    $stmt->bind_param(
        "issdiddsisdddsddddddddddddsssi",
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
        $overtime_rate,                   
        $meal_allowance,
        $rice_subsidy,
        $laundry_allowance,
        $communication_allowance,
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
.auto-calc { background-color: #f0f8ff; border: 1px solid #cce5ff; }
.auto-calc:read-only { background-color: #f0f8ff; }
.conditional-allowance { background-color: #fff3cd; border: 1px solid #ffeaa7; }
.driver-only { background-color: #d4edda; border: 1px solid #c3e6cb; }
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
                <option value="<?= $user['id'] ?>" 
                        data-daily-rate="<?= $user['daily_rate'] ?? 0 ?>"
                        data-employee-type="<?= htmlspecialchars($user['employee_type']) ?>"
                        data-department-name="<?= htmlspecialchars($user['department_name']) ?>">
                    <?= htmlspecialchars($user['first_name'] . ' ' . ($user['middle_initial'] ?? '') . ' ' . $user['last_name']) ?>
                    (<?= htmlspecialchars($user['department_name'] ?? 'N/A') ?> - <?= htmlspecialchars($user['employee_type']) ?>)
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
                <div class="col-md-12">
                    <label class="form-label">Regular Hours (Max 8 hrs/day)</label>
                    <input type="text" class="form-control bg-light" id="regular_hours" readonly>
                    <small class="text-muted">Note: Overtime is not calculated in this system</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Allowances -->
    <div class="col-12"><h6 class="mt-3 mb-3">Allowances</h6></div>
    <div class="col-md-3">
        <label class="form-label">Meal Allowance</label>
        <input type="number" step="0.01" class="form-control auto-calc driver-only" id="meal_allowance_display" readonly>
        <input type="hidden" name="meal_allowance" id="meal_allowance">
        <small class="text-muted">Auto-calc: ₱100/day worked (Drivers department only)</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Rice Subsidy</label>
        <input type="number" step="0.01" class="form-control auto-calc conditional-allowance" id="rice_subsidy_display" readonly>
        <input type="hidden" name="rice_subsidy" id="rice_subsidy">
        <small class="text-muted">Auto-calc: ₱2,000/month (Regular employees only)</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Laundry Allowance</label>
        <input type="number" step="0.01" class="form-control auto-calc conditional-allowance" id="laundry_allowance_display" readonly>
        <input type="hidden" name="laundry_allowance" id="laundry_allowance">
        <small class="text-muted">Auto-calc: ₱600/month (Regular employees only)</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Communication Allowance</label>
        <input type="number" step="0.01" class="form-control auto-calc" id="communication_allowance_display" readonly>
        <input type="hidden" name="communication_allowance" id="communication_allowance">
        <small class="text-muted">Auto-calc: ₱1,500/month (All employees)</small>
    </div>

    <!-- Deductions -->
    <div class="col-12"><h6 class="mt-3 mb-3">Deductions</h6></div>
    <div class="col-md-3">
        <label class="form-label">SSS</label>
        <input type="number" step="0.01" class="form-control auto-calc" id="sss_display" readonly>
        <input type="hidden" name="sss" id="sss">
        <small class="text-muted">Auto-calc: ₱500/month (All employees)</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">PhilHealth</label>
        <input type="number" step="0.01" class="form-control auto-calc" id="philhealth_display" readonly>
        <input type="hidden" name="philhealth" id="philhealth">
        <small class="text-muted">Auto-calc: ₱500/month (All employees)</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Pag-IBIG</label>
        <input type="number" step="0.01" class="form-control auto-calc" id="pagibig_display" readonly>
        <input type="hidden" name="pagibig" id="pagibig">
        <small class="text-muted">Auto-calc: ₱500/month (All employees)</small>
    </div>
    <div class="col-md-3">
        <label class="form-label">Tax/Other Deductions</label>
        <input type="number" step="0.01" class="form-control auto-calc" id="other_deductions_display" readonly>
        <input type="hidden" name="other_deductions" id="other_deductions">
        <small class="text-muted">Auto-calc: ₱100/month (All employees)</small>
    </div>

    <!-- Remarks -->
    <div class="col-12">
        <label class="form-label">Remarks</label>
        <textarea class="form-control" name="remarks" id="remarks" rows="3"></textarea>
    </div>

    <!-- Preview -->
    <div class="col-12 mt-4">
        <div class="preview-box">
            <h6 class="mb-3">Payroll Summary</h6>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Basic Pay (Regular Hours):</strong> ₱<span id="preview_basic">0.00</span></p>
                    <p><strong>Total Allowances:</strong> ₱<span id="preview_allowances">0.00</span></p>
                    <p class="total-row"><strong>Gross Pay:</strong> ₱<span id="preview_gross">0.00</span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Government Deductions:</strong> ₱<span id="preview_govt_deductions">0.00</span></p>
                    <p><strong>Tax/Other Deductions:</strong> ₱<span id="preview_other_deductions">0.00</span></p>
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

    // Monthly rates
    const MONTHLY_RATES = {
        sss: 500,
        philhealth: 500,
        pagibig: 500,
        tax_other: 100,
        communication_allowance: 1500,
        laundry_allowance: 600, // For Regular employees only
        rice_subsidy: 2000 // For Regular employees only
    };
    const MEAL_ALLOWANCE_PER_DAY = 100; // ₱100 per day worked for drivers
    const DAYS_IN_MONTH = 30;
    const REGULAR_HOURS_PER_DAY = 8;

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
        const employeeType = selectedEmployee.getAttribute('data-employee-type');
        const departmentName = selectedEmployee.getAttribute('data-department-name');
        
        if (!userId || !startDate || !endDate || dailyRate <= 0) {
            resetCalculations();
            return;
        }
        
        // Calculate days in period
        const start = new Date(startDate);
        const end = new Date(endDate);
        const timeDiff = end.getTime() - start.getTime();
        const daysInPeriod = Math.floor(timeDiff / (1000 * 3600 * 24)) + 1;
        
        // Calculate pro-rate factor
        const proRateFactor = daysInPeriod / DAYS_IN_MONTH;
        
        // Fetch attendance data
        const attendanceData = await fetchAttendanceData(userId, startDate, endDate);
        const totalHours = attendanceData?.total_hours || 0;
        const daysWorked = attendanceData?.days_worked || 0;
        
        // Calculate hourly rate
        const hourlyRate = dailyRate / 8;
        
        // Calculate regular hours (no overtime)
        const regularHours = Math.min(totalHours, daysWorked * REGULAR_HOURS_PER_DAY);
        
        // Update form fields
        document.getElementById('daily_rate').value = dailyRate.toFixed(2);
        document.getElementById('hourly_rate').value = hourlyRate.toFixed(2);
        document.getElementById('total_hours').value = totalHours.toFixed(2);
        document.getElementById('days_in_period').value = daysInPeriod;
        document.getElementById('days_worked').value = daysWorked;
        document.getElementById('regular_hours').value = regularHours.toFixed(2);
        
        // Calculate auto-calculated deductions and allowances
        calculateAutoAmounts(daysInPeriod, proRateFactor, employeeType, departmentName, daysWorked);
        
        // Update preview
        updatePreview(dailyRate, hourlyRate, regularHours);
    }

    function calculateAutoAmounts(daysInPeriod, proRateFactor, employeeType, departmentName, daysWorked) {
        // Calculate deductions
        const sss = MONTHLY_RATES.sss * proRateFactor;
        const philhealth = MONTHLY_RATES.philhealth * proRateFactor;
        const pagibig = MONTHLY_RATES.pagibig * proRateFactor;
        const taxOther = MONTHLY_RATES.tax_other * proRateFactor;
        
        // Calculate allowances
        const communicationAllowance = MONTHLY_RATES.communication_allowance * proRateFactor;
        
        // Laundry allowance only for Regular employees
        let laundryAllowance = 0;
        if (employeeType === 'Regular') {
            laundryAllowance = MONTHLY_RATES.laundry_allowance * proRateFactor;
        }
        
        // Rice subsidy only for Regular employees
        let riceSubsidy = 0;
        if (employeeType === 'Regular') {
            riceSubsidy = MONTHLY_RATES.rice_subsidy * proRateFactor;
        }
        
        // Meal allowance only for Drivers (₱100 per day worked)
        let mealAllowance = 0;
        if (departmentName === 'Driver') {
            mealAllowance = daysWorked * MEAL_ALLOWANCE_PER_DAY;
        }
        
        // Update display fields
        document.getElementById('sss_display').value = sss.toFixed(2);
        document.getElementById('philhealth_display').value = philhealth.toFixed(2);
        document.getElementById('pagibig_display').value = pagibig.toFixed(2);
        document.getElementById('other_deductions_display').value = taxOther.toFixed(2);
        document.getElementById('communication_allowance_display').value = communicationAllowance.toFixed(2);
        document.getElementById('laundry_allowance_display').value = laundryAllowance.toFixed(2);
        document.getElementById('rice_subsidy_display').value = riceSubsidy.toFixed(2);
        document.getElementById('meal_allowance_display').value = mealAllowance.toFixed(2);
        
        // Update hidden fields
        document.getElementById('sss').value = sss;
        document.getElementById('philhealth').value = philhealth;
        document.getElementById('pagibig').value = pagibig;
        document.getElementById('other_deductions').value = taxOther;
        document.getElementById('communication_allowance').value = communicationAllowance;
        document.getElementById('laundry_allowance').value = laundryAllowance;
        document.getElementById('rice_subsidy').value = riceSubsidy;
        document.getElementById('meal_allowance').value = mealAllowance;
    }

    function updatePreview(dailyRate, hourlyRate, regularHours) {
        // Calculate basic pay
        const basicPay = regularHours * hourlyRate;
        
        // Get allowances
        const mealAllowance = parseFloat(document.getElementById('meal_allowance').value) || 0;
        const riceSubsidy = parseFloat(document.getElementById('rice_subsidy').value) || 0;
        const laundryAllowance = parseFloat(document.getElementById('laundry_allowance').value) || 0;
        const communicationAllowance = parseFloat(document.getElementById('communication_allowance').value) || 0;
        
        const totalAllowances = mealAllowance + riceSubsidy + laundryAllowance + communicationAllowance;
        
        // Get deductions
        const sss = parseFloat(document.getElementById('sss').value) || 0;
        const philhealth = parseFloat(document.getElementById('philhealth').value) || 0;
        const pagibig = parseFloat(document.getElementById('pagibig').value) || 0;
        const otherDeductions = parseFloat(document.getElementById('other_deductions').value) || 0;
        
        const govtDeductions = sss + philhealth + pagibig;
        
        // Calculate totals (no overtime)
        const grossPay = basicPay + totalAllowances;
        const netPay = grossPay - govtDeductions - otherDeductions;
        
        // Update preview
        document.getElementById('preview_basic').textContent = basicPay.toFixed(2);
        document.getElementById('preview_allowances').textContent = totalAllowances.toFixed(2);
        document.getElementById('preview_gross').textContent = grossPay.toFixed(2);
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
        
        // Reset auto-calculated fields
        const autoFields = [
            'sss_display', 'philhealth_display', 'pagibig_display', 'other_deductions_display',
            'communication_allowance_display', 'laundry_allowance_display', 'rice_subsidy_display',
            'meal_allowance_display'
        ];
        autoFields.forEach(field => {
            document.getElementById(field).value = '0.00';
        });
        
        // Reset hidden fields
        const hiddenFields = [
            'sss', 'philhealth', 'pagibig', 'other_deductions',
            'communication_allowance', 'laundry_allowance', 'rice_subsidy',
            'meal_allowance'
        ];
        hiddenFields.forEach(field => {
            document.getElementById(field).value = '0';
        });
        
        document.getElementById('preview_basic').textContent = '0.00';
        document.getElementById('preview_allowances').textContent = '0.00';
        document.getElementById('preview_gross').textContent = '0.00';
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
    }

    // Event Listeners
    document.getElementById('employee_id').addEventListener('change', calculatePayroll);
    document.getElementById('pay_period_start').addEventListener('change', calculatePayroll);
    document.getElementById('pay_period_end').addEventListener('change', calculatePayroll);
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>