const previewModal = document.getElementById('previewModal');
const closePreview = document.getElementById('closePreview');
const viewButtons = document.querySelectorAll('.view');

viewButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    const username = btn.dataset.username || '@usuario';
    const displayName = btn.dataset.displayname || 'Sin nombre';
    const avatar = btn.dataset.avatar || 'assets/img/default-avatar.jpg';
    const title = btn.dataset.title || 'Sin título';
    const content = btn.dataset.content || 'Sin contenido';
    const media = btn.dataset.media || 'assets/img/placeholder.jpg';
    const mediaType = btn.dataset.mediatype || 'Texto';
    const createdAt = new Date(btn.dataset.date);

    // Insertar datos en el modal
    previewModal.querySelector('.preview-avatar').src = avatar;
    previewModal.querySelector('.preview-header h3').textContent = `@${username}`;
    previewModal.querySelector('.preview-header .display-name').textContent = displayName;
    previewModal.querySelector('.preview-title').textContent = title;
    previewModal.querySelector('.preview-text').textContent = content;

    // Mostrar media (imagen o video)
    const mediaContainer = previewModal.querySelector('.preview-media');
    if (mediaType.startsWith('image/')) {
      mediaContainer.innerHTML = `<img src="${media}" alt="Post media">`;
    } else if (mediaType.startsWith('video/')) {
      mediaContainer.innerHTML = `
        <video controls style="width:100%;border-radius:10px;">
          <source src="${media}" type="${mediaType}">
        </video>`;
    } else {
      mediaContainer.innerHTML = `<img src="assets/img/placeholder.jpg" alt="Sin media">`;
    }

    // Fecha y hora
    previewModal.querySelector('.preview-meta span:nth-child(1)').innerHTML = `
      <i class="fa-regular fa-calendar"></i> ${createdAt.toLocaleDateString()}`;
    previewModal.querySelector('.preview-meta span:nth-child(2)').innerHTML = `
      <i class="fa-regular fa-clock"></i> ${createdAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    previewModal.querySelector('.preview-meta span:nth-child(3)').innerHTML = `
      <i class="fa-solid fa-photo-film"></i> ${mediaType}`;

    const topics = btn.dataset.topics || 'Sin tema';

    // Mostrar el topic
    const topicsContainer = previewModal.querySelector('.preview-topics');
    if (topics && topics !== 'Sin tema') {
      topicsContainer.innerHTML = `
          <div class="topic-label">
            <i class="fa-solid fa-hashtag"></i> ${topics}
          </div>`;
    } else {
      topicsContainer.innerHTML = `<div class="topic-label empty">Sin tema</div>`;
    }


    // Mostrar modal
    previewModal.classList.add('active');


  });
});

// Cerrar modal
closePreview.addEventListener('click', () => previewModal.classList.remove('active'));
previewModal.addEventListener('click', (e) => {
  if (e.target === previewModal) previewModal.classList.remove('active');
});


const approveButtons = document.querySelectorAll('.approve');
const rejectButtons = document.querySelectorAll('.reject');

function showToast(message, type = 'success') {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: type,
    title: message,
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    background: '#2a2a2a',
    color: '#fff'
  });
}

function updatePostStatus(postId, status) {
  fetch('update_post.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${postId}&status=${status}`
  })
  .then(res => res.json())
  .then(data => {
    showToast(data.message, data.success ? 'success' : 'error');

    if (data.success) {
      document.querySelector(`.admin-card[data-postid="${postId}"]`)?.remove();
    }
  })
  .catch(err => {
    console.error('Error:', err);
    showToast('Error de conexión con el servidor', 'error');
  });
}

approveButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    const postId = btn.dataset.id;
    updatePostStatus(postId, 'Approved');
  });
});

rejectButtons.forEach(btn => {
  btn.addEventListener('click', () => {
    const postId = btn.dataset.id;
    updatePostStatus(postId, 'Rejected');
  });
});
