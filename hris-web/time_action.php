<?php
require_once 'config.php';
session_start();

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employee_id = $_SESSION['employee_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    try {
        if ($action == 'time_in') {
            // Check if already timed in today
            $check = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
            $check->bind_param("is", $employee_id, $today);
            $check->execute();
            $check->store_result();
            
            if ($check->num_rows > 0) {
                throw new Exception("You have already timed in today");
            }
            
            // Determine status (check if late)
            $status = 'Present';
            $office_start_time = '09:00:00'; // Adjust as needed
            if ($current_time > $office_start_time) {
                $status = 'Late';
            }
            
            // Insert new attendance record
            $stmt = $conn->prepare("
                INSERT INTO attendance (employee_id, date, time_in, status)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isss", $employee_id, $today, $current_time, $status);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Failed to record time in");
            }
            
        } elseif ($action == 'time_out') {
            $attendance_id = intval($_POST['attendance_id']);
            
            // Update time out
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET time_out = ?
                WHERE id = ? AND employee_id = ?
            ");
            $stmt->bind_param("sii", $current_time, $attendance_id, $employee_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Failed to record time out");
            }
        } else {
            throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}