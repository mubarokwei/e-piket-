<?php
session_start();
require_once 'config/database.php';

// Log aktivitas
if (isset($_SESSION['user_id'])) {
    $conn = getDB();
    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user_id, aktivitas, ip_address) VALUES (?, 'Logout', ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("is", $_SESSION['user_id'], $ip);
    $stmt->execute();
}

session_destroy();
header("Location: index.php");
exit();
