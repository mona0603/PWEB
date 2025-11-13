<?php
require_once("dbconnection.php");
$conn = dbConnection::connect();

// Middleware: verificar que el usuario esté logeado
require_once("middleware/auth_like.php");
requireLoginForLike(); // Si no está logeado, termina la ejecución

$userId = $_SESSION['ID'];
$postId = $_POST['post_id'] ?? 0;

// Verificar si ya dio like (solo usuarios activos)
$stmt = $conn->prepare("
    SELECT pl.* 
    FROM PostLike pl
    JOIN User u ON pl.User_ID = u.ID
    WHERE pl.Post_ID = ? AND pl.User_ID = ? AND u.Deactivated = 0
");
$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Ya dio like -> quitar
    $stmtDel = $conn->prepare("DELETE FROM PostLike WHERE Post_ID = ? AND User_ID = ?");
    $stmtDel->bind_param("ii", $postId, $userId);
    $stmtDel->execute();

    $message = "unliked";
} else {
    // No dio like -> agregar
    $stmtIns = $conn->prepare("INSERT INTO PostLike (Post_ID, User_ID) VALUES (?, ?)");
    $stmtIns->bind_param("ii", $postId, $userId);
    $stmtIns->execute();

    $message = "liked";

        // 🟩 AGREGAR NOTIFICACIÓN (solo si el post no es del mismo usuario)
        $stmtOwner = $conn->prepare("SELECT User_ID FROM Post WHERE PostID = ?");
        $stmtOwner->bind_param("i", $postId);
        $stmtOwner->execute();
        $ownerResult = $stmtOwner->get_result()->fetch_assoc();
        $postOwnerID = $ownerResult['User_ID'] ?? null;
        $stmtOwner->close();
        
        if ($postOwnerID && $postOwnerID != $userId)
         {
            $notif = $conn->prepare("
                INSERT INTO Notification (User_ID, Actor_ID, Type, Message)
                VALUES (?, ?, 'like', ?)
            ");
            $notifMessage = "liked your post";
            $notif->bind_param("iis", $postOwnerID, $userId, $notifMessage);
            $notif->execute();
            $notif->close();
        }

}

// Contar likes solo de usuarios activos
$stmtCount = $conn->prepare("
    SELECT COUNT(*) AS Likes
    FROM PostLike pl
    JOIN User u ON pl.User_ID = u.ID
    WHERE pl.Post_ID = ? AND u.Deactivated = 0
");
$stmtCount->bind_param("i", $postId);
$stmtCount->execute();
$likesCount = $stmtCount->get_result()->fetch_assoc()['Likes'] ?? 0;

// Devolver JSON con el conteo actualizado
echo json_encode([
    "success" => true,
    "message" => $message,
    "likes" => (int)$likesCount
]);
?>
