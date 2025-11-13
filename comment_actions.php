<?php
session_start();
require_once('dbconnection.php');
$conn = dbConnection::connect();



$userId = $_SESSION['ID'] ?? null;

$action = $_POST['action'] ?? '';

if (!$userId || !$action) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// ====================== Añadir Comentario ======================
if ($action === 'add') {
    $postId = $_POST['post_id'] ?? null;
    $content = $_POST['content'] ?? '';
    if ($postId && $content) {
        $stmt = $conn->prepare("INSERT INTO Comment (Post_ID, User_ID, Content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $postId, $userId, $content);
        $stmt->execute();

        $conn->query("UPDATE Post SET Comments = Comments + 1 WHERE PostID = $postId");


        

        echo json_encode(['success' => true, 'comment_id' => $stmt->insert_id, 'content' => htmlspecialchars($content)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    }
}

// ====================== Editar Comentario ======================
elseif ($action === 'edit') {
    $commentId = $_POST['comment_id'] ?? null;
    $content = $_POST['content'] ?? '';
    if ($commentId && $content) {
        $stmt = $conn->prepare("SELECT User_ID FROM Comment WHERE CommentID = ?");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && ($result['User_ID'] == $userId || $_SESSION['UserType'] == 1)) {
            $updateStmt = $conn->prepare("UPDATE Comment SET Content = ? WHERE CommentID = ?");
            $updateStmt->bind_param("si", $content, $commentId);
            $updateStmt->execute();
            echo json_encode(['success' => true, 'comment_id' => $commentId, 'content' => htmlspecialchars($content)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar']);
        }
    }
}

// ====================== Eliminar Comentario ======================
elseif ($action === 'delete') {
    $commentId = $_POST['comment_id'] ?? null;
    if ($commentId) {
        $stmt = $conn->prepare("SELECT User_ID, Post_ID FROM Comment WHERE CommentID = ?");
        $stmt->bind_param("i", $commentId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && ($result['User_ID'] == $userId || $_SESSION['UserType'] == 1)) {
            $postId = $result['Post_ID'];
            $conn->query("DELETE FROM Comment WHERE CommentID = $commentId");
            $conn->query("UPDATE Post SET Comments = Comments - 1 WHERE PostID = $postId AND Comments > 0");
            echo json_encode([
                'success' => true,
                'comment_id' => $commentId,
                'post_id' => $postId  // ✅ esto permite actualizar el contador en el main
            ]);

        } else {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar']);
        }
    }
}
?>