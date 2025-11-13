<?php
session_start();
require_once("dbconnection.php");
$conn = dbConnection::connect();

// Usuario logueado
$loggedIn = isset($_SESSION["ID"]);
require_once("middleware/auth_admin.php");
$auth = verifyAuthFusion($conn);
$isAdmin = $auth['isAdmin'];

if (isset($_GET['checkLogin'])) {
    header('Content-Type: application/json');
    echo json_encode(["loggedIn" => $loggedIn]);
    exit;
}

// ----------------- Cargar variables del archivo .env ----------------- //
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}


// Determinar qué perfil se está viendo
$currentProfileID = $_GET["id"] ?? ($_SESSION["ID"] ?? null);

// Perfil que se muestra
$profile = null;
if ($currentProfileID) {
    $stmt = $conn->prepare("SELECT * FROM ViewProfile WHERE ID = ?");
    $stmt->bind_param("i", $currentProfileID);
    $stmt->execute();
    $result = $stmt->get_result();
    $profile = $result->fetch_assoc();
}

// Datos del usuario en sesión (si existe)

//REemplace esa linea de codigo por la de abajo, para que jale con google, por si algo falla, solo quitar y probar
$myProfile = $loggedIn ? $conn->query("SELECT * FROM ViewProfile WHERE ID=" . (int) $_SESSION["ID"])->fetch_assoc() : null;





// Sugerencias de usuarios a seguir (si hay usuario logueado)
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

// Obtener conteo de seguidores y seguidos para el perfil que se está viendo
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
$notifications = [];
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
    $stmtNotif->bind_param("i", $_SESSION['ID']);
    $stmtNotif->execute();
    $notifications = $stmtNotif->get_result()->fetch_all(MYSQLI_ASSOC);
}
// Revisar si hay notificaciones no leídas
$hasUnread = false;
if ($loggedIn) {
    $stmtUnread = $conn->prepare("SELECT 1 FROM Notification WHERE User_ID=? AND IsRead=0 LIMIT 1");
    $stmtUnread->bind_param("i", $_SESSION['ID']);
    $stmtUnread->execute();
    $hasUnread = $stmtUnread->get_result()->num_rows > 0;
}

$query = "SELECT p.*, u.Username, u.Avatar, t.Name AS TopicName
          FROM Post p
          JOIN User u ON p.User_ID = u.ID
          LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
          LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
          ORDER BY p.CreatedAt DESC";
$result = $conn->query($query);

$topics = $conn->query("SELECT * FROM Topic ORDER BY Name ASC");


$stmtProfile = $conn->prepare("
SELECT vp.ID, vp.Username, vp.DisplayName, vp.Bio, vp.Avatar, vp.Banner,
       cf.total_following, cf.total_followers,
       (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = vp.ID) AS already_following
FROM ViewProfile vp
LEFT JOIN CountFollow cf ON cf.User_ID = vp.ID
WHERE vp.ID = ?
");


if (!isset($_GET['id'])) {
    die("Post no encontrado");
}

$postId = (int) $_GET['id'];

$loggedInId = $_SESSION['ID'] ?? 0;

$stmt = $conn->prepare("
SELECT 
    p.PostID, p.Content, p.Media, p.MediaType, p.CreatedAt, p.Edited, p.Views, p.Title,
    u.ID as UserID, u.Username, u.DisplayName, u.Avatar, u.Banner, u.Bio,
    t.Name AS TopicName,
    (SELECT COUNT(*) 
        FROM PostLike pl
        JOIN User u2 ON pl.User_ID = u2.ID
        WHERE pl.Post_ID = p.PostID AND (u2.Deactivated = 0 OR u2.Deactivated IS NULL)
    ) AS Likes,
    (SELECT COUNT(*) 
        FROM Comment c
        JOIN User u3 ON c.User_ID = u3.ID
        WHERE c.Post_ID = p.PostID AND (u3.Deactivated = 0 OR u3.Deactivated IS NULL)
    ) AS Comments,
    EXISTS(
        SELECT 1 
        FROM PostLike pl
        JOIN User u4 ON pl.User_ID = u4.ID
        WHERE pl.Post_ID = p.PostID 
          AND pl.User_ID = ? 
          AND (u4.Deactivated = 0 OR u4.Deactivated IS NULL)
    ) AS hasLiked
FROM Post p
LEFT JOIN User u ON u.ID = p.User_ID AND (u.Deactivated = 0 OR u.Deactivated IS NULL)
LEFT JOIN PostTopic pt ON p.PostID = pt.Post_ID
LEFT JOIN Topic t ON pt.Topic_ID = t.TopicID
WHERE p.PostID = ?
");


//filtra solo los likes y comentarios de usuarios ACTIVOS ! ! !

$stmt->bind_param("ii", $loggedInId, $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();


if (!$post) {
    die("Post no encontrado.");
}

$stmtComments = $conn->prepare("
SELECT c.CommentID, c.Content, c.CreatedAt, c.Edited, c.ParentCommentID,
       u.ID as UserID, u.Username, u.DisplayName, u.Avatar, u.Banner, u.Bio,
       cf.total_following, cf.total_followers,
       (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = u.ID) AS already_following
FROM Comment c
JOIN User u ON c.User_ID = u.ID AND u.Deactivated = 0
LEFT JOIN CountFollow cf ON cf.User_ID = u.ID
WHERE c.Post_ID = ?
ORDER BY c.CreatedAt ASC
");

$loggedInId = $_SESSION['ID'] ?? 0;
$stmtComments->bind_param("ii", $loggedInId, $postId);
$stmtComments->execute();
$comments = $stmtComments->get_result();


$authorId = $post['UserID'];
$loggedInId = $_SESSION['ID'] ?? 0;

$stmtProfile->bind_param("ii", $loggedInId, $authorId);
$stmtProfile->execute();
$displayProfile = $stmtProfile->get_result()->fetch_assoc();

//RESPUESTAS
$mainComments = [];
$replies = [];

while ($c = $comments->fetch_assoc()) {
    // Usar is_null() o comparar con == null
    if (is_null($c['ParentCommentID']) || $c['ParentCommentID'] === '') {
        $mainComments[$c['CommentID']] = $c;
        $mainComments[$c['CommentID']]['replies'] = []; // inicializamos arreglo de replies
    } else {
        $replies[] = $c;
    }
}

// Asociar respuestas a su comentario principal
foreach ($replies as $r) {
    if (isset($mainComments[$r['ParentCommentID']])) {
        $mainComments[$r['ParentCommentID']]['replies'][] = $r;
    }
}

//              ------ POP UP CREACION DE POST QUERYS php ------              //
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

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>FIFA</title>

    <!-- Responsive -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,
    initial-scale=1.0">

    <!-- Diseño .css -->
    <link rel="stylesheet" href="styles/POST.css">
    <link rel="stylesheet" href="styles/NAVSIDEBAR.css">
    <!-- contiene el diseño del header y funcionalidades responsive -->
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
        <!-- Barra superior -->
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
    <!-- Sidebar -->
    <section class="main">
        <section class="sidebar" id="s-sidebar">
            <?php if ($loggedIn): ?>
                <div class="user">
                    <a href="#" class="user-dd-link">
                        <input type="checkbox" id="user-checkb">
                        <label for="user-checkb">
                            <li class="user-a">
                                <img src="<?= "get_image.php?id=" . $loggedInId . "&type=avatar&v=" . time() ?>" alt="pfp">


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
            <!-- Topics -->
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
        <!-- OVERVIEW -->
        <section class="p-content">
            <div class="post-container secpost" data-postid="<?= $post['PostID'] ?>">
                <button onclick="history.back()" id="goback"><i class="fa-solid fa-chevron-left"></i></button>

                <div class="post">
                    <div class="card-header">
                        <div class="card-dropdown">
                            <img src="get_image.php?id=<?= $displayProfile['ID'] ?>&type=avatar&v=<?= time() ?>"
                                alt="pfp" class="card-img" data-url="VPROFILE.php?id=<?= $displayProfile['ID'] ?>">

                            <div class="dropdown-profile">
                                <div class="dd-banner">
                                    <img src="get_image.php?id=<?= $displayProfile['ID'] ?>&type=banner&v=<?= time() ?>"
                                        alt="banner">
                                </div>

                                <div class="dd-pfp">
                                    <img src="get_image.php?id=<?= $displayProfile['ID'] ?>&type=avatar&v=<?= time() ?>"
                                        alt="pfp">
                                </div>

                                <div class="dd-info">
                                    <p class="dd-user" data-url="VPROFILE.php?id=<?= $displayProfile['ID'] ?>">
                                        <?= htmlspecialchars($displayProfile['DisplayName'] ?: $displayProfile['Username']) ?>
                                    </p>
                                    <p id="dd-username">@<?= htmlspecialchars($displayProfile['Username']) ?></p>
                                    <p><?= htmlspecialchars($displayProfile['Bio'] ?: "This user hasn't set a bio.") ?>
                                    </p>

                                    <div class="dd-ff">
                                        <div class="following">
                                            <span><strong><?= $displayProfile['total_following'] ?? 0 ?></strong></span>
                                            <span id="dd-f">Following</span>
                                        </div>
                                        <div class="followers">
                                            <span><strong><?= $displayProfile['total_followers'] ?? 0 ?></strong></span>
                                            <span id="dd-f">Followers</span>
                                        </div>
                                    </div>

                                    <?php if ($loggedIn && $loggedInId != $displayProfile['ID']): ?>
                                        <button id="dd-follow-btn2" class="btn-follow"
                                            data-seguido-id="<?= $displayProfile['ID'] ?>">
                                            <?= $displayProfile['already_following'] ? 'Unfollow' : 'Follow' ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- EDICIÓN DE POST PROPIO -->
                        <div class="card-h">
                            <span>#<?= htmlspecialchars($post['TopicName'] ?? "News") ?></span>
                            <span id="dot">•</span>
                            <span id="pdate"><?= date("F j, Y", strtotime($post['CreatedAt'])) ?></span>
                            <p class="card-ul" data-url="VPROFILE.php?id=<?= $post['UserID'] ?>">
                                <?= htmlspecialchars($post['Username']) ?>
                                <span class="puc-edited">
                                    <?php if (!empty($post['Edited']) && $post['Edited'] == 1): ?>
                                        (Edited)
                                    <?php endif; ?>
                                </span>
                            </p>
                        </div>
                        <div class="c-opt">
                            <i class="fa-solid fa-ellipsis"></i>
                            <div class="c-menu">
                                <?php if ($post['UserID'] == $loggedInId): ?>
                                    <!-- Editar (solo autor) -->
                                    <button type="button" class="edit-post-btn iconi" data-postid="<?= $post['PostID'] ?>">
                                        <i class="fa-regular fa-pen-to-square"></i>Edit
                                    </button>
                                <?php endif; ?>

                                <!-- Borrar (solo admin) -->
                                <?php if ($isAdmin): ?>
                                    <form action="delete_post.php" method="POST" class="delete-post-form">
                                        <input type="hidden" name="post_id" value="<?= $post['PostID'] ?>">
                                        <button type="button" class="delete-post-btn iconi"
                                            data-id="<?= $post['PostID'] ?>">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </form>
                                <?php endif; ?>


                                <!-- Reportar (solo si NO es el autor) -->
                                <?php if ($post['UserID'] !== $loggedInId && !$isAdmin): ?>
                                    <button type="button" class="report-post-btn iconi"
                                        data-postid="<?= $post['PostID'] ?>">
                                        <i class="fa-solid fa-flag"></i>Report
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- CAMBIOS DE LA EDICION ,BOTONES, MENSAJES -->
                    <div class="card-texta">
                        <input type="text" readonly value="<?= htmlspecialchars($post['Title']) ?>" class="card-title">
                        <textarea readonly maxlength="360"><?= htmlspecialchars($post['Content']) ?></textarea>
                        <div class="inline-edit-actions"></div> <!-- botones Save/Cancel -->
                        <span class="editing-status"></span> <!-- "Editando..." temporal -->
                    </div>

                    <!--           ------ MEDIA DE LA PUBLICACION ------               -->
                    <?php if (!empty($post['Media']) && !empty($post['MediaType'])): ?>
                        <div class="card-media">
                            <?php if (str_starts_with($post['MediaType'], 'image/')): ?>
                                <img src="get_post_media.php?id=<?= $post['PostID'] ?>" alt="media"
                                    style="max-width:100%; max-height:500px; border-radius:var(--main-space); object-fit:cover;">
                            <?php elseif (str_starts_with($post['MediaType'], 'video/')): ?>
                                <video controls
                                    style="max-width:100%; max-height:500px; border-radius:var(--main-space); object-fit:cover;">
                                    <source src="get_post_media.php?id=<?= $post['PostID'] ?>" type="<?= $post['MediaType'] ?>">
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>


                    <div class="card-btns">
                        <button class="like-btn" data-postid="<?= $post['PostID'] ?>"
                            id="like-btn-<?= $post['PostID'] ?>">
                            <i class="<?= $post['hasLiked'] ? 'fa-solid liked' : 'fa-regular' ?> fa-star"></i>
                            <span id="like-count-<?= $post['PostID'] ?>"><?= $post['Likes'] ?></span>
                        </button>

                        <button class="cb-comment" data-post="<?= $post['PostID'] ?>">
                            <i class="fa-regular fa-comment"></i><span><?= $post['Comments'] ?></span>
                        </button>
                        <div class="stats">
                            <span><i class="fa-solid fa-chart-simple"></i></span>
                            <span><?= $post['Views'] ?></span>
                            <span>Views</span>
                        </div>
                    </div>
                </div>
                <div class="post-hr">
                    <hr>
                </div>
                <?php if ($loggedIn): ?>
                    <div class="post-comment" data-post="<?= $postId ?>">
                        <p class="comment-trigger">Join the conversation</p>
                        <div class="post-cpost">
                            <div class="post-textarea">
                                <textarea id="pc-comm"></textarea>
                            </div>
                            <div class="pc-comm-btns">
                                <button id="pcancel">Cancel</button>
                                <button id="pcomment">Comment</button>
                            </div>
                        </div>
                    </div>
                    <div class="post-hr">
                        <hr>
                    </div>
                <?php endif; ?>

                <div class="post-users-comm" data-post-id="<?= $postId ?>">
                    <?php foreach ($mainComments as $comment): ?>
                        <div class="puc-user" data-id="<?= $comment['CommentID'] ?>">

                            <div class="card-dropdown">
                                <img src="get_image.php?id=<?= $comment['UserID'] ?>&type=avatar&v=<?= time() ?>" alt="pfp"
                                    class="card-img" data-url="VPROFILE.php?id=<?= $comment['UserID'] ?>">



                                <div class="dropdown-profile">
                                    <div class="dd-banner">
                                        <img src="get_image.php?id=<?= $comment['UserID'] ?>&type=banner&v=<?= time() ?>"
                                            alt="banner">
                                    </div>
                                    <div class="dd-pfp">

                                        <img src="get_image.php?id=<?= $comment['UserID'] ?>&type=avatar&v=<?= time() ?>"
                                            alt="pfp">
                                    </div>
                                    <div class="dd-info">
                                        <p class="dd-user" data-url="VPROFILE.php?id=<?= $comment['UserID'] ?>">
                                            <?= htmlspecialchars($comment['DisplayName'] ?: $comment['Username']) ?>
                                        </p>
                                        <p id="dd-username">@<?= htmlspecialchars($comment['Username']) ?></p>
                                        <p><?= htmlspecialchars($comment['Bio'] ?: "This user hasn't set a bio.") ?></p>

                                        <div class="dd-ff">
                                            <div class="following">
                                                <span><strong><?= $comment['total_following'] ?? 0 ?></strong></span>
                                                <span id="dd-f">Following</span>
                                            </div>
                                            <div class="followers">
                                                <span><strong><?= $comment['total_followers'] ?? 0 ?></strong></span>
                                                <span id="dd-f">Followers</span>
                                            </div>
                                        </div>

                                        <?php if ($loggedIn && $_SESSION['ID'] != $comment['UserID']): ?>
                                            <button id="dd-follow-btn2" class="btn-follow"
                                                data-seguido-id="<?= $comment['UserID'] ?>">
                                                <?= $comment['already_following'] ? 'Unfollow' : 'Follow' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="puc">
                                <div class="puc-info">
                                    <span><?= htmlspecialchars($comment['Username']) ?></span>
                                    <span id="puc-dot">•</span>
                                    <span id="puc-date"><?= date("F j, Y", strtotime($comment['CreatedAt'])) ?></span>
                                    <?php if (!empty($comment['Edited']) && $comment['Edited'] == 1): ?>
                                        <span class="puc-edited">(Edited)</span>
                                    <?php endif; ?>
                                </div>

                                <div class="puc-comment">
                                    <textarea readonly><?= htmlspecialchars($comment['Content']) ?></textarea>
                                </div>

                              <!--  <?php if ($loggedIn): ?>
                                    <div class="reply-actions">
                                        <span class="reply-btn" data-comment-id="<?= $comment['CommentID'] ?>">Reply</span>
                                        <?php $replyCount = count($comment['replies']); ?>
                                        <?php if ($replyCount > 0): ?>
                                            <span class="view-replies-btn" data-comment-id="<?= $comment['CommentID'] ?>">
                                                View <?= $replyCount ?>             <?= $replyCount === 1 ? 'reply' : 'replies' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="reply-form-container" data-comment-id="<?= $comment['CommentID'] ?>"></div>
                                <?php endif; ?> -->

                                <?php if ($loggedIn && ($comment['UserID'] == $loggedInId)): ?>
                                    <div class="comment-actions">
                                        <button class="action-btn">⋯</button>
                                        <div class="action-menu">
                                            <button class="edit-comment-btn"
                                                data-id="<?= $comment['CommentID'] ?>">Edit</button>
                                            <button class="delete-comment-btn"
                                                data-id="<?= $comment['CommentID'] ?>">Delete</button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="replies-wrapper">

                                    <!-- Contenedor de replies oculto por defecto -->
                                    <div class="replies-container" data-comment-id="<?= $comment['CommentID'] ?>">

                                        <?php foreach ($comment['replies'] as $reply): ?>
                                            <div class="puc-user reply" data-id="<?= $reply['CommentID'] ?>">

                                                <!-- Card: avatar + dropdown + contenido -->
                                                <div class="card-dropdown">
                                                    <img src="<?= $reply['Avatar'] ?: 'img/pfp.jpg' ?>" alt="pfp"
                                                        class="card-img" data-url="VPROFILE.php?id=<?= $reply['UserID'] ?>">

                                                    <div class="dropdown-profile">
                                                        <div class="dd-banner">
                                                            <img src="<?= $reply['Banner'] ?: 'img/fifamty.jpg' ?>"
                                                                alt="banner">
                                                        </div>
                                                        <div class="dd-pfp">
                                                            <img src="<?= $reply['Avatar'] ?: 'img/pfp.jpg' ?>" alt="pfp">
                                                        </div>
                                                        <div class="dd-info">
                                                            <p id="dd-user" data-url="VPROFILE.php?id=<?= $reply['UserID'] ?>">
                                                                <?= htmlspecialchars($reply['DisplayName'] ?: $reply['Username']) ?>
                                                            </p>
                                                            <p id="dd-username">@<?= htmlspecialchars($reply['Username']) ?></p>
                                                            <p><?= htmlspecialchars($reply['Bio'] ?: "This user hasn't set a bio.") ?>
                                                            </p>
                                                            <div class="dd-ff">
                                                                <div class="following">
                                                                    <span><strong><?= $reply['total_following'] ?? 0 ?></strong></span>
                                                                    <span id="dd-f">Following</span>
                                                                </div>
                                                                <div class="followers">
                                                                    <span><strong><?= $reply['total_followers'] ?? 0 ?></strong></span>
                                                                    <span id="dd-f">Followers</span>
                                                                </div>
                                                            </div>
                                                            <?php if ($loggedIn && $_SESSION['ID'] != $reply['UserID']): ?>
                                                                <button id="dd-follow-btn2" class="btn-follow"
                                                                    data-seguido-id="<?= $reply['UserID'] ?>">
                                                                    <?= $reply['already_following'] ? 'Unfollow' : 'Follow' ?>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="puc">
                                                        <div class="puc-info">
                                                            <span><?= htmlspecialchars($reply['Username']) ?></span>
                                                            <span id="puc-dot">•</span>
                                                            <span id="puc-date" style="color: grey;">
                                                                <?= date("F j, Y", strtotime($reply['CreatedAt'])) ?>
                                                            </span>
                                                            <?php if (!empty($reply['Edited']) && $reply['Edited'] == 1): ?>
                                                                <span class="puc-edited">(Edited)</span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="puc-comment">
                                                            <textarea
                                                                readonly><?= htmlspecialchars($reply['Content']) ?></textarea>
                                                        </div>
                                                    </div>
                                                </div> <!-- .card-dropdown -->

                                                <?php if ($loggedIn && ($reply['UserID'] == $loggedInId)): ?>
                                                    <div class="comment-actions">
                                                        <button class="action-btn">⋯</button>
                                                        <div class="action-menu">
                                                            <button class="edit-comment-btn"
                                                                data-id="<?= $reply['CommentID'] ?>">Edit</button>
                                                            <button class="delete-comment-btn"
                                                                data-id="<?= $reply['CommentID'] ?>">Delete</button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                            </div> <!-- .puc-user.reply -->
                                        <?php endforeach; ?>
                                    </div> <!-- .replies-container -->
                                </div> <!-- .replies-wrapper -->


                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

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

    <!-- popup inicio de sesion -->
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

    <!-- Modal para eliminar comentario -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to delete this comment?</p>
            <div class="modal-buttons">
                <button id="modal-cancel">Cancel</button>
                <button id="modal-confirm">Delete</button>
            </div>
        </div>
    </div>

    <!-- Modal exclusivo para Replies -->
    <div id="reply-modal" class="modal">
        <div class="modal-content">
            <h3>Reply Actions</h3>
            <textarea id="reply-textarea" rows="4" style="width:100%; display:none;"></textarea>
            <div class="modal-buttons">
                <button id="reply-save-btn">Save</button>
                <button id="reply-delete-btn">Delete</button>
                <button id="reply-cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Modal eliminar post -->
    <div id="post-delete-modal" class="modal-overlay">
        <div class="modal-box">
            <p class="modal-text">Are you sure you want to delete this post?</p>
            <div class="modal-actions">
                <button id="post-modal-cancel" class="btn-cancel">Cancel</button>
                <button id="post-modal-confirm" class="btn-delete">Delete</button>
            </div>
        </div>
    </div>

    <!-- Modal Reportar post -->
    <div id="post-report-modal" class="modal-overlay">
        <div class="modal-box">
            <p>Are you sure you want to report this post?</p>
            <div class="modal-actions">
                <button id="report-modal-cancel" class="btn-cancel">Cancel</button>
                <button id="report-modal-confirm" class="btn-report">Report</button>
            </div>
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
                                <select class="c-category" name="topic">
                                    <option value="">Select a topic</option>
                                    <?php foreach ($infogArray as $infog): ?>
                                        <option value="<?= $infog['H_ID'] ?>"><?= htmlspecialchars($infog['H_Name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="i-title-img">
                            <div class="timg">
                                <!-- Cambiar name="avatar" a name="logo" -->
                                <input type="file" id="fileInput-title" name="logo"
                                    accept="image/jpeg, image/png, image/jpg" style="display: none;">
                                <img id="Image-title" src="img/avatar.jpg" alt="logo" onclick="selectImage_title()">
                            </div>
                            <!-- Agregar name="title" y required -->
                            <input type="text" name="title" placeholder="Title" maxlength="50">
                        </div>

                        <!-- Textarea para contenido -->
                        <textarea name="textcont" placeholder="What's happening?" class="i-textarea"></textarea>

                        <!-- Input de media -->
                        <input type="file" id="iInput" name="media" class="iInput" accept="image/*,video/*"
                            style="display: none;">
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

    <script>
        const loggedInUserID = <?= $_SESSION['ID'] ?? 'null' ?>;
    </script>
    
    <script type="module" src="https://widgets.api-sports.io/2.0.3/widgets.js">
    </script>
    <script src="js/NAVSIDEBAR.js" defer></script>
    <script src="js/POST.js" defer></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>