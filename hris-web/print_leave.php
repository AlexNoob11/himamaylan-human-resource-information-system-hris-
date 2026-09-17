<?php
session_start();
require_once 'conn.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT lr.*, u.first_name, u.middle_initial, u.last_name, u.email
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die('Leave request not found.');
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Leave Request #<?= htmlspecialchars($row['id']) ?></title>
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #fff;
      color: #000;
    }
    .doc-container {
      border: 1px solid #000;
      padding: 40px;
      margin: 30px auto;
      max-width: 750px;
      font-size: 14px;
    }
    .doc-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .doc-header h2 {
      margin: 0;
      font-size: 22px;
      text-transform: uppercase;
    }
    .doc-subtitle {
      margin-top: 4px;
      font-size: 13px;
      font-style: italic;
    }
    .details-table td {
      padding: 5px 10px;
      vertical-align: top;
    }
    .details-table td.label {
      font-weight: bold;
      width: 180px;
    }
    .approval-section {
      border-top: 1px solid #000;
      margin-top: 50px;
      padding-top: 20px;
    }
    .approval-table {
      width: 100%;
      margin-top: 10px;
    }
    .approval-table td {
      padding: 10px;
      vertical-align: top;
    }
    .signature-line {
      display: inline-block;
      border-top: 1px solid #000;
      width: 220px;
      text-align: center;
      margin-top: 25px;
    }
    .status-box {
      display: inline-block;
      border: 1px solid #000;
      width: 14px;
      height: 14px;
      margin-right: 6px;
    }
  </style>
</head>
<body onload="window.print()">
<div class="doc-container">
  <div class="doc-header">
    <!-- <img src="assets/img/logo.png" alt="Company Logo" style="height:60px;"><br> -->
    <h2>UPC Bio Energy / Head Office Name</h2>
    <div class="doc-subtitle">Official Leave Request Form</div>
  </div>

  <table class="details-table" width="100%">
    <tr>
      <td class="label">Leave Request #</td>
      <td><?= htmlspecialchars($row['id']) ?></td>
    </tr>
    <tr>
      <td class="label">Employee Name</td>
      <td><?= htmlspecialchars($row['first_name'].' '.$row['middle_initial'].' '.$row['last_name']) ?></td>
    </tr>
    <tr>
      <td class="label">Email</td>
      <td><?= htmlspecialchars($row['email']) ?></td>
    </tr>
    <tr>
      <td class="label">Leave Type</td>
      <td><?= htmlspecialchars($row['leave_type']) ?></td>
    </tr>
    <tr>
      <td class="label">Start Date</td>
      <td><?= htmlspecialchars($row['start_date']) ?></td>
    </tr>
    <tr>
      <td class="label">End Date</td>
      <td><?= htmlspecialchars($row['end_date']) ?></td>
    </tr>
    <tr>
      <td class="label">Reason / Comments</td>
      <td><?= nl2br(htmlspecialchars($row['comments'])) ?></td>
    </tr>
    <tr>
      <td class="label">Date Filed</td>
      <td><?= htmlspecialchars($row['date_filed']) ?></td>
    </tr>
  </table>

  <!-- Approval Section -->
  <div class="approval-section">
    <h4>Head Office / Manager Approval</h4>
    <table class="approval-table">
      <tr>
        <td>
          <div><span class="status-box"></span> Approved</div>
          <div><span class="status-box"></span> Declined</div>
        </td>
        <td style="text-align:right;">
          <div class="signature-line">Signature over Printed Name</div>
          <div>Date: ___________________</div>
        </td>
      </tr>
    </table>

    <table class="approval-table" style="margin-top:30px;">
      <tr>
        <td>
          <div>Remarks / Conditions (if any):</div>
          <div style="border:1px solid #000; height:60px; margin-top:5px;"></div>
        </td>
      </tr>
    </table>
  </div>
</div>
</body>
</html>
