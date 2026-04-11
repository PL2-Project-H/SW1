document.addEventListener('DOMContentLoaded', async () => {
  await checkSession(['freelancer']);
  
  
  const tabs = document.querySelectorAll('.tab');
  const panes = document.querySelectorAll('.tab-pane');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      panes.forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      document.getElementById(tab.dataset.target).classList.add('active');
    });
  });

  
  let profile = {};
  try {
    profile = await apiCall('freelancer.php?action=profile');
  } catch (e) {
    document.querySelector('.shell').innerHTML = `<div class="empty-state"><p>Error mapping profile: ${e.message}</p></div>`;
    return;
  }

  qs('bio').value = profile.bio || '';
  qs('niche').value = profile.niche || 'other';
  qs('hourly_rate').value = profile.hourly_rate || '';
  qs('timezone').value = profile.timezone || '';
  qs('skills').value = (profile.skills || []).map((item) => item.name).join(', ');
  qs('digest_opt_in').checked = Boolean(Number(profile.digest_opt_in ?? 1));

  
  renderList('portfolio-list', profile.portfolio || [], (item) => {
    const metadata = item.metadata_json ? JSON.parse(item.metadata_json) : {};
    const clientName = metadata.client_name || 'Private/Undisclosed';
    const outcome = metadata.project_outcome || metadata.dataset_description || metadata.case_type || metadata.language_pair || 'No outcome provided';
    return `
      <div style="padding:1.5rem; border:1px solid var(--border-input); border-radius:var(--radius-card); background:var(--paper);">
        <div style="display:flex; justify-content:space-between; align-items:start;">
          <h4 style="font-weight:700; font-size:1.1rem;">${escapeHtml(item.title)}</h4>
          <span class="badge ${item.is_confidential == 1 ? 'badge-danger' : 'badge-gold'}">${item.is_confidential == 1 ? 'Internal Proof' : 'Public Work'}</span>
        </div>
        <div style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">Stored path: ${item.file_path}</div>
        
        <div style="margin-top:1rem; font-size:0.9rem;">
          <div><strong style="color:var(--ink);">Entity:</strong> ${item.is_confidential == 1 ? 'Confidential Client' : escapeHtml(clientName)}</div>
          <div style="margin-top:0.25rem;"><strong style="color:var(--ink);">Impact Array:</strong> ${escapeHtml(outcome)}</div>
        </div>
      </div>
    `;
  });

  renderList('credential-list', profile.credentials || [], (item) => `
    <div style="display:flex; justify-content:space-between; align-items:center; padding:1.5rem; border:1px solid var(--border-input); border-radius:var(--radius-card); background:rgba(255,255,255,0.4);">
      <div>
        <div style="font-weight:700; text-transform:capitalize;">${item.type.replace('_', ' ')}</div>
        <div style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">Timestamped block reference</div>
      </div>
      <span class="badge ${item.status === 'verified' ? 'badge-open' : 'badge-warning'}">${item.status}</span>
    </div>
  `);

  renderList('kyc-list', profile.kyc_submissions || [], (item) => `
    <div style="display:flex; justify-content:space-between; align-items:center; padding:1.5rem; border:1px solid var(--border-input); border-radius:var(--radius-card); background:rgba(255,255,255,0.4);">
      <div>
        <div style="font-weight:700; text-transform:capitalize;">${item.document_kind.replace('_', ' ')}</div>
        <div style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">Taxonomy: ${item.account_type}</div>
      </div>
      <span class="badge ${item.status === 'verified' ? 'badge-open' : 'badge-warning'}">${item.status}</span>
    </div>
  `);

  renderAvailabilitySlots(profile.availability || []);
  renderAvailability7DayGrid(profile.availability || []);

  
  qs('profile-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const btn = event.target.querySelector('button');
    btn.textContent = 'Updating...';
    try {
      const formData = Object.fromEntries(new FormData(event.target).entries());
      formData.digest_opt_in = qs('digest_opt_in').checked ? 1 : 0;
      await apiCall('freelancer.php?action=profile/update', 'POST', formData);
      showSuccess(qs('profile-form-err'), 'Identity logic preserved.');
      btn.textContent = 'Update Core Profile';
    } catch (error) {
      showError(qs('profile-form-err'), error.message);
      btn.textContent = 'Update Core Profile';
    }
  });

  const uploadForms = [
    {id: 'credential-form', err: 'cred-err', endpoint: 'freelancer.php?action=credentials/submit'},
    {id: 'kyc-form', err: 'kyc-err', endpoint: 'freelancer.php?action=kyc/submit'},
    {id: 'portfolio-form', err: 'port-err', endpoint: 'freelancer.php?action=portfolio/add'}
  ];

  uploadForms.forEach(({id, err, endpoint}) => {
    const f = qs(id);
    if(f) {
      f.addEventListener('submit', async (event) => {
        event.preventDefault();
        const btn = event.target.querySelector('button');
        const ogText = btn.textContent;
        btn.textContent = 'Transmitting...';
        try {
          await apiCall(endpoint, 'POST', new FormData(event.target));
          showSuccess(qs(err), 'Payload securely handled.');
          setTimeout(() => location.reload(), 1500);
        } catch (error) {
          showError(qs(err), error.message);
          btn.textContent = ogText;
        }
      });
    }
  });

  document.getElementById('add-slot-btn')?.addEventListener('click', () => {
    renderAvailabilitySlots([
      ...collectCurrentSlots(),
      { day_of_week: 1, start_time_utc: '09:00:00', end_time_utc: '17:00:00' }
    ]);
  });

  qs('availability-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const btn = event.target.querySelector('button[type="submit"]');
    const ogText = btn.textContent;
    btn.textContent = 'Locking...';
    const slots = collectCurrentSlots();
    try {
      await apiCall('freelancer.php?action=availability/set', 'POST', { slots });
      showSuccess(qs('avail-err'), 'Architecture mapped.');
      setTimeout(() => location.reload(), 1500);
    } catch (error) {
      showError(qs('avail-err'), error.message);
      btn.textContent = ogText;
    }
  });
  
  
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', (e) => {
      const dropzone = e.target.closest('.file-dropzone');
      if (dropzone && e.target.files.length) {
        let label = dropzone.querySelector('div');
        if (label) label.textContent = 'Attached: ' + e.target.files[0].name;
      }
    });
  });
});

const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

function renderAvailability7DayGrid(slots) {
  const container = document.getElementById('availability-grid-container');
  if (!container) return;
  
  
  const grouped = {0:[],1:[],2:[],3:[],4:[],5:[],6:[]};
  slots.forEach(s => {
    if (grouped[s.day_of_week]) {
      grouped[s.day_of_week].push(s);
    }
  });

  let html = '';
  DAYS.forEach((dayName, idx) => {
    const daySlots = grouped[idx] || [];
    let slotsHtml = daySlots.map(s => {
      const start = (s.start_time_utc || '').substring(0,5);
      const end = (s.end_time_utc || '').substring(0,5);
      return `<div class="availability-slot-badge">${start} - ${end}</div>`;
    }).join('');
    
    html += `
      <div class="availability-day">
        <div class="availability-day-header">${dayName}</div>
        <div style="flex:1; display:flex; flex-direction:column; justify-content:center; align-items:center;">
          ${slotsHtml ? slotsHtml : '<span style="font-size:0.7rem; color:var(--border);">Not Mapped</span>'}
        </div>
      </div>
    `;
  });
  container.innerHTML = html;
}

function renderAvailabilitySlots(slots) {
  const container = document.getElementById('availability-slots');
  container.innerHTML = slots.map((slot, i) => `
    <div class="availability-slot" data-index="${i}" style="display:flex; gap:1rem; align-items:center; background:rgba(255,255,255,0.4); padding:0.5rem 1rem; border-radius:8px; border:1px solid var(--border-input);">
      <select name="day_of_week" class="sh-select" style="min-width:120px; background:transparent;">
        ${DAYS.map((d, idx) => `<option value="${idx}" ${Number(slot.day_of_week) === idx ? 'selected' : ''}>${d}</option>`).join('')}
      </select>
      <input type="time" name="start_time_utc" value="${(slot.start_time_utc || '09:00:00').substring(0,5)}" class="sh-input" style="background:transparent; border-color:transparent;">
      <span style="color:var(--muted)">to</span>
      <input type="time" name="end_time_utc" value="${(slot.end_time_utc || '17:00:00').substring(0,5)}" class="sh-input" style="background:transparent; border-color:transparent;">
      
      <button type="button" class="remove-slot-btn btn-danger btn-sm" style="margin-left:auto;">&times;</button>
    </div>
  `).join('') || '<div class="empty-state" style="padding:1rem;"><p>No granular configurations set.</p></div>';

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
  qs(id).innerHTML = items.length ? items.map(renderer).join('') : '<div class="empty-state" style="padding:1.5rem;"><p>No authenticated records established.</p></div>';
}

function escapeHtml(s) {
  if (!s) return '';
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
