document.addEventListener('DOMContentLoaded', async () => {
  await checkSession(['freelancer']);
  const profile = await apiCall('freelancer.php?action=profile');
  qs('bio').value = profile.bio || '';
  qs('niche').value = profile.niche || 'other';
  qs('hourly_rate').value = profile.hourly_rate || '';
  qs('timezone').value = profile.timezone || '';
  qs('skills').value = (profile.skills || []).map((item) => item.name).join(', ');
  qs('digest_opt_in').checked = Boolean(Number(profile.digest_opt_in ?? 1));
  renderList('portfolio-list', profile.portfolio || [], (item) => `<div class="rounded-2xl border p-4"><div class="font-semibold">${item.title}</div><div class="text-sm text-slate-500">${item.file_path}</div></div>`);
  renderList('credential-list', profile.credentials || [], (item) => `<div class="rounded-2xl border p-4"><div>${item.type}</div><div class="text-sm text-slate-500">${item.status}</div></div>`);
  renderList('availability-list', profile.availability || [], (item) => `<div class="rounded-xl border p-3 text-sm">Day ${item.day_of_week}: ${item.start_time_utc} - ${item.end_time_utc}</div>`);
  renderList('kyc-list', profile.kyc_submissions || [], (item) => `<div class="rounded-2xl border p-4"><div>${item.document_kind}</div><div class="text-sm text-slate-500">${item.account_type} | ${item.status}</div></div>`);

  qs('profile-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const formData = Object.fromEntries(new FormData(event.target).entries());
      formData.digest_opt_in = qs('digest_opt_in').checked ? 1 : 0;
      await apiCall('freelancer.php?action=profile/update', 'POST', formData);
      location.reload();
    } catch (error) {
      alert(error.message);
    }
  });

  qs('credential-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall('freelancer.php?action=credentials/submit', 'POST', new FormData(event.target));
      location.reload();
    } catch (error) {
      alert(error.message);
    }
  });

  qs('kyc-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall('freelancer.php?action=kyc/submit', 'POST', new FormData(event.target));
      location.reload();
    } catch (error) {
      alert(error.message);
    }
  });

  qs('portfolio-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall('freelancer.php?action=portfolio/add', 'POST', new FormData(event.target));
      location.reload();
    } catch (error) {
      alert(error.message);
    }
  });

  qs('availability-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      const slots = JSON.parse(qs('availability_json').value || '[]');
      await apiCall('freelancer.php?action=availability/set', 'POST', { slots });
      location.reload();
    } catch (error) {
      alert(error.message.includes('JSON') ? 'Availability JSON is invalid.' : error.message);
    }
  });
});

function renderList(id, items, renderer) {
  qs(id).innerHTML = items.length ? items.map(renderer).join('') : '<p class="text-sm text-slate-500">No records yet.</p>';
}
