<?php
session_start();
require_once("dbconnection.php");

header('Content-Type: application/json');

$pediaID = intval($_POST['id'] ?? 0);
$userID = $_SESSION['ID'] ?? null;

// Solo ejecutar si el usuario está logueado
if ($userID && $pediaID > 0) {
    // Conectar
    $conn = dbConnection::connect();

    // Intentar insertar
    $stmt = $conn->prepare("INSERT INTO MyPediaViews (ID_MP, ViewerID) VALUES (?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $pediaID, $userID);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => "Vista registrada para ID $pediaID"]);
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