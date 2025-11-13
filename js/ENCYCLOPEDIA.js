// Seleccionar TODOS los elementos
const eContents = document.querySelectorAll('.e-content');

eContents.forEach(item => {
  const p = item.querySelector('.ep-content');
  const fullText = p.textContent.trim();
  const previewLength = 200;
  const preview = fullText.substring(0, previewLength) + (fullText.length > previewLength ? "..." : "");

  p.textContent = preview;

  item.addEventListener('click', () => {
    document.querySelector('.epmodal').style.display = 'flex';

    const id = item.dataset.id;

    //Registrar visualización en la BD - AJAX
    fetch('view_pedia.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `id=${encodeURIComponent(id)}`
    })
    .then(res => res.json())
    .then(data => console.log(data)); // para ver en la consola q devuelve

    
    fetch(`ENCYCLOPEDIA.php?action=get&id=${id}`)
      .then(res => res.json())
      .then(data => {
        const modalContent = document.querySelector('.epmodal .epcontent');
        
        // Agregar el contenido dinamico fakkkk
        modalContent.innerHTML = `
          <div class="ep-close"><button><i class="fa-solid fa-chevron-left"></i></button></div>
          <div class="ep-display">
            <div class="ep-uno">
              <img src="data:image/jpeg;base64,${data.Logo}" class="e-logo">
              <h1>${data.Title}</h1>
            </div>
            <div class="ep-dos">
              <div class="e-table">
                <img src="data:image/jpeg;base64,${data.Media}" >
                ${data.tags && data.tags.length > 0 ? `
                <div class="ep-tags">
                  <h3><i class="fa-solid fa-circle-info"></i> Additional details</h3>
                  <table class="tags-table">
                    ${data.tags.map(tag => `
                      <tr>
                        <td class="tag-name"> ${tag.Field_Name}</td>
                        <td class="tag-value">${tag.Field_Value}</td>
                      </tr>
                    `).join('')}
                  </table>
                </div>
              ` : ''}
              </div>
              
              <span>Created by:</span>
              <span>${data.AuthorName}</span>
              <span>-</span>
              <span class = "ep-views"><i class="fa-solid fa-eye"></i></span>
              <span class = "ep-views">${data.Views}</span>
              <span class = "ep-views">Views</span>

              <p class="date-style">${data.CreatedAt}</p>
              <p>${data.Content}</p>
            </div>
          </div>
        `;

        // Re-asignar evento al botón close (porque se recrea)
        document.querySelector('.ep-close button').addEventListener('click', () => {
          document.querySelector('.epmodal').style.display = 'none';
        });
      });
  });
});


//Eliminar infografia con el diseño bonito hijodesumaiz

document.querySelectorAll('.delete-info-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation(); // evita abrir el modal
    const id = btn.dataset.id;

    // Buscar el contenedor principal (por ejemplo, .e-content)
    const infoCard = btn.closest('.e-content');

    Swal.fire({
      title: 'Delete infographic?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      iconColor: '#ff2e2e',
      showCancelButton: true,
      confirmButtonColor: '#ff2e2e',
      cancelButtonColor: '#ffffff',
      confirmButtonText: 'Yes, delete',
      cancelButtonText: 'Cancel',
      background: '#1e1e1e',
      color: '#fff',
      customClass: {
        cancelButton: 'cancel-btn-custom'
      }
    }).then((result) => {
      if (result.isConfirmed) {
        fetch('ENCYCLOPEDIA.php?action=delete', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `id=${encodeURIComponent(id)}`
        })
        .then(async res => {
          // 🔥 Convertir a texto primero para poder capturar errores HTML
          const text = await res.text();
          try {
            return JSON.parse(text);
          } catch {
            console.error('Respuesta no válida del servidor:', text);
            throw new Error('Respuesta no válida del servidor');
          }
        })
        .then(data => {
          if (data.success) {
            Swal.fire({
              icon: 'success',
              title: 'Deleted',
              text: 'This infographic has been deleted.',
              timer: 1200,
              showConfirmButton: false,
              background: '#1e1e1e',
              color: '#fff'
            });

            // 🔥 Animación de desaparición sin recargar
            infoCard.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            infoCard.style.opacity = '0';
            infoCard.style.transform = 'scale(0.9)';
            setTimeout(() => infoCard.remove(), 600);
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: data.message || 'Could not be deleted.',
              background: '#1e1e1e',
              color: '#fff'
            });
          }
        })
        .catch(err => {
          console.error('Error en la solicitud:', err);
          Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: 'No se pudo comunicar con el servidor.',
            background: '#1e1e1e',
            color: '#fff'
          });
        });
      }
    });
  });
});

