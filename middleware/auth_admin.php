<?php
// middleware/auth_admin.php
require_once("dbconnection.php");

function verifyAuthFusion($conn) {
    // Evita doble session_start
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Si no hay sesión activa
    if (!isset($_SESSION['ID'])) {
        return [
            'loggedIn' => false,
            'isAdmin' => false,
            'userID' => null
        ];
    }

    $userID = $_SESSION['ID'];

    // Obtener tipo de usuario y estado
    $stmt = $conn->prepare("SELECT UserType, Deactivated FROM User WHERE ID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->bind_result($userType, $deactivated);
    $stmt->fetch();
    $stmt->close();

    // Validar si está activo
    if ($deactivated == 1) {
        session_unset();
        session_destroy();
        return [
            'loggedIn' => false,
            'isAdmin' => false,
            'userID' => null
        ];
    }

    // Retornar el estado de autenticación
    return [
        'loggedIn' => true,
        'isAdmin' => ($userType == 1),
        'userID' => $userID
    ];
}
