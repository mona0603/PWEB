//2.              ----- SLIDER -----             //
const slides = document.querySelectorAll('.slide');
const manualBtns = document.querySelectorAll('.manual-btn');
let current = 0;
const total = slides.length;

function showSlide(index){
    slides.forEach((slide, i)=>{
        slide.classList.remove('active');
        manualBtns[i].classList.remove('active');
        if(i === index){
            slide.classList.add('active');
            manualBtns[i].classList.add('active');
        }
    });
    current = index;
}

//Auto slider cada 5 segundos
let interval = setInterval(()=>{
    let next = (current + 1) % total;
    showSlide(next);
}, 5000);

//Manual navigation
manualBtns.forEach((btn, i)=>{
    btn.addEventListener('click', ()=>{
        clearInterval(interval);
        showSlide(i);
        interval = setInterval(()=>{
            let next = (current + 1) % total;
            showSlide(next);
        }, 5000);
    });
});

//Inicializar el primer slide
showSlide(0);

// Manejar clicks en los botones "More info" del slider
document.querySelectorAll('.news-more-info-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Encontrar el slide padre más cercano
        const slide = this.closest('.slide');
        const postId = slide.getAttribute('data-postid');
        
        if (postId) {
            window.location.href = 'POST.php?id=' + postId;
        }
    });
});

//3.              ----- SORT BY DEL DASHBOARD -----             //
const sortSelect = document.getElementById('sort-select');

sortSelect.addEventListener('change', () => {
    const sortValue = sortSelect.value;
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    window.location.href = url.toString();
});