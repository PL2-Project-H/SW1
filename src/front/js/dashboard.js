document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const title = qs('dashboard-title');
  const grid = qs('dashboard-grid');
  title.textContent = `${user.role[0].toUpperCase()}${user.role.slice(1)} Dashboard`;

  if (user.role === 'freelancer') {
    const [contracts, bids, reputation] = await Promise.all([
      apiCall('project.php?action=contracts/active'),
      apiCall('project.php?action=bids/mine'),
      apiCall(`freelancer.php?action=reputation&freelancer_id=${user.id}`)
    ]);
    grid.innerHTML = `
      ${metric('Active Contracts', contracts.length)}
      ${metric('Pending Bids', bids.filter((item) => item.status === 'pending').length)}
      ${metric('Reputation', reputation.composite_score ?? 0)}
      ${metric('KYC', user.kyc_status)}
    `;
  } else if (user.role === 'client') {
    const [contracts, jobs, notifications] = await Promise.all([
      apiCall('project.php?action=contracts/active'),
      apiCall('client.php?action=jobs/mine'),
      Promise.resolve(user.notifications || [])
    ]);
    grid.innerHTML = `
      ${metric('Active Contracts', contracts.length)}
      ${metric('Open Jobs', jobs.length)}
      ${metric('Unread Notifications', notifications.length)}
      ${metric('KYC', user.kyc_status)}
    `;
  } else {
    const data = await apiCall('admin.php?action=dashboard');
    grid.innerHTML = Object.entries(data).map(([key, value]) => metric(key.replaceAll('_', ' '), value)).join('');
  }
});

function metric(label, value) {
  return `<div class="metric-card glass rounded-3xl p-6"><p class="text-sm uppercase tracking-[0.2em] text-slate-500">${label}</p><p class="mt-3 text-3xl font-semibold text-slate-900">${value}</p></div>`;
}
