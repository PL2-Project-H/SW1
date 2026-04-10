document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession(['admin']);
  const role = user.admin_role;

  await loadSection('admin-dashboard', 'admin.php?action=dashboard', (dashboard) =>
    Object.entries(dashboard).map(([key, value]) => `<div class="metric-card glass rounded-3xl p-5"><div class="text-sm text-slate-500">${key}</div><div class="mt-2 text-2xl font-semibold">${value}</div></div>`).join('')
  );

  if (['tech_support', 'dispute_mediator'].includes(role)) {
    await loadSection('users-table', 'admin.php?action=users', (users) =>
      users.map((userRow) => `<div class="rounded-2xl border p-4 text-sm">${userRow.name} | ${userRow.email} | ${userRow.status}</div>`).join('')
    );
  } else {
    setUnavailable('users-table', 'This admin role cannot view users.');
  }

  if (['tech_support', 'dispute_mediator'].includes(role)) {
    await loadSection('credentials-table', 'admin.php?action=credentials/queue', (credentials) =>
      credentials.map((item) => `<div class="rounded-2xl border p-4 text-sm">${item.freelancer_name} | ${item.type} | ${item.status}
        <div class="mt-2 flex gap-2"><button onclick="reviewCredential(${item.id},'verified')" class="rounded bg-emerald-600 px-3 py-1 text-white">Approve</button><button onclick="reviewCredential(${item.id},'rejected')" class="rounded bg-rose-600 px-3 py-1 text-white">Reject</button></div>
      </div>`).join('')
    );
  } else {
    setUnavailable('credentials-table', 'This admin role cannot review credentials.');
  }

  if (['tech_support', 'dispute_mediator', 'financial_admin'].includes(role)) {
    await loadSection('kyc-table', 'admin.php?action=kyc/queue', (rows) =>
      rows.map((item) => `<div class="rounded-2xl border p-4 text-sm">${item.name} | ${item.document_kind} | ${item.account_type}
        <div class="mt-2 flex gap-2"><button onclick="reviewKyc(${item.id},'verified')" class="rounded bg-emerald-600 px-3 py-1 text-white">Verify</button><button onclick="reviewKyc(${item.id},'rejected')" class="rounded bg-rose-600 px-3 py-1 text-white">Reject</button></div>
      </div>`).join('')
    );
  } else {
    setUnavailable('kyc-table', 'This admin role cannot review KYC submissions.');
  }

  await loadSection('dispute-table', 'dispute.php?action=mine', (disputes) =>
    disputes.map((item) => `<div class="rounded-2xl border p-4 text-sm">Dispute #${item.id} | ${item.status}</div>`).join('')
  );

  if (['tech_support', 'financial_admin'].includes(role)) {
    await loadSection('reports-table', 'admin.php?action=reports/niche', (reports) =>
      reports.map((row) => `<div class="rounded-2xl border p-4 text-sm">${row.niche} | Revenue ${row.total_revenue}
        <div class="mt-2 text-slate-600">Top rated: ${(row.top_rated_freelancers || []).map((item) => `${item.name} (${item.composite_score ?? 0})`).join(', ') || 'None yet'}</div>
      </div>`).join('')
    );
  } else {
    setUnavailable('reports-table', 'This admin role cannot access niche reports.');
  }

  if (role === 'tech_support') {
    await loadSection('audit-table', 'admin.php?action=audit-log', (audit) =>
      audit.map((row) => `<div class="rounded-2xl border p-4 text-sm">${row.action} | ${row.entity_type} #${row.entity_id}</div>`).join('')
    );
    await loadArchivedMessages();
  } else {
    setUnavailable('audit-table', 'This admin role cannot access the audit trail.');
    setUnavailable('archive-table', 'This admin role cannot browse archived messages.');
  }

  const rebuildButton = qs('rebuild-index');
  const digestButton = qs('send-digest');
  const canTechSupport = role === 'tech_support';
  rebuildButton.disabled = !canTechSupport;
  digestButton.disabled = !canTechSupport;
  rebuildButton.classList.toggle('opacity-50', !canTechSupport);
  digestButton.classList.toggle('opacity-50', !canTechSupport);

  rebuildButton.addEventListener('click', async () => {
    if (!canTechSupport) {
      alert('Only tech support admins can rebuild the index.');
      return;
    }
    try {
      const result = await apiCall('admin.php?action=search-index/rebuild');
      alert(`Search cache rebuilt for ${result.count} freelancers.`);
    } catch (error) {
      alert(error.message);
    }
  });

  digestButton.addEventListener('click', async () => {
    if (!canTechSupport) {
      alert('Only tech support admins can send the weekly digest.');
      return;
    }
    try {
      const result = await apiCall('admin.php?action=weekly-digest/send', 'POST', {});
      alert(`Generated ${result.generated} digests`);
    } catch (error) {
      alert(error.message);
    }
  });

  qs('archive-refresh')?.addEventListener('click', loadArchivedMessages);

  qs('flag-user-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!['tech_support', 'dispute_mediator'].includes(role)) {
      alert('Your admin role cannot flag users.');
      return;
    }
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
      await apiCall('admin.php?action=users/flag', 'POST', data);
      alert('User flagged.');
      e.target.reset();
    } catch (err) {
      alert(err.message);
    }
  });

  qs('sanction-user-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!['tech_support', 'dispute_mediator'].includes(role)) {
      alert('Your admin role cannot sanction users.');
      return;
    }
    const data = Object.fromEntries(new FormData(e.target).entries());
    try {
      await apiCall('admin.php?action=users/sanction', 'POST', data);
      alert('Sanction applied.');
      e.target.reset();
    } catch (err) {
      alert(err.message);
    }
  });

  
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.target);
      if (target) {
        target.classList.toggle('hidden');
        btn.querySelector('span').textContent = target.classList.contains('hidden') ? '▼' : '▲';
      }
    });
  });
});

async function reviewCredential(id, decision) {
  try {
    await apiCall('admin.php?action=credential/review', 'POST', { credential_id: id, decision });
    location.reload();
  } catch (error) {
    alert(error.message);
  }
}

async function reviewKyc(id, decision) {
  try {
    await apiCall('admin.php?action=kyc/review', 'POST', { submission_id: id, decision });
    location.reload();
  } catch (error) {
    alert(error.message);
  }
}

async function loadArchivedMessages() {
  const search = encodeURIComponent(qs('archive-search')?.value || '');
  await loadSection('archive-table', `admin.php?action=messages/archive&q=${search}&page=1&limit=20`, (data) =>
    (data.items || []).map((row) => `<div class="rounded-2xl border p-4 text-sm">
      <div class="font-semibold">${row.source_type} #${row.parent_id}</div>
      <div class="mt-1 text-slate-600">${row.decoded_message}</div>
      <div class="mt-2 text-xs text-slate-400">${row.sent_at}</div>
    </div>`).join('')
  );
}

async function loadSection(id, endpoint, render) {
  const target = qs(id);
  target.innerHTML = '<div class="rounded-2xl border p-4 text-sm text-slate-500">Loading...</div>';
  try {
    const data = await apiCall(endpoint);
    target.innerHTML = render(data) || '<div class="rounded-2xl border p-4 text-sm text-slate-500">No records found.</div>';
  } catch (error) {
    target.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${error.message}</div>`;
  }
}

function setUnavailable(id, message) {
  qs(id).innerHTML = `<div class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">${message}</div>`;
}
