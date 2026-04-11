document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession(['admin']);
  const role = user.admin_role;

  await loadSection('admin-dashboard', 'admin.php?action=dashboard', (dashboard) =>
    Object.entries(dashboard).map(([key, value]) => `
      <div class="hcard" style="margin:0;">
        <div class="hcard-eyebrow" style="text-transform:capitalize;">${key.replace(/_/g, ' ')}</div>
        <div class="hcard-amount">${value}</div>
      </div>
    `).join('')
  );

  if (['tech_support', 'dispute_mediator'].includes(role)) {
    await loadSection('users-table', 'admin.php?action=users', (users) =>
      users.map((userRow) => `
        <div class="tr">
          <div><strong style="color:var(--ink);">${userRow.name}</strong> <span style="color:var(--muted); margin-left:0.5rem;">${userRow.email}</span></div>
          <span class="badge ${userRow.status==='active'?'badge-open':'badge-warning'}">${userRow.status}</span>
        </div>
      `).join('')
    );
  } else {
    setUnavailable('users-table', 'Role restricted.');
  }

  if (['tech_support', 'dispute_mediator'].includes(role)) {
    await loadSection('credentials-table', 'admin.php?action=credentials/queue', (credentials) =>
      credentials.map((item) => `
        <div class="tr" style="align-items:start;">
          <div>
            <div style="font-weight:700;">Freelancer: ${item.freelancer_name}</div>
            <div style="font-size:0.8rem; color:var(--muted); margin-top:0.25rem;">Type: ${item.type} | <span class="badge ${item.status==='pending'?'badge-warning':'badge-info'}">${item.status}</span></div>
          </div>
          <div style="display:flex; gap:0.5rem;">
            <button onclick="reviewCredential(${item.id},'verified')" class="btn-secondary btn-sm" style="color:var(--success); border-color:var(--success);">Approve</button>
            <button onclick="reviewCredential(${item.id},'rejected')" class="btn-danger btn-sm">Reject</button>
          </div>
        </div>
      `).join('')
    );
  } else {
    setUnavailable('credentials-table', 'Role restricted.');
  }

  if (['tech_support', 'dispute_mediator', 'financial_admin'].includes(role)) {
    await loadSection('kyc-table', 'admin.php?action=kyc/queue', (rows) =>
      rows.map((item) => `
        <div class="tr" style="align-items:start;">
          <div>
            <div style="font-weight:700;">Entity: ${item.name}</div>
            <div style="font-size:0.8rem; color:var(--muted); margin-top:0.25rem;">Format: ${item.document_kind} | Tier: ${item.account_type}</div>
          </div>
          <div style="display:flex; gap:0.5rem;">
            <button onclick="reviewKyc(${item.id},'verified')" class="btn-secondary btn-sm" style="color:var(--success); border-color:var(--success);">Verify</button>
            <button onclick="reviewKyc(${item.id},'rejected')" class="btn-danger btn-sm">Reject</button>
          </div>
        </div>
      `).join('')
    );
  } else {
    setUnavailable('kyc-table', 'Role restricted.');
  }

  await loadSection('dispute-table', 'dispute.php?action=mine', (disputes) =>
    disputes.map((item) => `
      <div class="tr">
        <div><strong>Dispute Protocol #${item.id}</strong></div>
        <span class="badge badge-danger">${item.status}</span>
      </div>
    `).join('')
  );

  if (['tech_support', 'financial_admin'].includes(role)) {
    await loadSection('reports-table', 'admin.php?action=reports/niche', (reports) =>
      reports.map((row) => `
        <div class="tr" style="align-items:start;">
          <div>
            <div style="font-weight:700; text-transform:capitalize;">${row.niche.replace('_',' ')}</div>
            <div style="font-size:0.8rem; color:var(--muted); margin-top:0.25rem;">
              Top Experts: ${(row.top_rated_freelancers || []).map((item) => `${item.name} (${item.composite_score ?? 0})`).join(', ') || 'N/A'}
            </div>
          </div>
          <div style="font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:700;">
            $${parseFloat(row.total_revenue).toLocaleString()}
          </div>
        </div>
      `).join('')
    );
  } else {
    setUnavailable('reports-table', 'Role restricted.');
  }

  if (role === 'tech_support') {
    await loadSection('audit-table', 'admin.php?action=audit-log', (audit) =>
      audit.map((row) => `
        <div class="tr">
          <div>
            <span class="badge" style="background:#e5e7eb; color:#374151;">${row.action}</span>
            <span style="font-size:0.9rem; margin-left:0.5rem;">${row.entity_type} #${row.entity_id}</span>
          </div>
        </div>
      `).join('')
    );
    await loadArchivedMessages();
  } else {
    setUnavailable('audit-table', 'Role restricted.');
    setUnavailable('archive-table', 'Role restricted.');
  }

  const rebuildButton = qs('rebuild-index');
  const digestButton = qs('send-digest');
  const canTechSupport = role === 'tech_support';
  rebuildButton.disabled = !canTechSupport;
  digestButton.disabled = !canTechSupport;
  if(!canTechSupport) {
    rebuildButton.style.opacity = '0.5';
    digestButton.style.opacity = '0.5';
  }

  rebuildButton.addEventListener('click', async () => {
    if (!canTechSupport) return;
    try {
      rebuildButton.textContent = 'Processing...';
      const result = await apiCall('admin.php?action=search-index/rebuild');
      alert(`Search cache rebuilt for ${result.count} freelancers.`);
      rebuildButton.textContent = 'Trigger Index Rebuild';
    } catch (error) {
      alert(error.message);
      rebuildButton.textContent = 'Trigger Index Rebuild';
    }
  });

  digestButton.addEventListener('click', async () => {
    if (!canTechSupport) return;
    try {
      digestButton.textContent = 'Dispatching...';
      const result = await apiCall('admin.php?action=weekly-digest/send', 'POST', {});
      alert(`Generated ${result.generated} digests`);
      digestButton.textContent = 'Dispatch Recommendations';
    } catch (error) {
      alert(error.message);
      digestButton.textContent = 'Dispatch Recommendations';
    }
  });

  qs('archive-refresh')?.addEventListener('click', loadArchivedMessages);

  qs('flag-user-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!['tech_support', 'dispute_mediator'].includes(role)) return;
    const data = Object.fromEntries(new FormData(e.target).entries());
    const btn = e.target.querySelector('button'); btn.textContent = '...';
    try {
      await apiCall('admin.php?action=users/flag', 'POST', data);
      alert('User flagged.');
      e.target.reset(); btn.textContent = 'Attach Flag';
    } catch (err) { alert(err.message); btn.textContent = 'Attach Flag'; }
  });

  qs('sanction-user-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!['tech_support', 'dispute_mediator'].includes(role)) return;
    const data = Object.fromEntries(new FormData(e.target).entries());
    const btn = e.target.querySelector('button'); btn.textContent = '...';
    try {
      await apiCall('admin.php?action=users/sanction', 'POST', data);
      alert('Sanction applied.');
      e.target.reset(); btn.textContent = 'Execute Sanction Request';
    } catch (err) { alert(err.message); btn.textContent = 'Execute Sanction Request'; }
  });

  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.target);
      if (target) {
        const isHidden = target.classList.toggle('hidden');
        btn.classList.toggle('open', !isHidden);
        btn.querySelector('span').textContent = isHidden ? '▼' : '▲';
      }
    });
  });
});

async function reviewCredential(id, decision) {
  try {
    document.body.style.opacity = '0.5';
    await apiCall('admin.php?action=credential/review', 'POST', { credential_id: id, decision });
    location.reload();
  } catch (error) { document.body.style.opacity = '1'; alert(error.message); }
}

async function reviewKyc(id, decision) {
  try {
    document.body.style.opacity = '0.5';
    await apiCall('admin.php?action=kyc/review', 'POST', { submission_id: id, decision });
    location.reload();
  } catch (error) { document.body.style.opacity = '1'; alert(error.message); }
}

async function loadArchivedMessages() {
  const searchEl = qs('archive-search');
  const search = encodeURIComponent(searchEl ? searchEl.value : '');
  await loadSection('archive-table', `admin.php?action=messages/archive&q=${search}&page=1&limit=20`, (data) =>
    (data.items || []).map((row) => `
      <div class="tr" style="align-items:start; flex-direction:column; gap:0.5rem;">
        <div style="display:flex; justify-content:space-between; width:100%;">
          <strong style="font-size:0.9rem;">${row.source_type} #${row.parent_id}</strong>
          <span style="font-size:0.8rem; color:var(--muted);">${row.sent_at}</span>
        </div>
        <div style="font-size:0.85rem; color:var(--ink);">${escapeHtml(row.decoded_message)}</div>
      </div>
    `).join('')
  );
}

async function loadSection(id, endpoint, render) {
  const target = qs(id);
  if(!target) return;
  target.innerHTML = '<div class="empty-state"><p>Querying datastore...</p></div>';
  try {
    const data = await apiCall(endpoint);
    target.innerHTML = render(data) || '<div class="empty-state"><p>No records located.</p></div>';
  } catch (error) {
    target.innerHTML = `<div class="empty-state"><p style="color:var(--danger);">${error.message}</p></div>`;
  }
}

function setUnavailable(id, message) {
  const target = qs(id);
  if(target) target.innerHTML = `<div class="empty-state"><p>${message}</p></div>`;
}

function escapeHtml(s) {
  if(!s) return '';
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
