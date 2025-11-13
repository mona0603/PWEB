<?php
session_start();
require_once("dbconnection.php");
$conn = dbConnection::connect();

// Solo POST y usuario logueado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['ID'])) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Recibir datos
$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
$title = isset($_POST['title']) ? trim($_POST['title']) : '';


if (!$postId || $title === '') {  // título sigue siendo obligatorio
    http_response_code(400);
    echo "Invalid input";
    exit;
}


// Verificar que el post pertenece al usuario
$stmt = $conn->prepare("SELECT User_ID FROM Post WHERE PostID = ?");
$stmt->bind_param("i", $postId);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

if (!$post) {
    http_response_code(404);
    echo "Post not found";
    exit;
}

if ($post['User_ID'] != $_SESSION['ID']) {
    http_response_code(403);
    echo "You are not allowed to edit this post";
    exit;
}

// Actualizar contenido y marcar como editado
$stmt = $conn->prepare("UPDATE Post SET Content = ?, Title = ?, Edited = 1 WHERE PostID = ?");
$stmt->bind_param("ssi", $content, $title, $postId);

if ($stmt->execute()) {
    echo "success";
} else {
    http_response_code(500);
    echo "Database error: " . $stmt->error;
}
