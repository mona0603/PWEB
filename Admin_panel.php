<?php
require_once("dbconnection.php");
$conn = dbConnection::connect();

session_start();
if (!($_SESSION['isAdmin'] ?? false)) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$postId = $_POST['post_id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!$postId || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$status = ($action === 'approve') ? 'Approved' : 'Rejected';
$stmt = $conn->prepare("UPDATE Post SET Status = ? WHERE PostID = ?");
$stmt->bind_param("si", $status, $postId);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => "Post $status correctamente"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
}





