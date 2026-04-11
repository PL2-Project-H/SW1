const API = '../api';

let isFetchingSession = null;

async function apiCall(endpoint, method = 'GET', body = null, options = {}) {
  
  if (method !== 'GET' && (!window.sessionUser || !window.sessionUser.csrf_token) && !endpoint.includes('auth.php?action=login') && !endpoint.includes('auth.php?action=register')) {
    if (!isFetchingSession) {
       isFetchingSession = loadSession(false);
    }
    await isFetchingSession;
  }

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

function showNotifications() {
  const badge = document.getElementById('nav-bell-dot');
  if (!badge || !window.sessionUser) return;
  const notifications = window.sessionUser.notifications || [];
  const unreadCount = notifications.filter(n => parseInt(n.is_read) === 0).length;
  
  if (unreadCount > 0) {
    badge.classList.add('show');
  } else {
    badge.classList.remove('show');
  }
}

function renderNav() {
  
  
  
  let nav = document.querySelector('nav');
  if (!nav) {
    nav = document.createElement('nav');
    document.body.prepend(nav);
  }

  if (!window.sessionUser) return;
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
  const currentPath = window.location.pathname.split('/').pop() || 'dashboard.html';

  nav.innerHTML = `
    <a href="index.html" class="nav-logo">Specialist<span>Hub</span></a>
    
    <button class="hamburger" id="mobile-menu-btn">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    </button>

    <div class="nav-links" id="nav-links">
      ${roleLinks.map(([href, label]) => `
        <a class="nav-link ${currentPath === href ? 'active' : ''}" href="${href}">${label}</a>
      `).join('')}
    </div>

    <div class="nav-actions hidden-mobile" style="display:flex">
      <span class="nav-chip">${window.sessionUser.name}</span>
      <div style="position: relative;">
        <button id="notif-toggle" class="nav-bell">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
          <span class="nav-bell-dot" id="nav-bell-dot"></span>
        </button>
        <div id="notif-dropdown" class="hidden" style="position: absolute; right: 0; top: 120%; width: 320px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 4px; box-shadow: var(--shadow-card); z-index: 100;">
          <div style="display:flex; justify-content:space-between; align-items:center; padding: 1rem; border-bottom: 1px solid var(--border);">
            <span style="font-family:'Playfair Display',serif; font-weight:700;">Notifications</span>
            <button id="clear-notifs" style="background:none;border:none;color:var(--gold);font-size:0.75rem;cursor:pointer;text-transform:uppercase;letter-spacing:0.1em;">Clear</button>
          </div>
          <div id="notif-list" style="max-height: 300px; overflow-y: auto; padding: 0.5rem;">
            Loading...
          </div>
        </div>
      </div>
      <button id="logout-btn" class="btn-primary btn-sm">Logout</button>
    </div>
  `;

  showNotifications();

  document.getElementById('mobile-menu-btn')?.addEventListener('click', () => {
    document.getElementById('nav-links').classList.toggle('mobile-open');
  });

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
      showError(document.getElementById('notif-dropdown'), err.message);
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
    list.innerHTML = '<div class="empty-state" style="padding:2rem;"><p>No new notifications.</p></div>';
    return;
  }
  list.innerHTML = notifs.map(n => `
    <div style="padding: 0.75rem; border-bottom: 1px solid rgba(13,13,13,0.05);">
      <div style="font-size:0.75rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;color:${parseInt(n.is_read) === 0 ? 'var(--ink)' : 'var(--muted)'};margin-bottom:0.25rem;">${n.type.replace('_', ' ')}</div>
      <div style="font-size:0.85rem;color:var(--muted);">${n.message}</div>
      <div style="font-size:0.7rem;color:rgba(107,101,96,0.6);margin-top:0.25rem;">${n.created_at}</div>
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
  return user && user.id ? user : null;
}


function showLoading(container) {
  if (typeof container === 'string') container = qs(container);
  if (!container) return;
  container.innerHTML = '<div class="spinner-wrapper"><div class="loading-spinner"></div></div>';
}

function stopLoading(container) {
  if (typeof container === 'string') container = qs(container);
  if (!container) return;
  const spinner = container.querySelector('.spinner-wrapper');
  if (spinner) spinner.remove();
}

function showError(container, message) {
  if (typeof container === 'string') container = qs(container);
  if (!container) {
    console.error(message);
    return;
  }
  
  let banner = container.querySelector('.banner-error');
  if (!banner) {
    banner = document.createElement('div');
    banner.className = 'banner banner-error';
    container.prepend(banner);
  }
  banner.innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <span>${message}</span>
  `;
  setTimeout(() => { if(banner.parentElement) banner.remove() }, 5000);
}

function showSuccess(container, message) {
  if (typeof container === 'string') container = qs(container);
  if (!container) return;
  let banner = container.querySelector('.banner-success');
  if (!banner) {
    banner = document.createElement('div');
    banner.className = 'banner banner-success';
    container.prepend(banner);
  }
  banner.innerHTML = `
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
    <span>${message}</span>
  `;
  setTimeout(() => { if(banner.parentElement) banner.remove() }, 3000);
}


document.addEventListener('DOMContentLoaded', () => {
    
    
    if (!window.location.pathname.includes('login.html') && 
        !window.location.pathname.includes('register.html') && 
        !window.location.pathname.endsWith('index.html') && 
        window.location.pathname !== '/') {
        
    } else {
        loadSession();
    }
});
