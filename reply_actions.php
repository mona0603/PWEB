```php
<?php
session_start();
require_once("dbconnection.php");
$conn = dbConnection::connect();

// Verificar login
if (!isset($_SESSION["ID"])) {
    echo json_encode(["success" => false, "error" => "Not logged in"]);
    exit;
}

$userId = $_SESSION["ID"];

// Datos enviados desde JS
$replyId = $_POST["reply_id"] ?? null;
$action  = $_POST["action"] ?? null;
$content = $_POST["content"] ?? null;

if (!$replyId || !$action) {
    echo json_encode(["success" => false, "error" => "Missing parameters"]);
    exit;
}

if ($action === "edit") {
    if (!$content || trim($content) === "") {
        echo json_encode(["success" => false, "error" => "Empty content"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE comments SET Content = ?, Edited = 1 WHERE CommentID = ? AND UserID = ?");
    $stmt->bind_param("sii", $content, $replyId, $userId);
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "newContent" => htmlspecialchars($content)]);
    } else {
        echo json_encode(["success" => false, "error" => "DB error"]);
    }
    $stmt->close();

} elseif ($action === "delete") {
    $stmt = $conn->prepare("DELETE FROM comments WHERE CommentID = ? AND UserID = ?");
    $stmt->bind_param("ii", $replyId, $userId);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "DB error"]);
    }
    $stmt->close();

} else {
    echo json_encode(["success" => false, "error" => "Invalid action"]);
}

$conn->close();
?>
```
