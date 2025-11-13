// //1.              ----- PREVENCIÓN DE REDIRECCIÓN DE ELEMENTOS POR ETIQUETA a -----             //
// document.querySelectorAll(".card-ul, .card-img, #dd-user").forEach(el => {
//     el.addEventListener("click", function (event) {
//         event.stopPropagation();
//         event.preventDefault();
//         window.location.href = this.dataset.url;
//     });
// });

//abrir followers / following
const followingBtn = document.getElementById('dd-following');
const followersBtn = document.getElementById('dd-followers');
const fmodal = document.querySelector('.fmodal');
const ffTitle = document.getElementById('span-f');
const ffBtnClose = document.querySelector('.ff-close');
const ffFollowing = document.getElementById("ff-following");
const ffFollowers = document.getElementById("ff-followers");

// Abrir modal en modo "Following"
if (followingBtn) {
    followingBtn.addEventListener('click', function() {
        ffTitle.innerText = "Following";
        fmodal.style.display = 'flex';
        ffFollowing.style.display = "block";   // mostrar lista following
        ffFollowers.style.display = "none";    // ocultar lista followers
    });
}

// Abrir modal en modo "Followers"
if (followersBtn) {
    followersBtn.addEventListener('click', function() {
        ffTitle.innerText = "Followers";
        fmodal.style.display = 'flex';
        ffFollowing.style.display = "none";    // ocultar lista following
        ffFollowers.style.display = "block";   // mostrar lista followers
    });
}


// Cerrar modal
if (ffBtnClose) {
    ffBtnClose.addEventListener('click', function() {
        fmodal.style.display = 'none';
    });
}

//toggle entre followers y following
const btnFollowing = document.getElementById("btn-following");
    const btnFollowers = document.getElementById("btn-followers");
    

    // Mostrar lista Following
    btnFollowing.addEventListener("click", function() {
        ffFollowing.style.display = "block";
        ffFollowers.style.display = "none";
        document.getElementById("span-f").textContent = "Following";
    });

    // Mostrar lista Followers
    btnFollowers.addEventListener("click", function() {
        ffFollowing.style.display = "none";
        ffFollowers.style.display = "block";
        document.getElementById("span-f").textContent = "Followers";
    });
