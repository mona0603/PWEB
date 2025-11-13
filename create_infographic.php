<?php
session_start();
require_once("dbconnection.php");

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Debes iniciar sesión para crear una infografía']);
    exit;
}

$conn = dbConnection::connect();
$authorID = $_SESSION['ID'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['textcont'] ?? '');
    $topicID = intval($_POST['topic'] ?? 0);

    if (empty($title)) throw new Exception('You must introduce a title');
    if (strlen($title) > 50) throw new Exception('Title cannot exceed 50 characters');
    if (empty($content)) throw new Exception('You must write the content');
    if ($topicID <= 0) throw new Exception('Select a valid topic');

    // Validar usuario activo
    $stmtUser = $conn->prepare("SELECT ID FROM User WHERE ID = ? AND Deactivated = 0");
    $stmtUser->bind_param("i", $authorID);
    $stmtUser->execute();
    if ($stmtUser->get_result()->num_rows === 0) throw new Exception('User not valid/Deactivated user');
    $stmtUser->close();

    // Validar tema existente
    $stmtTopic = $conn->prepare("SELECT H_ID FROM History WHERE H_ID = ?");
    $stmtTopic->bind_param("i", $topicID);
    $stmtTopic->execute();
    if ($stmtTopic->get_result()->num_rows === 0) throw new Exception('El tema seleccionado no es válido');
    $stmtTopic->close();

    // Validar título único
    $stmtTitle = $conn->prepare("SELECT ID_MP FROM MyPedia WHERE MP_Title = ?");
    $stmtTitle->bind_param("s", $title);
    $stmtTitle->execute();
    if ($stmtTitle->get_result()->num_rows > 0) throw new Exception('Ya existe una infografía con ese título');
    $stmtTitle->close();

    // Procesar logo
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE)
        throw new Exception('El logo es obligatorio');
    if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK)
        throw new Exception('Error al subir el logo');
    $logoType = mime_content_type($_FILES['logo']['tmp_name']);
    if (!str_starts_with($logoType, 'image/')) throw new Exception('El logo debe ser una imagen válida');
    if ($_FILES['logo']['size'] > 5 * 1024 * 1024) throw new Exception('El logo no puede exceder 5MB');
    $logoData = file_get_contents($_FILES['logo']['tmp_name']);

    // Procesar media opcional
    $mediaData = null;
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $mediaType = mime_content_type($_FILES['media']['tmp_name']);
        if (!str_starts_with($mediaType, 'image/') && !str_starts_with($mediaType, 'video/'))
            throw new Exception('El archivo multimedia debe ser una imagen o video');
        if ($_FILES['media']['size'] > 50 * 1024 * 1024)
            throw new Exception('El archivo multimedia no puede exceder 50MB');
        $mediaData = file_get_contents($_FILES['media']['tmp_name']);
    }

    // Insertar infografía principal
    $stmt = $conn->prepare("CALL CreatePedia(?, ?, ?, ?, ?, ?)");
    $null = null;
    $stmt->bind_param("ssbbii", $title, $content, $null, $null, $authorID, $topicID);
    $stmt->send_long_data(2, $logoData);
    if ($mediaData !== null) $stmt->send_long_data(3, $mediaData);
    $stmt->execute();
    $result = $stmt->get_result();

    $newID = 0;
    if ($result && $row = $result->fetch_assoc()) $newID = $row['NewID'];
    $stmt->close();

    if ($newID <= 0) throw new Exception('No se pudo obtener el ID de la infografía creada');

    // INSERTAR TAGS PERSONALIZADOS
    if (isset($_POST['extraFields']['name']) && isset($_POST['extraFields']['value'])) {
        $names = $_POST['extraFields']['name'];
        $values = $_POST['extraFields']['value'];
        
        if (is_array($names) && is_array($values)) {
            $stmtExtra = $conn->prepare("INSERT INTO MyPediaContent (ID_MP, Field_Name, Field_Value) VALUES (?, ?, ?)");
            
            $count = min(count($names), count($values));
            for ($i = 0; $i < $count; $i++) {
                $name = trim($names[$i]);
                $value = trim($values[$i]);
                
                if ($name !== '' && $value !== '') {
                    $stmtExtra->bind_param("iss", $newID, $name, $value);
                    $stmtExtra->execute();
                }
            }
            $stmtExtra->close();
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Infografía creada exitosamente con tags',
        'id' => $newID
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>