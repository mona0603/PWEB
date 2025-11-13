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

    $stmtUnread = $conn->prepare("SELECT 1 FROM Notification WHERE User_ID=? AND IsRead=0 LIMIT 1");
    $stmtUnread->bind_param("i", $loggedInId);
    $stmtUnread->execute();
    $hasUnread = $stmtUnread->get_result()->num_rows > 0;
}

// ------------------- TEMAS ------------------- //

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
// ------------------- PARÁMETROS DE BÚSQUEDA ------------------- //
$searchTerm = trim($_GET['q'] ?? '');
$topic = trim($_GET['topic'] ?? '');
$sort = $_GET['sort'] ?? 'default';
$dsort = $_GET['dsort'] ?? 'alltime';

// Filtros
$whereDate = "";
if ($dsort === "pastyear")
    $whereDate = " AND p.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
elseif ($dsort === "pastmonth")
    $whereDate = " AND p.CreatedAt >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";

$whereTopic = "";
$whereSearch = "";

// IMPORTANTE: Primero inicializamos params y types con el loggedInId
// porque es el PRIMER parámetro en la query (hasLiked)
$params = [$loggedInId];
$types = "i";

// Ahora agregamos los demás filtros
if (!empty($topic)) {
    $whereTopic = " AND LOWER(TRIM(t.Name)) = LOWER(TRIM(?)) ";
    $params[] = $topic;
    $types .= "s";
}

if ($searchTerm !== '') {
    $whereSearch = " AND (p.Content LIKE CONCAT('%', ?, '%') 
                     OR u.Username LIKE CONCAT('%', ?, '%') 
                     OR u.DisplayName LIKE CONCAT('%', ?, '%')) ";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

// ------------------- ORDEN ------------------- //
$orderBy = "p.CreatedAt DESC";
if ($sort === "tlikes")
    $orderBy = "COUNT(DISTINCT pl.LikeID) DESC";
elseif ($sort === "tcomments")
    $orderBy = "COUNT(DISTINCT c.CommentID) DESC";

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
$whereSearch
$whereDate
GROUP BY p.PostID
ORDER BY $orderBy
LIMIT 50
";

// YA NO agregamos $loggedInId aquí porque ya está en $params
// $params[] = $loggedInId;  <-- ELIMINA ESTA LÍNEA
// $types .= "i";            <-- ELIMINA ESTA LÍNEA

$stmtPosts = $conn->prepare($query);
if (!$stmtPosts)
    die("Error en preparación: " . $conn->error);
$stmtPosts->bind_param($types, ...$params);
$stmtPosts->execute();
$resultPosts = $stmtPosts->get_result();


// ------------------- CONSULTA DE USUARIOS ------------------- //
$resultUsers = [];
if ($searchTerm !== '') {
    $stmtUsers = $conn->prepare("
        SELECT 
            U.ID,
            U.Username,
            U.DisplayName,
            U.Avatar,
            U.Banner,
            U.Bio,
            -- Total de seguidores
            (SELECT COUNT(*) FROM Follower F1 WHERE F1.Following_ID = U.ID) AS total_followers,
            -- Total de seguidos
            (SELECT COUNT(*) FROM Follower F2 WHERE F2.Follower_ID = U.ID) AS total_following,
            -- Si el usuario logueado ya lo sigue
            (SELECT COUNT(*) > 0 FROM Follower F3 WHERE F3.Follower_ID = ? AND F3.Following_ID = U.ID) AS already_following
        FROM User U
        WHERE U.Deactivated = 0
        AND (
            U.Username LIKE CONCAT('%', ?, '%') 
            OR U.DisplayName LIKE CONCAT('%', ?, '%') 
            OR U.Bio LIKE CONCAT('%', ?, '%')
        )
        ORDER BY U.DisplayName ASC
    ");
    $stmtUsers->bind_param("ssss", $loggedInId, $searchTerm, $searchTerm, $searchTerm);
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

// Avatar y username del usuario logueado (para el header, etc.)
// $userAvatar = $myProfile['Avatar'] ?? 'img/pfp.jpg';
$username = $myProfile['Username'] ?? 'Username';

// ------------------- QUERYs DE LA ENCICLOPEDIA ------------------- //
// AJAX: Consulta individual
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Verificar que la conexión está activa
    if (!$conn->ping()) {
        $conn = dbConnection::connect();
    }

    // Obtener datos principales de la VIEW usando prepared statement
    $stmt = $conn->prepare("SELECT * FROM ViewMyPedia WHERE ID = ?");
    if (!$stmt) {
        echo json_encode(['error' => 'Error en preparación: ' . $conn->error]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();

    if (!$data) {
        echo json_encode(['error' => 'Infografía no encontrada']);
        $conn->close();
        exit;
    }

    // Convertir imagen a base64 para JSON
    if (isset($data['Logo'])) {
        $data['Logo'] = base64_encode($data['Logo']);
    }
    if (isset($data['Media']) && $data['Media']) {
        $data['Media'] = base64_encode($data['Media']);
    }

    // Obtener los tags personalizados usando prepared statement
    $stmtTags = $conn->prepare("SELECT Field_Name, Field_Value FROM MyPediaContent WHERE ID_MP = ? ORDER BY ID_Extra ASC");
    if (!$stmtTags) {
        echo json_encode(['error' => 'Error al obtener tags: ' . $conn->error]);
        $conn->close();
        exit;
    }

    $stmtTags->bind_param("i", $id);
    $stmtTags->execute();
    $resTags = $stmtTags->get_result();

    $tags = [];
    while ($tag = $resTags->fetch_assoc()) {
        $tags[] = $tag;
    }
    $stmtTags->close();

    // Agregar los tags al array de datos
    $data['tags'] = $tags;

    // IMPORTANTE: Cerrar la conexión antes de salir
    $conn->close();

    echo json_encode($data);
    exit;
}

//              ------ FILTRAR ViewMyPedia por TEMA php ------              //
$topic = $_GET['topic'] ?? '';

// Si se seleccionó un tema, se filtra por él
if (!empty($topic)) {
    $stmt = $conn->prepare("
        SELECT v.*
        FROM ViewMyPedia v
        JOIN History h ON v.H_ID = h.H_ID
        WHERE h.H_Name = ?
        ORDER BY v.CreatedAt DESC
    ");
    $stmt->bind_param("s", $topic);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Consulta por defecto (sin filtro)
    $sql = "SELECT * FROM ViewMyPedia ORDER BY CreatedAt DESC";
    $result = $conn->query($sql);
}

// Verificar que la consulta fue exitosa
if (!$result) {
    die("Error en la consulta: " . $conn->error);
}

//              ------ POP UP CREACION DE INFOGRAFIAS php ------              //
$infog = $conn->query("SELECT * FROM History ORDER BY H_Name ASC");
$infogArray = [];
if ($infog) {
    while ($it = $infog->fetch_assoc()) {
        $infogArray[] = $it;
    }
}

// $epSort = $_GET['sort'] ?? 'default';

// // Obtener todos los temas de History
// $sql = "SELECT H_Name FROM History ORDER BY H_Name";
// $result = $conn->query($sql);

// $topics = [];
// if ($result && $result->num_rows > 0) {
//     while ($row = $result->fetch_assoc()) {
//         $topics[] = $row['H_Name'];
//     }
// }


// BORRAR INFOGRAFIAS
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    header('Content-Type: application/json');

    $id = $_POST['id'] ?? null;

    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no recibido']);
        exit;
    }

    try {
        $stmt = $conn->prepare("DELETE FROM MyPedia WHERE ID_MP = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();

        echo json_encode(['success' => $success]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
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
    <link rel="stylesheet" href="styles/ENCYCLOPEDIA.css">
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
                <?php $epSort = $_GET['sort'] ?? 'default'; ?>
                <div class="t-content">
                    <ul class="tc-dd">
                        <li class="dropdown-topic">
                            <a href="?topic=Origins">Origins</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="?topic=Legends">Legends</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="?topic=Top Leagues">Top Leagues</a>
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
                            <a href="?topic=World Cups">World Cups</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="?topic=Eurocup">Eurocup</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="?topic=Champions">Champions</a>
                        </li>
                        <li class="dropdown-topic">
                            <a href="?topic=America's Cup">America's Cup</a>
                        </li>
                    </ul>
                </div>
            </div>
        </section>
        <!--           ------ OVERVIEW ------               -->
        <section class="page-content">
            <!--           ------ INFOGRAFIAS ------               -->
            <section class="main-section">
                <?php while ($ep = $result->fetch_assoc()): ?>
                    <div class="e-content" data-id="<?= $ep['ID'] ?>">
                        <div class="ep-img">
                            <img src="data:image/jpeg;base64,<?= base64_encode($ep['Logo']) ?>" alt="">
                        </div>
                        <div class="ep-details">
                            <h1><?= htmlspecialchars($ep['Title']) ?></h1>
                            <span class="ep-content"><?= htmlspecialchars($ep['Content']) ?></span>
                        </div>

                        <?php if ($isAdmin): ?>
                            <button class="delete-info-btn" data-id="<?= $ep['ID'] ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
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
    <!--           ------ VER INFOGRAFIA COMPLETA ------               -->
    <div class="epmodal">
        <div class="epcontent">
            <!-- <div><button class="ep-close">close</button></div> -->
        </div>
    </div>
    <!-- Toast Notification -->



    <!--           ------ SCRIPTS ------               -->
    <script>
        const loggedInUserID = <?= $_SESSION['ID'] ?? 'null' ?>;
    </script>
    <script src="js/NAVSIDEBAR.js"></script>
    <script src="js/ENCYCLOPEDIA.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="module" src="https://widgets.api-sports.io/2.0.3/widgets.js"></script>
</body>