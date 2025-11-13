<?php
session_start();
require_once("dbconnection.php");
$conn = dbConnection::connect();

$user_id = $_POST['user_id'] ?? null;
if ($user_id) {
    $stmt = $conn->prepare("UPDATE Notification SET IsRead=1 WHERE User_ID=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}
