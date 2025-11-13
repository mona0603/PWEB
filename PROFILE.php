<?php
session_start();
if (!isset($_SESSION["ID"])) {
    $loggedIn = false; // usuario no logueado
} else {
    $loggedIn = true;
    require_once("dbconnection.php");
    $conn = dbconnection::connect();
    $ID = $_SESSION["ID"];
    
    require_once("middleware/auth_admin.php");
    $auth = verifyAuthFusion($conn);
    $isAdmin = $auth['isAdmin'];

    $stmt = $conn->prepare("SELECT * FROM User WHERE ID = ?");
    $stmt->bind_param("i", $ID);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $username = $user["Username"];
    $displayname = $user["DisplayName"];
    $avatar = $user["Avatar"];
    $banner = $user["Banner"];
    $email = $user["Email"];
    $password = $user["Password"];
    $register = $user["Register"];
    $bio = $user["Bio"];

    // Pregunta de recuperación
    $recovery = $user['Recovery'];
    $birthdate = $user["Birthdate"] ?? null;

    if ($birthdate) {
        $birthArr = explode('-', $birthdate);
        $birthYear = $birthArr[0] ?? '';
        $birthMonthNum = isset($birthArr[1]) ? (int)$birthArr[1] : 0;
        $birthDay = isset($birthArr[2]) ? (int)$birthArr[2] : 0;
    
        // Lista de meses
        $months = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];
    
        $birthMonth = $birthMonthNum > 0 ? $months[$birthMonthNum - 1] : '';
    } else {
        // Usuario sin fecha registrada
        $birthYear = '';
        $birthMonth = '';
        $birthDay = '';
    }
    

    // ----------- TOPICS PARA EL MODAL -----------
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
    $username = $username ?? 'Username';

    // --- NOTIFICACIONES ---
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


}
// --- 1️⃣ Si hay POST, actualizar datos ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $displayname = $_POST["displayname"];
    $email = $_POST["email"];
    $bio = $_POST["bio"];
    $gender = $_POST["genero"] ?? null;

    $year = $_POST["y-month"] ?? '';
    $month = $_POST["m-month"] ?? '';
    $day = $_POST["d-month"] ?? '';
    $birthdate = ($year && $month && $day) ? "$year-$month-$day" : null;

    $avatarData = !empty($_FILES["avatar"]["tmp_name"])
        ? file_get_contents($_FILES["avatar"]["tmp_name"])
        : null;
    $bannerData = !empty($_FILES["banner"]["tmp_name"])
        ? file_get_contents($_FILES["banner"]["tmp_name"])
        : null;

    $stmt = $conn->prepare("CALL UpdateUser(?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $ID, $username, $displayname, $email, $gender, $birthdate, $avatarData, $bannerData, $bio);
    $stmt->execute();

    do {
        if ($res = $stmt->get_result()) {
            $res->free();
        }
    } while ($stmt->more_results() && $stmt->next_result());

    $stmt->close();

    // ✅ Confirmar cambios si hay transacciones
    $conn->commit();

    // 🔹 No cierres la conexión aquí todavía
    header("Location: PROFILE.php");
    exit;
}
//              ------ POP UP CREACION DE INFOGRAFIAS php ------              //
$infog = $conn->query("SELECT * FROM History ORDER BY H_Name ASC");
$infogArray = [];
if ($infog) {
    while ($it = $infog->fetch_assoc()) {
        $infogArray[] = $it;
    }
}

// --- 2️⃣ Obtener datos del usuario actualizados ---
$stmt = $conn->prepare("SELECT * FROM User WHERE ID = ?");
$stmt->bind_param("i", $ID);
$stmt->execute();


$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$conn->close();
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
    <link rel="stylesheet" href="styles/PROFILE.css">
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
    <!--           ------ SIDEBAR ------               -->
    <section class="main">
        <section class="sidebar" id="s-sidebar">
            <div class="user">
                <a href="#" class="user-dd-link">
                    <input type="checkbox" id="user-checkb">
                    <label for="user-checkb">
                        <li class="user-a">
                            <img src="data:image/jpeg;base64,<?= base64_encode($avatar) ?>" alt="pfp">
                            <div class="u-pfp">
                                <p id="id_user"><?= htmlspecialchars($displayname) ?></p>
                                <p id="id_id">@<?= htmlspecialchars($username) ?></p>
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
        <!--           ------ EDITAR PERFIL ------               -->
        <section class="profile-content">
            <div class="p-main">
                <form action="PROFILE.php" class="edit-form" method="POST" enctype="multipart/form-data">
                    <div class="up-banner">
                        <input type="file" id="fileInputBanner" name="banner" accept="image/jpeg, image/png, image/jpg"
                            style="display: none;">
                        <img id="bannerImage" src="get_image.php?id=<?= $ID ?>&type=banner&v=<?= time() ?>" alt="banner"
                            onclick="selectImageBanner()">
                    </div>
                    <div class="up-pfp">
                        <input type="file" id="fileInput" name="avatar" accept="image/jpeg, image/png, image/jpg"
                            style="display: none;">
                        <img id="profileImage" src="get_image.php?id=<?= $ID ?>&type=avatar&v=<?= time() ?>" alt="pfp"
                            onclick="selectImage()">

                    </div>
                    <div class="down-member">
                        <span>Member since:</span>
                        <span><?= htmlspecialchars($register) ?></span>
                    </div>
                    <div class="down-display">
                        <div class="displayname-wrapper">
                            <input type="text" name="displayname" value="<?= htmlspecialchars($displayname) ?>" id="dw">
                        </div>
                        <div class="username-wrapper">
                            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" id="uw">
                        </div>
                    </div>
                    <div class="down-user">
                        <textarea maxlength="100" name="bio"
                            placeholder="This is my bio."><?= htmlspecialchars($bio) ?></textarea>
                        <input type="text" name="email" value="<?= htmlspecialchars($email) ?>" required="email">
                        <button id="changep" type="button"><i class="fa-solid fa-key"></i>Change Password</button>
                    </div>
                    <div class="down-bdate">
                        <p>Birthdate:</p>
                        <div class="down-date">
                            <select name="m-month" id="down-month-select" data-selected="<?= $birthMonth ?>"></select>
                            <select name="d-month" id="down-day-select" data-selected="<?= $birthDay ?>"></select>
                            <select name="y-month" id="down-year-select" data-selected="<?= $birthYear ?>"></select>
                        </div>
                    </div>
                    <div class="down-gender">
                        <label>
                            <input type="radio" name="genero" value="Female" <?php if ($user['Gender'] == 'Female')
                                echo 'checked'; ?>>Female
                        </label>
                        <label>
                            <input type="radio" name="genero" value="Male" <?php if ($user['Gender'] == 'Male')
                                echo 'checked'; ?>>Male
                        </label>
                        <label>
                            <input type="radio" name="genero" value="Other" <?php if ($user['Gender'] == 'Other')
                                echo 'checked'; ?>>Other
                        </label>
                    </div>
                    <div class="down-btns">
                        <button id="save-btn" type="submit">Save Changes</button>
                    </div>
                </form>
                <div class="down-btns">
                    <button id="delete-btn">Delete Account</button>
                </div>
            </div>
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
    <!--           ------ CAMBIAR CONTRASEÑA ------               -->
    <div class="cpmodal"> <!-- fondo -->
        <div class="change-content" id="change-c"> <!-- ventana base -->
            <div class="btn-cp-close">
                <button class="cp-close"><i class="fa-solid fa-xmark"></i></button>
                <span>Change Password</span>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <form class="cp-aux" action="PROFILE.php">
                <div class="cp-u">
                    <input type="text" placeholder="Current Password" id="current_password">
                    <input type="text" placeholder="New Password" id="new_password">
                    <input type="text" placeholder="Confirm new Password" id="confirm_password">
                    <div id="mensajecontra"></div>
                </div>
                <div class="cp-btn">
                    <button id="cp-cancel">Cancel</button>
                    <button id="cp-save" type="submit">Save</button>
                </div>
                <div class="cp-u-forgot">
                    <p>Forgot your password?</p>
                    <a href="#" id="Recover">Recover</a>
                </div>
            </form>
            <form class="cp-question" style="display: none;" action="PROFILE.php">
                <div class="cpq">
                    <p>What's your pet's name?</p>
                    <div class="cpq-inputs">
                        <input type="text" placeholder="My answer" id="recoveryAnswer">
                        <input type="text" placeholder="YourNewGeneratedPassword" id="newGeneratedPassword" value=""
                            style="display:none;">
                    </div>
                    <div id="mensajerestaura"></div>
                </div>
                <div class="cpq-btn">
                    <button id="cpq-cancel">Cancel</button>
                    <button id="cpq-verify">Verify</button>
                </div>
            </form>
        </div>
    </div>
    <!--           ------ BORRAR CUENTA ------               -->
    <div class="dmodal"> <!-- fondo -->
        <div class="delete-modal"> <!-- ventana base -->
            <div class="btn-d-close">
                <button class="d-close" id="d-close"><i class="fa-solid fa-xmark"></i></button>
                <span>Delete account</span>
            </div>
            <div class="division-hr">
                <hr>
            </div>
            <div class="d-question">
                <h2>Confirm account deletion</h2>
                <br>
                <p>Please enter your password to confirm that you wish to delete your account.</p>
                <br>
                <p class="undone">This action cannot be undone.</p>
                <br>
                <div class="confirm-delete">
                    <input type="password" id="confirmPassword" placeholder="Password123!">
                    <button onclick="ConfirmDelete()">Confirm</button>

                </div>
                <p id="errorMessage" style="color: red;"></p>
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
    <script src="js/NAVSIDEBAR.js"></script>
    <script src="js/PROFILE.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
</body>