<?php
session_start();
require_once("dbconnection.php");

if(!isset($_SESSION["ID"])) exit;

$conn = dbconnection::connect();
$userID = $_SESSION["ID"];

$new = $_POST["new"] ?? null;
$current = $_POST["current"] ?? null;
$recoveryAnswer = $_POST["recovery"] ?? null;

// Función para generar contraseña segura
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-={}[]|:;<>,.?/';
    $password = '';
    $maxIndex = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $maxIndex)];
    }
    return $password;
}

// 1. Cambiar usando contraseña actual
if($current !== null){
    $stmt = $conn->prepare("SELECT Password FROM User WHERE ID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if(password_verify($current, $user['Password'])){
        $newHashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE User SET Password=? WHERE ID=?");
        $stmt->bind_param("si", $newHashed, $userID);
        $stmt->execute();

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "current"]);
    }
}
// 2. Cambiar usando respuesta de recuperación
elseif($recoveryAnswer !== null){
    $stmt = $conn->prepare("SELECT Recovery FROM User WHERE ID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if(strtolower(trim($recoveryAnswer)) === strtolower($user["Recovery"])){
        $newPassword = generatePassword(8); //contraseña generada
        $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE User SET Password=? WHERE ID=?");
        $stmt->bind_param("si", $newHashed, $userID);
        $stmt->execute();

        // Retornar la contraseña generada
        echo json_encode(["success" => true, "newPassword" => $newPassword]);
    } else {
        echo json_encode(["success" => false, "error" => "recovery"]);
    }
}
?>
