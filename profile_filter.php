<?php
require_once("dbconnection.php");
$conn = dbconnection::connect();

$type = $_GET['type'] ?? 'posts';
$userId = $_GET['id'] ?? null;

if (!$userId) exit('ID de usuario no proporcionado.');

// Datos del perfil
$currentProfileID = $userId;
$stmtProfile = $conn->prepare("
    SELECT vp.ID, vp.Username, vp.DisplayName, vp.Bio, vp.Avatar, vp.Banner,
           cf.total_following, cf.total_followers,
           (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = vp.ID) AS already_following
    FROM ViewProfile vp
    LEFT JOIN CountFollow cf ON cf.User_ID = vp.ID
    WHERE vp.ID = ?
");
$stmtProfile->bind_param("ii", $loggedInId, $currentProfileID);
$stmtProfile->execute();
// $displayProfile = $stmtProfile->get_result()->fetch_assoc();

$displayProfile = $stmtProfile->get_result()->fetch_assoc() ?: [
    'ID' => $currentProfileID,
    'Username' => 'Unknown',
    'DisplayName' => 'Unknown',
    'Bio' => '',
    'Avatar' => 'img/pfp.jpg',
    'Banner' => 'img/banner.png',
    'total_following' => 0,
    'total_followers' => 0,
    'already_following' => 0
];
$stmtProfile->close();

// Consultas según tipo
// Consultas según tipo (agregamos already_following al SELECT de cada usuario)
if ($type === 'likes') {
    $sql = "
        SELECT DISTINCT 
            p.*, 
            u.ID AS User_ID, u.Username, u.DisplayName, u.Bio, u.Avatar, u.Banner,
            cf.total_following, cf.total_followers,
            (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = u.ID) AS already_following,
            t.Name AS TopicName,
            (SELECT COUNT(*) FROM PostLike WHERE Post_ID = p.PostID) AS Likes,
            (SELECT COUNT(*) FROM Comment WHERE Post_ID = p.PostID) AS Comments,
            IF(pl.User_ID IS NULL, 0, 1) AS hasLiked
        FROM PostLike l
        JOIN Post p ON l.Post_ID = p.PostID
        JOIN User u ON p.User_ID = u.ID
        LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
        LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
        LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
        LEFT JOIN PostLike pl ON pl.Post_ID = p.PostID AND pl.User_ID = ?
        WHERE l.User_ID = ? 
        ORDER BY p.CreatedAt DESC
    ";
} elseif ($type === 'comments') {
    $sql = "
        SELECT DISTINCT 
            p.*, 
            u.ID AS User_ID, u.Username, u.DisplayName, u.Bio, u.Avatar, u.Banner,
            cf.total_following, cf.total_followers,
            (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = u.ID) AS already_following,
            t.Name AS TopicName,
            (SELECT COUNT(*) FROM PostLike WHERE Post_ID = p.PostID) AS Likes,
            (SELECT COUNT(*) FROM Comment WHERE Post_ID = p.PostID) AS Comments,
            IF(pl.User_ID IS NULL, 0, 1) AS hasLiked
        FROM Comment c
        JOIN Post p ON c.Post_ID = p.PostID
        JOIN User u ON p.User_ID = u.ID
        LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
        LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
        LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
        LEFT JOIN PostLike pl ON pl.Post_ID = p.PostID AND pl.User_ID = ?
        WHERE c.User_ID = ? 
        ORDER BY p.CreatedAt DESC
    ";
} else { // posts
    $sql = "
        SELECT 
            p.*, 
            u.ID AS User_ID, u.Username, u.DisplayName, u.Bio, u.Avatar, u.Banner,
            cf.total_following, cf.total_followers,
            (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = u.ID) AS already_following,
            t.Name AS TopicName,
            (SELECT COUNT(*) FROM PostLike WHERE Post_ID = p.PostID) AS Likes,
            (SELECT COUNT(*) FROM Comment WHERE Post_ID = p.PostID) AS Comments,
            IF(pl.User_ID IS NULL, 0, 1) AS hasLiked
        FROM Post p
        JOIN User u ON p.User_ID = u.ID
        LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
        LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
        LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
        LEFT JOIN PostLike pl ON pl.Post_ID = p.PostID AND pl.User_ID = ?
        WHERE p.User_ID = ? 
          AND p.PostType = 'Post'
          AND p.Status = 'Approved'
        ORDER BY p.CreatedAt DESC
    ";
}


// Ejecutar consulta
$stmt = $conn->prepare($sql);
if (!$stmt) exit("Error al preparar la consulta: " . $conn->error);
$stmt->bind_param("iii", $loggedInId, $userId, $userId); // el primer parámetro es para already_following
$stmt->execute();
$posts = $stmt->get_result();

// Renderizar
while ($post = $posts->fetch_assoc()) {
    // Definir $postUser según tipo
    if ($type === 'posts') {
        $postUser = [
            'ID' => $displayProfile['ID'],
            'Username' => $displayProfile['Username'],
            'DisplayName' => $displayProfile['DisplayName'] ?? $displayProfile['Username'],
            'Bio' => $displayProfile['Bio'] ?? '',
            'Avatar' => $displayProfile['Avatar'] ?? 'img/pfp.jpg',
            'Banner' => $displayProfile['Banner'] ?? 'img/banner.png',
            'total_following' => $displayProfile['total_following'] ?? 0,
            'total_followers' => $displayProfile['total_followers'] ?? 0
        ];
    } else {
        // Aquí agregamos el JOIN para traer los conteos de cada usuario
        $stmtCount = $conn->prepare("SELECT total_following, total_followers FROM CountFollow WHERE User_ID = ?");
        $stmtCount->bind_param("i", $post['User_ID']);
        $stmtCount->execute();
        $countData = $stmtCount->get_result()->fetch_assoc() ?: ['total_following'=>0, 'total_followers'=>0];
        $stmtCount->close();

        $postUser = [
            'ID' => $post['User_ID'],
            'Username' => $post['Username'],
            'DisplayName' => $post['DisplayName'] ?? $post['Username'],
            'Bio' => $post['Bio'] ?? '',
            'Avatar' => $post['Avatar'] ?? 'img/pfp.jpg',
            'Banner' => $post['Banner'] ?? 'img/banner.png',
            'already_following' => $post['already_following'] ?? 0,
            'total_following' => $countData['total_following'] ?? 0,
            'total_followers' => $countData['total_followers'] ?? 0
        ];
    }

    include 'toggle_pcl.php';
}

$stmt->close();
$conn->close();
