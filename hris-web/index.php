<?php
session_start();
require 'conn.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include_once 'theme/navbar.php';
include_once 'theme/sidebar.php';
include_once 'theme/template.php';
?>
