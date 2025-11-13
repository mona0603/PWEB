<?php
session_start();
require_once("dbconnection.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = dbConnection::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['ID'])) {
    $userId = $_SESSION['ID'];
    $content = isset($_POST['content']) ? trim($_POST['content']) : ''; // 🔹 Valor por defecto vacío
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $topicId = $_POST['topic'] ?? null;
    $isNews = isset($_POST['is_news']) && $_POST['is_news'] == '1';

    $postType = $isNews ? 'News' : 'Post';

        // 🔹 Verificar tipo de usuario (para saber si es admin)
    $stmtRole = $conn->prepare("SELECT UserType FROM User WHERE ID = ?");
    $stmtRole->bind_param("i", $userId);
    $stmtRole->execute();
    $resultRole = $stmtRole->get_result();
    $user = $resultRole->fetch_assoc();
    $stmtRole->close();

    // 🔹 Si es admin (UserType = 1) => Approved, si no => Pending
    $status = ($user['UserType'] == 1) ? 'Approved' : 'Pending';

    $mediaData = null;
    $mediaType = null;

    // Manejar archivo (imagen o video)
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $mediaData = file_get_contents($_FILES['media']['tmp_name']);
        $mediaType = mime_content_type($_FILES['media']['tmp_name']);
    }

    if ($mediaData !== null) {
        $stmt = $conn->prepare(
            "INSERT INTO Post (User_ID, Content, Media, MediaType, PostType, Title, Status)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $null = NULL;
        $stmt->bind_param("isbssss", $userId, $content, $null, $mediaType, $postType, $title, $status);
        $stmt->send_long_data(2, $mediaData);
    } else {
        // 🔹 Insertar aunque content esté vacío
        $stmt = $conn->prepare(
            "INSERT INTO Post (User_ID, Content, Media, MediaType, PostType, Title, Status)
            VALUES (?, ?, NULL, NULL, ?, ?, ?)"
        );
        $stmt->bind_param("issss", $userId, $content, $postType, $title, $status);
    }

    if (!$stmt->execute()) {
        die("Error al insertar post: " . $stmt->error);
    }

    $postId = $stmt->insert_id;

    // Insertar relación con topic SOLO si NO es noticia y hay topic seleccionado
    if (!$isNews && $topicId) {
        $stmtTopic = $conn->prepare(
            "INSERT INTO PostTopic (Post_ID, Topic_ID) VALUES (?, ?)"
        );
        $stmtTopic->bind_param("ii", $postId, $topicId);
        $stmtTopic->execute();
    }

    $stmt->close();
    if (isset($stmtTopic))
        $stmtTopic->close();
    $conn->close();

    // Devolver JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'post' => [
            'id' => $postId,
            'userId' => $userId,
            'content' => htmlspecialchars($content),
            'title' => htmlspecialchars($title),
            'topicId' => !$isNews ? $topicId : null,
            'postType' => $postType,
            'status' => $status, // 🔹 ahora incluimos el status
            'hasMedia' => $mediaData !== null,
            'mediaType' => $mediaType
        ]
    ]);
    exit();
}
?>