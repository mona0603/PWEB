<?php
require_once("dbconnection.php");
require_once("middleware/auth_admin.php");


header('Content-Type: application/json'); 

$conn = dbConnection::connect();
$auth = verifyAuthFusion($conn);

if (!$auth['loggedIn']) {
    die("You must be logged in to delete posts.");
}

if (!isset($_POST['post_id'])) {
    die("Post ID is required.");
}

$postId = (int) $_POST['post_id'];
$userId = $auth['userID'];
$isAdmin = $auth['isAdmin'];

// --- Obtener el post ---
$stmt = $conn->prepare("SELECT User_ID FROM Post WHERE PostID = ?");
$stmt->bind_param("i", $postId);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

if (!$post) {
    die("Post not found.");
}

// --- Solo el autor o el admin pueden eliminar ---
if ($post['User_ID'] != $userId && !$isAdmin) {
    die("You are not allowed to delete this post.");
}

// --- Eliminar el post ---
$stmtDel = $conn->prepare("DELETE FROM Post WHERE PostID = ?");
$stmtDel->bind_param("i", $postId);
$stmtDel->execute();

header("Location: MAINPAGE.php");
exit();
?>
