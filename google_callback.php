<?php
require_once 'vendor/autoload.php';
require_once 'dbconnection.php';
session_start();

$conn = dbConnection::connect();

// Cargar variables del archivo .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// 🔐 Credenciales de Google
$clientID = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
$redirectUri = getenv('GOOGLE_REDIRECT_URI');

// Crear cliente Google
$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['access_token'])) {
        $client->setAccessToken($token['access_token']);

        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $avatarUrl = $google_account_info->picture;
        $googleID = $google_account_info->id;

        // ✅ Descargar la imagen como binario (para BLOB)
        $avatarData = null;
        if ($avatarUrl) {
            $avatarData = file_get_contents($avatarUrl);
        }

        // Buscar usuario existente
        $stmt = $conn->prepare("SELECT ID, Deactivated FROM User WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Usuario existente → verificar si está desactivado
            $user = $result->fetch_assoc();
            
            if ($user['Deactivated'] == 1) {
                // Usuario desactivado - redirigir a página de reactivación
                $_SESSION['reactivate_email'] = $email;
                header("Location: MAINPAGE.php?reactivate=1");
                exit();
            } else {
                // Usuario activo → iniciar sesión
                $_SESSION['ID'] = $user['ID'];
            }
        } else {
            // 🔹 CREAR USUARIO NUEVO
            
            // 1. Generar Recovery aleatorio
            $recovery = bin2hex(random_bytes(8));
            
            // 2. INSERT inicial (Username vacío, DisplayName = nombre de Google)
            $stmt = $conn->prepare("
                INSERT INTO User (Username, DisplayName, Email, Avatar, Password, Recovery, GoogleID, UserType, Deactivated)
                VALUES ('', ?, ?, ?, '', ?, ?, 0, 0)
            ");
            
            $null = null;
            $stmt->bind_param("ssbss", $name, $email, $null, $recovery, $googleID);
            
            // Enviar Avatar como BLOB
            if ($avatarData) {
                $stmt->send_long_data(2, $avatarData); // índice 2 = Avatar (tercer parámetro)
            }
            
            if (!$stmt->execute()) {
                die("Error al crear usuario: " . $stmt->error);
            }
            
            // 3. Obtener el ID generado
            $userId = $stmt->insert_id;
            $_SESSION['ID'] = $userId;
            $stmt->close();
            
            // 4. Actualizar SOLO el Username con el ID
            $username = "User" . $userId;
            
            $update = $conn->prepare("UPDATE User SET Username = ? WHERE ID = ?");
            $update->bind_param("si", $username, $userId);
            
            if (!$update->execute()) {
                die("Error al actualizar username: " . $update->error);
            }
            
            $update->close();
        }

        header("Location: MAINPAGE.php");
        exit();
    } else {
        echo "Error al obtener el token de Google.";
    }
} else {
    echo "Error al iniciar sesión con Google.";
}
?>