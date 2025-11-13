<?php
header('Content-Type: application/json; charset=utf-8');
require_once("dbconnection.php");
session_start();

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode(["success" => false, "error" => "Unsupported method"]);
        exit;
    }

    $conn = dbConnection::connect();

    $email = trim($_POST["Email"] ?? "");
    $password = $_POST["Password"] ?? "";

    if ($email === "" || $password === "") {
        echo json_encode(["success" => false, "error" => "Fill in both fields."]);
        exit;
    }

    // Buscar usuario desactivado
    $stmt = $conn->prepare("SELECT ID, Password, Deactivated FROM User WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ((int)$user["Deactivated"] === 1) {
            // Verificar contraseña
            if (password_verify($password, $user["Password"])) {
                // Reactivar cuenta
                $update = $conn->prepare("UPDATE User SET Deactivated = 0 WHERE ID = ?");
                $update->bind_param("i", $user["ID"]);
                $update->execute();
                $update->close();

                echo json_encode(["success" => true, "message" => "Account reactivated successfully!"]);
            } else {
                echo json_encode(["success" => false, "error" => "Incorrect password."]);
            }
        } else {
            echo json_encode(["success" => false, "error" => "This account is already active."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "User not found."]);
    }

    $conn->close();

} catch (Throwable $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
