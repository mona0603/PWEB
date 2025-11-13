<?php
session_start();
header('Content-Type: application/json');
require_once('dbconnection.php');
$conn = dbConnection::connect();

$userId = $_SESSION['ID'] ?? null;
$userType = $_SESSION['UserType'] ?? 0; // por si en el futuro usas admins
$commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : null;
$content = $_POST['content'] ?? '';

if (!$userId || !$commentId || trim($content) === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid data or not logged in']);
    exit;
}

// opcional: limitar longitud
$maxLen = 2000;
if (mb_strlen($content) > $maxLen) {
    echo json_encode(['success' => false, 'error' => 'Content too long']);
    exit;
}

// Verificar propietario (o admin)
$stmt = $conn->prepare("SELECT User_ID FROM Comment WHERE CommentID = ?");
$stmt->bind_param("i", $commentId);
$stmt->execute();
$stmt->bind_result($ownerId);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    echo json_encode(['success' => false, 'error' => 'Comment not found']);
    exit;
}

if ($ownerId != $userId && $userType != 1) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ✅ Actualizar comentario y marcarlo como editado
$stmt = $conn->prepare("UPDATE Comment SET Content = ?, Edited = 1 WHERE CommentID = ?");
$stmt->bind_param("si", $content, $commentId);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'content' => htmlspecialchars($content), // para devolver listo para mostrar
        'updatedAt' => date("F j, Y H:i"),
        'edited' => true // ✅ indicador para el frontend
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'DB update failed']);
}
?>
