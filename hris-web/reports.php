<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';

// --- Filters ---
$filter_employee = $_GET['employee'] ?? '';
$filter_department = $_GET['department'] ?? '';
$filter_year = $_GET['year'] ?? date('Y');
$filter_month = $_GET['month'] ?? '';
$filter_quarter = $_GET['quarter'] ?? '';
$report_type = $_GET['report_type'] ?? 'payslip';

// Quarter to months mapping
$quarterMonths = [
    'Q1' => [1, 2, 3],
    'Q2' => [4, 5, 6],
    'Q3' => [7, 8, 9],
    'Q4' => [10, 11, 12]
];

// --- Dynamic WHERE conditions ---
$where = [];
$params = [];
$paramTypes = '';

if ($filter_employee !== '') {
    $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ?)';
    $params[] = "%$filter_employee%";
    $params[] = "%$filter_employee%";
    $paramTypes .= 'ss';
}

if ($filter_department !== '') {
    $where[] = 'd.department_name = ?';
    $params[] = $filter_department;
    $paramTypes .= 's';
}

if ($filter_year !== '') {
    if ($report_type === 'payslip') {
        $where[] = 'YEAR(p.payment_date) = ?';
    } elseif ($report_type === 'leave') {
        $where[] = 'YEAR(l.start_date) = ?';
    } elseif ($report_type === 'attendance') {
        $where[] = 'YEAR(a.date) = ?';
    } elseif ($report_type === 'performance') {
        $where[] = 'YEAR(pr.review_date) = ?';
    }
    $params[] = (int)$filter_year;
    $paramTypes .= 'i';
}

if ($filter_month !== '') {
    if ($report_type === 'payslip') {
        $where[] = 'MONTH(p.payment_date) = ?';
    } elseif ($report_type === 'leave') {
        $where[] = 'MONTH(l.start_date) = ?';
    } elseif ($report_type === 'attendance') {
        $where[] = 'MONTH(a.date) = ?';
    } elseif ($report_type === 'performance') {
        $where[] = 'MONTH(pr.review_date) = ?';
    }
    $params[] = (int)$filter_month;
    $paramTypes .= 'i';
}

if ($filter_quarter !== '' && isset($quarterMonths[$filter_quarter])) {
    if ($report_type === 'payslip') {
        $where[] = 'MONTH(p.payment_date) IN (' . implode(',', $quarterMonths[$filter_quarter]) . ')';
    } elseif ($report_type === 'leave') {
        $where[] = 'MONTH(l.start_date) IN (' . implode(',', $quarterMonths[$filter_quarter]) . ')';
    } elseif ($report_type === 'attendance') {
        $where[] = 'MONTH(a.date) IN (' . implode(',', $quarterMonths[$filter_quarter]) . ')';
    } elseif ($report_type === 'performance') {
        $where[] = 'MONTH(pr.review_date) IN (' . implode(',', $quarterMonths[$filter_quarter]) . ')';
    }
}

// --- Fetch data based on report type ---
$reportRows = [];
$totalGross = 0;
$totalNet = 0;
$totalLeaveCount = 0;

if ($report_type === 'payslip') {
    $sql = "SELECT p.*, u.first_name, u.middle_initial, u.last_name, d.department_name,
                   u.id as user_id
            FROM payslips p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN departments d ON u.department_id = d.id";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY p.payment_date DESC";

    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $reportRows = $res->fetch_all(MYSQLI_ASSOC);

    foreach ($reportRows as $r) {
        $totalGross += $r['gross_pay'];
        $totalNet += $r['net_pay'];
    }
} 
elseif ($report_type === 'leave') {
    $sql = "SELECT l.*, 
               u.first_name,
               u.middle_initial,
               u.last_name,
               u.id as user_id,
               d.department_name,
               a.username as approver_name
        FROM leave_applications l
        JOIN users u ON l.employee_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN admin a ON l.hr_approver_id = a.admin_id";   
    
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY l.start_date DESC";
    
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $reportRows = $res->fetch_all(MYSQLI_ASSOC);
    
    $totalLeaveCount = count($reportRows);
}
elseif ($report_type === 'attendance') {
    $sql = "SELECT a.*, u.first_name, u.middle_initial, u.last_name, u.id as user_id,
                   d.department_name
            FROM attendance a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN departments d ON u.department_id = d.id";
    
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY a.date DESC";
    
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $reportRows = $res->fetch_all(MYSQLI_ASSOC);
}
elseif ($report_type === 'performance') {
    $sql = "SELECT pr.*, 
               u.first_name, u.middle_initial, u.last_name, u.id as user_id,
               d.department_name,
               a.username as reviewer_name
        FROM performance_reviews pr
        JOIN users u ON pr.user_id = u.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN admin a ON pr.reviewer_id = a.admin_id";
    
    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY pr.review_date DESC";
    
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param($paramTypes, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $reportRows = $res->fetch_all(MYSQLI_ASSOC);
}

// Fetch employees for dropdown
$employees = [];
$empRes = $conn->query("SELECT id, CONCAT(first_name, ' ', COALESCE(middle_initial, ''), ' ', last_name) as full_name FROM users ORDER BY first_name");
while($row = $empRes->fetch_assoc()) {
    $employees[] = $row;
}

// Fetch departments for dropdown
$departments = [];
$deptRes = $conn->query("SELECT id, department_name FROM departments ORDER BY department_name");
while($row = $deptRes->fetch_assoc()) {
    $departments[] = $row;
}

// --- Analytics Data Preparation ---
$analytics = [];
$chartLabels = [];
$chartData = [];

if ($reportRows) {
    // Prepare monthly data for charts
    $monthlyData = [];
    $departmentData = [];
    $statusData = [];
    
    foreach($reportRows as $row) {
        $month = date('M', strtotime($report_type === 'payslip' ? $row['payment_date'] : 
                                     ($report_type === 'leave' ? $row['start_date'] : 
                                     ($report_type === 'attendance' ? $row['date'] : $row['review_date']))));
        
        // Monthly aggregation
        if (!isset($monthlyData[$month])) {
            $monthlyData[$month] = 0;
        }
        
        switch($report_type) {
            case 'payslip':
                $monthlyData[$month] += $row['net_pay'];
                break;
            case 'leave':
                $monthlyData[$month] += 1;
                break;
            case 'attendance':
                if (!isset($monthlyData[$month])) {
                    $monthlyData[$month] = ['present' => 0, 'total' => 0];
                }
                $monthlyData[$month]['total'] += 1;
                if ($row['status'] === 'Present') {
                    $monthlyData[$month]['present'] += 1;
                }
                break;
            case 'performance':
                $ratingValue = match($row['overall_rating']) {
                    'Excellent' => 5,
                    'Good' => 4,
                    'Satisfied' => 3,
                    'Poor' => 2,
                    default => 0
                };
                if (!isset($monthlyData[$month])) {
                    $monthlyData[$month] = ['sum' => 0, 'count' => 0];
                }
                $monthlyData[$month]['sum'] += $ratingValue;
                $monthlyData[$month]['count'] += 1;
                break;
        }
        
        // Department aggregation
        if ($row['department_name']) {
            if (!isset($departmentData[$row['department_name']])) {
                $departmentData[$row['department_name']] = 0;
            }
            $departmentData[$row['department_name']] += 
                $report_type === 'payslip' ? $row['net_pay'] : 1;
        }
        
        // Status aggregation (for leave and attendance)
        if (in_array($report_type, ['leave', 'attendance']) && isset($row['status'])) {
            if (!isset($statusData[$row['status']])) {
                $statusData[$row['status']] = 0;
            }
            $statusData[$row['status']] += 1;
        }
    }
    
    // Prepare chart data
    $chartLabels = array_keys($monthlyData);
    foreach($monthlyData as $data) {
        if ($report_type === 'attendance') {
            $chartData[] = $data['total'] > 0 ? round(($data['present'] / $data['total']) * 100, 2) : 0;
        } elseif ($report_type === 'performance') {
            $chartData[] = $data['count'] > 0 ? round($data['sum'] / $data['count'], 2) : 0;
        } else {
            $chartData[] = $data;
        }
    }
    
    $analytics = [
        'monthlyData' => $monthlyData,
        'departmentData' => $departmentData,
        'statusData' => $statusData,
        'chartLabels' => $chartLabels,
        'chartData' => $chartData
    ];
}

// --- Summary Statistics ---
$summary = [];
if ($report_type === 'payslip' && $reportRows) {
    $totalEmployees = count(array_unique(array_column($reportRows, 'user_id')));
    $averageGross = $totalGross / max($totalEmployees, 1);
    $averageNet = $totalNet / max($totalEmployees, 1);
    $maxGross = max(array_column($reportRows, 'gross_pay'));
    $minGross = min(array_column($reportRows, 'gross_pay'));
    
    $summary = [
        ['label' => 'Total Employees', 'value' => $totalEmployees, 'icon' => 'bi-people', 'color' => 'primary'],
        ['label' => 'Total Gross Pay', 'value' => '₱' . number_format($totalGross, 2), 'icon' => 'bi-cash-stack', 'color' => 'success'],
        ['label' => 'Total Net Pay', 'value' => '₱' . number_format($totalNet, 2), 'icon' => 'bi-wallet2', 'color' => 'info'],
        ['label' => 'Avg Gross/Employee', 'value' => '₱' . number_format($averageGross, 2), 'icon' => 'bi-graph-up', 'color' => 'warning'],
        ['label' => 'Avg Net/Employee', 'value' => '₱' . number_format($averageNet, 2), 'icon' => 'bi-graph-down', 'color' => 'secondary'],
        ['label' => 'Highest Gross', 'value' => '₱' . number_format($maxGross, 2), 'icon' => 'bi-arrow-up', 'color' => 'danger'],
        ['label' => 'Lowest Gross', 'value' => '₱' . number_format($minGross, 2), 'icon' => 'bi-arrow-down', 'color' => 'dark']
    ];
} 
elseif ($report_type === 'leave' && $reportRows) {
    $statusCounts = array_count_values(array_column($reportRows, 'status'));
    $leaveTypes = array_count_values(array_column($reportRows, 'leave_type_name'));
    $avgDuration = array_sum(array_map(function($r) {
        return (strtotime($r['end_date']) - strtotime($r['start_date'])) / (60 * 60 * 24);
    }, $reportRows)) / max(count($reportRows), 1);
    
    $summary = [
        ['label' => 'Total Leaves', 'value' => $totalLeaveCount, 'icon' => 'bi-calendar-check', 'color' => 'primary'],
        ['label' => 'Approved', 'value' => $statusCounts['Approved'] ?? 0, 'icon' => 'bi-check-circle', 'color' => 'success'],
        ['label' => 'Pending', 'value' => $statusCounts['Pending'] ?? 0, 'icon' => 'bi-clock', 'color' => 'warning'],
        ['label' => 'Rejected', 'value' => $statusCounts['Rejected'] ?? 0, 'icon' => 'bi-x-circle', 'color' => 'danger'],
        ['label' => 'Avg Duration', 'value' => round($avgDuration, 1) . ' days', 'icon' => 'bi-calendar-range', 'color' => 'info'],
        ['label' => 'Unique Types', 'value' => count($leaveTypes), 'icon' => 'bi-tags', 'color' => 'secondary']
    ];
}
elseif ($report_type === 'attendance' && $reportRows) {
    $statusCounts = array_count_values(array_column($reportRows, 'status'));
    $totalRecords = count($reportRows);
    $attendanceRate = ($statusCounts['Present'] ?? 0) / max($totalRecords, 1) * 100;
    $lateRate = ($statusCounts['Late'] ?? 0) / max($totalRecords, 1) * 100;
    
    $summary = [
        ['label' => 'Total Records', 'value' => $totalRecords, 'icon' => 'bi-calendar-day', 'color' => 'primary'],
        ['label' => 'Present', 'value' => $statusCounts['Present'] ?? 0, 'icon' => 'bi-check-lg', 'color' => 'success'],
        ['label' => 'Absent', 'value' => $statusCounts['Absent'] ?? 0, 'icon' => 'bi-x-lg', 'color' => 'danger'],
        ['label' => 'Late', 'value' => $statusCounts['Late'] ?? 0, 'icon' => 'bi-clock-history', 'color' => 'warning'],
        ['label' => 'Attendance Rate', 'value' => round($attendanceRate, 1) . '%', 'icon' => 'bi-graph-up-arrow', 'color' => 'info'],
        ['label' => 'Late Rate', 'value' => round($lateRate, 1) . '%', 'icon' => 'bi-exclamation-triangle', 'color' => 'secondary']
    ];
}
elseif ($report_type === 'performance' && $reportRows) {
    $ratings = array_map(function($r) {
        return match($r['overall_rating']) {
            'Excellent' => 5,
            'Good' => 4,
            'Satisfied' => 3,
            'Poor' => 2,
            default => 0
        };
    }, $reportRows);
    
    $ratingDistribution = array_count_values(array_column($reportRows, 'overall_rating'));
    $totalReviews = count($reportRows);
    $avgRating = array_sum($ratings) / max($totalReviews, 1);
    
    $summary = [
        ['label' => 'Total Reviews', 'value' => $totalReviews, 'icon' => 'bi-file-text', 'color' => 'primary'],
        ['label' => 'Average Rating', 'value' => round($avgRating, 2) . '/5', 'icon' => 'bi-star-half', 'color' => 'warning'],
        ['label' => 'Excellent', 'value' => $ratingDistribution['Excellent'] ?? 0, 'icon' => 'bi-star-fill', 'color' => 'success'],
        ['label' => 'Good', 'value' => $ratingDistribution['Good'] ?? 0, 'icon' => 'bi-star', 'color' => 'info'],
        ['label' => 'Satisfied', 'value' => $ratingDistribution['Satisfied'] ?? 0, 'icon' => 'bi-star-half', 'color' => 'secondary'],
        ['label' => 'Poor', 'value' => $ratingDistribution['Poor'] ?? 0, 'icon' => 'bi-star', 'color' => 'danger']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytical Reports - HRIS UPC BioEnergy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
<style>
.analytics-card {
    transition: transform 0.3s;
    border-radius: 10px;
    overflow: hidden;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.stat-icon {
    font-size: 2.5rem;
    opacity: 0.8;
}
.chart-container {
    height: 300px;
    position: relative;
}
.export-btn-group .btn {
    border-radius: 5px !important;
}
.filter-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    padding: 25px;
    color: white;
    margin-bottom: 30px;
}
.filter-section .form-label {
    color: white;
    font-weight: 500;
}
.filter-section .form-control, 
.filter-section .form-select {
    background: rgba(255,255,255,0.9);
    border: 1px solid rgba(255,255,255,0.3);
}
.filter-section .form-control:focus, 
.filter-section .form-select:focus {
    background: white;
    border-color: white;
    box-shadow: 0 0 0 0.25rem rgba(255,255,255,0.25);
}
.report-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}
</style>
</head>
<body>
<main id="main" class="main">
<div class="pagetitle">
<h1><i class="bi bi-graph-up"></i> Analytical Reports</h1>
<nav>
<ol class="breadcrumb">
<li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door"></i> Home</a></li>
<li class="breadcrumb-item active"><i class="bi bi-bar-chart-line"></i> Reports</li>
</ol>
</nav>
</div>

<div class="report-header">
    <div class="row">
        <div class="col-md-8">
            <h2 class="mb-1"><?= ucfirst($report_type) ?> Report Analysis</h2>
            <p class="mb-0">Comprehensive insights and analytics for better decision making</p>
        </div>
        <div class="col-md-4 text-end">
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-gear"></i> Report Actions
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="bi bi-file-earmark-excel"></i> Export to Excel</a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf"></i> Export to PDF</a></li>
                    <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="bi bi-printer"></i> Print Report</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#"><i class="bi bi-save"></i> Save Report</a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<section class="section mb-4">
    <div class="filter-section">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Report Type</label>
                <select name="report_type" class="form-select" onchange="this.form.submit()">
                    <option value="payslip" <?= $report_type==='payslip'?'selected':'' ?>><i class="bi bi-cash-coin"></i> Payroll</option>
                    <option value="leave" <?= $report_type==='leave'?'selected':'' ?>><i class="bi bi-calendar-event"></i> Leave</option>
                    <option value="attendance" <?= $report_type==='attendance'?'selected':'' ?>><i class="bi bi-clock-history"></i> Attendance</option>
                    <option value="performance" <?= $report_type==='performance'?'selected':'' ?>><i class="bi bi-star"></i> Performance</option>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Employee</label>
                <select name="employee" class="form-select">
                    <option value="">All Employees</option>
                    <?php foreach($employees as $emp): ?>
                    <option value="<?= htmlspecialchars($emp['full_name']) ?>" <?= ($filter_employee == $emp['full_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['id'] . ' - ' . $emp['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Department</label>
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept['department_name']) ?>" <?= ($filter_department == $dept['department_name']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept['department_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Year</label>
                <input type="number" name="year" class="form-control" value="<?= htmlspecialchars($filter_year) ?>" min="2000" max="<?= date('Y') ?>">
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Month</label>
                <select name="month" class="form-select">
                    <option value="">All Months</option>
                    <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($filter_month == $m) ? 'selected' : '' ?>>
                        <?= date("F", mktime(0,0,0,$m,1)) ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-lg-2 col-md-6">
                <label class="form-label">Quarter</label>
                <select name="quarter" class="form-select">
                    <option value="">All Quarters</option>
                    <option value="Q1" <?= ($filter_quarter == 'Q1') ? 'selected' : '' ?>>Q1 (Jan-Mar)</option>
                    <option value="Q2" <?= ($filter_quarter == 'Q2') ? 'selected' : '' ?>>Q2 (Apr-Jun)</option>
                    <option value="Q3" <?= ($filter_quarter == 'Q3') ? 'selected' : '' ?>>Q3 (Jul-Sep)</option>
                    <option value="Q4" <?= ($filter_quarter == 'Q4') ? 'selected' : '' ?>>Q4 (Oct-Dec)</option>
                </select>
            </div>

            <div class="col-12 text-end mt-3">
                <button type="submit" class="btn btn-light me-2"><i class="bi bi-funnel-fill"></i> Apply Filters</button>
                <a href="reports.php" class="btn btn-outline-light"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
            </div>
        </form>
    </div>
</section>

<?php if(!empty($summary)): ?>
<section class="section mb-4">
    <div class="row">
        <?php foreach($summary as $stat): ?>
        <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
            <div class="card analytics-card border-<?= $stat['color'] ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-8">
                            <h6 class="card-title text-muted"><?= $stat['label'] ?></h6>
                            <h3 class="card-text"><?= $stat['value'] ?></h3>
                        </div>
                        <div class="col-4 text-end">
                            <i class="bi <?= $stat['icon'] ?> text-<?= $stat['color'] ?> stat-icon"></i>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height: 5px;">
                        <div class="progress-bar bg-<?= $stat['color'] ?>" role="progressbar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section mb-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-graph-up"></i> <?= ucfirst($report_type) ?> Trend Analysis</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-pie-chart"></i> Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-table"></i> Detailed Report</h5>
            <div class="export-btn-group">
                <button class="btn btn-success btn-sm" onclick="exportTableToExcel()">
                    <i class="bi bi-file-excel"></i> Excel
                </button>
                <button class="btn btn-danger btn-sm" onclick="exportTableToPDF()">
                    <i class="bi bi-file-pdf"></i> PDF
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="reportTable" style="width:100%">
                    <thead>
                        <tr class="table-dark">
                            <th>#</th>
                            <th>Employee ID</th>
                            <th>Employee</th>
                            <th>Department</th>
                            
                            <?php if($report_type==='payslip'): ?>
                                <th>Pay Period</th><th>Gross Pay</th><th>Deductions</th><th>Net Pay</th><th>Payment Date</th>
                            <?php elseif($report_type==='leave'): ?>
                                <th>Leave Type</th><th>Period</th><th>Duration</th><th>Status</th><th>Approver</th>
                            <?php elseif($report_type==='attendance'): ?>
                                <th>Date</th><th>Time In/Out</th><th>Total Hours</th><th>Status</th><th>Remarks</th>
                            <?php elseif($report_type==='performance'): ?>
                                <th>Review Date</th><th>Rating</th><th>Reviewer</th><th>Strengths</th><th>Areas for Improvement</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                   <tbody>
                    <?php
                    $count = 1;

                    foreach ($reportRows as $row):
                    ?>
                    <tr>
                        <td><?= $count++ ?></td>
                        <td>EMP-<?= str_pad($row['user_id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td><?= htmlspecialchars($row['first_name'].' '.($row['middle_initial'] ?? '').' '.$row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['department_name'] ?? '-') ?></td>

                        <?php if($report_type==='payslip'): ?>
                            <td><?= date('M d', strtotime($row['pay_period_start'])).' - '.date('M d, Y', strtotime($row['pay_period_end'])) ?></td>
                            <td class="fw-bold">₱<?= number_format($row['gross_pay'],2) ?></td>
                            <td class="text-danger">₱<?= number_format($row['gross_pay'] - $row['net_pay'],2) ?></td>
                            <td class="fw-bold text-success">₱<?= number_format($row['net_pay'],2) ?></td>
                            <td><?= date('M d, Y', strtotime($row['payment_date'])) ?></td>

                        <?php elseif($report_type==='leave'): ?>
                            <td><?= htmlspecialchars($row['leave_type_name']) ?></td>
                            <td><?= date('M d', strtotime($row['start_date'])).' - '.date('M d, Y', strtotime($row['end_date'])) ?></td>
                            <td>
                                <?php
                                $days = (strtotime($row['end_date']) - strtotime($row['start_date'])) / (60*60*24) + 1;
                                echo $days . ' day' . ($days > 1 ? 's' : '');
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $row['status'] == 'Approved' ? 'success' : ($row['status'] == 'Pending' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['approver_name'] ?? 'Pending') ?></td>

                        <?php elseif($report_type==='attendance'): ?>
                            <td><?= date('D, M d, Y', strtotime($row['date'])) ?></td>
                            <td>
                                <small>
                                    <?= $row['am_in'] ? 'AM In: '.htmlspecialchars($row['am_in']).'<br>' : '' ?>
                                    <?= $row['am_out'] ? 'AM Out: '.htmlspecialchars($row['am_out']).'<br>' : '' ?>
                                    <?= $row['pm_in'] ? 'PM In: '.htmlspecialchars($row['pm_in']).'<br>' : '' ?>
                                    <?= $row['pm_out'] ? 'PM Out: '.htmlspecialchars($row['pm_out']) : '' ?>
                                </small>
                            </td>
                            <td>
                                <?php
                                $totalHours = 0;
                                if ($row['am_in'] && $row['am_out']) {
                                    $totalHours += (strtotime($row['am_out']) - strtotime($row['am_in'])) / 3600;
                                }
                                if ($row['pm_in'] && $row['pm_out']) {
                                    $totalHours += (strtotime($row['pm_out']) - strtotime($row['pm_in'])) / 3600;
                                }
                                echo round($totalHours, 2) . ' hrs';
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $row['status'] == 'Present' ? 'success' : ($row['status'] == 'Late' ? 'warning' : 'danger') ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['remarks'] ?? '-') ?></td>

                        <?php elseif($report_type==='performance'): ?>
                            <td><?= date('M d, Y', strtotime($row['review_date'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $row['overall_rating'] == 'Excellent' ? 'success' : ($row['overall_rating'] == 'Good' ? 'primary' : ($row['overall_rating'] == 'Satisfied' ? 'info' : 'danger')) ?>">
                                    <?= htmlspecialchars($row['overall_rating']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['reviewer_name'] ?? '-') ?></td>
                            <td><small><?= htmlspecialchars($row['strengths'] ?? '-') ?></small></td>
                            <td><small><?= htmlspecialchars($row['areas_for_improvement'] ?? '-') ?></small></td>
                        <?php endif; ?>

                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
</main>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    $('#reportTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        language: {
            emptyTable: "No data found matching the selected filters."
        }
    });
});
</script>
</body>
</html>