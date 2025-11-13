document.addEventListener("DOMContentLoaded", function() { //envolver
    const btnPosts = document.getElementById("sort-posts");
    const btnPeople = document.getElementById("sort-people");

    const containerPosts = document.querySelector(".results-show-posts");
    const containerUsers = document.querySelector(".results-show-users");
    const barFilter = document.querySelector(".results-sort-by");

    // Función para mostrar posts
    btnPosts.addEventListener("click", function() {
        containerPosts.style.display = "block";
        containerUsers.style.display = "none";
        barFilter.style.display = "inline-flex"
    });

    // Función para mostrar usuarios
    btnPeople.addEventListener("click", function() {
        containerPosts.style.display = "none";
        containerUsers.style.display = "block";
        barFilter.style.display = "none"
    });

    // Por defecto, mostrar posts y ocultar usuarios
    containerPosts.style.display = "block";
    containerUsers.style.display = "none";
});

//2.              ----- SORT BY DE LAS PUBLICACCIONES -----             //
const sortSelect = document.getElementById('sort-select');
const dateSelect = document.getElementById('sort-date');

function updatePosts() {
    // Obtener la URL actual
    const url = new URL(window.location.href);

    // Actualizar solo los parámetros que nos interesan
    url.searchParams.set('sort', sortSelect.value);
    url.searchParams.set('dsort', dateSelect.value);

    // Mantiene q, topic y cualquier otro parámetro que ya existía
    window.location.href = url.toString();
}

// Escuchar cambios
sortSelect.addEventListener('change', updatePosts);
dateSelect.addEventListener('change', updatePosts);
