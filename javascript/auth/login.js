document.addEventListener('DOMContentLoaded', () => {
  const API_URL = './base/login.php';
  const wrapper = document.querySelector('.login-wrapper');

  // 1) Llamada al backend siempre devuelve JSON
  async function apiCall(payload) {
    const response = await fetch(API_URL, {
      method:      'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept':        'application/json'
      },
      body: JSON.stringify(payload)
    });

    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch {
      throw new Error('Respuesta inesperada del servidor.');
    }

    if (response.status >= 500) {
      throw new Error(data.message || 'Error interno del servidor.');
    }

    return data;
  }

  // 2) Escapar texto para mostrar errores con seguridad
  function sanitize(txt) {
    const div = document.createElement('div');
    div.textContent = txt;
    return div.innerHTML;
  }

  // 3) Alternar vistas Login / Registro
  document.querySelectorAll('.toggle-message a').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      const tgt = a.dataset.target;
      wrapper.classList.toggle('show-login',  tgt === 'login');
      wrapper.classList.toggle('show-register', tgt === 'register');
    });
  });

  // 4) UX para inputs: solo números y sin dígitos en nombres
  document.querySelectorAll('input.numeric-only').forEach(i => {
    i.addEventListener('input',   e => e.target.value = e.target.value.replace(/\D/g, ''));
    i.addEventListener('keypress', e => !/\d/.test(e.key) && e.preventDefault());
  });
  ['firstName','lastName'].forEach(name => {
    document.querySelectorAll(`input[name="${name}"]`).forEach(i => {
      i.addEventListener('input', () => i.value = i.value.replace(/\d/g, ''));
    });
  });
  function limit(name, max) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(i => {
      i.addEventListener('input', () => {
        if (i.value.length > max) i.value = i.value.slice(0, max);
      });
    });
  }
  limit('username',  12);
  limit('firstName', 30);
  limit('lastName',  30);
  limit('email',    100);

  // 5) Mostrar/ocultar contraseña
  function togglePassword(btnId, inpId) {
    const btn = document.getElementById(btnId);
    const inp = document.getElementById(inpId);
    if (!btn || !inp) return;
    btn.addEventListener('click', () => {
      inp.type = inp.type === 'password' ? 'text' : 'password';
    });
  }
  togglePassword('toggle-password',      'password');
  togglePassword('toggle-reg-password',  'reg-password');

  // 6) Indicador de fortaleza de contraseña (registro)
  const regPwd = document.getElementById('reg-password');
  const regErr = document.getElementById('reg-password-error');
  if (regPwd && regErr) {
    const strongPattern = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/;
    regPwd.addEventListener('input', () => {
      regErr.textContent = strongPattern.test(regPwd.value)
        ? ''
        : 'Mín. 8 caracteres, mayúscula, minúscula y número';
    });
  }

  // 7) Calcular edad y fijar fecha máxima para 18+
  function calcularEdad(fechaStr) {
    const nacimiento = new Date(fechaStr);
    const hoy         = new Date();
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const m = hoy.getMonth() - nacimiento.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) edad--;
    return edad;
  }
  const fechaInput = document.getElementById('fechaNacimiento');
  if (fechaInput) {
    const d = new Date();
    d.setFullYear(d.getFullYear() - 18);
    fechaInput.max = d.toISOString().split('T')[0];
  }

  // 8) LOGIN (sin CAPTCHA)
  const loginForm = document.getElementById('login-form');
  const loginErr  = document.getElementById('error-message-login');
  if (loginForm) {
    loginForm.addEventListener('submit', async e => {
      e.preventDefault();
      loginErr.textContent = '';

      const u = loginForm.querySelector('input[name="username"]').value.trim();
      const p = loginForm.querySelector('input[name="password"]').value;

      if (!u || !p) {
        loginErr.textContent = 'Ingresa usuario y contraseña';
        return;
      }

      try {
        const res = await apiCall({ username: u, password: p, accion: 'login' });
        if (!res.success) {
          loginErr.textContent = sanitize(res.message);
          return;
        }
        // Redirigir al dashboard
        window.location.href = './dashboard.html';
      } catch (err) {
        loginErr.textContent = sanitize(err.message);
      }
    });
  }

  // 9) REGISTRO (con CAPTCHA)
  const registerForm = document.getElementById('register-form');
  const registerErr  = document.getElementById('error-message-register');
  if (registerForm) {
    registerForm.addEventListener('submit', async e => {
      e.preventDefault();
      registerErr.textContent = '';

      if (!registerForm.checkValidity()) {
        registerErr.textContent = 'Revisa los campos obligatorios.';
        return;
      }

      const formData = new FormData(registerForm);
      const u               = formData.get('username').toString().trim();
      const fn              = formData.get('firstName').toString().trim();
      const ln              = formData.get('lastName').toString().trim();
      const td              = formData.get('documentType').toString();
      const idn             = formData.get('identity').toString();
      const ph              = formData.get('phone').toString();
      const em              = formData.get('email').toString().trim();
      const bd              = formData.get('fechaNacimiento').toString();
      const pw              = formData.get('password').toString();
      const cp              = formData.get('confirm-password').toString();
      const captchaResponse = grecaptcha.getResponse();

      if (pw !== cp) {
        registerErr.textContent = 'Las contraseñas no coinciden.';
        return;
      }
      if (calcularEdad(bd) < 18) {
        registerErr.textContent = 'Debes tener al menos 18 años.';
        return;
      }
      if (!captchaResponse) {
        registerErr.textContent = 'Completa el CAPTCHA.';
        return;
      }

      try {
        const res = await apiCall({
          username:               u,
          firstName:              fn,
          lastName:               ln,
          documentType:           td,
          identity:               idn,
          phone:                  ph,
          email:                  em,
          fechaNacimiento:        bd,
          password:               pw,
          accion:                 'registrar',
          'g-recaptcha-response': captchaResponse
        });

        // Siempre resetear CAPTCHA tras la llamada
        grecaptcha.reset();

        if (!res.success) {
          registerErr.textContent = sanitize(res.message);
          return;
        }

        alert('Registro exitoso. ¡Inicia sesión ahora!');
        wrapper.classList.replace('show-register', 'show-login');
      } catch (err) {
        grecaptcha.reset();
        registerErr.textContent = sanitize(err.message);
      }
    });
  }
});