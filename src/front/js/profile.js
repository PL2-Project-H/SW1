document.addEventListener('DOMContentLoaded', async () => {
  await checkSession(['freelancer']);
  const profile = await apiCall('freelancer.php?action=profile');
  qs('bio').value = profile.bio || '';
  qs('niche').value = profile.niche || 'other';
  qs('hourly_rate').value = profile.hourly_rate || '';
  qs('timezone').value = profile.timezone || '';
  qs('skills').value = (profile.skills || []).map((item) => item.name).join(', ');
  qs('digest_opt_in').checked = Boolean(Number(profile.digest_opt_in ?? 1));
  renderList('portfolio-list', profile.portfolio || [], (item) => {
    const metadata = item.metadata_json ? JSON.parse(item.metadata_json) : {};
    const clientName = metadata.client_name || 'Private client';
    const outcome = metadata.project_outcome || metadata.dataset_description || metadata.case_type || metadata.language_pair || 'No outcome provided';
    return `<div class="rounded-2xl border p-4">
      <div class="font-semibold">${item.title}</div>
      <div class="text-sm text-slate-500">${item.file_path}</div>
      <div class="mt-2 text-sm text-slate-600">Client: ${item.is_confidential == 1 ? 'Hidden' : clientName}</div>
      <div class="text-sm text-slate-600">Outcome: ${outcome}</div>
    </div>`;
  });
  renderList('credential-list', profile.credentials || [], (item) => `<div class="rounded-2xl border p-4"><div>${item.type}</div><div class="text-sm text-slate-500">${item.status}</div></div>`);
  renderList('availability-list', profile.availability || [], (item) => `<div class="rounded-xl border p-3 text-sm">Day ${item.day_of_week}: ${item.start_time_utc} - ${item.end_time_utc}</div>`);
  renderList('kyc-list', profile.kyc_submissions || [], (item) => `<div class="rounded-2xl border p-4"><div>${item.document_kind}</div><div class="text-sm text-slate-500">${item.account_type} | ${item.status}</div></div>`);

  renderAvailabilitySlots(profile.availability || []);

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

  document.getElementById('add-slot-btn')?.addEventListener('click', () => {
    renderAvailabilitySlots([
      ...collectCurrentSlots(),
      { day_of_week: 1, start_time_utc: '09:00', end_time_utc: '17:00' }
    ]);
  });

  qs('availability-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const slots = collectCurrentSlots();
    try {
      await apiCall('freelancer.php?action=availability/set', 'POST', { slots });
      alert('Availability saved.');
      location.reload();
    } catch (error) {
      alert(error.message);
    }
  });
});

const DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

function renderAvailabilitySlots(slots) {
  const container = document.getElementById('availability-slots');
  container.innerHTML = slots.map((slot, i) => `
    <div class="availability-slot grid grid-cols-4 gap-2 items-center" data-index="${i}">
      <select name="day_of_week" class="rounded-xl border px-3 py-2 text-sm">
        ${DAYS.map((d, idx) => `<option value="${idx}" ${Number(slot.day_of_week) === idx ? 'selected' : ''}>${d}</option>`).join('')}
      </select>
      <input type="time" name="start_time_utc" value="${(slot.start_time_utc || '09:00').substring(0,5)}" class="rounded-xl border px-3 py-2 text-sm">
      <input type="time" name="end_time_utc" value="${(slot.end_time_utc || '17:00').substring(0,5)}" class="rounded-xl border px-3 py-2 text-sm">
      <button type="button" class="remove-slot-btn rounded-xl bg-rose-500 px-3 py-2 text-white text-sm">Remove</button>
    </div>
  `).join('') || '<p class="text-sm text-slate-500">No slots yet. Click Add to start.</p>';

  container.querySelectorAll('.remove-slot-btn').forEach(btn => {
    btn.addEventListener('click', () => btn.closest('.availability-slot').remove());
  });
}

function collectCurrentSlots() {
  return Array.from(document.querySelectorAll('.availability-slot')).map(row => ({
    day_of_week: parseInt(row.querySelector('[name=day_of_week]').value, 10),
    start_time_utc: row.querySelector('[name=start_time_utc]').value + ':00',
    end_time_utc: row.querySelector('[name=end_time_utc]').value + ':00',
  }));
}

function renderList(id, items, renderer) {
  qs(id).innerHTML = items.length ? items.map(renderer).join('') : '<p class="text-sm text-slate-500">No records yet.</p>';
}
