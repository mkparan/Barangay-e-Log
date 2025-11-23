<?php
session_start();
include '../inc/db.php';
include '../inc/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $appointmentId = $_POST['appointment_id'];

    if ($action == 'approve') {
        // Code to approve the appointment
        $query = "UPDATE appointments SET status='approved' WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $appointmentId);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment approved successfully.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error approving appointment.";
            $_SESSION['msg_type'] = "danger";
        }
    } elseif ($action == 'reject') {
        // Code to reject the appointment
        $query = "UPDATE appointments SET status='rejected' WHERE id=?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $appointmentId);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment rejected successfully.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['message'] = "Error rejecting appointment.";
            $_SESSION['msg_type'] = "danger";
        }
    }

    header("Location: appointments.php");
    exit();
}
?>