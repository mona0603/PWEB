<?php
// get_image.php
require_once("dbconnection.php");

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    http_response_code(400);
    exit("Missing parameters.");
}

$id = intval($_GET['id']);
$type = $_GET['type']; // "avatar" o "banner"

$conn = dbconnection::connect();

if ($type === 'avatar') {
    $stmt = $conn->prepare("SELECT Avatar FROM User WHERE ID=?");
} else if ($type === 'banner') {
    $stmt = $conn->prepare("SELECT Banner FROM User WHERE ID=?");
} else {
    http_response_code(400);
    exit("Invalid type.");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($imageData);
$stmt->fetch();

if ($imageData) {
    // Detectar tipo de imagen
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_buffer($finfo, $imageData);
    finfo_close($finfo);

    header("Content-Type: $mime");

    header("Cache-Control: max-age=3600, public"); // cache 1 hora

    echo $imageData;
} else {
    // Imagen por defecto si no hay
    if ($type === 'avatar') {
        readfile('img/avatar.jpg');
    } else {
        readfile('img/banner.png');
    }
}

$stmt->close();
$conn->close();
?>
