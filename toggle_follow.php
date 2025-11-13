<?php
session_start();
require_once("dbconnection.php");

if (!isset($_SESSION["ID"])) {
    http_response_code(403);
    exit("Not authorized");
}

$seguidor_id = $_SESSION["ID"];
$seguido_id = $_POST["seguido_id"] ?? null;

if (!$seguido_id || !is_numeric($seguido_id)) {
    http_response_code(400);
    exit("Invalid ID");
}

$seguido_id = (int)$seguido_id;

if ($seguido_id === $seguidor_id) {
    http_response_code(400);
    exit("You can't follow yourself");
}

$conn = dbConnection::connect();

// Ver si ya sigue
$stmt = $conn->prepare("SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = ?");
$stmt->bind_param("ii", $seguidor_id, $seguido_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    // Ya sigue, borramos
    $del = $conn->prepare("DELETE FROM Follower WHERE Follower_ID = ? AND Following_ID = ?");
    $del->bind_param("ii", $seguidor_id, $seguido_id);
    if ($del->execute()) {
        echo "Follow"; // Texto del botón
    } else {
        http_response_code(500);
        echo "An error has appeared"; //Error al dejar de seguir
    }
} else {
    // No sigue, insertamos
    $add = $conn->prepare("INSERT INTO Follower (Follower_ID, Following_ID) VALUES (?, ?)");
    $add->bind_param("ii", $seguidor_id, $seguido_id);
    if ($add->execute()) {
        echo "Unfollow"; // Texto del botón
    
        // --- AÑADIR NOTIFICACIÓN ---
        $notif = $conn->prepare("
            INSERT INTO Notification (User_ID, Actor_ID, Type, Message)
            VALUES (?, ?, 'follow', ?)
        ");
        $message = "Started following you";
        $notif->bind_param("iis", $seguido_id, $seguidor_id, $message);
        $notif->execute();
        // --- FIN NOTIFICACIÓN ---
    
    }  else {
        http_response_code(500);
        echo "This action cannot be done"; //Error al seguir
    }
}

$conn->close();



?>
