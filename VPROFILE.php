<?php
// Antes de incluirlo, asegúrate de tener $notifications definido 
// Si estás en otra página, recupera las notificaciones igual que en el main

session_start();
require_once("dbconnection.php");
$conn = dbConnection::connect();

//                     1. --- USUARIO LOGEADO ---                      //
require_once("middleware/auth_admin.php");

$auth = verifyAuthFusion($conn);
$loggedIn = $auth['loggedIn'];
$isAdmin = $auth['isAdmin'];
$loggedInID = $auth['userID'] ?? 0;

// Determinar qué perfil se está viendo
$currentProfileID = $_GET["id"] ?? $loggedInID;

// Perfil que se muestra
$profile = null;

// ----------------- Cargar variables del archivo .env ----------------- //
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

//                  ------ CURRENT PERFIL (USUARIO LOGEADO) php ------                  //
if ($currentProfileID) {
    $stmt = $conn->prepare("SELECT * FROM ViewProfile WHERE ID = ?");
    $stmt->bind_param("i", $currentProfileID);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();

    // Lista de usuarios que sigue (Following)
    $stmtFollowing = $conn->prepare("SELECT * FROM ViewFollowing WHERE Follower_ID = ?");
    $stmtFollowing->bind_param("i", $currentProfileID);
    $stmtFollowing->execute();
    $followingList = $stmtFollowing->get_result();

    // Lista de usuarios que lo siguen (Followers)
    $stmtFollowers = $conn->prepare("SELECT * FROM ViewFollowers WHERE Following_ID = ?");
    $stmtFollowers->bind_param("i", $currentProfileID);
    $stmtFollowers->execute();
    $followersList = $stmtFollowers->get_result();
}

//                  ------ FUNCIÓN PARA SABER SI EL USUARIO YA SIGUE A OTRO php ------                  //
function yaSigue($conn, $usuarioActual, $otroUsuario)
{
    $stmt = $conn->prepare("SELECT AlreadyFollows(?, ?) + 0 AS resultado");
    $stmt->bind_param("ii", $usuarioActual, $otroUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    return !empty($result->fetch_assoc()['resultado']); // true si 1, false si 0
}

//                  ------ DATOS DEL USUARIO EN SESIÓN php ------                  //
$myProfile = $loggedIn ? $conn->query("SELECT * FROM ViewProfile WHERE ID=" . (int) $_SESSION["ID"])->fetch_assoc() : null;




//                  ------ SUGERENCIAS DE USUARIOS A SEGUIR php ------                  //
$resultado_sugerencias = null;
if ($loggedIn) {
    $sugerencias = $conn->prepare("
        SELECT u.ID, u.DisplayName, u.Username, u.Avatar, u.Banner, u.Bio
        FROM User u
        WHERE u.ID != ? AND NOT EXISTS (
            SELECT 1 FROM Follower
            WHERE Follower_ID = ? AND Following_ID = u.ID
        )
        LIMIT 10
    ");
    $sugerencias->bind_param("ii", $_SESSION["ID"], $_SESSION["ID"]);
    $sugerencias->execute();
    $resultado_sugerencias = $sugerencias->get_result();
}

//                  ------ OTENER SEGUIDOS Y SEGUIDORES DEL PERFIL QUE SE ESTA VIENDO php ------                  //
$totalfollowing = 0;
$totalfollowers = 0;
if ($currentProfileID) {
    $stmtcount = $conn->prepare("
        SELECT total_following, total_followers 
        FROM CountFollow 
        WHERE User_ID = ?
    ");
    $stmtcount->bind_param("i", $currentProfileID);
    $stmtcount->execute();
    $resultcount = $stmtcount->get_result();
    if ($row = $resultcount->fetch_assoc()) {
        $totalfollowing = $row['total_following'];
        $totalfollowers = $row['total_followers'];
    }
}

//                  ------ POST NORMALES DEL PERFIL php ------                  //
$stmtPosts = $conn->prepare("
SELECT 
    p.PostID,
    p.Content,
    p.Media,
    p.MediaType,
    p.CreatedAt,
    p.Edited,
    p.Views,
    u.ID AS UserID,
    u.Username,
    u.DisplayName,
    u.Avatar,
    t.Name AS TopicName,
    -- Contar likes solo de usuarios activos
    (SELECT COUNT(*) 
     FROM PostLike pl
     JOIN User u2 ON pl.User_ID = u2.ID
     WHERE pl.Post_ID = p.PostID AND u2.Deactivated = 0
    ) AS Likes,
    -- Contar comentarios solo de usuarios activos
    (SELECT COUNT(*) 
     FROM Comment c
     JOIN User u3 ON c.User_ID = u3.ID
     WHERE c.Post_ID = p.PostID AND u3.Deactivated = 0
    ) AS Comments,
    -- Verificar si el usuario logueado activo dio like
    EXISTS(
        SELECT 1 
        FROM PostLike pl
        JOIN User u4 ON pl.User_ID = u4.ID
        WHERE pl.Post_ID = p.PostID AND pl.User_ID = ? AND u4.Deactivated = 0
    ) AS hasLiked
FROM Post p
JOIN User u ON p.User_ID = u.ID AND u.Deactivated = 0
LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
WHERE p.User_ID = ? AND p.PostType = 'Post'
ORDER BY p.CreatedAt DESC
");


//se añadió AND u.Deactivated = 0 en la linea 110
$stmtPosts->bind_param("ii", $loggedInId, $currentProfileID);
$stmtPosts->execute();
$posts = $stmtPosts->get_result();

//                  ------ QUERYS PERFIL php ------                  //
$loggedInId = $_SESSION['ID'] ?? 0;

//Perfil básico
$stmt = $conn->prepare("SELECT * FROM ViewProfile WHERE ID = ?");
$stmt->bind_param("i", $currentProfileID);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

//                  ------ CONTEO DE FOLLOWERS/FOLLOWING php ------                  //
$stmtCount = $conn->prepare("
    SELECT total_following, total_followers
    FROM CountFollow
    WHERE User_ID = ?
");
$stmtCount->bind_param("i", $currentProfileID);
$stmtCount->execute();
$countData = $stmtCount->get_result()->fetch_assoc();
$totalfollowing = $countData['total_following'] ?? 0;
$totalfollowers = $countData['total_followers'] ?? 0;

//                  ------ PERFIL CON VISTA AlreadyFollows php ------                  //
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
$displayProfile = $stmtProfile->get_result()->fetch_assoc();

//Fallback por si no encuentra datos
if (!$displayProfile) {
    $displayProfile = [
        'ID' => $currentProfileID,
        'Username' => 'Unknown',
        'DisplayName' => 'Unknown',
        'Bio' => '',
        'Avatar' => "get_image.php?id={$currentProfileID}&type=avatar",
        'Banner' => "get_image.php?id={$currentProfileID}&type=banner",
        'total_following' => 0,
        'total_followers' => 0,
        'already_following' => 0
    ];
}

//                  ------ POST DONDE EL USUARIO HA COMENTADO php ------                  //
$commentedPostsStmt = $conn->prepare("
    SELECT DISTINCT 
        p.PostID,
        p.Content,
        p.Media,
        p.MediaType,
        p.CreatedAt,
        p.Edited,
        p.Views,
        u.ID AS UserID,
        u.Username,
        u.DisplayName,
        u.Avatar,
        t.Name AS TopicName,
        -- Likes solo de usuarios activos
        (SELECT COUNT(*) 
         FROM PostLike pl
         JOIN User u2 ON pl.User_ID = u2.ID
         WHERE pl.Post_ID = p.PostID AND u2.Deactivated = 0
        ) AS Likes,
        -- Comentarios solo de usuarios activos
        (SELECT COUNT(*) 
         FROM Comment c
         JOIN User u3 ON c.User_ID = u3.ID
         WHERE c.Post_ID = p.PostID AND u3.Deactivated = 0
        ) AS Comments
    FROM Comment c
    JOIN Post p ON c.Post_ID = p.PostID
    JOIN User u ON p.User_ID = u.ID AND u.Deactivated = 0
    LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
    LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
    WHERE c.User_ID = ? AND p.PostType = 'Post'
    ORDER BY p.CreatedAt DESC
");

$commentedPostsStmt->bind_param("i", $currentProfileID);
$commentedPostsStmt->execute();
$commentedPostsArray = $commentedPostsStmt->get_result()->fetch_all(MYSQLI_ASSOC);


//                  ------ POST QUE EL USUARIO DIO LIKE php ------                  //
$likedPostsStmt = $conn->prepare("
    SELECT DISTINCT 
        p.PostID,
        p.Content,
        p.Media,
        p.MediaType,
        p.CreatedAt,
        p.Edited,
        u.ID AS UserID,
        u.Username,
        u.DisplayName,
        u.Avatar,
        t.Name AS TopicName,
        -- Likes solo de usuarios activos
        (SELECT COUNT(*) 
         FROM PostLike pl
         JOIN User u2 ON pl.User_ID = u2.ID
         WHERE pl.Post_ID = p.PostID AND u2.Deactivated = 0
        ) AS Likes,
        -- Comentarios solo de usuarios activos
        (SELECT COUNT(*) 
         FROM Comment c
         JOIN User u3 ON c.User_ID = u3.ID
         WHERE c.Post_ID = p.PostID AND u3.Deactivated = 0
        ) AS Comments
    FROM PostLike pl
    JOIN Post p ON pl.Post_ID = p.PostID
    JOIN User u ON p.User_ID = u.ID AND u.Deactivated = 0
    LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
    LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
    WHERE pl.User_ID = ? AND p.PostType = 'Post'
    ORDER BY p.CreatedAt DESC
");

$likedPostsStmt->bind_param("i", $currentProfileID);
$likedPostsStmt->execute();
$likedPostsArray = $likedPostsStmt->get_result()->fetch_all(MYSQLI_ASSOC);


//                  ------ NOTIFICACIONES php ------                  //
$notifications = [];
$hasUnread = false;
if ($loggedIn) {
    $stmtNotif = $conn->prepare("
        SELECT n.NID, n.Type, n.Actor_ID, n.Message, n.IsRead, n.CreatedAt,
               u.Username, u.DisplayName, u.Avatar
        FROM Notification n
        JOIN User u ON u.ID = n.Actor_ID
        WHERE n.User_ID = ?
        ORDER BY n.CreatedAt DESC
        LIMIT 10
    ");
    $stmtNotif->bind_param("i", $loggedInId);
    $stmtNotif->execute();
    $notifications = $stmtNotif->get_result()->fetch_all(MYSQLI_ASSOC);

    // Revisar si hay no leídas
    $stmtUnread = $conn->prepare("SELECT 1 FROM Notification WHERE User_ID=? AND IsRead=0 LIMIT 1");
    $stmtUnread->bind_param("i", $loggedInId);
    $stmtUnread->execute();
    $hasUnread = $stmtUnread->get_result()->num_rows > 0;
}

// --- ORDEN DE POSTS ---
$sort = $_GET['sort'] ?? 'default';
$orderBy = "p.CreatedAt DESC";
if ($sort === "tlikes") {
    $orderBy = "p.Likes DESC";
} elseif ($sort === "tcomments") {
    // No uses el alias Comments en ORDER BY, usa el subquery
    $orderBy = "(SELECT COUNT(*) FROM Comment c WHERE c.Post_ID = p.PostID) DESC";
}

// --- POSTS CON INFO COMPLETA ---
$query = "
SELECT 
    p.PostID,
    p.Content,
    p.Media,
    p.MediaType,
    p.CreatedAt,
    p.Edited,
    p.Views,
    p.Title,
    -- Likes solo de usuarios activos
    (SELECT COUNT(*) 
     FROM PostLike pl
     JOIN User u2 ON pl.User_ID = u2.ID
     WHERE pl.Post_ID = p.PostID AND u2.Deactivated = 0
    ) AS Likes,
    u.ID AS UserID,
    u.Username,
    u.Avatar,
    t.Name AS TopicName,
    -- Verificar si el usuario logueado dio like
    IF(EXISTS(
        SELECT 1 
        FROM PostLike pl
        JOIN User u2 ON pl.User_ID = u2.ID
        WHERE pl.Post_ID = p.PostID AND pl.User_ID = ? AND u2.Deactivated = 0
    ), 1, 0) AS hasLiked,
    -- Comentarios solo de usuarios activos
    (SELECT COUNT(*) 
     FROM Comment c
     JOIN User u3 ON c.User_ID = u3.ID
     WHERE c.Post_ID = p.PostID AND u3.Deactivated = 0
    ) AS Comments
FROM Post p
JOIN User u ON p.User_ID = u.ID AND u.Deactivated = 0
LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
WHERE p.User_ID = ? AND p.PostType = 'Post' AND p.Status = 'Approved'
ORDER BY $orderBy
";


$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $loggedInId, $currentProfileID);
$stmt->execute();
$posts = $stmt->get_result();

//Modificado para filtrar por autor del post, es decir, si veo el perfil de usuario2, me tienen que salir todas sus publicaciones

//              ------ POP UP CREACION DE POST QUERYS php ------              //
$topics = $conn->query("SELECT * FROM Topic ORDER BY Name ASC");
$topicsArray = [];
if ($topics) {
    while ($t = $topics->fetch_assoc()) {
        $topicsArray[] = $t;
    }
}
// Avatar y username del usuario logueado (para el header, etc.)
if (!empty($myProfile['Avatar'])) {
    // Convertir BLOB a base64
    $userAvatar = 'data:image/jpeg;base64,' . base64_encode($myProfile['Avatar']);
} else {
    $userAvatar = 'img/pfp.jpg'; // Imagen por defecto
}
$username = $myProfile['Username'] ?? 'Username';

//              ------ POP UP CREACION DE INFOGRAFIAS php ------              //
$infog = $conn->query("SELECT * FROM History ORDER BY H_Name ASC");
$infogArray = [];
if ($infog) {
    while ($it = $infog->fetch_assoc()) {
        $infogArray[] = $it;
    }
}
?>

<!--           ------ INICIO HTML ------               -->
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>WE ARE FIFA</title>

    <!-- Responsive -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,
    initial-scale=1.0">

    <!-- Diseño .css -->
    <link rel="stylesheet" href="styles/VPROFILE.css">
    <link rel="stylesheet" href="styles/NAVSIDEBAR.css">
    <!-- Contiene el diseño del header y funcionalidades responsive -->
    <!-- Uso de GSAP para animaciones -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"
        integrity="sha512-7eHRwcbYkK4d9g/6tD/mhkf++eoTHwpNM9woBxtPUBWm67zeAfFC+HrdoE2GanKeocly/VxeLvIqwvCdk7qScg=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <!-- Fonts Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <input type="checkbox" id="sidebar-check">
    <!--           ------ HEADER ------               -->
    <header class="header">
        <!-- toggle sidebar iconos -->
        <label for="sidebar-check" class="sb-icons">
            <i class="fa-solid fa-bars" id="menu-sb"></i>
            <i class="fa-solid fa-xmark" id="close-sb"></i>
        </label>
        <!-- Logo de la página -->
        <div class="Logo">
            <img src="img/logo.png" alt="">
            <a href="MAINPAGE.php">FIFA</a>
        </div>
        <!-- Barra de búsqueda -->
        <div class="search-bar">
            <i class="fa-solid fa-magnifying-glass"></i>
            <form action="SEARCH.php" method="GET">
                <input type="text" name="q" placeholder="Search...">
            </form>
        </div>
        <!-- Iconos -->
        <input type="checkbox" id="check">
        <label for="check" class="icons">
            <i class="fa-solid fa-ellipsis-vertical" id="menu-icon"></i>
            <i class="fa-solid fa-xmark" id="close-icon"></i>
        </label>
        <!--           ------ HEADER pt2 ------               -->
        <nav class="navbar">
            <?php if (!$loggedIn): ?>
                <a href="#" id="opt-log"><i class="fa-solid fa-user"></i>Log In</a>
            <?php else: ?>
                <a href="logout.php" id="opt-logout"><i class="fa-solid fa-right-from-bracket"></i>Log Out</a>
            <?php endif; ?>
            <!-- mobile -->
            <a href="#" id="opt-mode"><i class="fa-solid fa-moon"></i>Dark mode</a>
            <div class="dropdown">
                <a href="#"><i class="fa-solid fa-ellipsis-vertical" id="opt-icon"></i></a>
                <div class="dropdown-opt">
                    <!-- desktop -->
                    <a href="#" id="opt-mode-desk"><i class="fa-solid fa-moon"></i>Dark mode</a>
                </div>
            </div>
            <!--           ------ NOTIFICACIONES ------               -->
            <?php if ($loggedIn): ?>
                <a class="notif-container" href="VNOTIFICATIONS.php">
                    <div id="notif-icon">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($hasUnread): ?>
                            <span id="notif-badge"></span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endif; ?>
        </nav>
    </header>
    <!--           ------ SIDEBAR y OVERVIEW ------               -->
    <section class="main">
        <section class="sidebar" id="s-sidebar">
            <?php if ($loggedIn): ?>
                <div class="user">
                    <a href="#" class="user-dd-link">
                        <input type="checkbox" id="user-checkb">
                        <label for="user-checkb">
                            <li class="user-a">
                                <img src="data:image/jpeg;base64,<?= base64_encode($myProfile['Avatar']) ?>" alt="pfp">

                                <div class="u-pfp">
                                    <p id="id_user"><?= htmlspecialchars($myProfile['DisplayName']) ?></p>
                                    <p id="id_id">@<?= htmlspecialchars($myProfile['Username']) ?></p>
                                </div>
                                <i class="fa-solid fa-chevron-down"></i>
                            </li>
                        </label>
                    </a>
                    <div class="user-content">
                        <ul class="user-tc-dd">
                            <li class="user-dropdown-topic">
                                <a href="VPROFILE.php">View Profile</a>
                            </li>
                            <li class="user-dropdown-topic">
                                <a href="PROFILE.php">Settings</a>
                            </li>
                            <?php if ($auth['isAdmin']): ?>
                                <li class="user-dropdown-topic">
                                    <a href="SoyAdmin.php">Administrator</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                <div class="division-hr">
                    <hr>
                </div>
            <?php endif; ?>
            <div class="home">
                <i class="fa-solid fa-house"></i>
                <a href="MAINPAGE.php">Home</a>
            </div>
            <div class="popular">
                <i class="fa-solid fa-fire"></i>
                <a href="MAINPAGE.php?sort=tviews">Popular</a>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <?php if ($loggedIn): ?>
                <div class="create-post">
                    <i class="fa-solid fa-plus"></i>
                    <span id="cpost">Create post</span>
                </div>
                <div class="division-hr">
                    <hr>
                </div>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <div class="slidec create-i">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Create Slide</a>
                </div>
                <div class="division-hr">
                    <hr>
                </div>
            <?php endif; ?>
            <!--           ------ TOPICS ------               -->
            <div class="topics">
                <a href="#" class="dd-link">
                    <input type="checkbox" id="topic-checkb">
                    <label for="topic-checkb">
                        <li class="topics-a">
                            <i class="fa-solid fa-hashtag" id="icon-tcs"><span>TOPICS</span></i>
                            <i class="fa-solid fa-chevron-down"></i>
                        </li>
                    </label>
                </a>
                <div class="t-content">
                    <ul class="tc-dd">
                        <li class="dropdown-topic"><a href="MAINPAGE.php?topic=Qualifiers">Qualifiers</a></li>
                        <li class="dropdown-topic"><a href="MAINPAGE.php?topic=Tournaments">Tournaments</a></li>
                        <li class="dropdown-topic"><a href="MAINPAGE.php?topic=Players">Players</a></li>
                        <li class="dropdown-topic"><a href="MAINPAGE.php?topic=World Rankings">World Rankings</a></li>
                        <li class="dropdown-topic"><a href="MAINPAGE.php?topic=Controversies">Controversies</a></li>
                    </ul>
                </div>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <div class="topics">
                <a href="#" class="dd-link">
                    <input type="checkbox" id="history-checkb">
                    <label for="history-checkb">
                        <li class="topics-a">
                            <span>HISTORY</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </li>
                    </label>
                </a>
                <div class="t-content">
                    <ul class="tc-dd">
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=Origins">Origins</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=Legends">Legends</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=Top Leagues">Top Leagues</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <div class="topics">
                <a href="#" class="dd-link">
                    <input type="checkbox" id="events-checkb">
                    <label for="events-checkb">
                        <li class="topics-a">
                            <span>EVENTS</span>
                            <i class="fa-solid fa-chevron-down"></i>
                        </li>
                    </label>
                </a>
                <div class="t-content">
                    <ul class="tc-dd">
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=World Cups">World Cups</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=Eurocup">Eurocup</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=Champions">Champions</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="ENCYCLOPEDIA.php?topic=America's Cup">America's Cup</a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
        <!--           ------ OVERVIEW ------               -->
        <section class="o-content">
            <div class="o-aux-cont" data-user-id="<?= $displayProfile['ID'] ?>">
                <?php if ($displayProfile): ?>
                    <div class="ov-user">
                        <div class="ov-banner">
                            <img src="<?= !empty($displayProfile['Banner']) 
                            ? 'data:image/jpeg;base64,' . base64_encode($displayProfile['Banner']) 
                            : 'img/banner.png' ?>" 
                            alt="banner">
                        </div>
                        <div class="ov-pfp">
                            <img src="data:image/jpeg;base64,<?= base64_encode($displayProfile['Avatar']) ?>" alt="avatar">
                        </div>
                        <div class="profile-dply">
                            <div class="ov-display">
                                <p id="ov-dname">
                                    <?= htmlspecialchars($displayProfile["DisplayName"] ?: $displayProfile["Username"]) ?>
                                </p>
                                <p id="ov-username">@<?= htmlspecialchars($displayProfile["Username"]) ?></p>
                                <p id="ov-bio"><?= htmlspecialchars($displayProfile["Bio"] ?: "This user hasn't set a bio.") ?></p>
                            </div>
                            <div class="ov-ff">
                                <div class="following">
                                    <span class="ftotalfollowing"><strong><?= $totalfollowing ?></strong></span>
                                    <span id="dd-following">Following</span>
                                </div>
                                <div class="followers">
                                    <span class="ftotalfollowers"><strong><?= $totalfollowers ?></strong></span>
                                    <span id="dd-followers">Followers</span>
                                </div>
                            </div>

                            <!-- Botones de filtro -->
                            <div class="ov-filter">
                                <button data-type="posts" class="active">Posts</button>
                                <button data-type="comments">Comments</button>
                                <button data-type="likes">Likes</button>
                            </div>
                        </div>
                    </div>

                    <!--           ------ POSTS DEL USUARIO (LOGEADO) ------               -->
                    <!-- insertar de manera dinamica pa no tener codigo de espagueti ajsajsjasj u_u -->
                    <div id="profile-posts" data-userid="<?= $displayProfile['ID'] ?>">
                        <?php while ($post = $posts->fetch_assoc()): ?>
                            <?php
                            // Preparar HTML del media
                            $mediaHTML = '';
                            if (!empty($post['Media'])) {
                                $mediaURL = "get_media.php?id=" . $post['PostID'] . "&type=post";
                                $mime = $post['MediaType'] ?? '';

                                if (str_starts_with($mime, 'image/')) {
                                    $mediaHTML = '<img src="' . $mediaURL . '" alt="post media">';
                                } elseif (str_starts_with($mime, 'video/')) {
                                    $mediaHTML = '<video controls src="' . $mediaURL . '"></video>';
                                } else {
                                    $mediaHTML = '<a href="' . $mediaURL . '">Download media</a>';
                                }
                            }
                            // Info del autor del post
                            $postUser = [
                                'ID' => $displayProfile['ID'],
                                'Username' => $displayProfile['Username'],
                                'DisplayName' => $displayProfile['DisplayName'] ?? $displayProfile['Username'],
                                'Bio' => $displayProfile['Bio'] ?? '',
                                'Avatar' => $displayProfile['Avatar'], // BLOB real
                                'Banner' => $displayProfile['Banner'], // BLOB real
                                'total_following' => $displayProfile['total_following'] ?? 0,
                                'total_followers' => $displayProfile['total_followers'] ?? 0,
                                'already_following' => $displayProfile['already_following'] ?? 0
                            ];

                            include 'toggle_pcl.php';
                            ?>
                        <?php endwhile; ?>

                    </div>
                <?php else: ?>
                    <p>No profile to display</p>
                <?php endif; ?>
            </div>

            <!--           ------ WIDGET API ------               -->
            <section class="right-w">
                <div class="widget-r">
                    <div id="wg-api-football-games" data-host="v3.football.api-sports.io"
                        data-key="<?php echo getenv('FOOTBALL_API_KEY'); ?>" data-date="" data-league="" data-season=""
                        data-theme="" data-refresh="15" data-show-toolbar="true" data-show-errors="false"
                        data-show-logos="true" data-modal-game="true" data-modal-standings="true"
                        data-modal-show-logos="true">
                    </div>
                </div>
                <?php if ($resultado_sugerencias && $resultado_sugerencias->num_rows > 0): ?>
                    <div class="f-suggest">
                        <p><strong>Who to follow</strong></p>
                        <?php while ($sugerido = $resultado_sugerencias->fetch_assoc()): ?>
                            <?php
                            //
                            $stmtCountSugerido = $conn->prepare("SELECT total_following, total_followers FROM CountFollow WHERE User_ID = ?");
                            $stmtCountSugerido->bind_param("i", $sugerido['ID']);
                            $stmtCountSugerido->execute();
                            $resultCountSugerido = $stmtCountSugerido->get_result();
                            $followCounts = $resultCountSugerido->fetch_assoc();
                            $totalFollowingSugerido = $followCounts['total_following'] ?? 0;
                            $totalFollowersSugerido = $followCounts['total_followers'] ?? 0;
                            //
                            ?>
                            <div div class="s-aux" data-id="<?= $sugerido['ID'] ?>">
                                <a class="s-user" href="VPROFILE.php?id=<?= $sugerido['ID'] ?>">
                                    <div class="card-dropdown">
                                        <img src="<?= "get_image.php?id=" . $sugerido['ID'] . "&type=avatar&v=" . time() ?>"
                                            alt="pfp" class="card-img" data-url="VPROFILE.php?id=<?= $sugerido['ID'] ?>">

                                        <div class="dropdown-profile">
                                            <div class="dd-banner">
                                                <img src="<?= "get_image.php?id=" . $sugerido['ID'] . "&type=banner&v=" . time() ?>"
                                                    alt="banner">
                                            </div>
                                            <div class="dd-pfp">
                                                <img src="<?= $sugerido['Avatar'] ? "get_image.php?id={$sugerido['ID']}&type=avatar&v=" . time() : 'img/default.jpg' ?>"
                                                    alt="pfp">

                                            </div>
                                            <div class="dd-info">
                                                <p class="dd-user" data-url="VPROFILE.php?id=<?= $sugerido['ID'] ?>">
                                                    <?= htmlspecialchars($sugerido['DisplayName']) ?>
                                                </p>
                                                <p id="dd-username">@<?= htmlspecialchars($sugerido['Username']) ?></p>
                                                <p><?= htmlspecialchars($sugerido['Bio']) ?></p>
                                                <div class="dd-ff">
                                                    <div class="ff">
                                                        <span><strong><?= $totalFollowingSugerido ?></strong></span>
                                                        <span id="dd-fg">Following</span>
                                                    </div>
                                                    <div class="ff">
                                                        <span><strong><?= $totalFollowersSugerido ?></strong></span>
                                                        <span id="dd-fw">Followers</span>
                                                    </div>
                                                </div>
                                                <button id="dd-follow-btn" class="btn-follow"
                                                    data-seguido-id="<?= $sugerido['ID'] ?>">Follow</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="s-user-display">
                                        <p id="whotof"><?= htmlspecialchars($sugerido['DisplayName']) ?></p>
                                        <p id="s-user-unm">@<?= htmlspecialchars($sugerido['Username']) ?></p>
                                    </div>
                                    <button class="btn-follow" data-seguido-id="<?= $sugerido['ID'] ?>">Follow</button>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </section>
    <!--           ------ LOGIN Y REGISTRO ------               -->
    <div class="modal"> <!-- fondo -->
        <div class="modal-content" id="modal-login"> <!-- ventana base -->
            <div class="btn-m-close">
                <button class="m-close"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="m-log">
                <h1>Log In</h1>
                <p>Come connect with other users, join us, thrive.</p>
            </div>
            <div class="m-opt">
                <div class="google-img">
                    <img src="img/google.png" alt="Google">
                </div>
                <span>Continue with Google</span>
            </div>
            <div class="m-div">
                <hr>
                <span>OR</span>
                <hr>
            </div>
            <form method="POST" id="log-form">
                <div class="m-email-pass">
                    <input type="text" placeholder="email@example.com" name="Email">
                    <input type="password" placeholder="password124!" name="Password">
                </div>
                <div id="mensajelog"></div>
                <button id="btn-log" type="submit" name="Login">Log In</button>
                <div class="m-sign" id="msign">
                    <p>New to FIFA?</p>
                    <a href="#" id="open-signup">Sign Up</a>
                </div>
            </form>
            <form action="MAINPAGE.php" id="sign-form" method="POST" enctype="multipart/form-data">
                <div class="m-birthdate">
                    <p>How old are you?</p>
                    <div class="m-date">
                        <select name="month" id="month-select"></select>
                        <select name="day" id="day-select"></select>
                        <select name="year" id="year-select"></select>
                        <input type="hidden" name="Birthdate" id="birthdate">

                    </div>
                </div>
                <div class="m-email-pass">
                    <input type="text" name="Email" id="email" placeholder="email@example.com">
                    <div class="mep-pass">
                        <input type="password" name="Password" id="password" placeholder="Password">
                        <input type="password" name="ConfirmPassword" id="confirm-password"
                            placeholder="Confirm password">
                    </div>
                </div>
                <div class="m-recovery">
                    <p>Recovery question</p>
                    <input type="text" placeholder="What's your pet name?" name="Recovery">
                </div>
                <div id="mensaje"></div>
                <button id="btn-sign" type="submit">Sign Up</button>
                <div class="m-backlog" id="mlog">
                    <p>Already have an account?</p>
                    <a href="#" id="open-login">Log In</a>
                </div>
            </form>
        </div>
    </div>
    <!--           ------ CREAR POST ------               -->
    <div class="cmodal"> <!-- fondo -->
        <div class="create-content" id="create-c"> <!-- ventana base -->
            <form method="POST" enctype="multipart/form-data" class="post-post">

                <!-- Botón de cerrar y título -->
                <div class="btn-c-close">
                    <button type="button" class="c-close"><i class="fa-solid fa-xmark"></i></button>
                    <span>Create Post</span>
                </div>

                <!-- Línea divisoria -->
                <div class="division-hr">
                    <hr>
                </div>

                <!-- Usuario y selector de categoría -->
                <div class="c-u">
                    <div class="c-pfp">
                        <img src="get_image.php?id=<?= $loggedInId ?>&type=avatar&v=<?= time() ?>"
                            alt="Profile Picture">
                    </div>
                    <div class="c-select">
                        <p>@<?= htmlspecialchars($username) ?></p>
                        <select class="c-category" name="topic" id="topic-select" required>
                            <option value="">Select a topic</option>
                            <?php foreach ($topicsArray as $topic): ?>
                                <option value="<?= $topic['TopicID'] ?>"><?= htmlspecialchars($topic['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isAdmin): ?>
                            <div class="news-checkbox">
                                <input type="checkbox" class="news-admin" name="is_news" id="news-admin" value="1" />
                                <label for="news-admin">Mark as news</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- titulo del post -->
                <input type="text" placeholder="Title" class="c-title" name="title" required>
                <!-- Textarea para contenido -->
                <textarea name="content" id="content" placeholder="What's happening?" class="c-textarea"></textarea>

                <!-- Input de media -->
                <input type="file" name="media" id="cInput" accept="image/*,video/*" style="display: none;">
                <label for="cInput" id="cLabel" class="labelc"><i class="fa-solid fa-images"></i> Add to your
                    post</label>

                <!-- Contenedor para mostrar la media seleccionada -->
                <div class="c-display-media" id="cdm"></div>

                <!-- Botón de enviar -->
                <div class="c-btn">
                    <button type="submit">Post</button>
                </div>
            </form>
        </div>
    </div>
    <!--           ------ FOLLOWING/FOLLOWERS ------               -->
    <div class="fmodal">
        <div class="ff-content" id="ff-c"> <!-- ventana base -->
            <div class="btn-ff-close">
                <button class="ff-close"><i class="fa-solid fa-xmark"></i></button>
                <span id="span-f">Following</span>
            </div>
            <div class="ff-toggle-btns">
                <button id="btn-following">Following</button>
                <button id="btn-followers">Followers</button>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <!-- MODAL FOLLOWING -->
            <div class="ff-all" id="ff-following">
                <?php if ($followingList && $followingList->num_rows > 0): ?>
                    <?php while ($user = $followingList->fetch_assoc()): ?>
                        <?php $followingID = $user['Following_ID']; ?>
                        <?php $userFollows = $loggedIn ? yaSigue($conn, $loggedInID, $followingID) : false; ?>
                        <div class="s-aux" data-fid="<?= $followingID ?>">
                            <a class="s-user" href="VPROFILE.php?id=<?= $followingID ?>">
                                <img src="get_image.php?id=<?= $followingID ?>&type=avatar&v=<?= time() ?>" alt="pfp"
                                    class="card-img">


                                <div class="s-user-display">
                                    <p id="whotof"><?= htmlspecialchars($user['DisplayName']) ?></p>
                                    <p id="s-user-unm">@<?= htmlspecialchars($user['Username']) ?></p>
                                </div>

                                <?php if ($loggedIn && $loggedInID != $followingID): ?>
                                    <button class="btn-follow" data-seguido-id="<?= $followingID ?>">
                                        <?= $userFollows ? 'Unfollow' : 'Follow' ?>
                                    </button>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No following users found</p>
                <?php endif; ?>
            </div>
            <!-- MODAL FOLLOWERS -->
            <div class="ff-all" id="ff-followers" style="display:none;">
                <?php if ($followersList && $followersList->num_rows > 0): ?>
                    <?php while ($user = $followersList->fetch_assoc()): ?>
                        <?php $followerID = $user['Follower_ID']; ?>
                        <?php $userFollows = $loggedIn ? yaSigue($conn, $loggedInID, $followerID) : false; ?>
                        <div class="s-aux" data-fwid="<?= $followerID ?>">
                            <a class="s-user" href="VPROFILE.php?id=<?= $followerID ?>">
                                <img src="get_image.php?id=<?= $followerID ?>&type=avatar&v=<?= time() ?>" alt="pfp"
                                    class="card-img">


                                <div class="s-user-display">
                                    <p id="whotof"><?= htmlspecialchars($user['DisplayName']) ?></p>
                                    <p id="s-user-unm">@<?= htmlspecialchars($user['Username']) ?></p>
                                </div>

                                <?php if ($loggedIn && $loggedInID != $followerID): ?>
                                    <button class="btn-follow" data-seguido-id="<?= $followerID ?>">
                                        <?= $userFollows ? 'Unfollow' : 'Follow' ?>
                                    </button>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No followers found</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!--           ------ BORRAR POST ------               -->
    <div id="post-delete-modal" class="modal-overlay">
        <div class="modal-box">
            <p class="modal-text">Are you sure you want to delete this post?</p>
            <div class="modal-actions">
                <button id="post-modal-cancel" class="btn-cancel">Cancel</button>
                <button id="post-modal-confirm" class="btn-delete">Delete</button>
            </div>
        </div>
    </div>
    <!--           ------ REACTIVAR CUENTA ------               -->
    <div class="rmodal" style="display:none;">
        <div class="r-content">
            <div class="rone"> <!-- rone es un aux para acomodar mejor las cosas -->
                <div class="rclose">
                    <button class="r-close"><i class="fa-solid fa-xmark"></i></button>
                    <span>Reactivate account</span>
                </div>
                <!-- Línea divisoria -->
                <div class="division-hr">
                    <hr>
                </div>
            </div>
            <div class="rtwo">
                <p>This email is associated with a deactivated account. Do you want to reactivate it?</p>
                <p class="reactivateError"></p>
                <div class="reactivate-c">
                    <input type="password" class="reactivatePassword" placeholder="Enter your password">
                    <button class="reactivateBtn">Reactivate</button>
                </div>
            </div>
        </div>
    </div>
    <!-- ------ CREAR INFOGRAFIA ------ -->
    <div class="imodal"> <!-- fondo -->
        <div class="create-if" id="create-inf"> <!-- ventana base -->
            <form method="POST" enctype="multipart/form-data" class="info-post">
                <!-- Botón de cerrar y título -->
                <div class="btn-i-close">
                    <button type="button" class="i-close"><i class="fa-solid fa-xmark"></i></button>
                    <span>Create Pedia</span>
                </div>

                <!-- Línea divisoria -->
                <div class="division-hr">
                    <hr>
                </div>
                <!-- Auxiliar general para acomodar todo nada mas -->
                <div class="aux-general">
                    <!-- AQUI VA LA INFO SENCILLA, titulo, logo, contenido, media, etc -->
                    <div class="aux-i">
                        <!-- Usuario y selector de categoría -->
                        <div class="i-u">
                            <div class="i-pfp">
                            <img src="<?= $userAvatar ?>" alt="pfp" />
                            </div>
                            <div class="i-select">
                            <p><?= htmlspecialchars($username) ?></p>
                            <select class="c-category" name="topic" >
                                <option value="">Select a topic</option>
                                <?php foreach ($infogArray as $infog): ?>
                                    <option value="<?= $infog['H_ID'] ?>"><?= htmlspecialchars($infog['H_Name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            </div>
                        </div>
                        <div class="i-title-img">
                            <div class="timg">
                                <!-- Cambiar name="avatar" a name="logo" -->
                                <input type="file" id="fileInput-title" name="logo" accept="image/jpeg, image/png, image/jpg"
                                        style="display: none;" >
                                <img id="Image-title" src="img/avatar.jpg" alt="logo"
                                    onclick="selectImage_title()">
                            </div>
                            <!-- Agregar name="title" y required -->
                            <input type="text" name="title" placeholder="Title" maxlength="50" >
                        </div>

                        <!-- Textarea para contenido -->
                        <textarea name="textcont" placeholder="What's happening?" class="i-textarea"></textarea>

                        <!-- Input de media -->
                        <input type="file" id="iInput" name="media" class="iInput" accept="image/*,video/*" style="display: none;">
                        <label for="iInput" class="iLabel"><i class="fa-solid fa-images"></i> Add to your post</label>

                        <!-- Contenedor para mostrar la media seleccionada -->
                        <div class="i-display-media"></div>
                    </div>
                    <!-- AQUI VAN LOS TAGS PERSONALIZADOS (como wikipedia) tendriamos por ejemplo: -->
                    <!-- Agregar etiqueta +: Campeón del mundial: México  -->
                    <!-- Agregar etiqueta +: Sede: México  -->
                    <!-- Agregar etiqueta +: Fecha de inicio: 2022  -->
                    <div class="aux-tags">
                        <div class="addtag">
                            <span>Add tag:</span>
                            <p><i class="fa-solid fa-plus"></i></p>
                        </div>
                        <div class="tags">
                            <input type="text" name="extraFields[name][]" placeholder="Name:">
                            <input type="text" name="extraFields[value][]" placeholder="Value">
                        </div>
                    </div>
                </div>
                <!-- Botón de enviar -->
                <div class="i-btn">
                    <button type="submit">Post</button>
                </div>
            </form>
        </div>
    </div>

    <!--           ------ SCRIPTS ------               -->
    <script type="module" src="https://widgets.api-sports.io/2.0.3/widgets.js">
    </script>
    <script src="js/NAVSIDEBAR.js" defer></script>
    <script src="js/VPROFILE.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>