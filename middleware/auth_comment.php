<?php
// middleware/auth_comment.php
session_start();

function requireLoginToComment() {
    if (!isset($_SESSION['ID'])) {
        // No hay sesión activa
        echo json_encode([
            "success" => false,
            "error" => "Must be logged to interact"
        ]);
        exit;
    }
}
?>
