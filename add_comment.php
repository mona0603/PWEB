<?php
session_start();
require_once('dbconnection.php');
$conn = dbConnection::connect();

$userId = $_SESSION['ID'] ?? null;
$postId = $_POST['post_id'] ?? null;
$content = $_POST['content'] ?? '';
$parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;

if ($userId && $postId && $content) {
    // Insertar comentario (soporta respuestas)
    $stmt = $conn->prepare("INSERT INTO Comment (Post_ID, User_ID, Content, ParentCommentID) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $postId, $userId, $content, $parentId); // ParentCommentID puede ser null
    $stmt->execute();

    $commentId = $stmt->insert_id; // ID del comentario nuevo

    // Actualizar contador de comentarios en Post
    $conn->query("UPDATE Post SET Comments = Comments + 1 WHERE PostID = $postId");

    // Obtener datos del usuario
    $stmtUser = $conn->prepare("
        SELECT u.Username, u.DisplayName, u.Avatar, u.Banner, u.Bio,
               cf.total_following, cf.total_followers,
               (SELECT 1 FROM Follower WHERE Follower_ID=? AND Following_ID=u.ID) AS already_following,
               u.UserType
        FROM User u
        LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
        WHERE u.ID=?
    ");
    $stmtUser->bind_param("ii", $userId, $userId);
    $stmtUser->execute();
    $userData = $stmtUser->get_result()->fetch_assoc();

    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'parent_id' => $parentId,
        'userID' => $userId,
        'userType' => $userData['UserType'],
        'username' => $userData['Username'],
        'displayName' => $userData['DisplayName'],
        'avatar' => "get_image.php?id={$userId}&type=avatar",
        'banner' => "get_image.php?id={$userId}&type=banner",
        'bio' => $userData['Bio'],
        'total_following' => $userData['total_following'] ?? 0,
        'total_followers' => $userData['total_followers'] ?? 0,
        'already_following' => $userData['already_following'],
        'loggedIn' => isset($_SESSION['ID']),
        'loggedInId' => $_SESSION['ID'] ?? 0,
        'content' => $content,
        'createdAt' => date("F j, Y H:i")
    ]);

}
?>