<?php
session_start();
require_once("dbconnection.php");
$conn = dbConnection::connect();

// ------------------- USUARIO LOGEADO ------------------- //
require_once("middleware/auth_admin.php");
$auth = verifyAuthFusion($conn);
$loggedIn = $auth['loggedIn'];
$isAdmin = $auth['isAdmin'];
$loggedInId = $auth['userID'] ?? 0;

// Perfil actual (el que se está viendo)
$currentProfileID = $_GET["id"] ?? $loggedInId;

// ----------------- Cargar variables del archivo .env ----------------- //
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// ------------------- PERFIL DEL USUARIO MOSTRADO ------------------- //
$profile = null;
if ($currentProfileID) {
    $stmt = $conn->prepare("SELECT * FROM ViewProfile WHERE ID = ?");
    $stmt->bind_param("i", $currentProfileID);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
}

// Datos del usuario logueado
$myProfile = $loggedIn ? $conn->query("SELECT * FROM ViewProfile WHERE ID=" . (int)$loggedInId)->fetch_assoc() : null;


// Avatar y banner del usuario logueado (con fallback si no tiene)
if ($myProfile) {
    $userAvatar = !empty($myProfile['Avatar'])
        ? "get_image.php?id={$myProfile['ID']}&type=avatar&v=" . time()
        : "img/avatar.png";

    $userBanner = !empty($myProfile['Banner'])
        ? "get_image.php?id={$myProfile['ID']}&type=banner&v=" . time()
        : "img/banner.png";

    $username = $myProfile['Username'] ?? 'Usuario';
} else {
    // Si no hay sesión o no existe el perfil
    $userAvatar = "img/pfp.jpg";
    $userBanner = "img/fifamty.jpg";
    $username = "Invitado";
}


// ------------------- SUGERENCIAS ------------------- //
$resultado_sugerencias = null;
if ($loggedIn) {
    $sugerencias = $conn->prepare("
        SELECT u.ID, u.DisplayName, u.Username, u.Avatar, u.Banner, u.Bio
        FROM User u
        WHERE u.ID != ? AND u.Deactivated = 0
        AND NOT EXISTS (
            SELECT 1 FROM Follower
            WHERE Follower_ID = ? AND Following_ID = u.ID
        )
        LIMIT 10
    ");
    $sugerencias->bind_param("ii", $loggedInId, $loggedInId);
    $sugerencias->execute();
    $resultado_sugerencias = $sugerencias->get_result();
}

// ------------------- NOTIFICACIONES ------------------- //
$notifications = [];
$hasUnread = false;
if ($loggedIn) {
    $stmtNotif = $conn->prepare("
        SELECT n.NID, n.Type, n.Actor_ID, n.Message, n.IsRead, n.CreatedAt,
               u.Username, u.DisplayName, u.Avatar
        FROM Notification n
        JOIN User u ON u.ID = n.Actor_ID
        WHERE n.User_ID = ? AND u.Deactivated = 0
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
// ------------------- FILTRO POR TOPIC ------------------- //
$topic = trim($_GET['topic'] ?? '');
$whereTopic = "";
$params = [$loggedInId]; // primer parámetro para hasLiked
$types = "i";

if (!empty($topic)) {
    $whereTopic = " AND LOWER(TRIM(t.Name)) = LOWER(TRIM(?)) ";
    $params[] = $topic;
    $types .= "s";
}

// ------------------- ORDEN DE POSTS ------------------- //
$sort = $_GET['sort'] ?? 'default';
$orderBy = "p.CreatedAt DESC"; // default
if ($sort === "tlikes") {
    $orderBy = "COUNT(DISTINCT pl.LikeID) DESC"; // en vez de 'Likes DESC'
} elseif ($sort === "tcomments") {
    $orderBy = "COUNT(DISTINCT c.CommentID) DESC"; // en vez de 'Comments DESC'
}

// ------------------- QUERY DE POSTS ------------------- //
$query = "
SELECT
    p.PostID,
    p.Content,
    p.Media,
    p.MediaType,
    p.CreatedAt,
    p.Edited,
    u.ID AS UserID,
    u.Username,
    u.Avatar,
    t.Name AS TopicName,
    COUNT(DISTINCT pl.LikeID) AS Likes,
    COUNT(DISTINCT c.CommentID) AS Comments,
    IF(
        EXISTS(
            SELECT 1
            FROM PostLike pl2
            WHERE pl2.Post_ID = p.PostID AND pl2.User_ID = ?
        ), 1, 0
    ) AS hasLiked
FROM Post p
JOIN User u ON u.ID = p.User_ID AND u.Deactivated = 0
LEFT JOIN PostTopic pt ON pt.Post_ID = p.PostID
LEFT JOIN Topic t ON t.TopicID = pt.Topic_ID
LEFT JOIN PostLike pl ON pl.Post_ID = p.PostID
LEFT JOIN User pl_user ON pl_user.ID = pl.User_ID AND pl_user.Deactivated = 0
LEFT JOIN Comment c ON c.Post_ID = p.PostID
LEFT JOIN User c_user ON c_user.ID = c.User_ID AND c_user.Deactivated = 0
WHERE 1=1
$whereTopic
GROUP BY p.PostID
ORDER BY $orderBy
";


// ------------------- PREPARAR Y EJECUTAR ------------------- //
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ------------------- PERFIL DE AUTOR DE CADA POST ------------------- //
$stmtProfile = $conn->prepare("
SELECT vp.ID, vp.Username, vp.DisplayName, vp.Bio, vp.Avatar, vp.Banner,
       cf.total_following, cf.total_followers,
       (SELECT 1 FROM Follower WHERE Follower_ID = ? AND Following_ID = vp.ID) AS already_following
FROM ViewProfile vp
LEFT JOIN CountFollow cf ON cf.User_ID = vp.ID
WHERE vp.ID = ?
");


//              ------ POP UP CREACION DE POST QUERYS php ------              //
$topics = $conn->query("SELECT * FROM Topic ORDER BY Name ASC");
$topicsArray = [];
if ($topics) {
    while ($t = $topics->fetch_assoc()) {
        $topicsArray[] = $t;
    }
}
// Avatar y username del usuario logueado (para el header, etc.)
$userAvatar = $myProfile['Avatar'] ?? 'img/pfp.jpg';
$username = $myProfile['Username'] ?? 'Username';
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title>INFO</title>

    <!-- Responsive -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,
    initial-scale=1.0">

    <!-- Diseño .css -->
    <link rel="stylesheet" href="styles/INFO.css">
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
                <div class="notif-container">
                    <div id="notif-icon">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($hasUnread): ?>
                            <span id="notif-badge"></span>
                        <?php endif; ?>
                    </div>
                    <div id="notif-dropdown" class="notif-dropdown">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $n): ?>
                                <div class="notif-item <?= $n['IsRead'] ? 'read' : 'unread' ?>">
                                    <div class="notif-avatar">
                                    <?php
$stmtActor = $conn->prepare("SELECT Avatar FROM User WHERE ID=?");
$stmtActor->bind_param("i", $n['Actor_ID']);
$stmtActor->execute();
$actor = $stmtActor->get_result()->fetch_assoc();

// Si el usuario tiene avatar (en BLOB), lo servimos con get_image.php.
// Si no, usamos una imagen predeterminada.
$actorAvatar = !empty($actor['Avatar'])
    ? "get_image.php?id={$n['Actor_ID']}&type=avatar&v=" . time()
    : "img/default_avatar.jpg";
?>

<img src="<?= htmlspecialchars($actorAvatar) ?>" alt="avatar">

                                    </div>
                                    <div class="notif-content">
                                        <p><?= htmlspecialchars($n['Message']) ?></p>
                                        <span class="notif-time"><?= date("d M H:i", strtotime($n['CreatedAt'])) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="notif-empty">No notifications yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </nav>
    </header>
    <section class="main">
        <!--           ------ SIDEBAR ------               -->
        <section class="sidebar" id="s-sidebar">
            <?php if ($loggedIn): ?>
                <div class="user">
                    <a href="#" class="user-dd-link">
                        <input type="checkbox" id="user-checkb">
                        <label for="user-checkb">
                            <li class="user-a">
                            <img src="get_image.php?id=<?= $loggedInId ?>&type=avatar&v=<?= time() ?>" alt="pfp">


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
                        </ul>
                    </div>

                </div>
                <div class="division-hr">
                    <hr>
                </div>
            <?php endif; ?>
            <div class="home">
                <i class="fa-solid fa-house"></i>
                <a href="">Home</a>
            </div>
            <div class="popular">
                <i class="fa-solid fa-fire"></i>
                <a href="">Popular</a>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <!-- Mostrar solo cuando hace login -->
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
                <!-- Filtrar por topics -->
                <?php $currentSort = $_GET['sort'] ?? 'default'; ?>
                <div class="t-content">
                    <ul class="tc-dd">
                        <li class="dropdown-topic"><a href="?topic=Qualifiers&sort=<?= $currentSort ?>">Qualifiers</a></li>
                        <li class="dropdown-topic"><a href="?topic=Tournaments&sort=<?= $currentSort ?>">Tournaments</a></li>
                        <li class="dropdown-topic"><a href="?topic=Players&sort=<?= $currentSort ?>">Players</a></li>
                        <li class="dropdown-topic"><a href="?topic=World Rankings&sort=<?= $currentSort ?>">World Rankings</a></li>
                        <li class="dropdown-topic"><a href="?topic=Controversies&sort=<?= $currentSort ?>">Controversies</a></li>
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
                            <a href="">Origins</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="">Legends</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="">Top Leagues</a>
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
                            <a href="">World Cups</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="">Eurocup</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="">Champions</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="">America's Cup</a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
        <!--           ------ OVERVIEW ------               -->
        <section class="page-content">
            <!-- sliders -->
            
            <!--           ------ FEED ------               -->
            <section class="main-section">
                <!--           ------ POSTS ------               -->
                <div class="infografia-container">
        <!-- Tarjeta 1 -->
        <a href="infografia_detalle.php?id=1" class="infografia-card">
            <div class="card-media">
                <img src="img/mundial2026.jpg" alt="Infografía Mundial 2026">
            </div>
            <div class="card-header">
                <img src="img/logo.png" alt="FIFA" class="card-img">
                <div class="card-h">
                    <strong>Mundial 2026</strong>
                    <p>FIFA World Cup overview</p>
                </div>
            </div>
            <div class="card-texta">
                <p>Una mirada rápida a los estadios, equipos y curiosidades del Mundial 2026.</p>
            </div>
        </a>

        <!-- Tarjeta 2 -->
        <a href="infografia_detalle.php?id=2" class="infografia-card">
            <div class="card-media">
                <img src="img/equipos_destacados.jpg" alt="Equipos Destacados">
            </div>
            <div class="card-header">
                <img src="img/logo.png" alt="FIFA" class="card-img">
                <div class="card-h">
                    <strong>Equipos Destacados</strong>
                    <p>Lo mejor del torneo</p>
                </div>
            </div>
            <div class="card-texta">
                <p>Explora los equipos favoritos y sus estadísticas en el Mundial 2026.</p>
            </div>
        </a>

        <!-- Tarjeta 3 -->
        <a href="infografia_detalle.php?id=3" class="infografia-card">
            <div class="card-media">
                <img src="img/estadios.jpg" alt="Estadios">
            </div>
            <div class="card-header">
                <img src="img/logo.png" alt="FIFA" class="card-img">
                <div class="card-h">
                    <strong>Estadios</strong>
                    <p>Arquitectura y capacidad</p>
                </div>
            </div>
            <div class="card-texta">
                <p>Descubre los estadios donde se jugará el Mundial 2026 y sus características.</p>
            </div>
        </a>
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
            <form method="POST" enctype="multipart/form-data" class="post-post"> <!-- quite el action, el create_post ya se encarga de que se actualice -->
                <!-- tambien uso class="post-post" en vez de usar IDs, asi en las demás paginas se pueda usar sin problemas -->
                
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
                    <img src="get_image.php?id=<?= $loggedInId ?>&type=avatar&v=<?= time() ?>" alt="Profile Picture">
                    </div>
                    <div class="c-select">
                        <p><?= htmlspecialchars($username) ?></p>
                        <select class="c-category" name="topic" required>
                            <option value="">Select a topic</option>
                            <?php foreach ($topicsArray as $topic): ?>
                                <option value="<?= $topic['TopicID'] ?>"><?= htmlspecialchars($topic['Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Textarea para contenido -->
                <textarea name="content" id="content" placeholder="What's happening?" class="c-textarea"
                    required></textarea>

                <!-- Input de media -->
                <input type="file" name="media" id="cInput" accept="image/*,video/*" style="display: none;">
                <label for="cInput" id="cLabel"><i class="fa-solid fa-images"></i> Add to your post</label>

                <!-- Contenedor para mostrar la media seleccionada -->
                <div class="c-display-media" id="cdm"></div>

                <!-- Botón de enviar -->
                <div class="c-btn">
                    <button type="submit">Post</button>
                </div>
            </form>
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

            <!-- Usuario y selector de categoría -->
            <div class="i-u">
                <div class="i-pfp">
                <img src="img/avatar.jpg" alt="pfp">
                </div>
                <div class="i-select">
                <p><?= htmlspecialchars($username) ?></p>
                <select class="c-category" name="topic" required>
                    <option value="">Select a topic</option>
                </select>
                </div>
            </div>
            <div class="i-title-img">
                <div class="timg">
                    <img src="img/avatar.jpg" alt="logo">
                </div>
                <input type="text" placeholder="Title">
            </div>

            <!-- Textarea para contenido -->
            <textarea name="textcont" placeholder="What's happening?" class="i-textarea" required></textarea>

            <!-- Input de media -->
            <input type="file" id="iInput" name="media" class="iInput" accept="image/*,video/*" style="display: none;">
            <label for="iInput" class="iLabel"><i class="fa-solid fa-images"></i> Add to your post</label>

            <!-- Contenedor para mostrar la media seleccionada -->
            <div class="i-display-media"></div>

            <!-- Botón de enviar -->
            <div class="i-btn">
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
                    <button class = "r-close"><i class="fa-solid fa-xmark"></i></button>
                    <span>Reactivate account</span>
                </div>
                <!-- Línea divisoria -->
                <div class="division-hr"><hr></div>
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
    
    <!--           ------ SCRIPTS ------               -->
    <script>
        const loggedInUserID = <?= $_SESSION['ID'] ?? 'null' ?>;
    </script>
    <script src="js/NAVSIDEBAR.js"></script>
    <script src="js/INFO.js"></script>
    <script type="module" src="https://widgets.api-sports.io/2.0.3/widgets.js"></script>
</body>