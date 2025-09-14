document.addEventListener('DOMContentLoaded', () => {
  fetch('base/dashboard.php', {
    method: 'POST',
    credentials: 'same-origin',               // incluye cookies de sesión
    headers: {
      'X-Requested-With': 'XMLHttpRequest'    // marca la petición como AJAX
    }
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error ${response.status}`);
      }
      return response.json();
    })
    .then(data => {
      if (data.error) {
        // Redirige al login en caso de no autorizado
        window.location.replace('login.html');
        return;
      }

      // Inyecta datos del usuario en el DOM
      document.querySelector('.card-user').textContent    = data.userName;
      document.querySelector('.card-balance').textContent = `$${data.balance}`;
      document.querySelector('.card-number').textContent  = `•••• ${data.cardLastDigits}`;
    })
    .catch(err => {
      console.error('Error al cargar datos del usuario:', err);
      // Aquí podrías mostrar un mensaje de error en UI si lo deseas
    });
});