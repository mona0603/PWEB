
document.addEventListener("DOMContentLoaded", () => {
    // Obtener selects
    const daySelect = document.getElementById("down-day-select");
    const monthSelect = document.getElementById("down-month-select");
    const yearSelect = document.getElementById("down-year-select");

    // Obtener valores por defecto desde data-selected
    const selectedDay = parseInt(daySelect.dataset.selected);
    const selectedMonth = monthSelect.dataset.selected; // ej: "June"
    const selectedYear = parseInt(yearSelect.dataset.selected);

    // Llenar días
    for (let i = 1; i <= 31; i++) {
        const option = document.createElement("option");
        option.value = i;
        option.textContent = i;
        if (i === selectedDay) option.selected = true;
        daySelect.appendChild(option);
    }

    // Llenar meses
    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    months.forEach((month, index) => {
        const option = document.createElement("option");
        option.value = index + 1; // valor numérico del mes
        option.textContent = month;
        if (month === selectedMonth) option.selected = true;
        monthSelect.appendChild(option);
    });

    // Llenar años
    const currentYear = new Date().getFullYear();
    for (let y = currentYear; y >= 1900; y--) {
        const option = document.createElement("option");
        option.value = y;
        option.textContent = y;
        if (y === selectedYear) option.selected = true;
        yearSelect.appendChild(option);
    }
});

//foto de perfil
function selectImage() {
            document.getElementById("fileInput").click();
        }
        document.getElementById("fileInput").addEventListener("change", function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById("profileImage").src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

//foto del banner
function selectImageBanner() {
            document.getElementById("fileInputBanner").click();
        }
        document.getElementById("fileInputBanner").addEventListener("change", function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById("bannerImage").src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

// Abrir modal
document.getElementById('changep').addEventListener('click', function() {
    document.querySelector('.cpmodal').style.display = 'flex';
});

// Función para resetear modal completamente
function resetModal() {
    const modal = document.querySelector('.cpmodal');
    modal.style.display = 'none';

    // Restaurar secciones
    const cpAux = modal.querySelector('.cp-aux');
    const cpQuestion = modal.querySelector('.cp-question');
    if (cpAux && cpQuestion) {
        cpAux.style.display = 'inline-flex'; // sección principal visible
        cpQuestion.style.display = 'none';    // sección de recuperación oculta
    }

    // Limpiar todos los inputs
    modal.querySelectorAll('input').forEach(input => input.value = '');

    // Limpiar mensajes de error
    const mensaje = modal.querySelector('#mensajecontra');
    if (mensaje) mensaje.innerText = '';
}

// Cerrar modal con la X
document.querySelector('.cp-close').addEventListener('click', resetModal);

// Cerrar modal con el botón Cancel principal
document.getElementById('cp-cancel').addEventListener('click', function(e) {
    e.preventDefault();
    resetModal();
});

// Botón Cancel dentro de la sección Recover → solo vuelve a la sección principal
document.getElementById('cpq-cancel').addEventListener('click', function(e) {
    e.preventDefault();
    const modal = document.querySelector('.cpmodal');
    modal.querySelector('.cp-question').style.display = 'none';
    modal.querySelector('.cp-aux').style.display = 'inline-flex';

    // Limpiar todos los inputs
    modal.querySelectorAll('input').forEach(input => input.value = '');
    // Limpiar mensajes
    const mensaje = modal.querySelector('#mensajerestaura');
    if (mensaje) mensaje.innerText = '';

    // Limpiar mensajes de error
    const mensajes = modal.querySelector('#mensajecontra');
    if (mensajes) mensajes.innerText = '';

    modal.querySelector('#newGeneratedPassword').style.display = 'none';
});

// Mostrar sección de recuperación al hacer click en "Recover"
document.getElementById('Recover').addEventListener('click', function(e) {
    e.preventDefault();
    const modal = document.querySelector('.cpmodal');
    modal.querySelector('.cp-aux').style.display = 'none';
    modal.querySelector('.cp-question').style.display = 'inline-flex';
});

//CAMBIAR CONTRASEÑA
document.getElementById("cp-save").addEventListener("click", function(e){
    e.preventDefault(); // prevenimos envío normal

    const currentPass = document.getElementById("current_password").value;
    const newPass = document.getElementById("new_password").value;
    const confirmPass = document.getElementById("confirm_password").value;
    const mensaje = document.getElementById("mensajecontra");

    if(newPass !== confirmPass){
        mensaje.style.color = "red";
        mensaje.innerText = "Passwords do not match";
        return;
    }
    if (newPass === "" || confirmPass === "" || currentPass ==="") {
        mensaje.style.color = "red";
        mensaje.innerText = "Fill the remaining fields";
        return;
    }
    //Validar nueva contraseña
    const passwordRegex = /^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
    if(!passwordRegex.test(newPass)){
        mensaje.style.color = "red";
        mensaje.innerText = "Password must be at least 8 characters, include a capital letter, a number, and a special character.";
        return;
    }

    // Aquí enviamos la current password + nueva password al servidor
    fetch("changepassword.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `current=${encodeURIComponent(currentPass)}&new=${encodeURIComponent(newPass)}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            const mensaje = document.getElementById("mensajecontra");
            mensaje.style.color = "green";
            mensaje.innerText = "Passwords changed succesfully";
            setTimeout(() => window.location.href = "PROFILE.php", 800);
        } else {
            const mensaje = document.getElementById("mensajecontra");
            mensaje.style.color = "red";
            mensaje.innerText = "Current password is incorrect";
        }
    });
});

//RECUPERACION DE CONTRASEÑA
document.getElementById("cpq-verify").addEventListener("click", function(e){
    e.preventDefault(); // prevenimos envío normal del forms

    const recoveryAnswer = document.getElementById("recoveryAnswer").value;
    const mensaje = document.getElementById("mensajerestaura");

    if(recoveryAnswer === ""){
        mensaje.style.color = "red";
        mensaje.innerText = "Please enter your recovery answer";
        return;
    }

    // Aquí enviamos la respuesta
    fetch("changepassword.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `recovery=${encodeURIComponent(recoveryAnswer)}`
    })
    .then(res => res.json())
    .then(data => {
        if(data.success){
            const mensaje = document.getElementById("mensajerestaura");
            const NewGeneratedPassword = document.getElementById("newGeneratedPassword");

            NewGeneratedPassword.value = data.newPassword;
            NewGeneratedPassword.style.display = "block";
            mensaje.style.color = "green";
            mensaje.innerText = "New password generated successfully";
        } else {
            const mensaje = document.getElementById("mensajerestaura");
            mensaje.style.color = "red";
            mensaje.innerText = "Recovery answer is incorrect";
        }
    });
});

//              ----- ABRIR Y CERRAR MODAL PARA BORRAR LA CUENTA -----             //
document.getElementById('delete-btn').addEventListener('click', function() {
    document.querySelector('.dmodal').style.display = 'flex';
});
document.getElementById('d-close').addEventListener('click', function() {
    document.querySelector('.dmodal').style.display = 'none';
});

//              ----- BORRAR CUENTA -----             //
function ConfirmDelete() {
    const password = document.getElementById("confirmPassword").value;
    const errorMsg = document.getElementById("errorMessage");

    if (!password) {
        errorMsg.innerText = "Enter your password";
        return;
    }

    fetch('deactivate_account.php', {
        method: 'POST',
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "password=" + encodeURIComponent(password)
    })
    .then(res => res.text())
    .then(response => {
        if (response.trim() === "ok") {
            alert("Your account has been deleted. See you next time.");
            window.location.href = "MAINPAGE.php"; // Redirigir al inicio
        } else {
            errorMsg.innerText = response;
        }
    })
    .catch(err => {
        errorMsg.innerText = "An error has ocured, try again.";
    });
}
