<?php
require_once("dbconnection.php");
$conn = dbConnection::connect();

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT Media, MediaType FROM Post WHERE PostID = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row && !empty($row['Media'])) {
    header("Content-Type: " . $row['MediaType']);
    header('Content-Length: ' . strlen($row['Media']));
    echo $row['Media'];
    exit;
}

http_response_code(404);
exit;

