<?php
session_start();
require_once("dbconnection.php");

header('Content-Type: application/json');

$postID = intval($_POST['id'] ?? 0);
$userID = $_SESSION['ID'] ?? null;

// Solo ejecutar si el usuario está logueado
if ($userID && $postID > 0) {
    // Conectar
    $conn = dbConnection::connect();

    // Intentar insertar la vista
    $stmt = $conn->prepare("INSERT INTO PostViews (post_ID, ViewerID) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $postID, $userID);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "Vista registrada para el post ID $postID"]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    
    $conn->close();
} else {
    // No hacer nada si no está logueado o ID inválido
    echo json_encode(['status' => 'skipped']);
}
?>