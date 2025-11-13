//1.              ----- ABRIR MODALES -----             //
const logBtn = document.getElementById('opt-log');
if (logBtn) {
    logBtn.addEventListener('click', function () {
        document.querySelector('.modal').style.display = 'flex';
    });
}

const closeBtn = document.querySelector('.m-close');
if (closeBtn) {
    closeBtn.addEventListener('click', function () {
        document.querySelector('.modal').style.display = 'none';
    });
}

//      PEDIA
const createi = document.querySelector('.create-i');
if (createi) {
    createi.addEventListener('click', function () {
        document.querySelector('.imodal').style.display = 'flex';
    });
}

const closei = document.querySelector('.i-close');
if (closei) {
    closei.addEventListener('click', function () {
        document.querySelector('.imodal').style.display = 'none';
    });
}

document.addEventListener("DOMContentLoaded", () => {
    const cpostBtn = document.getElementById("cpost");   // Botón que abre modal
    const cmodal = document.querySelector(".cmodal");  // Fondo del modal
    const ccloseBtn = document.querySelector(".c-close"); // Botón cerrar modal

    // Abrir modal solo si existe el botón
    if (cpostBtn && cmodal) {
        cpostBtn.addEventListener("click", () => {
            cmodal.style.display = "flex";
        });
    }

    // Cerrar modal si existe el botón de cerrar
    if (ccloseBtn && cmodal) {
        ccloseBtn.addEventListener("click", () => {
            cmodal.style.display = "none";
        });
    }
});

//2.              ----- DARK MODE/ LIGHT MODE -----             //
const modeBtns = [
    document.getElementById("opt-mode-desk"),
    document.getElementById("opt-mode")
];
const body = document.body;

//LocalStorage para guardar la preferencia de modo oscuro/modo light
if (localStorage.getItem("theme") === "dark") {
    body.classList.add("dark-theme");
    modeBtns.forEach(b => {
        if (b) b.innerHTML = `<i class="fa-solid fa-sun"></i> Light mode`;
    });
}

modeBtns.forEach(btn => {
    if (!btn) return;

    btn.addEventListener("click", (e) => {
        e.preventDefault();

        const isDark = body.classList.contains("dark-theme");

        // Cambiar tema
        body.classList.toggle("dark-theme", !isDark);

        // Guardar preferencia
        localStorage.setItem("theme", !isDark ? "dark" : "light");

        // Actualizar botones
        modeBtns.forEach(b => {
            if (!b) return;
            b.innerHTML = !isDark
                ? `<i class="fa-solid fa-sun"></i> Light mode`
                : `<i class="fa-solid fa-moon"></i> Dark mode`;
        });
    });
});

//3.              ----- TOGGLE ENTRE LOGIN Y REGISTRO -----             //
document.addEventListener("DOMContentLoaded", () => {
    const openSignup = document.getElementById("open-signup");
    const backBtn = document.getElementById("open-login");

    const formsign = document.getElementById("sign-form");
    const formlog = document.getElementById("log-form");

    const container = document.querySelector(".m-log");
    const h1 = container.querySelector("h1");
    const p = container.querySelector("p");

    function toggleForm(isSignup) {
        if (isSignup) {
            formsign.style.display = "flex";
            formlog.style.display = "none";
            h1.textContent = "Sign Up";
            p.textContent = "Join us, thrive.";
        } else {
            formsign.style.display = "none";
            formlog.style.display = "flex";
            h1.textContent = "Log In";
            p.textContent = "Come connect with other users, join us, thrive.";
        }
    }

    openSignup.addEventListener("click", (e) => {
        e.preventDefault();
        toggleForm(true);
    });

    backBtn.addEventListener("click", (e) => {
        e.preventDefault();
        toggleForm(false);
    });
});

//4.              ----- FECHA DE NA PARA EL REGISTRO -----             //
//dias
const daySelect = document.getElementById("day-select");
for (let i = 1; i <= 31; i++) {
    const option = document.createElement("option");
    option.value = i;
    option.textContent = i;
    daySelect.appendChild(option);
}
//meses
const months = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];

const monthSelect = document.getElementById("month-select");

months.forEach((month, index) => {
    const option = document.createElement("option");
    option.value = index + 1;
    option.textContent = month;
    monthSelect.appendChild(option);
});

// años
const yearSelect = document.getElementById("year-select");
const currentYear = new Date().getFullYear();
for (let y = currentYear; y >= 1900; y--) {
    const option = document.createElement("option");
    option.value = y;
    option.textContent = y;
    yearSelect.appendChild(option);
}

daySelect.selectedIndex = 0;
monthSelect.selectedIndex = 0;
yearSelect.selectedIndex = 0;


//5.              ----- PREVIEW IMAGEN Y VIDEO DE PUBLICACION -----             //
document.addEventListener("DOMContentLoaded", () => {
    const mediaInput = document.getElementById("cInput");
    const displayMedia = document.getElementById("cdm");
    //const ci = document.getElementById("cInput");
    const closeBtn = document.querySelector(".c-close");

    if (mediaInput && displayMedia) {
        mediaInput.addEventListener("change", function () {
            const file = mediaInput.files[0];
            displayMedia.innerHTML = "";
            document.getElementById("cLabel").style.display = "none";

            if (!file) return;
            if (file.type.startsWith("image/")) {
                const img = document.createElement("img");
                img.src = URL.createObjectURL(file);
                displayMedia.appendChild(img);
            } else if (file.type.startsWith("video/")) {
                const video = document.createElement("video");
                video.src = URL.createObjectURL(file);
                video.controls = true;
                displayMedia.appendChild(video);
            }
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            mediaInput.value = "";         // limpia el input
            displayMedia.innerHTML = "";   // limpia el preview
            cLabel.style.display = "flex"; // vuelve a mostrar el label
        });
    }
});
//5.1              ----- PREVIEW IMAGEN Y VIDEO DE INFOGRAFIAS -----             //
document.addEventListener("DOMContentLoaded", () => {
    const mediaInput = document.querySelector(".iInput");
    const displayMedia = document.querySelector(".i-display-media");
    const label = document.querySelector(".iLabel");
    const closeBtn = document.querySelector(".i-close");

    if (mediaInput && displayMedia && label) {
        mediaInput.addEventListener("change", () => {
            const file = mediaInput.files[0];
            displayMedia.innerHTML = "";
            label.style.display = "none";

            if (!file) return;

            if (file.type.startsWith("image/")) {
                const img = document.createElement("img");
                img.src = URL.createObjectURL(file);
                displayMedia.appendChild(img);
            } else if (file.type.startsWith("video/")) {
                const video = document.createElement("video");
                video.src = URL.createObjectURL(file);
                video.controls = true;
                displayMedia.appendChild(video);
            }

            // Siempre añadimos un span con el nombre del archivo
            const name = document.createElement("span");
            name.classList.add("media-name");
            name.textContent = file.name;
            displayMedia.appendChild(name);
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener("click", () => {
            mediaInput.value = "";
            displayMedia.innerHTML = "";
            label.style.display = "flex";
        });
    }
});
//5.2              ----- AGREGAR TAGS PARA INFOGRAFIAS -----             //
document.addEventListener("DOMContentLoaded", () => {
    const auxTags = document.querySelector(".aux-tags");

    auxTags.querySelector(".addtag p").addEventListener("click", () => {
        // Crear un nuevo bloque de inputs
        const newTag = document.createElement("div");
        newTag.classList.add("tags");

        const inputName = document.createElement("input");
        inputName.type = "text";
        inputName.name = "extraFields[name][]"; // importante para PHP
        inputName.placeholder = "Name:";

        const inputValue = document.createElement("input");
        inputValue.type = "text";
        inputValue.name = "extraFields[value][]"; // importante para PHP
        inputValue.placeholder = "Value";

        newTag.appendChild(inputName);
        newTag.appendChild(inputValue);

        auxTags.appendChild(newTag);
    });
});


//6.              ----- AJAX REGISTRO -----             //
document.addEventListener("DOMContentLoaded", () => {
    const signForm = document.getElementById("sign-form");
    //Asegura que exista antes de añadir el listener
    if (signForm) {
        signForm.addEventListener("submit", function (event) {
            event.preventDefault();

            //Actualizar hidden con la fecha
            const day = daySelect.value.padStart(2, "0");
            const month = monthSelect.value.padStart(2, "0");
            const year = yearSelect.value;
            document.getElementById("birthdate").value = `${year}-${month}-${day}`;

            const mensaje = document.getElementById("mensaje");
            const password = document.getElementById("password").value;
            const confirmpassword = document.getElementById("confirm-password").value;

            if (password !== confirmpassword) {
                mensaje.style.color = "red";
                mensaje.innerText = "Passwords do not match";
                return;
            }

            //Crear FormData después
            const formData = new FormData(signForm);

            fetch("register.php", { //AJAX
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    const mensaje = document.getElementById("mensaje");

                    if (data.success) {
                        mensaje.style.color = "green";
                        mensaje.innerText = "Registration successful. Redirecting...";
                        setTimeout(() => window.location.href = "MAINPAGE.php", 1500);
                    } else if (data.deactivated) {
                        // Si la cuenta está desactivada, abrir modal de reactivación
                        showReactivateModal(document.getElementById("email").value);
                    } else {
                        mensaje.style.color = "red";
                        mensaje.innerText = data.error;
                    }
                })

                .catch(error => {
                    document.getElementById("mensaje").innerText = "Unexpected error:";
                    console.error("Error in fetch:", error);
                });
        });
    } else {
        console.error("Sign form wasn't found.");
    }
    //6.1.              ----- LIGADO AL REGISTRO POR SI SE REACTIVA UNA CUENTA -----             //
    function showReactivateModal(email) {
        const rmodal = document.querySelector(".rmodal");
        const rcloseBtn = document.querySelector(".r-close");
        const passwordInput = document.querySelector(".reactivatePassword");
        const reactivateBtn = document.querySelector(".reactivateBtn");
        const errorMsg = document.querySelector(".reactivateError");

        console.log(rmodal, rcloseBtn, passwordInput, reactivateBtn, errorMsg);
        if (!rmodal || !rcloseBtn || !passwordInput || !reactivateBtn || !errorMsg) {
            console.error("Reactivate modal elements are missing");
            return;
        }

        passwordInput.value = "";
        errorMsg.innerText = "";
        rmodal.style.display = "flex";

        rcloseBtn.onclick = () => {
            rmodal.style.display = "none";
            passwordInput.value = "";
            errorMsg.innerText = "";
            console.log("Modal closed");
        };

        reactivateBtn.onclick = () => {
            const password = passwordInput.value;
            if (!password) {
                errorMsg.style.color = "red";
                errorMsg.innerText = "Please enter your password to reactivate.";
                return;
            }

            fetch("reactivate_account.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `Email=${encodeURIComponent(email)}&Password=${encodeURIComponent(password)}`
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        errorMsg.style.color = "green";
                        errorMsg.innerText = "Reactivation successful. Redirecting...";

                        setTimeout(() => {
                            rmodal.style.display = "none";
                            window.location.href = "MAINPAGE.php";
                        }, 1500);
                    } else {
                        errorMsg.style.color = "red";
                        errorMsg.innerText = data.error;
                    }
                });
        };
    }
});

//7.              ----- AJAX LOGIN -----             //
const logForm = document.getElementById("log-form");
if (logForm) {
    logForm.addEventListener("submit", function (event) {
        event.preventDefault();

        const formData = new FormData(logForm);
        //Asegurarse que PHP detecte Login
        formData.append("Login", "1");

        fetch("login.php", { //AJAX
            method: "POST",
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                const mensaje = document.getElementById("mensajelog");
                if (data.success) {
                    mensaje.style.color = "green";
                    mensaje.innerText = "Login successful. Redirecting...";
                    setTimeout(() => window.location.href = "MAINPAGE.php", 800);
                } else {
                    mensaje.style.color = "red";
                    mensaje.innerText = data.error;
                }
            })
            .catch(error => {
                const mensaje = document.getElementById("mensajelog");
                mensaje.style.color = "red";
                mensaje.innerText = "Unexpected error. Check console.";
                console.error("Error in fetch:", error);
            });
    });
} else {
    console.error("Login form not found.");
}

//8.              ----- AJAX TOGGLE FOLLOW -----             //
document.querySelectorAll('.btn-follow').forEach(button => {
    button.addEventListener('click', function () {
        const seguidoId = this.dataset.seguidoId;
        const btn = this;

        fetch('toggle_follow.php', { //AJAX
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'seguido_id=' + encodeURIComponent(seguidoId)
        })
            .then(res => res.text())
            .then(newState => {
                // Eliminamos el contenedor completo del usuario seguido
                const usuarioContainer = btn.closest('.s-aux');
                if (usuarioContainer) usuarioContainer.remove();
                btn.textContent = newState;
            })
            .catch(err => console.error(err));
    });
});

//9.              ----- NOTIFICACIONES -----             //
document.addEventListener("DOMContentLoaded", function () {
    // Marcar como leídas las notificaciones al hacer click
    const notisDisplayItems = document.querySelectorAll(".notis-display");

    notisDisplayItems.forEach(item => {
        item.addEventListener("click", function () {
            // Cambiar el estado visual solo si es unread
            if (this.classList.contains("unread")) {
                this.classList.remove("unread");
                this.classList.add("read");

                // Enviar AJAX para actualizar en la base de datos
                fetch("mark_notifications_read.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: "user_id=" + encodeURIComponent(loggedInUserID)
                });
            }
        });
    });
});

//10.              ----- EDITAR POST -----             //
document.addEventListener('DOMContentLoaded', () => {

    // Evitar redirección en ciertos elementos
    document.addEventListener('click', function (e) {
        const noRedirect = e.target.closest('.like-btn, .edit-post-btn, .delete-post-btn, .report-post-btn, .btn-follow, .save-btn, .cancel-btn, .c-menu, .c-opt, .c-opt i');
        if (noRedirect) {
            e.stopPropagation();
            e.preventDefault();
            return;
        }

        // Redirección a perfiles o posts
        const redirectEl = e.target.closest('.card-ul, .card-img, .dd-user');
        if (redirectEl && redirectEl.dataset.url) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = redirectEl.dataset.url;
        }
    });

    // Delegación: abrir/ocultar menú (tres puntos)
    document.addEventListener('click', e => {
        const icon = e.target.closest('.c-opt i');
        if (!icon) return;

        const opt = icon.closest('.c-opt');
        const menu = opt?.querySelector('.c-menu');
        if (!menu) return;

        e.stopPropagation();
        document.querySelectorAll('.c-menu').forEach(m => {
            if (m !== menu) m.classList.remove('show');
        });
        menu.classList.toggle('show');
    });

    // Cerrar menú si se hace click fuera
    document.addEventListener('click', e => {
        if (!e.target.closest('.c-opt')) {
            document.querySelectorAll('.c-menu').forEach(m => m.classList.remove('show'));
        }
    });

    // Modal eliminar publicación (delegación también)  
    //YA NO ES UN MODAL es el diseño piola, no afecta borrar ni nada, solo ya no lo manda a llamar

    document.addEventListener('click', e => {
        const btn = e.target.closest('.delete-post-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const form = btn.closest('form');
        const postCard = btn.closest('.secpost'); // Ajusta al contenedor real del post
        const postId = form.querySelector('input[name="post_id"]').value;

        // Cerrar menú de opciones si está abierto
        document.querySelectorAll('.c-menu').forEach(m => m.classList.remove('show'));

        Swal.fire({
            title: '¿Eliminar post?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            iconColor: '#ff2e2e',
            showCancelButton: true,
            confirmButtonColor: '#ff2e2e',
            cancelButtonColor: '#ffffff',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            background: '#1e1e1e',
            color: '#fff',
            customClass: {
                cancelButton: 'cancel-btn-custom'
            }
        }).then(result => {
            if (result.isConfirmed) {
                fetch('delete_post.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `post_id=${encodeURIComponent(postId)}`
                })
                    .then(res => res.text())
                    .then(() => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Eliminado',
                            text: 'El post ha sido eliminado correctamente.',
                            timer: 1200,
                            showConfirmButton: false,
                            background: '#1e1e1e',
                            color: '#fff'
                        });

                        // 🔥 Animación de salida suave
                        postCard.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        postCard.style.opacity = '0';
                        postCard.style.transform = 'scale(0.9)';

                        setTimeout(() => {
                            postCard.remove();
                            // 👉 Redirección a MAINPAGE después de eliminar
                            window.location.href = 'MAINPAGE.php';
                        }, 800);
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo eliminar el post.',
                            background: '#1e1e1e',
                            color: '#fff'
                        });
                    });
            }
        });
    });



    // --- Editar publicación (delegación global) ---
    document.addEventListener('click', e => {
        const btn = e.target.closest('.edit-post-btn');
        if (!btn) return;

        e.stopPropagation();
        document.querySelectorAll('.c-menu').forEach(m => m.classList.remove('show'));

        const postEl = btn.closest('.secpost');
        if (!postEl) return;

        const title = postEl.querySelector('.card-title'); //

        const textarea = postEl.querySelector('textarea');
        const actions = postEl.querySelector('.inline-edit-actions');
        if (!textarea || !actions) return;

        const original = textarea.value;
        postEl.classList.add('editing');
        textarea.removeAttribute('readonly');
        textarea.focus();

        title.removeAttribute('readonly');
        title.focus();


        textarea.style.height = textarea.scrollHeight + 'px';

        actions.innerHTML = `
            <button class="save-btn">Save</button>
            <button class="cancel-btn">Cancel</button>
        `;
        actions.style.display = 'flex';
        actions.classList.add('show');

        const cancelBtn = actions.querySelector('.cancel-btn');
        const saveBtn = actions.querySelector('.save-btn');

        cancelBtn.onclick = () => cancelPostEdit(postEl, original);
        saveBtn.onclick = () => {
            const newContent = textarea.value.trim();
            const newTitle = title.value.trim();

            fetch('edit_post.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `post_id=${postEl.dataset.postid}&content=${encodeURIComponent(newContent)}&title=${encodeURIComponent(newTitle)}`
            })

                .then(r => r.text())
                .then(res => {
                    if (res.trim() === 'success') {
                        cancelPostEdit(postEl, newContent, newTitle);
                        const editedLabel = postEl.querySelector('.puc-edited');
                        if (editedLabel) editedLabel.textContent = '(Edited)';
                    }
                });
        };
    });

    function cancelPostEdit(postEl, content, titleValue) {
        const title = postEl.querySelector('.card-title');
        const textarea = postEl.querySelector('textarea');
        const actions = postEl.querySelector('.inline-edit-actions');

        postEl.classList.remove('editing');

        if (textarea) {
            textarea.setAttribute('readonly', true);
            textarea.style.height = '';
            textarea.value = content;
        }

        if (title) {
            title.setAttribute('readonly', true);
            title.value = titleValue; // restaurar título
        }

        if (actions) {
            actions.style.display = 'none';
            actions.classList.remove('show');
        }
    }


    const toggleBtns = document.querySelectorAll('.ov-filter button');
    const postsContainer = document.getElementById('profile-posts');

    // Suponiendo que tengas el ID del perfil en algún lado, por ejemplo:
    const userId = document.querySelector('#profile-posts')?.dataset.userid;

    toggleBtns.forEach(btn => { //para hacer toggle en el perfil entre posts, comentarios y likes
        btn.addEventListener('click', () => {
            // Activar botón seleccionado
            toggleBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const type = btn.dataset.type;

            if (!userId) {
                console.error("ID de usuario no definido.");
                return;
            }

            // Llamada AJAX para cargar posts, comentarios o likes
            fetch(`profile_filter.php?type=${type}&id=${userId}`)
                .then(r => r.text())
                .then(html => {
                    postsContainer.innerHTML = html;
                })
                .catch(err => console.error(err));
        });
    });
});
//10.1              ----- EDICION? -----             //
// Mostrar "(Edited)" con animación al cargar la página
document.querySelectorAll('.puc-edited').forEach(label => {
    requestAnimationFrame(() => {
        label.classList.add('show');
    });
});

//11.              ----- LIKES PARA PUBLICACIONES -----             //
function createExplosion(button) {
    const colors = ['#ffc107', '#ffd54f', '#ffeb3b', '#fff176', '#ffe082']; // tonos dorados

    //Partículas principales
    for (let i = 0; i < 12; i++) {
        const particle = document.createElement('span');
        particle.classList.add('particle');
        particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];

        const x = (Math.random() - 0.5) * 80 + 'px';
        const y = (Math.random() - 0.5) * 80 + 'px';
        particle.style.setProperty('--x', x);
        particle.style.setProperty('--y', y);

        button.appendChild(particle);
        particle.style.animation = 'explode 0.6s forwards';

        particle.addEventListener('animationend', () => particle.remove());
    }

    //Destellos adicionales
    for (let i = 0; i < 6; i++) {
        const spark = document.createElement('span');
        spark.classList.add('spark');

        spark.style.left = Math.random() * 20 - 10 + 'px';
        spark.style.top = Math.random() * 20 - 10 + 'px';

        button.appendChild(spark);
        spark.style.animation = 'spark 0.4s forwards';

        spark.addEventListener('animationend', () => spark.remove());
    }

    //Icono pop y cambio de color
    const icon = button.querySelector('i');
    icon.classList.remove('animate-like'); //Reinicia animación
    void icon.offsetWidth;
    icon.classList.add('animate-like');
}
//11.1
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.like-btn'); // busca el botón más cercano al target
    if (!btn) return; // si no es un like-btn, salir
    console.log("like");
    const postId = btn.dataset.postid;
    const countSpan = document.getElementById(`like-count-${postId}`);
    const icon = btn.querySelector('i');

    fetch('like_post.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `post_id=${postId}`
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                const modal = document.querySelector('.modal');
                if (modal) modal.style.display = 'flex';
                alert(data.error || "Error inesperado");
                return;
            }

            // Actualizar contador
            if (countSpan) countSpan.textContent = data.likes;

            // Cambiar icono
            if (data.message === 'liked') {
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid', 'liked');
                createExplosion(btn);
            } else if (data.message === 'unliked') {
                icon.classList.remove('liked', 'fa-solid');
                icon.classList.add('fa-regular');
                icon.style.color = '#000';
            }
        })
        .catch(err => console.error("Error al dar like:", err));
});

//12.              ----- AJAX DEL MODAL CREAR POST -----             //
document.addEventListener('DOMContentLoaded', () => {
    const postForm = document.querySelector('.post-post');
    if (!postForm) return;

    postForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // evita recarga

        const formData = new FormData(postForm);

        try {
            const response = await fetch('create_post.php', { method: 'POST', body: formData });

            if (!response.ok) throw new Error('HTTP error ' + response.status);

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Posted!',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true,
                    background: '#2a2a2a',
                    color: '#fff'
                });

                postForm.reset();

                // Recarga la página después de un momento
                setTimeout(() => {
                    window.location.reload();
                }, 1000);

            } else {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Cannot create the post',
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true,
                    background: '#2a2a2a',
                    color: '#fff'
                });
            }

        } catch (err) {
            console.error(err);
            alert('Error: Comunication with the server failed');
        }
    });
});

//13.              ----- SELECCION DE LOGO PARA LAS INFOGRAFIAS -----              //
function selectImage_title() {
    document.getElementById("fileInput-title").click();
}
document.getElementById("fileInput-title").addEventListener("change", function (event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById("Image-title").src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

//14.              ----- CREAR INFOGRAFIA -----              //
const infoForm = document.querySelector('.info-post');



if (infoForm) {
    infoForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // evita recarga

        const formData = new FormData(infoForm);

        try {
            const response = await fetch('create_infographic.php', { // Nombre correcto del archivo
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('HTTP error ' + response.status);

            const data = await response.json();

            // Tu PHP devuelve 'status', no 'success'
            if (data.status === 'success') {
                alert('Infografía creada exitosamente!');
                infoForm.reset(); // limpia los campos

                // Opcional: resetear previews
                const logoImg = document.getElementById('logo-img');
                if (logoImg) logoImg.src = 'img/placeholder.png';

                const mediaDisplay = document.getElementById('i-display-media');
                if (mediaDisplay) mediaDisplay.innerHTML = '';

                // Cerrar modal si existe
                const imodal = document.querySelector('.imodal');
                if (imodal) imodal.style.display = 'none';

                // Redirige a la infografía creada o recarga la página
                // if (data.id) {
                //     window.location.href = `view_infografia.php?id=${data.id}`;
                // } else {
                //     window.location.reload();
                // }

            } else {
                // Mostrar el mensaje de error específico del servidor
                alert('Error: ' + (data.message || 'No se pudo crear la infografía'));
            }
        } catch (err) {
            console.error(err);
            alert('Error: Falló la comunicación con el servidor');
        }
    });
}
//15.              ----- LLAMAR EL TRIGGER DE LAS VIEWS DEL POST -----              //
// Seleccionar todos los posts
const posts = document.querySelectorAll('.secpost');
posts.forEach(post => {
    post.addEventListener('click', () => {
        const postID = post.dataset.postid;

        // Registrar la vista con AJAX
        fetch('view_post.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(postID)}`
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(`Vista registrada para post ID ${postID}`);
                } else {
                    console.error('Error al registrar vista:', data.message);
                }
            })
            .catch(err => console.error('Error en fetch:', err));
    });
});

//16.              ----- HABILITAR O DESHABILITAR EL SELECT TOPIC -----              //
document.addEventListener('DOMContentLoaded', function () {
    const newsCheckbox = document.getElementById('news-admin');
    const topicSelect = document.getElementById('topic-select');
    const mediaInput = document.getElementById('cInput');
    const mediaLabel = document.getElementById('cLabel'); // Agregar esta línea

    if (newsCheckbox && topicSelect && mediaInput) {
        newsCheckbox.addEventListener('change', function () {
            if (this.checked) {
                // Deshabilitar el select cuando se marca como noticia
                topicSelect.disabled = true;
                topicSelect.value = ''; // Limpiar selección
                topicSelect.removeAttribute('required'); // Quitar requerido

                // Hacer obligatorio subir media
                mediaInput.setAttribute('required', 'required');
                if (mediaLabel) mediaLabel.classList.add('required'); // Agregar clase
            } else {
                // Habilitar el select cuando se desmarca
                topicSelect.disabled = false;
                topicSelect.setAttribute('required', 'required'); // Restaurar requerido

                // Quitar obligatoriedad de media
                mediaInput.removeAttribute('required');
                if (mediaLabel) mediaLabel.classList.remove('required'); // Quitar clase
            }
        });
    }
});
