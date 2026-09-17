<?php
require_once 'config.php';
session_start();

// Check if admin
if ($_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $attendance_id = intval($_POST['attendance_id']);
    $date = $_POST['date'];
    $time_in = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
    $time_out = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    try {
        $stmt = $conn->prepare("
            UPDATE attendance 
            SET time_in = ?, time_out = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $time_in, $time_out, $status, $notes, $attendance_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Attendance updated successfully";
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Failed to update attendance");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}