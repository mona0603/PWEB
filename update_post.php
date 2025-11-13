<?php
require_once("dbconnection.php");
require_once("middleware/auth_admin.php");

$conn = dbConnection::connect();

// Verificar autenticación y rol admin
$auth = verifyAuthFusion($conn);
if (!$auth['loggedIn'] || !$auth['isAdmin']) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = intval($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$postId || !in_array($status, ['Approved', 'Rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }

    // Llamar la función MySQL para actualizar el estado
    $stmt = $conn->prepare("SELECT fn_update_post_status(?, ?) AS result");
    $stmt->bind_param("is", $postId, $status);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // === Obtener datos del post (autor y título) ===
    $stmt = $conn->prepare("SELECT User_ID, Title FROM Post WHERE PostID = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $stmt->bind_result($userId, $title);
    $stmt->fetch();
    $stmt->close();

    // === Crear mensaje personalizado ===
    $message = ($status === 'Approved')
        ? "Ha aprobado tu publicacion \"$title\""
        : "Ha rechazado tu publicacion \"$title\"";

    // === Insertar notificación tipo 'review' ===
    $stmt = $conn->prepare("
        INSERT INTO Notification (User_ID, Actor_ID, Type, Message)
        VALUES (?, ?, 'review', ?)
    ");
    $stmt->bind_param("iis", $userId, $auth['userID'], $message);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => $res['result'] ?? 'Estado actualizado y notificación enviada'
    ]);
}
?>
