<?php
require_once("dbConnection.php");
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION["ID"])) {
        echo "Acceso denegado";
        exit;
    }

    $conn = dbConnection::connect();

    // Verificar si la contraseña fue enviada
    if (isset($_POST['password'])) {
        $password = $_POST['password'];
    } else {
        echo "Contraseña no proporcionada.";
        exit;
    }

    $user_id = $_SESSION["ID"];

    // Obtener contraseña del usuario
    $stmt = $conn->prepare("SELECT Password FROM User WHERE ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verificar contraseña
        if (password_verify($password, $user["Password"])) {
            // Marcar cuenta como desactivada
            $stmt = $conn->prepare("UPDATE User SET Deactivated = 1 WHERE ID = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            session_destroy();
            echo "ok";
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "User not found.";
    }

    $conn->close();
}
?>
