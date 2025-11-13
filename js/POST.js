//2.              ----- SHOW TEXTAREA Y BOTONES PARA COMENTAR -----             //
async function isUserLoggedIn() {
    const res = await fetch("POST.php?checkLogin=1");
    const data = await res.json();
    return data.loggedIn;
}

document.addEventListener("DOMContentLoaded", () => {
    //Manejo de triggers y cancel dentro de cada post-comment
    document.querySelectorAll(".post-comment").forEach(post => {
        const commentBox = post.querySelector(".post-cpost");
        const textarea = commentBox.querySelector("textarea");
        const trigger = post.querySelector(".comment-trigger");
        const cancelBtn = post.querySelector("#pcancel");

        const openComment = () => {
            commentBox.classList.add("active");
            trigger.classList.add("hidden");
            textarea.focus();
        };

        // Click en trigger
        trigger.addEventListener("click", openComment);

        // Click en Cancel
        cancelBtn.addEventListener("click", () => {
            commentBox.classList.remove("active");
            trigger.classList.remove("hidden");
        });
    });

    // Manejo del botón cb-comment dentro de card-btns
    document.querySelectorAll(".cb-comment").forEach(btn => {
        btn.addEventListener("click", async() => {
            const postId = btn.dataset.post;
            const post = document.querySelector(`.post-comment[data-post="${postId}"]`);

            const logged = await isUserLoggedIn();
            if (!logged) {
                // Usuario no logeado o error
                    const modal = document.querySelector('.modal');
                    if (modal) {
                        modal.style.display = 'flex'; // se abre el modal
                    }
                alert("Must be logged to interact");
                return;
            }

            if (post) {
                const commentBox = post.querySelector(".post-cpost");
                const trigger = post.querySelector(".comment-trigger");
                const textarea = commentBox.querySelector("textarea");

                // Abrir el comentario como si se hiciera click en el trigger
                commentBox.classList.add("active");
                trigger.classList.add("hidden");
                textarea.focus();
            }
        });
    });
});

//3.              ----- NAVEGAR A PERFILES CON DATA-URL -----             //
document.addEventListener('click', e => {
    const target = e.target.closest('[data-url]');
    if (target) {
        const url = target.dataset.url;
        if (url) window.location.href = url;
    }
});

//4.              ----- EDITAR Y ELIMINAR COMENTARIOS -----             //
const commentsContainer = document.querySelector('.post-users-comm');
let activeEditor = null;
let deleteTarget = null; // comentario a eliminar

//Modal para borrar comentario
const modal = document.getElementById('delete-modal');
const modalCancel = document.getElementById('modal-cancel');
const modalConfirm = document.getElementById('modal-confirm');

//Delegación de eventos
commentsContainer.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-comment-btn');
    const deleteBtn = e.target.closest('.delete-comment-btn');



    //===== EDITAR =====
    if (editBtn) {
        const commentId = editBtn.dataset.id;
        const pucUser = editBtn.closest('.puc-user');
        const pucComment = pucUser.querySelector('.puc-comment');
        const textarea = pucComment.querySelector('textarea');

        if (activeEditor && activeEditor !== pucComment) {
            cancelEdit(activeEditor);
        }
        startEdit(pucComment, textarea, commentId);
        return;
    }

    //===== ELIMINAR =====
    if (deleteBtn) {
        deleteTarget = e.target.closest('.puc-user'); //Mantiene referencia correcta incluso en replies
        modal.style.display = 'flex';
        requestAnimationFrame(() => modal.classList.add('show'));
    }

});

// Cancelar modal
modalCancel.addEventListener('click', () => {
    modal.classList.remove('show'); // quitar clase para animar cierre
    setTimeout(() => {
        modal.style.display = 'none';
        deleteTarget = null;
    }, 300); // 300ms = duración de la transición CSS

});

// Confirmar eliminación
modalConfirm.addEventListener('click', () => {
    if (!deleteTarget) return;

    const commentID = deleteTarget.querySelector('.delete-comment-btn').dataset.id;

    // Cerrar modal inmediatamente
    closeModal();

    fetch('comment_actions.php', { //AJAX
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete&comment_id=${commentID}`
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Animación de desvanecimiento
                deleteTarget.style.transition = 'opacity 0.4s, transform 0.4s';
                deleteTarget.style.opacity = '0';
                deleteTarget.style.transform = 'translateY(-10px)';

                setTimeout(() => {
                    deleteTarget.remove();

                    // Actualizar contador en main
                    const postLink = document.querySelector(`.post[data-postid="${data.post_id}"]`);
                    if (postLink) {
                        const commentBtn = postLink.querySelector('.comment-btn span');
                        if (commentBtn) {
                            let count = parseInt(commentBtn.textContent) || 0;
                            commentBtn.textContent = Math.max(0, count - 1);
                        }
                    }

                    deleteTarget = null;
                }, 400);
            }

        });
});

function closeModal() {
    modal.style.display = 'none';
}

//Cerrar modal si se hace clic fuera
modal.addEventListener('click', (e) => {
    if (e.target === modal) {
        modal.style.display = 'none';
        deleteTarget = null;
    }
});

//5.              ----- EDICION DE COMENTARIO -----             //
function startEdit(pucComment, textarea, commentId) {
    if (!pucComment || !textarea) return;
    const original = textarea.value;

    pucComment.classList.add('editing');
    textarea.removeAttribute('readonly');
    textarea.focus();
    textarea.style.height = 'auto';
    textarea.style.height = (textarea.scrollHeight) + 'px';

    if (!pucComment.querySelector('.edit-actions')) {
        const actions = document.createElement('div');
        actions.className = 'pc-comm-btns edit-actions';
        actions.innerHTML = `
            <button class="cancel-btn">Cancel</button>
            <button class="save-btn">Save</button>
        `;
        pucComment.appendChild(actions);
        requestAnimationFrame(() => actions.classList.add('show-edit'));

        actions.querySelector('.cancel-btn').addEventListener('click', () => {
            textarea.value = original;
            cancelEdit(pucComment);
        });

        actions.querySelector('.save-btn').addEventListener('click', () => {
            const newContent = textarea.value.trim();
            if (newContent === '') return;

            fetch('edit_comment.php', { //AJAX
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `comment_id=${encodeURIComponent(commentId)}&content=${encodeURIComponent(newContent)}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        textarea.value = data.content;
                        cancelEdit(pucComment);

                        //Mostrar "Editando..." temporal
                        let status = pucComment.querySelector('.editing-status');
                        if (!status) {
                            status = document.createElement('span');
                            status.className = 'editing-status';
                            pucComment.appendChild(status);
                        }
                        status.textContent = 'Editando...';
                        status.classList.add('show');

                        //Quitar "Editando..." y mostrar "(Edited)"
                        setTimeout(() => {
                            status.classList.remove('show');
                            setTimeout(() => {
                                status.remove();

                                //Insertar o actualizar el span "(Edited)" en puc-info
                                const pucUser = pucComment.closest('.puc-user');
                                const infoDiv = pucUser.querySelector('.puc-info');
                                let editedLabel = infoDiv.querySelector('.puc-edited');

                                if (!editedLabel) {
                                    editedLabel = document.createElement('span');
                                    editedLabel.className = 'puc-edited';
                                    infoDiv.appendChild(editedLabel);
                                }
                                editedLabel.textContent = '(Edited)'; //Siempre aparece solo una vez?
                            }, 300);
                        }, 500);

                    } else {
                        alert(data.error || 'Error al actualizar');
                    }
                });
        });
    }
    activeEditor = pucComment;
}

function cancelEdit(pucComment) {
    if (!pucComment) return;
    const textarea = pucComment.querySelector('textarea');
    pucComment.classList.remove('editing');
    if (textarea) {
        textarea.setAttribute('readonly', true);
        textarea.style.height = '';
    }
    const actions = pucComment.querySelector('.edit-actions');
    if (actions) actions.remove();
    if (activeEditor === pucComment) activeEditor = null;
}

//Delegación para menú ⋯
document.addEventListener('click', e => {
    const actionBtn = e.target.closest('.action-btn');
    if (!actionBtn) {
        document.querySelectorAll('.action-menu').forEach(m => m.classList.remove('show'));
        return;
    }

    // Buscar el .comment-actions más cercano
    const commentActions = actionBtn.closest('.comment-actions');
    if (!commentActions) return;

    const menu = commentActions.querySelector('.action-menu');
    if (!menu) return;

    menu.classList.toggle('show');

    // Cerrar otros menús
    document.querySelectorAll('.action-menu').forEach(m => {
        if (m !== menu) m.classList.remove('show');
    });

    e.stopPropagation();
});

//6.              ----- COMENTARIOS -----             //
//Contenedor principal y postId (una vez, al inicio)
document.addEventListener('DOMContentLoaded', () => {
    // Contenedor principal de comentarios
    const container = document.querySelector('.post-users-comm');
    if (!container) return; // Salir si no existe

    const postId = container.dataset.postId;
    if (!postId) return;

    // Botón y textarea de comentar
    const pcommentBtn = document.getElementById('pcomment');
    const textarea = document.getElementById('pc-comm');

    if (!pcommentBtn || !textarea) return; // Salir si alguno no existe

    pcommentBtn.addEventListener('click', () => {
        const content = textarea.value.trim();
        if (!content) return; // No hacer nada si está vacío
        const timestamp = Date.now();
        fetch('add_comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `post_id=${encodeURIComponent(postId)}&content=${encodeURIComponent(content)}`
        })
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success) {
                alert(data?.error || 'Ha ocurrido un error');
                return;
            }

            // === Crear nuevo comentario dinámicamente ===
            const newComment = document.createElement('div');
            newComment.classList.add('puc-user');
            if (data.comment_id) newComment.dataset.id = data.comment_id;

            // Card dropdown del autor
            const cardDropdown = document.createElement('div');
            
            cardDropdown.className = 'card-dropdown';
            cardDropdown.innerHTML = `
            <img src="get_image.php?id=${data.userID || ''}&type=avatar&v=${timestamp}" alt="pfp" class="card-img" data-url="VPROFILE.php?id=${data.userID || ''}">
            <div class="dropdown-profile">
                <div class="dd-banner">
                    <img src="get_image.php?id=${data.userID || ''}&type=banner&v=${timestamp}" alt="banner">
                </div>
                <div class="dd-pfp">
                    <img src="get_image.php?id=${data.userID || ''}&type=avatar&v=${timestamp}" alt="pfp">
                </div>
                <div class="dd-info">
                    <p class="dd-user" data-url="VPROFILE.php?id=${data.userID || ''}">${data.displayName || data.username || 'Usuario'}</p>
                    <p id="dd-username">@${data.username || 'username'}</p>
                    <p>${data.bio || "This user hasn't set a bio."}</p>
                </div>
            </div>
        `;
            newComment.appendChild(cardDropdown);

            // Contenido del comentario
            const puc = document.createElement('div');
            puc.className = 'puc';
            puc.innerHTML = `
                <div class="puc-info">
                    <span>${data.username || 'Usuario'}</span>
                    <span id="puc-dot">•</span>
                    <span id="puc-date">${data.createdAt || ''}</span>
                </div>
                <div class="puc-comment">
                    <textarea readonly>${data.content || ''}</textarea>
                </div>
            `;
            newComment.appendChild(puc);

            // Menú de acciones si el usuario es el autor
            if (data.loggedIn && data.loggedInId && data.userID && data.loggedInId === data.userID) {
                const commentActions = document.createElement('div');
                commentActions.className = 'comment-actions';
                commentActions.innerHTML = `
                    <button class="action-btn">⋯</button>
                    <div class="action-menu">
                        <button class="edit-comment-btn" data-id="${data.comment_id || ''}">Edit</button>
                        <button class="delete-comment-btn" data-id="${data.comment_id || ''}">Delete</button>
                    </div>
                `;
                newComment.appendChild(commentActions);
            }

            container.appendChild(newComment);

            // Animación de aparición
            newComment.style.opacity = 0;
            newComment.style.transform = 'translateY(-10px)';
            newComment.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            newComment.getBoundingClientRect(); // Forzar reflow
            requestAnimationFrame(() => {
                newComment.style.opacity = 1;
                newComment.style.transform = 'translateY(0)';
            });

            // Limpiar textarea
            textarea.value = "";
        })
        .catch(err => {
            console.error(err);
            alert('Error al agregar comentario.');
        });
    });
});


//7.              ----- RESPUESTAS A OTROS COMENTARIOS -----             //
document.querySelector('.post-users-comm').addEventListener('click', e => {
    const replyBtn = e.target.closest('.reply-btn');
    if (!replyBtn) return;

    const pucUser = replyBtn.closest('.puc-user');
    const replyFormContainer = pucUser.querySelector('.reply-form-container');
    const repliesContainer = pucUser.querySelector('.replies-container');


    const commentId = replyBtn.dataset.commentId;

    if (replyFormContainer.querySelector('textarea')) return;

    const div = document.createElement('div');
    div.className = 'reply-box';
    div.innerHTML = `
        <textarea placeholder="Write a reply..."></textarea>
        <div class="reply-buttons">
            <button class="cancel-reply">Cancel</button>
            <button class="send-reply">Reply</button>           
        </div>
    `;
    replyFormContainer.appendChild(div);
//AQUI ESTA EL DETALLE
    const textarea = div.querySelector('textarea');
    const btnCancel = div.querySelector('.cancel-reply');
    const btnSend = div.querySelector('.send-reply');

    btnCancel.addEventListener('click', () => div.remove());

    btnSend.addEventListener('click', () => {
        const content = textarea.value.trim();
        if (!content) return;

        //const postId = replyBtn.closest('.post-users-comm').dataset.postId;
        fetch('add_comment.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `post_id=${container.dataset.postId}&content=${encodeURIComponent(content)}&parent_id=${commentId}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const replyDiv = document.createElement('div');
                    replyDiv.classList.add('puc-user', 'reply');
                    replyDiv.dataset.id = data.comment_id;

                    replyDiv.innerHTML = `
                    <div class="card-dropdown">
                        <!-- Avatar -->
                        <img src="${data.avatar}" alt="pfp" class="card-img" data-url="VPROFILE.php?id=${data.userID}">
                
                        <!-- Dropdown -->
                        <div class="dropdown-profile">
                            <div class="dd-banner">
                                <img src="${data.banner || 'img/fifamty.jpg'}" alt="banner">
                            </div>
                            <div class="dd-pfp">
                                <img src="${data.avatar}" alt="pfp">
                            </div>
                            <div class="dd-info">
                                <p class="dd-user" data-url="VPROFILE.php?id=${data.userID}">${data.displayName || data.username}</p>
                                <p id="dd-username">@${data.username}</p>
                                <p>${data.bio || "This user hasn't set a bio."}</p>
                            </div>
                        </div>
                
                        <!-- Contenido del reply ( ahora dentro del mismo card-dropdown) -->
                        <div class="puc">
                            <div class="puc-info">
                                <span>${data.username}</span>
                                <span id="puc-dot">•</span>
                                <span id="puc-date" style="color: grey;">${data.createdAt}</span>
                            </div>
                            <div class="puc-comment">
                                <textarea readonly>${data.content}</textarea>
                            </div>
                        </div>
                    </div> <!-- .card-dropdown -->
                
                    <!-- Menú ⋯ -->
                    ${data.loggedIn && data.userID == data.loggedInId ? `
                        <div class="comment-actions">
                            <button class="action-btn">⋯</button>
                            <div class="action-menu">
                            <button class="edit-comment-btn" data-id="${data.comment_id}">Edit</button>
                            <button class="delete-comment-btn" data-id="${data.comment_id}">Delete</button>
                            
                            </div>
                        </div>
                    ` : ''}
                `;

                    //Animación al aparecer
                    replyDiv.style.opacity = 0;
                    replyDiv.style.transform = 'translateY(-10px)';
                    replyDiv.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    repliesContainer.appendChild(replyDiv);
                    replyDiv.getBoundingClientRect();
                    requestAnimationFrame(() => {
                        replyDiv.style.opacity = 1;
                        replyDiv.style.transform = 'translateY(0)';
                    });

                    div.remove();

                    const replyCount = repliesContainer.children.length; // incluye el nuevo
                    let viewBtn = pucUser.querySelector('.view-replies-btn');
                    
                    if (!viewBtn) {
                        viewBtn = document.createElement('span');
                        viewBtn.className = 'view-replies-btn';
                        viewBtn.dataset.commentId = commentId;
                        viewBtn.style.cursor = 'pointer';
                        viewBtn.style.color = '#007bff';
                        viewBtn.style.display = 'block';
                        viewBtn.style.marginBottom = '5px';
                        viewBtn.textContent = `View ${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}`;
                        repliesContainer.parentElement.insertBefore(viewBtn, repliesContainer);
                    }
                    
                    // Abrir contenedor automáticamente
                    repliesContainer.classList.add('show');
                    repliesContainer.style.maxHeight = repliesContainer.scrollHeight + "px";
                    repliesContainer.style.overflow = 'hidden';
                    repliesContainer.style.transition = 'max-height 0.3s ease';
                }
            });
    });
});

document.querySelector('.post-users-comm').addEventListener('click', e => {
    const btn = e.target.closest(".view-replies-btn");
    if (!btn) return;

    const commentId = btn.dataset.commentId;
    const container = document.querySelector(`.replies-container[data-comment-id="${commentId}"]`);
    if (!container) return;

    if (container.classList.contains("show")) {
        container.classList.remove("show");
        btn.textContent = `View ${container.children.length} ${container.children.length === 1 ? 'reply' : 'replies'}`;
        container.style.maxHeight = "0";
    } else {
        container.classList.add("show");
        btn.textContent = `Hide ${container.children.length} ${container.children.length === 1 ? 'reply' : 'replies'}`;
        container.style.maxHeight = container.scrollHeight + "px"; // recalcular altura
    }
});
