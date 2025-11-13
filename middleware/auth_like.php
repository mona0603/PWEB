<?php
// middleware/auth_like.php
session_start();

function requireLoginForLike() {
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
