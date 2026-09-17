<?php
session_start();
require_once "conn.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

/* --------------------- VALIDATE REQUIRED FIELDS --------------------- */
if (
    !isset($_POST['user_id'], $_POST['reviewer_id'], $_POST['review_start'], $_POST['review_end'])
) {
    $_SESSION['success_message'] = "Missing required fields.";
    header("Location: evaluation.php");
    exit();
}

$user_id       = $_POST['user_id'];
$reviewer_id   = $_POST['reviewer_id'];
$review_start  = $_POST['review_start'];
$review_end    = $_POST['review_end'];

$strengths             = $_POST['strengths'] ?? '';
$areas_for_improvement = $_POST['areas_for_improvement'] ?? '';
$goals                 = $_POST['goals'] ?? '';
$reviewer_comments     = $_POST['reviewer_comments'] ?? '';

/* --------------------- GET ALL METRICS --------------------- */
$metrics_result = $conn->query("SELECT metric_id FROM metrics ORDER BY metric_id ASC");

$metric_scores = [];
$total_score = 0;
$score_count = 0;

/* Rating → score mapping */
$rating_to_score = [
    "Excellent" => 4,
    "Good"      => 3,
    "Satisfied" => 2,
    "Poor"      => 1
];

/* --------------------- COLLECT SCORE INPUTS --------------------- */
while ($m = $metrics_result->fetch_assoc()) {
    $metric_id = $m['metric_id'];
    $field = "metric_" . $metric_id;

    if (!isset($_POST[$field])) continue;

    $rating = $_POST[$field];

    if (!isset($rating_to_score[$rating])) continue;

    $score = $rating_to_score[$rating];

    $metric_scores[$metric_id] = $rating;
    $total_score += $score;
    $score_count++;
}

/* --------------------- COMPUTE OVERALL RATING --------------------- */
$avg = $score_count > 0 ? $total_score / $score_count : 0;

if ($avg >= 3.6)      $overall_rating = "Excellent";
elseif ($avg >= 2.6)  $overall_rating = "Good";
elseif ($avg >= 1.6)  $overall_rating = "Satisfied";
else                  $overall_rating = "Poor";

/* --------------------- INSERT MAIN REVIEW --------------------- */
$stmt = $conn->prepare("
    INSERT INTO performance_reviews 
    (user_id, reviewer_id, review_start, review_end, overall_rating, strengths, areas_for_improvement, goals, reviewer_comments, review_date)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$stmt->bind_param(
    "iisssssss",
    $user_id,
    $reviewer_id,
    $review_start,
    $review_end,
    $overall_rating,
    $strengths,
    $areas_for_improvement,
    $goals,
    $reviewer_comments
);

$stmt->execute();
$review_id = $stmt->insert_id;
$stmt->close();

/* --------------------- INSERT METRIC SCORES --------------------- */
$score_stmt = $conn->prepare("
    INSERT INTO performance_review_scores (review_id, metric_id, rating)
    VALUES (?, ?, ?)
");

foreach ($metric_scores as $metric_id => $rating) {
    $score_stmt->bind_param("iis", $review_id, $metric_id, $rating);
    $score_stmt->execute();
}

$score_stmt->close();

/* --------------------- SUCCESS --------------------- */
$_SESSION['success_message'] = "Performance evaluation submitted successfully!";
header("Location: evaluation.php");
exit();

?>
