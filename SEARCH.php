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

// ----------------- Cargar variables del archivo .env ----------------- //
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

// Perfil actual
$currentProfileID = $_GET["id"] ?? $loggedInId;
$profile = null;
if ($currentProfileID) {
    $stmt = $conn->prepare("SELECT * FROM ViewProfile WHERE ID = ?");
    $stmt->bind_param("i", $currentProfileID);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Datos del usuario logueado
$myProfile = $loggedIn ? $conn->query("SELECT * FROM ViewProfile WHERE ID=" . (int) $loggedInId)->fetch_assoc() : null;

// ------------------- SUGERENCIAS ------------------- //
$resultado_sugerencias = null;
if ($loggedIn) {
    $sugerencias = $conn->prepare("
        SELECT u.ID, u.DisplayName, u.Username, u.Avatar, u.Banner, u.Bio
        FROM User u
        WHERE u.ID != ? 
        AND u.Deactivated = 0
        AND NOT EXISTS (
            SELECT 1 FROM Follower
            WHERE Follower_ID = ? AND Following_ID = u.ID
        )
        LIMIT 10
    ");
    $sugerencias->bind_param("ii", $loggedInId, $loggedInId);
    $sugerencias->execute();
    $resultado_sugerencias = $sugerencias->get_result();
    $sugerencias->close();
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
    $stmtNotif->close();

    $stmtUnread = $conn->prepare("SELECT 1 FROM Notification WHERE User_ID=? AND IsRead=0 LIMIT 1");
    $stmtUnread->bind_param("i", $loggedInId);
    $stmtUnread->execute();
    $hasUnread = $stmtUnread->get_result()->num_rows > 0;
    $stmtUnread->close();
}

// ------------------- TEMAS ------------------- //
// ------ POP UP CREACION DE POST QUERYS php ------ //
$topics = $conn->query("SELECT * FROM Topic ORDER BY Name ASC");
$topicsArray = [];
if ($topics) {
    while ($t = $topics->fetch_assoc()) {
        $topicsArray[] = $t;
    }
}

// Username del usuario logueado
$username = $myProfile['Username'] ?? 'Username';

// ------------------- PARÁMETROS DE BÚSQUEDA ------------------- //
$searchTerm = trim($_GET['q'] ?? '');
$topic = trim($_GET['topic'] ?? '');
$sort = $_GET['sort'] ?? 'default';
$dsort = $_GET['dsort'] ?? 'alltime';

// ------------------- PREPARAR FILTROS PARA LA VIEW ------------------- //
$whereViewTopic = "";
$whereViewSearch = "";
$whereViewDate = "";

// Inicializar parámetros (el primero siempre es para hasLiked)
$params = [$loggedInId];
$types = "i";

// Filtro por tema
if (!empty($topic)) {
    $whereViewTopic = " AND LOWER(TRIM(v.TopicName)) = LOWER(TRIM(?)) ";
    $params[] = $topic;
    $types .= "s";
}

// Filtro por búsqueda
if ($searchTerm !== '') {
    $whereViewSearch = " AND (v.Content LIKE CONCAT('%', ?, '%') 
                         OR v.Username LIKE CONCAT('%', ?, '%')) ";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

// Filtro por fecha
if ($dsort === "pastyear") {
    $whereViewDate = " AND v.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
} elseif ($dsort === "pastmonth") {
    $whereViewDate = " AND v.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
}

// ------------------- ORDEN DE POSTS ------------------- //
$orderBy = "v.CreatedAt DESC"; // default
if ($sort === "tlikes") {
    $orderBy = "v.Likes DESC";
} elseif ($sort === "tcomments") {
    $orderBy = "v.Comments DESC";
}

// ------------------- QUERY DE POSTS USANDO LA VIEW ------------------- //
$query = "
SELECT v.*,
       IF(EXISTS(SELECT 1 FROM PostLike pl2 WHERE pl2.Post_ID = v.PostID AND pl2.User_ID = ?), 1, 0) AS hasLiked
FROM ViewPosts v
WHERE (v.Status = 'Approved' OR v.UserID = ?)
$whereViewTopic
$whereViewSearch
$whereViewDate
ORDER BY $orderBy
LIMIT 50
";
// 🔹 Agregar el parámetro extra para el filtro por UserID
$params[] = $loggedInId;
$types .= "i";


$stmtPosts = $conn->prepare($query);
if (!$stmtPosts) {
    die("Error preparando consulta de posts: " . $conn->error);
}
$stmtPosts->bind_param($types, ...$params);
$stmtPosts->execute();
$resultPosts = $stmtPosts->get_result();

// ------------------- CONSULTA DE USUARIOS ------------------- //
$resultUsers = [];
if ($searchTerm !== '') {
    // Asegurar que loggedInId sea un entero válido
    $searchUserId = (int)$loggedInId;
    
    $sqlUsers = "
        SELECT 
            U.ID,
            U.Username,
            U.DisplayName,
            U.Avatar,
            U.Banner,
            U.Bio,
            (SELECT COUNT(*) FROM Follower F1 WHERE F1.Following_ID = U.ID) AS total_followers,
            (SELECT COUNT(*) FROM Follower F2 WHERE F2.Follower_ID = U.ID) AS total_following,
            (SELECT COUNT(*) > 0 FROM Follower F3 WHERE F3.Follower_ID = ? AND F3.Following_ID = U.ID) AS already_following
        FROM User U
        WHERE U.Deactivated = 0
        AND (
            U.Username LIKE ? 
            OR U.DisplayName LIKE ? 
            OR U.Bio LIKE ?
        )
        ORDER BY U.DisplayName ASC
    ";
    
    $stmtUsers = $conn->prepare($sqlUsers);
    
    if (!$stmtUsers) {
        die("Error preparando consulta de usuarios: " . $conn->error);
    }
    
    // Preparar el término de búsqueda con los %
    $searchPattern = "%{$searchTerm}%";
    
    // Ahora hacemos bind: 1 integer + 3 strings
    $stmtUsers->bind_param("isss", 
        $searchUserId,      // Placeholder 1: integer
        $searchPattern,     // Placeholder 2: string
        $searchPattern,     // Placeholder 3: string
        $searchPattern      // Placeholder 4: string
    );
    
    $stmtUsers->execute();
    $resultUsers = $stmtUsers->get_result();
}

// ------------------- QUERY DE PERFIL DE CADA AUTOR ------------------- //
$stmtProfile = $conn->prepare("
    SELECT ID, Username, DisplayName, Avatar, Banner, Bio,
           (SELECT COUNT(*) FROM Follower WHERE Follower_ID = ?) AS total_following,
           (SELECT COUNT(*) FROM Follower WHERE Following_ID = ?) AS total_followers,
           (SELECT COUNT(*) > 0 FROM Follower WHERE Follower_ID = ? AND Following_ID = ?) AS already_following
    FROM User
    WHERE ID = ?
");

// ------ POP UP CREACION DE INFOGRAFIAS php ------ //
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
    <link rel="stylesheet" href="styles/SEARCH.css">
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
    <section class="main">
        <!--           ------ SIDEBAR ------               -->
        <section class="sidebar" id="s-sidebar">
            <?php if ($loggedIn): ?>
                <div class="user">
                    <a href="#" class="user-dd-link">
                        <input type="checkbox" id="user-checkb">
                        <label for="user-checkb">
                            <li class="user-a">
                                <img src="get_image.php?id=<?= $myProfile['ID'] ?>&type=avatar&v=<?= time() ?>" alt="pfp">

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
                                    <a href="SoyAdmin.php">Soy admin</a>
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
                        <li class="dropdown-topic"><a href="SEARCH.php?q=<?= $searchTerm ?>&topic=Qualifiers&sort=<?= $currentSort ?>">Qualifiers</a></li>
                        <li class="dropdown-topic"><a href="SEARCH.php?q=<?= $searchTerm ?>&topic=Tournaments&sort=<?= $currentSort ?>">Tournaments</a></li>
                        <li class="dropdown-topic"><a href="SEARCH.php?q=<?= $searchTerm ?>&topic=Players&sort=<?= $currentSort ?>">Players</a></li>
                        <li class="dropdown-topic"><a href="SEARCH.php?q=<?= $searchTerm ?>&topic=World Rankings&sort=<?= $currentSort ?>">World Rankings</a></li>
                        <li class="dropdown-topic"><a href="SEARCH.php?q=<?= $searchTerm ?>&topic=Controversies&sort=<?= $currentSort ?>">Controversies</a></li>
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
        <section class="page-content">
            <!--           ------ FEED ------               -->
            <section class="main-section">
                <!--           ------ RESULTS ------               -->
                <section class="results-section">
                    <div class="results-bar">
                        <button id="sort-posts">Posts</button>
                        <button id="sort-people">People</button>
                    </div>
                    <div class="results-sort-by">
                        <!--Defaults-->
                        <label for="sort-select"></label>
                        <select name="sort" id="sort-select">
                            <option value="default" <?= $sort === 'default' ? 'selected' : '' ?>>Default</option>
                            <option value="tlikes" <?= $sort === 'tlikes' ? 'selected' : '' ?>>Top Likes</option>
                            <option value="tcomments" <?= $sort === 'tcomments' ? 'selected' : '' ?>>Top Comments</option>
                        </select>
                        <!--Filtra cualquier fecha, año y mes-->
                        <label for="sort-date"></label>
                        <select name="dsort" id="sort-date">
                            <option value="alltime" <?= $dsort === 'alltime' ? 'selected' : '' ?>>All time</option>
                            <option value="pastyear" <?= $dsort === 'pastyear' ? 'selected' : '' ?>>Past year</option>
                            <option value="pastmonth" <?= $dsort === 'pastmonth' ? 'selected' : '' ?>>Past month</option>
                        </select>
                    </div>
                    <div class="post-hr">
                        <hr>
                    </div>
                    <!--Muestra las publicaciones-->
                    <div class="results-show-posts">
                        <?php while ($row = $resultPosts->fetch_assoc()): ?>
                            <?php
                            $postUserId = $row['UserID'];
                            $loggedInId = $_SESSION['ID'] ?? null;

                            // Traer datos del autor
                            $stmtProfile->bind_param("iiiis", $loggedInId, $postUserId, $loggedInId, $postUserId, $postUserId);
                            $stmtProfile->execute();
                            $displayProfile = $stmtProfile->get_result()->fetch_assoc();

                            $mediaURL = $row['media'] ?? null;       // aquí puedes usar media y mediaType si ya los tienes
                            $mediaType = $row['mediaType'] ?? null; // "image" o "video"
                        
                            // Timestamp para evitar cache
                            $timestamp = time();
                            ?>

                            <div class="post-aux secpost" data-postid="<?= $row['PostID'] ?>">
                                <a class="post" href="POST.php?id=<?= $row['PostID'] ?>">
                                    <div class="card-header">
                                        <div class="card-dropdown">
                                            <img src="get_image.php?id=<?= $displayProfile['ID'] ?>&type=avatar&v=<?= time() ?>"
                                                alt="pfp" class="card-img"
                                                data-url="VPROFILE.php?id=<?= $displayProfile['ID'] ?>">

                                            <!-- dropdown -->
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
                                                    <p class="dd-user"
                                                        data-url="VPROFILE.php?id=<?= $displayProfile['ID'] ?>">
                                                        <?= htmlspecialchars($displayProfile['DisplayName'] ?: $displayProfile['Username']) ?>
                                                    </p>
                                                    <p id="dd-username">
                                                        @<?= htmlspecialchars($displayProfile['Username']) ?></p>
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

                                        <div class="card-h">
                                            <span>#<?= htmlspecialchars($row['TopicName'] ?? "News") ?></span>
                                            <span id="dot">•</span>
                                            <span id="pdate"><?= date("F j, Y", strtotime($row['CreatedAt'])) ?></span>
                                            <p class="card-ul" data-url="VPROFILE.php?id=<?= $displayProfile['ID'] ?>">
                                                <?= htmlspecialchars($displayProfile['Username']) ?>
                                                <?php if (!empty($row['Edited']) && $row['Edited'] == 1): ?>
                                                    <span class="puc-edited">(Edited)</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="card-texta">
                                        <input type="text" readonly value = "<?= htmlspecialchars($row['Title']) ?>" class="card-title">
                                        <textarea readonly
                                        maxlength="360"><?= htmlspecialchars($row['Content']) ?></textarea>
                                        <div class="inline-edit-actions"></div> <!-- botones Save/Cancel -->
                                        <span class="editing-status"></span> <!-- "Editando..." temporal -->
                                    </div>

                                    <!--           ------ MEDIA DE LA PUBLICACION ------               -->
                                    <?php if (!empty($row['Media']) && !empty($row['MediaType'])): ?>
                                        <div class="card-media">
                                            <?php if (str_starts_with($row['MediaType'], 'image/')): ?>
                                                <img src="get_post_media.php?id=<?= $row['PostID'] ?>&v=<?= time() ?>" alt="media"
                                                    style="width:100%; max-height:500px; object-fit:cover; border-radius:var(--main-space);">

                                            <?php elseif (str_starts_with($row['MediaType'], 'video/')): ?>
                                                <video class="post-video" controls
                                                    style="width:100%; max-height:500px; object-fit:cover; border-radius:var(--main-space);">
                                                    <source src="get_post_media.php?id=<?= $row['PostID'] ?>&v=<?= time() ?>"
                                                        type="<?= $row['MediaType'] ?>">
                                                </video>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-btns">
                                        <button class="like-btn" data-postid="<?= $row['PostID'] ?>"
                                            id="like-btn-<?= $row['PostID'] ?>">
                                            <i
                                                class="<?= $row['hasLiked'] ? 'fa-solid liked' : 'fa-regular' ?> fa-star"></i>
                                            <span id="like-count-<?= $row['PostID'] ?>"><?= $row['Likes'] ?></span>
                                        </button>
                                        <button class="comment-btn">
                                            <i class="fa-regular fa-comment"></i><span><?= $row['Comments'] ?></span>
                                        </button>
                                        <div class="stats">
                                            <span><i class="fa-solid fa-chart-simple"></i></span>
                                            <span><?= $row['Views'] ?></span>
                                            <span>Views</span>
                                        </div>
                                    </div>
                                </a>
                                <div class="c-opt">
                                    <i class="fa-solid fa-ellipsis"></i>
                                    <div class="c-menu">
                                        <?php if ($loggedInId === $postUserId): ?>
                                            <!-- Editar (solo autor) -->
                                            <button type="button" class="edit-post-btn iconi"
                                                data-postid="<?= $row['PostID'] ?>">
                                                <i class="fa-regular fa-pen-to-square"></i>Edit
                                            </button>
                                        <?php endif; ?>

                                        <!-- Borrar (solo admin) -->
                                        <?php if ($isAdmin): ?>
                                            <form action="delete_post.php" method="POST" class="delete-post-form">
                                                <input type="hidden" name="post_id" value="<?= $row['PostID'] ?>">
                                                <button type="button" class="delete-post-btn iconi">
                                                    <i class="fa-solid fa-trash"></i>Delete<?= $isAdmin ? '' : '' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Reportar (solo si NO es el autor) -->
                                        <?php if ($loggedInId !== $postUserId && !$isAdmin): ?>
                                            <button type="button" class="report-post-btn iconi"
                                                data-postid="<?= $row['PostID'] ?>">
                                                <i class="fa-solid fa-flag"></i>Report
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="post-hr">
                                <hr>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <!--Muestra loss perfiles-->
                    <div class="results-show-users">
                        <?php while ($user = $resultUsers->fetch_assoc()): ?>
                            <div class="su-aux">
                                <a class="su-user" data-url="VPROFILE.php?id=<?= $user['ID'] ?>">
                                    <div class="su-img">
                                        <div class="card-dropdown">
                                            <img src="get_image.php?id=<?= $user['ID'] ?>&type=avatar&v=<?= time() ?>"
                                                alt="pfp" class="card-img" data-url="VPROFILE.php?id=<?= $user['ID'] ?>">
                                            <!-- dropdown -->
                                            <div class="dropdown-profile">
                                                <div class="dd-banner">
                                                    <img src="get_image.php?id=<?= $user['ID'] ?>&type=banner&v=<?= time() ?>"
                                                        alt="banner">
                                                </div>
                                                <div class="dd-pfp">
                                                    <img src="get_image.php?id=<?= $user['ID'] ?>&type=avatar&v=<?= time() ?>"
                                                        alt="pfp">
                                                </div>
                                                <div class="dd-info">
                                                    <p class="dd-user" data-url="VPROFILE.php?id=<?= $user['ID'] ?>">
                                                        <?= htmlspecialchars($user['DisplayName'] ?: $user['Username']) ?>
                                                    </p>
                                                    <p id="dd-username">@<?= htmlspecialchars($user['Username']) ?></p>
                                                    <p><?= htmlspecialchars($user['Bio'] ?: "This user hasn't set a bio.") ?>
                                                    </p>
                                                    <div class="dd-ff">
                                                        <div class="following">
                                                            <span><strong><?= $user['total_following'] ?? 0 ?></strong></span>
                                                            <span id="dd-f">Following</span>
                                                        </div>
                                                        <div class="followers">
                                                            <span><strong><?= $user['total_followers'] ?? 0 ?></strong></span>
                                                            <span id="dd-f">Followers</span>
                                                        </div>
                                                    </div>
                                                    <?php if ($loggedIn && $loggedInId != $user['ID']): ?>
                                                        <button id="dd-follow-btn2" class="btn-follow"
                                                            data-seguido-id="<?= $user['ID'] ?>">
                                                            <?= $user['already_following'] ? 'Unfollow' : 'Follow' ?>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="su-user-display">
                                        <p id="suu"><?= htmlspecialchars($user['DisplayName']) ?></p>
                                        <p id="sun">@<?= htmlspecialchars($user['Username']) ?></p>
                                        <p><?= htmlspecialchars($user['Bio'] ?: "This user hasn't set a bio.") ?>
                                    </div>
                                    <div class="su-btn">
                                        <?php if ($loggedIn && $loggedInId != $user['ID']): ?>
                                            <button class="btn-follow" data-seguido-id="<?= $user['ID'] ?>">
                                                <?= $user['already_following'] ? 'Unfollow' : 'Follow' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>

                </section>
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
                                            <img src="get_image.php?id=<?= $sugerido['ID'] ?>&type=avatar&v=<?= time() ?>"
                                                alt="pfp" class="card-img" data-url="VPROFILE.php?id=<?= $sugerido['ID'] ?>">
                                            <div class="dropdown-profile">
                                                <div class="dd-banner">
                                                    <img src="get_image.php?id=<?= $sugerido['ID'] ?>&type=banner&v=<?= time() ?>"
                                                        alt="banner">
                                                </div>
                                                <div class="dd-pfp">
                                                    <img src="get_image.php?id=<?= $sugerido['ID'] ?>&type=avatar&v=<?= time() ?>"
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
    <script>
        const loggedInUserID = <?= $_SESSION['ID'] ?? 'null' ?>;
    </script>
    <script src="js/NAVSIDEBAR.js"></script>
    <script src="js/SEARCH.js"></script>
    <script type="module" src="https://widgets.api-sports.io/2.0.3/widgets.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
</body>