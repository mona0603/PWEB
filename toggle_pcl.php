<?php
require_once("middleware/auth_admin.php");
$auth = verifyAuthFusion($conn);
$loggedIn = $auth['loggedIn'];
$isAdmin = $auth['isAdmin'];
$loggedInId = $auth['userID'] ?? 0;

// Determinar qué perfil se está viendo
$currentProfileID = $postUser['ID'] ?? $loggedInId;
$postUserId = $postUser['ID'];


$postUser = [
    'ID' => $postUserId,
    'Username' => $post['Username'] ?? 'Unknown',
    'DisplayName' => $post['DisplayName'] ?? ($post['Username'] ?? 'Unknown'),
    'Bio' => $post['Bio'] ?? '',
    'Avatar' => $post['Avatar'] ?? null,
    'Banner' => $post['Banner'] ?? null, // BLOB real
    'total_following' => $post['total_following'] ?? 0,
    'total_followers' => $post['total_followers'] ?? 0,
    'already_following' => $post['already_following'] ?? 0
];
// Perfil que se muestra
$profile = null;
?>

<!-- Lo que se insertara -->
<div class="post-hr">
    <hr>
</div>
<div class="post-container secpost" data-postid="<?= $post['PostID'] ?>">
    <a class="post" href="POST.php?id=<?= $post['PostID'] ?>">
        <div class="card-header">
            <div class="card-dropdown">
                <!-- Avatar del autor -->
                <img src="<?= $postUser['Avatar']
                    ? "data:image/jpeg;base64," . base64_encode($postUser['Avatar'])
                    : 'img/pfp.jpg' ?>" alt="pfp" class="card-img"
                    data-url="VPROFILE.php?id=<?= htmlspecialchars($postUser['ID']) ?>">


                <div class="dropdown-profile">
                    <div class="dd-banner">
                        <img src="data:image/jpeg;base64,<?= base64_encode($postUser['Banner']) ?>" alt="banner">
                    </div>
                    <div class="dd-pfp">
                        <img src="data:image/jpeg;base64,<?= base64_encode($postUser['Avatar']) ?>" alt="pfp">
                    </div>
                    <div class="dd-info">
                        <p class="dd-user" data-url="VPROFILE.php?id=<?= $postUser['ID'] ?>">
                            <?= htmlspecialchars($postUser['DisplayName'] ?: $postUser['Username']) ?>
                        </p>
                        <p id="dd-username">@<?= htmlspecialchars($postUser['Username']) ?></p>
                        <p><?= htmlspecialchars($postUser['Bio'] ?: "This user hasn't set a bio.") ?></p>

                        <?php if (isset($postUser['total_following'])): ?>
                            <div class="dd-ff">
                                <div class="following">
                                    <span><strong><?= $postUser['total_following'] ?? 0 ?></strong></span>
                                    <span id="dd-f">Following</span>
                                </div>
                                <div class="followers">
                                    <span><strong><?= $postUser['total_followers'] ?? 0 ?></strong></span>
                                    <span id="dd-f">Followers</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($loggedIn && $loggedInId != $postUser['ID'] && isset($postUser['already_following'])): ?>
                            <button id="dd-follow-btn2" class="btn-follow" data-seguido-id="<?= $postUser['ID'] ?>">
                                <?= $postUser['already_following'] ? 'Unfollow' : 'Follow' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Información del post -->
            <div class="card-h">
                <span>#<?= htmlspecialchars($post['TopicName'] ?? "News") ?></span>
                <span id="dot">•</span>
                <span id="pdate"><?= date("F j, Y", strtotime($post['CreatedAt'])) ?></span>
                <p class="card-ul" data-url="VPROFILE.php?id=<?= $postUser['ID'] ?>">
                    <?= htmlspecialchars($postUser['Username']) ?>
                </p>
            </div>
        </div>

        <!-- Contenido del post -->
        <div class="card-texta">
            <input type="text" readonly value="<?= htmlspecialchars($post['Title']) ?>" class="card-title">
            <textarea readonly maxlength="360"><?= htmlspecialchars($post['Content']) ?></textarea>
            <div class="inline-edit-actions"></div> <!-- botones Save/Cancel -->
            <span class="editing-status"></span> <!-- "Editando..." temporal -->
        </div>

        <!-- Media -->
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
                <?php else: ?>
                    <a href="get_post_media.php?id=<?= $post['PostID'] ?>">Download media</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <!-- Botones like/comentario -->
        <div class="card-btns">
            <button class="like-btn" data-postid="<?= $post['PostID'] ?>" id="like-btn-<?= $post['PostID'] ?>">
                <i class="<?= $post['hasLiked'] ? 'fa-solid liked' : 'fa-regular' ?> fa-star"></i>
                <span id="like-count-<?= $post['PostID'] ?>"><?= $post['Likes'] ?></span>
            </button>
            <button class="comment-btn">
                <i class="fa-regular fa-comment"></i><span><?= $post['Comments'] ?></span>
            </button>
            <div class="stats">
                <span><i class="fa-solid fa-chart-simple"></i></span>
                <span><?= $post['Views'] ?></span>
                <span>Views</span>
            </div>
        </div>
    </a>

    <div class="c-opt">
        <i class="fa-solid fa-ellipsis"></i>
        <div class="c-menu">
            <?php if ($loggedInId === $postUserId): ?>
                <!-- Editar (solo autor) -->
                <button type="button" class="edit-post-btn iconi" data-postid="<?= $post['PostID'] ?>">
                    <i class="fa-regular fa-pen-to-square"></i>Edit
                </button>
            <?php endif; ?>

            <!-- Borrar (solo admin) -->
            <?php if ($isAdmin): ?>
                <form action="delete_post.php" method="POST" class="delete-post-form">
                    <input type="hidden" name="post_id" value="<?= $post['PostID'] ?>">
                    <button type="button" class="delete-post-btn iconi">
                        <i class="fa-solid fa-trash"></i>Delete<?= $isAdmin ? '' : '' ?>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Reportar (solo si NO es el autor) -->
            <?php if ($loggedInId !== $postUserId && !$isAdmin): ?>
                <button type="button" class="report-post-btn iconi" data-postid="<?= $post['PostID'] ?>">
                    <i class="fa-solid fa-flag"></i>Report
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>