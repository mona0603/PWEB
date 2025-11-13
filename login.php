<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

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

    // Buscar usuario por correo
    $stmt = $conn->prepare("SELECT * FROM User WHERE Email = ?");
    if (!$stmt) throw new RuntimeException("Prepare failed: " . $conn->error);

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        //Verificar si el usuario está desactivado
        if (isset($user["Deactivated"]) && (int)$user["Deactivated"] === 1) {
            echo json_encode([
                "success" => false,
                "error" => "Your account has been deactivated. Create a new one."
            ]);
            exit;
        }

        //Verificar contraseña
        if (password_verify($password, $user["Password"])) {
            $_SESSION["ID"] = $user["ID"];
            $_SESSION["Email"] = $user["Email"];
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "Incorrect password."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "User not found."]);
    }

    $conn->close();
} catch (Throwable $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
