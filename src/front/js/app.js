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
  const unreadCount = notifications.filter(n => !Number(unread_only_if_required = n.is_read)).length;
  
  holder.textContent = notifications.length;
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
    <div class="flex items-center gap-4 relative">
      <span class="text-sm text-slate-600">${window.sessionUser.name}</span>
      <div class="relative">
        <button id="notif-toggle" class="badge badge-info cursor-pointer">
          Notifications <span data-notifications class="ml-2">0</span>
        </button>
        <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 glass rounded-2xl border p-4 shadow-xl z-50">
          <div class="flex items-center justify-between border-b pb-2 mb-2">
            <span class="font-semibold">Notifications</span>
            <button id="clear-notifs" class="text-xs text-rose-600 hover:underline">Clear all</button>
          </div>
          <div id="notif-list" class="max-h-64 overflow-y-auto space-y-2 text-xs text-slate-600">
            Loading...
          </div>
        </div>
      </div>
      <button id="logout-btn" class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white">Logout</button>
    </div>
  `;

  showNotifications();

  document.getElementById('notif-toggle')?.addEventListener('click', (e) => {
    e.stopPropagation();
    const dropdown = document.getElementById('notif-dropdown');
    dropdown.classList.toggle('hidden');
    if (!dropdown.classList.contains('hidden')) {
      renderNotifications();
    }
  });

  document.getElementById('clear-notifs')?.addEventListener('click', async () => {
    try {
      await apiCall('auth.php?action=notifications/clear', 'POST', {});
      window.sessionUser.notifications = [];
      showNotifications();
      renderNotifications();
    } catch (err) {
      alert(err.message);
    }
  });

  document.addEventListener('click', () => {
    document.getElementById('notif-dropdown')?.classList.add('hidden');
  });

  document.getElementById('logout-btn')?.addEventListener('click', async () => {
    await apiCall('auth.php?action=logout', 'POST', {});
    location.href = 'index.html';
  });
}

function renderNotifications() {
  const list = document.getElementById('notif-list');
  if (!list || !window.sessionUser) return;
  const notifs = window.sessionUser.notifications || [];
  if (notifs.length === 0) {
    list.innerHTML = '<p class="text-center py-4 text-slate-400">No new notifications.</p>';
    return;
  }
  list.innerHTML = notifs.map(n => `
    <div class="border-b pb-2 last:border-0">
      <div class="font-medium ${n.is_read == 0 ? 'text-slate-950' : 'text-slate-500'}">${n.type.replace('_', ' ')}</div>
      <div>${n.message}</div>
      <div class="text-[10px] text-slate-400 mt-1">${n.created_at}</div>
    </div>
  `).join('');
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
