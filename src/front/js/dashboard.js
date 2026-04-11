document.addEventListener('DOMContentLoaded', async () => {
  const title = qs('dashboard-title');
  const roleBadge = qs('role-badge');
  const grid = qs('dashboard-grid');
  const quickActions = qs('quick-actions');
  const notifsContainer = qs('dashboard-notifications');

  showLoading('dashboard-grid');

  try {
    const user = await checkSession();
    if (!user) return;

    title.textContent = `Good morning, ${user.name.split(' ')[0]}`;
    
    
    let badgeClass = 'badge-open';
    if (user.role === 'admin') badgeClass = 'badge-purple';
    else if (user.role === 'client') badgeClass = 'badge-info';
    else badgeClass = 'badge-gold';

    roleBadge.className = `badge ${badgeClass}`;
    roleBadge.textContent = user.role.toUpperCase();

    
    if (user.role === 'client') {
      quickActions.innerHTML = `
        <a href="jobs.html" class="btn-primary">Post a Job</a>
        <a href="contract.html" class="btn-secondary">View Contracts</a>
      `;
    } else if (user.role === 'freelancer') {
      quickActions.innerHTML = `
        <a href="jobs.html" class="btn-primary">Browse Jobs</a>
        <a href="contract.html" class="btn-secondary">Active Work</a>
      `;
    } else if (user.role === 'admin') {
      quickActions.innerHTML = `
        <a href="admin.html" class="btn-primary">Admin Control Center</a>
        <a href="dispute.html" class="btn-secondary">Mediator View</a>
      `;
    }

    
    const notifs = user.notifications || [];
    const unread = notifs.filter(n => Number(n.is_read) === 0).slice(0, 3);
    if (unread.length > 0) {
      notifsContainer.innerHTML = `
        <div class="card">
          <div class="card-body" style="padding: 1rem 1.5rem;">
            ${unread.map(n => `
              <div style="border-bottom: 1px solid var(--border); padding: 0.75rem 0; ${unread.indexOf(n) === unread.length-1 ? 'border-bottom:none; padding-bottom:0;' : ''}">
                <div style="font-size: 0.75rem; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold);">${n.type.replace('_', ' ')}</div>
                <div style="font-size: 0.95rem; color: var(--ink); margin-top: 0.25rem;">${n.message}</div>
                <div style="font-size: 0.75rem; color: var(--muted); margin-top: 0.25rem;">${n.created_at}</div>
              </div>
            `).join('')}
          </div>
        </div>
      `;
    } else {
      notifsContainer.innerHTML = `<div class="empty-state"><p>You're all caught up!</p></div>`;
    }

    
    if (user.role === 'freelancer') {
      const [contracts, bids, reputation] = await Promise.all([
        safeApiCall('project.php?action=contracts/active', []),
        safeApiCall('project.php?action=bids/mine', []),
        safeApiCall(`freelancer.php?action=reputation&freelancer_id=${user.id}`, {})
      ]);
      grid.innerHTML = `
        ${metricCard('Active Contracts', contracts.length)}
        ${metricCard('Pending Bids', bids.filter(item => item.status === 'pending').length)}
        ${metricCard('Reputation', reputation.composite_score || 0)}
        ${metricCard('KYC Status', user.kyc_status)}
      `;
      return;
    }

    if (user.role === 'client') {
      const [contracts, jobs] = await Promise.all([
        safeApiCall('project.php?action=contracts/active', []),
        safeApiCall('client.php?action=jobs/mine', [])
      ]);
      grid.innerHTML = `
        ${metricCard('Active Contracts', contracts.length)}
        ${metricCard('Open Jobs', jobs.length)}
        ${metricCard('Escrow Ready', '100%')}
        ${metricCard('KYC Status', user.kyc_status)}
      `;
      return;
    }

    
    const data = await safeApiCall('admin.php?action=dashboard', {});
    grid.innerHTML = Object.entries(data)
      .map(([key, value]) => metricCard(key.replaceAll('_', ' '), value))
      .join('') || emptyState();

  } catch (error) {
    grid.innerHTML = '';
    showError(grid, error.message || 'Dashboard failed to load.');
  }
});

function metricCard(label, value) {
  return `
    <div class="hcard" style="transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;">
      <div class="hcard-eyebrow">${label}</div>
      <div class="hcard-amount" style="font-size: 2.2rem; margin-top: 0.5rem;">${value}</div>
    </div>
  `;
}

async function safeApiCall(endpoint, fallback) {
  try {
    return await apiCall(endpoint);
  } catch (error) {
    console.error(`Dashboard request failed: ${endpoint}`, error);
    return fallback;
  }
}

function emptyState() {
  return '<div class="empty-state"><p>No dashboard data yet.</p></div>';
}
