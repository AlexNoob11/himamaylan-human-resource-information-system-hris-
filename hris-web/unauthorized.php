<?php
session_start();
require_once 'conn.php';
require_once 'theme/navbar.php';
require_once 'theme/sidebar.php';
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
</head>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Unauthorized Access</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Unauthorized</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="py-5">
                            <div class="mb-4">
                                <i class="bi bi-exclamation-octagon-fill text-danger" style="font-size: 5rem;"></i>
                            </div>
                            <h1 class="display-5 fw-bold text-danger">Access Denied</h1>
                            <p class="lead mb-4">You don't have permission to access this page.</p>
                            
                            <?php if (isset($_SESSION['username'])): ?>
                                <p>You are logged in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>.</p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-center gap-3 mt-4">
                                <a href="index.php" class="btn btn-primary">
                                    <i class="bi bi-house-door"></i> Go to Dashboard
                                </a>
                                <a href="logout.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
