<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false,
    'cookie_samesite' => 'Strict'
]);

require_once 'conn.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

/* ---------------- METRICS TABLE ---------------- */
$conn->query("
    CREATE TABLE IF NOT EXISTS metrics (
        metric_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

/* Check if table empty */
$result = $conn->query("SELECT COUNT(*) AS cnt FROM metrics");
$row = $result->fetch_assoc();
if ($row['cnt'] == 0) {
    $conn->query("
        INSERT INTO metrics (name, description) VALUES
        ('Job Knowledge', 'Understanding of job duties and technical skills'),
        ('Work Quality', 'Accuracy, thoroughness, and reliability'),
        ('Attendance', 'Regularity and reliability of attendance'),
        ('Punctuality', 'Timeliness and meeting deadlines'),
        ('Productivity', 'Volume and efficiency of work'),
        ('Communication', 'Verbal and written communication skills'),
        ('Teamwork', 'Collaboration and team contribution'),
        ('Initiative', 'Self-motivation and proactiveness')
    ");
}

/* ---------------- FETCH METRICS ---------------- */
$metrics = [];
$metrics_result = $conn->query("SELECT metric_id, name, description FROM metrics ORDER BY metric_id ASC");
while ($m = $metrics_result->fetch_assoc()) {
    $metrics[] = $m;
}

/* ---------------- FETCH EMPLOYEES ---------------- */
$users_list = [];
$users_result = $conn->query("SELECT id, first_name, middle_initial, last_name FROM users ORDER BY first_name ASC");
while ($u = $users_result->fetch_assoc()) {
    $users_list[] = $u;
}

/* ---------------- FILTER VALUES ---------------- */
$filter_start = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 year')); // last year
$filter_end = $_GET['end_date'] ?? date('Y-m-d');


/* ---------------- FETCH REVIEWS ---------------- */
$reviews = [];
$reviews_sql = "
    SELECT 
        pr.id AS review_id,
        u.first_name, u.middle_initial, u.last_name,
        pr.review_start, pr.review_end, pr.overall_rating,
        pr.strengths, pr.areas_for_improvement, pr.goals, pr.reviewer_comments,
        a.username AS reviewer_username,
        pr.review_date
    FROM performance_reviews pr
    JOIN users u ON pr.user_id = u.id
    JOIN admin a ON pr.reviewer_id = a.admin_id
    WHERE pr.review_date BETWEEN '$filter_start' AND '$filter_end'
    ORDER BY pr.review_date DESC
";
$reviews_result = $conn->query($reviews_sql);

while ($r = $reviews_result->fetch_assoc()) {
    $metrics_sql = "SELECT m.name, rms.rating 
                    FROM performance_review_scores rms 
                    JOIN metrics m ON rms.metric_id = m.metric_id 
                    WHERE rms.review_id = ".$r['review_id'];
    $metrics_r = $conn->query($metrics_sql);
    $r['metrics'] = [];
    while ($mr = $metrics_r->fetch_assoc()) {
        $r['metrics'][] = $mr;
    }
    $reviews[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Performance Evaluation - HRIS</title>
<link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">

<style>
.modal-xl { max-width: 95%; }
.modal-content { border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
.modal-header { background: linear-gradient(135deg,#4154f1,#717ff5); color:white; border-radius:12px 12px 0 0; border:none; padding:20px 25px; }
.modal-header .modal-title { font-weight:600; font-size:1.3rem; }
.modal-header .btn-close { filter:invert(1); opacity:0.8; }
.modal-body { padding:25px; max-height:70vh; overflow-y:auto; }
.modal-footer { border-top:1px solid #e9ecef; padding:20px 25px; border-radius:0 0 12px 12px; }
.modal-body::-webkit-scrollbar { width:6px; }
.modal-body::-webkit-scrollbar-track { background:#f1f5ff; border-radius:10px; }
.modal-body::-webkit-scrollbar-thumb { background:linear-gradient(135deg,#4154f1,#717ff5); border-radius:10px; }
.modal .card { border:1px solid #e9ecef; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
.modal .card-body { padding:20px; }
.modal .form-control, .modal .form-select { border:2px solid #e2e8f0; border-radius:8px; padding:10px 15px; transition:0.3s; }
.modal .form-control:focus, .modal .form-select:focus { border-color:#4154f1; box-shadow:0 0 0 0.2rem rgba(65,84,241,0.15); }
.rating-badge { padding:4px 12px; border-radius:20px; font-size:0.85rem; font-weight:500; }
.rating-excellent { background:linear-gradient(135deg,#28a745,#20c997); color:white; }
.rating-good { background:linear-gradient(135deg,#17a2b8,#6f42c1); color:white; }
.rating-satisfied { background:linear-gradient(135deg,#ffc107,#fd7e14); color:white; }
.rating-poor { background:linear-gradient(135deg,#dc3545,#e83e8c); color:white; }
@media (max-width:768px) { .modal-xl { max-width:100%; margin:10px; } .modal-body { padding:15px; } .modal .card-body { padding:15px; } }
</style>
</head>
<body>

<?php require_once 'theme/navbar.php'; ?>
<?php require_once 'theme/sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Performance Evaluation Dashboard</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Performance Evaluation</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row mb-3">
            <div class="col-lg-12">
                <!-- FILTER FORM -->
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-md-3">
                        <label>Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($filter_start) ?>">
                    </div>
                    <div class="col-md-3">
                        <label>End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($filter_end) ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="evaluation.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="d-flex justify-content-end mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#evaluationModal">
                        <i class="bi bi-plus-circle"></i> New Evaluation
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Review Period</th>
                                        <th>Overall Rating</th>
                                        <th>Reviewer</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['first_name'].' '.$r['middle_initial'].' '.$r['last_name']) ?></td>
                                        <td><?= htmlspecialchars($r['review_start'].' to '.$r['review_end']) ?></td>
                                        <td>
                                            <span class="rating-badge rating-<?= strtolower($r['overall_rating']) ?>">
                                                <?= htmlspecialchars($r['overall_rating']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($r['reviewer_username']) ?></td>
                                        <td><?= htmlspecialchars($r['review_date']) ?></td>
                                        <td>
                                            <button class="btn btn-info btn-sm view-btn" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewEvaluationModal"
                                                    data-review='<?= json_encode($r) ?>'>
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<!-- NEW EVALUATION MODAL -->
<div class="modal fade" id="evaluationModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
    <h5 class="modal-title"><i class="bi bi-clipboard-plus"></i> New Performance Evaluation</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="POST" action="save_evaluation.php">
<input type="hidden" name="reviewer_id" value="<?= $_SESSION['admin_id'] ?>">
<div class="modal-body">
<div class="row mb-3">
    <div class="col-md-6">
        <label>Select Employee</label>
        <select class="form-select" name="user_id" required>
            <option value="">-- Select Employee --</option>
            <?php foreach ($users_list as $user): ?>
            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['first_name'].' '.$user['middle_initial'].' '.$user['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label>Review Start</label>
        <input type="date" class="form-control" name="review_start" required>
    </div>
    <div class="col-md-3">
        <label>Review End</label>
        <input type="date" class="form-control" name="review_end" required>
    </div>
</div>

<h6>Performance Ratings</h6>
<div class="row">
<?php foreach ($metrics as $metric): ?>
<div class="col-md-6 mb-3">
    <label><?= htmlspecialchars($metric['name']) ?> <small class="text-muted">(<?= htmlspecialchars($metric['description']) ?>)</small></label>
    <select class="form-select" name="metric_<?= $metric['metric_id'] ?>" required>
        <option value="Excellent">Excellent</option>
        <option value="Good">Good</option>
        <option value="Satisfied">Satisfied</option>
        <option value="Poor">Poor</option>
    </select>
</div>
<?php endforeach; ?>
</div>

<div class="row">
<div class="col-md-6 mb-3"><label>Strengths</label><textarea class="form-control" name="strengths" rows="3"></textarea></div>
<div class="col-md-6 mb-3"><label>Areas for Improvement</label><textarea class="form-control" name="areas_for_improvement" rows="3"></textarea></div>
<div class="col-md-6 mb-3"><label>Goals</label><textarea class="form-control" name="goals" rows="3"></textarea></div>
<div class="col-md-6 mb-3"><label>Reviewer Comments</label><textarea class="form-control" name="reviewer_comments" rows="3"></textarea></div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Submit Evaluation</button>
</div>
</form>
</div></div></div>

<!-- VIEW MODAL -->
<div class="modal fade" id="viewEvaluationModal" tabindex="-1">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
    <h5>Performance Evaluation Details</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="card mb-3">
<div class="card-body">
    <p><strong>Employee:</strong> <span id="viewEmployee"></span></p>
    <p><strong>Review Period:</strong> <span id="viewPeriod"></span></p>
    <p><strong>Overall Rating:</strong> <span id="viewOverall"></span></p>
    <p><strong>Reviewer:</strong> <span id="viewReviewer"></span></p>
    <p><strong>Date:</strong> <span id="viewDate"></span></p>
</div>
</div>

<div class="card mb-3">
<div class="card-body">
    <p><strong>Strengths:</strong> <p id="viewStrengths"></p></p>
    <p><strong>Areas for Improvement:</strong> <p id="viewAreas"></p></p>
    <p><strong>Goals:</strong> <p id="viewGoals"></p></p>
    <p><strong>Reviewer Comments:</strong> <p id="viewComments"></p></p>
</div>
</div>

<div class="card">
<div class="card-body">
    <h6>Performance Metrics</h6>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Rating</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody id="metricsTable"></tbody>
        <tfoot>
            <tr>
                <th colspan="2">Overall Score</th>
                <th id="viewOverallScore"></th>
            </tr>
        </tfoot>
    </table>
</div>
</div>

</div>
</div></div></div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.view-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const review = JSON.parse(this.dataset.review);
        document.getElementById('viewEmployee').textContent = `${review.first_name} ${review.middle_initial} ${review.last_name}`;
        document.getElementById('viewPeriod').textContent = `${review.review_start} to ${review.review_end}`;
        document.getElementById('viewOverall').textContent = review.overall_rating ?? 'Pending';
        document.getElementById('viewReviewer').textContent = review.reviewer_username;
        document.getElementById('viewDate').textContent = review.review_date;
        document.getElementById('viewStrengths').textContent = review.strengths ?? 'N/A';
        document.getElementById('viewAreas').textContent = review.areas_for_improvement ?? 'N/A';
        document.getElementById('viewGoals').textContent = review.goals ?? 'N/A';
        document.getElementById('viewComments').textContent = review.reviewer_comments ?? 'N/A';

        const ratingScores = { "Excellent":4, "Good":3, "Satisfied":2, "Poor":1 };
        let tbody = "";
        let totalScore = 0;
        review.metrics.forEach(m => {
            let score = ratingScores[m.rating] ?? 0;
            totalScore += score;
            tbody += `<tr><td>${m.name}</td><td>${m.rating}</td><td>${score}</td></tr>`;
        });
        let avgScore = review.metrics.length ? (totalScore / review.metrics.length).toFixed(2) : '0.00';
        document.getElementById("metricsTable").innerHTML = tbody;
        document.getElementById("viewOverallScore").textContent = avgScore;
    });
});
</script>
</body>
</html>
