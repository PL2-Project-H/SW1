document.addEventListener('DOMContentLoaded', async () => {
  const title = qs('dashboard-title');
  const grid = qs('dashboard-grid');
  try {
    const user = await checkSession();
    if (!user) {
      return;
    }

    const roleLabel = typeof user.role === 'string' && user.role.length
      ? `${user.role[0].toUpperCase()}${user.role.slice(1)}`
      : 'User';
    title.textContent = `${roleLabel} Dashboard`;

    if (user.role === 'freelancer') {
      const [contracts, bids, reputation] = await Promise.all([
        safeApiCall('project.php?action=contracts/active', []),
        safeApiCall('project.php?action=bids/mine', []),
        safeApiCall(`freelancer.php?action=reputation&freelancer_id=${user.id}`, {})
      ]);
      grid.innerHTML = `
        ${metric('Active Contracts', contracts.length)}
        ${metric('Pending Bids', bids.filter((item) => item.status === 'pending').length)}
        ${metric('Reputation', reputation.composite_score ?? 0)}
        ${metric('KYC', user.kyc_status)}
      `;
      return;
    }

    if (user.role === 'client') {
      const [contracts, jobs, notifications] = await Promise.all([
        safeApiCall('project.php?action=contracts/active', []),
        safeApiCall('client.php?action=jobs/mine', []),
        Promise.resolve(user.notifications || [])
      ]);
      grid.innerHTML = `
        ${metric('Active Contracts', contracts.length)}
        ${metric('Open Jobs', jobs.length)}
        ${metric('Unread Notifications', notifications.length)}
        ${metric('KYC', user.kyc_status)}
      `;
      return;
    }

    const data = await safeApiCall('admin.php?action=dashboard', {});
    grid.innerHTML = Object.entries(data).map(([key, value]) => metric(key.replaceAll('_', ' '), value)).join('') || emptyState();
  } catch (error) {
    title.textContent = 'Dashboard';
    grid.innerHTML = `<div class="glass rounded-3xl border border-rose-200 bg-rose-50 p-6 text-rose-700">${error.message || 'Dashboard failed to load.'}</div>`;
  }
});

function metric(label, value) {
  return `<div class="metric-card glass rounded-3xl p-6"><p class="text-sm uppercase tracking-[0.2em] text-slate-500">${label}</p><p class="mt-3 text-3xl font-semibold text-slate-900">${value}</p></div>`;
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
  return '<div class="glass rounded-3xl border p-6 text-slate-500">No dashboard data yet.</div>';
}
