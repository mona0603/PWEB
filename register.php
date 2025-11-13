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
    $fecha_nacimiento = $_POST["Birthdate"] ?? "";
    $pass_plana = $_POST["Password"] ?? "";
    $recovery = $_POST["Recovery"] ?? "";

    if ($email === "" || $fecha_nacimiento === "" || $pass_plana === "" || $recovery === "") {
        echo json_encode(["success" => false, "error" => "Fill the remaining fields."]);
        exit;
    }

    //Verificar si el correo ya existe
    $checkEmail = $conn->prepare("SELECT Email, Deactivated FROM User WHERE Email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkResult = $checkEmail->get_result();

    if ($checkResult->num_rows > 0) {
        $user = $checkResult->fetch_assoc();
        if ((int)$user["Deactivated"] === 1) {
            echo json_encode([
                "success" => false,
                "deactivated" => true, //Esta desactivada
                "error" => "This email is associated with a deactivated account."
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "deactivated" => false,
                "error" => "This email is already registered."
            ]);
        }
        exit;
    }

    //Validar edad
    $fecha_nac = new DateTime($fecha_nacimiento);
    $edad = (new DateTime())->diff($fecha_nac)->y;
    if ($edad < 18) {
        echo json_encode(["success" => false, "error" => "You must be at least 18 to register."]);
        exit;
    }

    //Validar contraseña
    if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $pass_plana)) {
        echo json_encode(["success" => false, "error" => "The password must be at least 8 characters, include an uppercase letter, a number, and a special character."]);
        exit;
    }

    $rutaFoto = file_get_contents("img/avatar.jpg");
    $rutaBanner = file_get_contents("img/banner.png");
    $hash = password_hash($pass_plana, PASSWORD_DEFAULT);
    $tipo = "0";
    $estadoCuenta = "0";
    $tempName = "UserTemp";

    //Registrar usuario
    $stmt = $conn->prepare("CALL RegisterUser(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new RuntimeException("Error in prepare: " . $conn->error);
    }

    $stmt->bind_param("ssssssssss", $tempName, $tempName, $email, $fecha_nacimiento, $hash, $recovery, $rutaFoto, $rutaBanner, $tipo, $estadoCuenta);
    $stmt->execute();

    //Limpiar posibles result sets del CALL
    while ($conn->more_results() && $conn->next_result()) { /* limpiar */ }

    //Obtener el ID del usuario insertado
    $result = $conn->query("SELECT ID FROM User WHERE Email = '" . $conn->real_escape_string($email) . "' LIMIT 1");
    if (!$result) throw new RuntimeException("Error fetching user ID: " . $conn->error);

    $userId = $result->fetch_assoc()["ID"];

    //Actualizar Username y DisplayName
    $username = "User" . $userId;
    $displayname = "User" . $userId;

    $update = $conn->prepare("UPDATE User SET Username = ?, DisplayName = ? WHERE ID = ?");
    $update->bind_param("ssi", $username, $displayname, $userId);
    $update->execute();

    echo json_encode(["success" => true]);

} catch (Throwable $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
