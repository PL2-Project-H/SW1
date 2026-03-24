const API = '../api';

async function apiCall(endpoint, method = 'GET', body = null, options = {}) {
  const config = {
    method,
    credentials: 'include',
    headers: {
      'X-CSRF-Token': window.sessionUser?.csrf_token || '',
      ...(options.headers || {})
    }
  };

  if (!(body instanceof FormData) && body) {
    config.headers['Content-Type'] = 'application/json';
    config.body = JSON.stringify(body);
  } else if (body instanceof FormData) {
    config.body = body;
  }

  const response = await fetch(`${API}/${endpoint}`, config);
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

async function loadSession(redirect = false) {
  let user = null;
  try {
    user = await apiCall('auth.php?action=me');
  } catch (error) {
    if (redirect) {
      location.href = 'index.html';
    }
    return null;
  }

  user = normalizeSessionUser(user);
  if (!user) {
    if (redirect) {
      location.href = 'index.html';
    }
    return null;
  }

  window.sessionUser = user;

  try {
    renderNav();
    showNotifications();
  } catch (error) {
    console.error('Session UI bootstrap failed', error);
  }

  return window.sessionUser;
}

async function checkSession(roles = []) {
  const user = await loadSession(true);
  if (!user || typeof user.role !== 'string' || user.role === '') {
    location.href = 'index.html';
    return null;
  }
  if (roles.length && !roles.includes(user.role)) {
    location.href = 'dashboard.html';
    return null;
  }
  return user;
}

async function showNotifications() {
  const holder = document.querySelector('[data-notifications]');
  if (!holder || !window.sessionUser) {
    return;
  }
  const notifications = window.sessionUser.notifications || [];
  holder.textContent = notifications.filter((item) => !Number(item.is_read)).length;
}

function renderNav() {
  const nav = document.getElementById('nav-links');
  if (!nav || !window.sessionUser) {
    return;
  }
  const role = window.sessionUser.role;
  const links = {
    freelancer: [
      ['dashboard.html', 'Dashboard'],
      ['profile.html', 'Profile'],
      ['jobs.html', 'Jobs'],
      ['contract.html', 'Contracts'],
      ['dispute.html', 'Disputes']
    ],
    client: [
      ['dashboard.html', 'Dashboard'],
      ['jobs.html', 'Jobs'],
      ['contract.html', 'Contracts'],
      ['escrow.html', 'Escrow'],
      ['dispute.html', 'Disputes']
    ],
    admin: [
      ['dashboard.html', 'Dashboard'],
      ['admin.html', 'Admin Panel'],
      ['dispute.html', 'Disputes']
    ]
  };

  const roleLinks = links[role] ?? [['dashboard.html', 'Dashboard']];

  nav.innerHTML = `
    <div class="flex items-center gap-3 text-sm text-slate-700">
      ${roleLinks.map(([href, label]) => `<a class="hover:text-slate-950" href="${href}">${label}</a>`).join('')}
    </div>
    <div class="flex items-center gap-4">
      <span class="text-sm text-slate-600">${window.sessionUser.name}</span>
      <span class="badge badge-info">Notifications <span data-notifications class="ml-2">0</span></span>
      <button id="logout-btn" class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">Logout</button>
    </div>
  `;

  document.getElementById('logout-btn')?.addEventListener('click', async () => {
    await apiCall('auth.php?action=logout', 'POST', {});
    location.href = 'index.html';
  });
}

function debounce(fn, wait = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}

function qs(id) {
  return document.getElementById(id);
}

function normalizeSessionUser(payload) {
  const user = payload && typeof payload === 'object' && payload.user && typeof payload.user === 'object'
    ? payload.user
    : payload;

  if (!user || typeof user !== 'object') {
    return null;
  }

  if (typeof user.role !== 'string' || user.role.trim() === '') {
    console.error('Session payload missing role', user);
    return null;
  }

  return user;
}
