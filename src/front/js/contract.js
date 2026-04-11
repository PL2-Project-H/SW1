document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  if (!user) return;
  const params = new URLSearchParams(location.search);
  const contractId = params.get('contract_id');
  const milestoneId = params.get('milestone_id');

  if (milestoneId) {
    try {
      const milestone = await apiCall(`project.php?action=milestones/${milestoneId}`);
      await renderMilestoneDetail(milestone, user);
    } catch (e) {
      console.error('Failed to load milestone', e);
    }
  }

  const contracts = contractId ? [await apiCall(`project.php?action=contracts/${contractId}`)] : await apiCall('project.php?action=contracts/active');
  const container = qs('contract-list');
  if (!contracts.length) {
    container.innerHTML = `<div class="empty-state"><p>No active contracts found.</p></div>`;
  } else {
    container.innerHTML = contracts.map(c => renderContract(c, user)).join('');
  }

  if (contractId && contracts.length) {
    const activeContract = contracts[0];

    if (activeContract.status === 'pending_nda') {
      const ndaWrap = qs('nda-signing-wrap');
      const ndaContent = qs('nda-content');
      if (ndaWrap && ndaContent) {
        ndaWrap.classList.remove('hidden');
        ndaContent.textContent = activeContract.nda_content || 'Please review the standard mutual Non-Disclosure Agreement for this execution.';
        qs('sign-nda-btn').onclick = async () => {
          try {
            const endpoint = user.role === 'client' ? 'client.php?action=contracts/nda/sign' : 'project.php?action=contracts/nda/sign';
            qs('sign-nda-btn').textContent = 'Signing...';
            await apiCall(endpoint, 'POST', { job_id: activeContract.job_id });
            location.reload();
          } catch (err) { 
            alert(err.message); 
            qs('sign-nda-btn').textContent = 'Digitally Sign';
          }
        };
      }
    }

    if (user.role !== 'admin') {
      const wrap = qs('contract-messages-wrap');
      if (wrap) {
        wrap.classList.remove('hidden');
        await loadContractMessages(contractId, user);
        qs('contract-message-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const text = qs('contract_message_input').value.trim();
          if (!text) return;
          qs('contract_message_input').disabled = true;
          await apiCall('project.php?action=contracts/message', 'POST', { contract_id: Number(contractId), message: text });
          qs('contract_message_input').value = '';
          qs('contract_message_input').disabled = false;
          qs('contract_message_input').focus();
          await loadContractMessages(contractId, user);
        });
      }
    }

    const buildInput = qs('build_contract_id');
    if (buildInput && !buildInput.value) {
      buildInput.value = contractId;
    }
  }

  document.getElementById('add-milestone-btn')?.addEventListener('click', () => addMilestoneRow());

  qs('milestone-build-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const contractIdInput = qs('build_contract_id').value;
    if (!contractIdInput) return;
    const btn = document.querySelector('#milestone-build-form button[type="submit"]');
    btn.textContent = 'Saving...';
    
    const rows = Array.from(document.querySelectorAll('.milestone-row'));
    if (rows.length === 0) { alert('Add at least one milestone'); btn.textContent = 'Commit Architecture'; return; }
    const milestones = rows.map((row, i) => ({
      title: row.querySelector('[name=title]').value.trim(),
      amount: parseFloat(row.querySelector('[name=amount]').value),
      due_date: row.querySelector('[name=due_date]').value.replace('T', ' ') + ':00',
      order_index: parseInt(row.querySelector('[name=order_index]').value, 10) || (i + 1),
      dependency_milestone_id: row.querySelector('[name=dependency_milestone_id]').value
        ? parseInt(row.querySelector('[name=dependency_milestone_id]').value, 10)
        : null,
    }));
    try {
      await apiCall('project.php?action=contracts/milestones/build', 'POST', { contract_id: contractIdInput, milestones });
      location.reload();
    } catch (error) {
      alert(error.message);
      btn.textContent = 'Commit Architecture';
    }
  });

});

function addMilestoneRow(data = {}) {
  const container = document.getElementById('milestone-rows');
  const idx = container.querySelectorAll('.milestone-row').length;
  const row = document.createElement('div');
  row.className = 'milestone-row form-grid';
  row.style.background = 'rgba(255,255,255,0.4)';
  row.style.padding = '1rem';
  row.style.borderRadius = '8px';
  row.style.border = '1px solid var(--border-input)';
  row.innerHTML = `
    <div class="form-group mb-0">
      <label class="sh-label">Title</label>
      <input name="title" class="sh-input" placeholder="Phase identifier" value="${data.title || ''}" required>
    </div>
    <div class="form-group mb-0">
      <label class="sh-label">Valuation ($)</label>
      <input name="amount" type="number" step="0.01" min="0" class="sh-input" placeholder="500.00" value="${data.amount || ''}" required>
    </div>
    <div class="form-group mb-0">
      <label class="sh-label">Due Date</label>
      <input name="due_date" type="datetime-local" class="sh-input" value="${data.due_date ? data.due_date.replace(' ', 'T').substring(0,16) : ''}" required>
    </div>
    <div class="form-group mb-0">
      <label class="sh-label">Sequence #</label>
      <input name="order_index" type="number" min="1" class="sh-input" value="${data.order_index || (idx + 1)}">
    </div>
    <div class="form-group mb-0">
      <label class="sh-label">Depends on Milestone # <span style="text-transform:none">(optional)</span></label>
      <input name="dependency_milestone_id" type="number" min="1" class="sh-input" placeholder="Leave blank if first" value="${data.dependency_milestone_id || ''}">
    </div>
    <div class="form-group mb-0" style="display:flex; align-items:flex-end;">
      <button type="button" class="remove-milestone-btn btn-danger" style="width:100%;">Remove</button>
    </div>
  `;
  row.querySelector('.remove-milestone-btn').addEventListener('click', () => row.remove());
  container.appendChild(row);
}

async function loadContractMessages(contractId, user) {
  try {
    const messages = await apiCall(`project.php?action=contracts/${contractId}/messages`);
    const list = qs('contract-message-list');
    list.innerHTML = messages.map(m => {
      const isMe = m.sender_name === user.name;
      return `
        <div class="bubble ${isMe ? 'bubble-sent' : 'bubble-recv'}">
          <div class="bubble-meta">${escapeHtml(m.sender_name)} · ${m.sent_at.split(' ')[1]}</div>
          <div>${escapeHtml(m.message)}</div>
        </div>
      `;
    }).join('') || '<div class="empty-state" style="padding:1rem;"><p>Safe-room is empty.</p></div>';
    list.scrollTop = list.scrollHeight;
  } catch (e) {
    qs('contract-message-list').innerHTML = `<p style="color:var(--danger)">${escapeHtml(e.message)}</p>`;
  }
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

function getStepperStatus(status) {
  const s1 = ['locked','in_progress','submitted','revision','complete'].includes(status) ? 'done' : (status === 'open' ? 'active' : '');
  const s2 = ['in_progress','submitted','revision','complete'].includes(status) ? 'done' : (status === 'locked' ? 'active' : '');
  const s3 = ['submitted','revision','complete'].includes(status) ? 'done' : (status === 'in_progress' ? 'active' : '');
  const s4 = ['complete'].includes(status) ? 'done' : (status === 'submitted' || status === 'revision' ? 'active' : '');
  
  return `
    <div class="stepper">
      <div class="step ${s1}">Open</div>
      <div class="step ${s2}">Locked</div>
      <div class="step ${s3}">Active</div>
      <div class="step ${s4}">Submit</div>
      <div class="step ${status === 'complete' ? 'done' : ''}">Done</div>
    </div>
  `;
}

function renderContract(contract, user) {
  const milestones = contract.milestones || [];
  const amendments = contract.amendments || [];

  const amendBlock = (user.role === 'client' || user.role === 'freelancer') ? `
    <div style="margin-top:2rem; padding: 1.5rem; background:rgba(13,13,13,0.02); border-radius: 8px;">
      <h4 style="font-family:'Playfair Display',serif; font-size:1.1rem; font-weight:700; margin-bottom: 1rem;">Scope Amendments</h4>
      <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem;">
        ${amendments.map((a) => `
          <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.85rem;">
            <div>
              <span class="badge ${a.status==='approved'?'badge-open':'badge-warning'}">#${a.id} ${a.status}</span>
              <span style="color:var(--muted); margin-left:0.5rem;">${escapeHtml(a.change_description || '').slice(0, 100)}</span>
            </div>
            ${a.status === 'pending' && Number(user.id) !== Number(a.proposed_by) ? `
              <div style="display:flex; gap:0.25rem;">
                <button class="btn-primary btn-sm" style="background:var(--success)" onclick="respondAmend(${a.id},'approved')">Approve</button>
                <button class="btn-danger btn-sm" onclick="respondAmend(${a.id},'rejected')">Reject</button>
              </div>
            ` : ''}
          </div>
        `).join('') || '<span style="color:var(--muted);font-size:0.85rem;">No active amendments</span>'}
      </div>
      <div style="display:flex; gap:0.5rem;">
        <input id="amend_desc_${contract.id}" class="sh-input" placeholder="Propose contractual change...">
        <button type="button" class="btn-secondary" onclick="proposeAmend(${contract.id})">Propose</button>
      </div>
    </div>
  ` : '';

  return `
    <article class="card" style="margin-bottom:2rem;">
      <div class="card-header" style="flex-wrap:wrap; gap: 1rem;">
        <div>
          <h3 style="font-family:'Playfair Display',serif; font-size:1.8rem; font-weight:700;">Execution #${contract.id}</h3>
          <p style="font-size:0.85rem; color:var(--muted); font-weight:600; text-transform:uppercase; letter-spacing:0.1em; margin-top:0.25rem;">
            ${contract.status} | Escrow Balance: $${parseFloat(contract.total_escrow_amount || 0).toLocaleString()}
          </p>
        </div>
        <div style="display:flex; gap:0.5rem;">
          <a class="btn-secondary" href="contract.html?contract_id=${contract.id}">Manage Safespace</a>
          <a class="btn-primary" href="escrow.html?contract_id=${contract.id}">Escrow Operations</a>
        </div>
      </div>

      <div class="card-body">
        ${amendBlock}

        <h4 style="font-family:'Playfair Display',serif; font-size:1.4rem; font-weight:700; margin-top:2.5rem; margin-bottom:1.5rem;">Milestone Operations</h4>
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
          ${milestones.map(item => `
            <div style="border: 1px solid var(--border-input); border-radius: var(--radius-card); padding: 1.5rem; background: var(--paper);">
              <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom: 1rem;">
                <div>
                  <div style="font-weight:700; font-size:1.1rem; color:var(--ink);">${item.title}</div>
                  <div style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">$${parseFloat(item.amount).toLocaleString()} | Due: ${item.due_date.split(' ')[0]}</div>
                </div>
              </div>
              
              ${getStepperStatus(item.status)}

              <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <button onclick="actionPost('escrow.php?action=lock',{milestone_id:${item.id}})" class="btn-secondary btn-sm" style="border-radius:9999px;">Lock Escrow</button>
                <button onclick="actionPost('project.php?action=milestones/start',{milestone_id:${item.id}})" class="btn-primary btn-sm" style="border-radius:9999px;">Start</button>
                <button onclick="window.location='contract.html?contract_id=${contract.id}&milestone_id=${item.id}'" class="btn-primary btn-sm" style="background:var(--gold); border-radius:9999px; color:var(--ink);">Open Details</button>
                ${user.role === 'client' && Number(contract.partial_release_pct) > 0 && item.status === 'submitted' ? `<button onclick="actionPost('escrow.php?action=partial-release',{milestone_id:${item.id}})" class="btn-primary btn-sm" style="background:#8b5cf6; border-radius:9999px;">Partial Release (${contract.partial_release_pct}%)</button>` : ''}
                <button onclick="actionPost('project.php?action=milestones/revision',{milestone_id:${item.id}})" class="btn-danger btn-sm" style="border-radius:9999px;">Request Revision</button>
                <button onclick="actionPost('project.php?action=milestones/approve',{milestone_id:${item.id}})" class="btn-primary btn-sm" style="background:var(--success); border-radius:9999px;">Approve</button>
                ${user.role === 'freelancer' ? `<button onclick="actionPost('project.php?action=milestones/confirm',{milestone_id:${item.id}})" class="btn-secondary btn-sm" style="border-radius:9999px;">Mark Complete</button>` : ''}
              </div>
            </div>
          `).join('') || '<div class="empty-state"><p>No milestones mapped.</p></div>'}
        </div>
      </div>
    </article>
  `;
}

async function renderMilestoneDetail(milestone, user) {
  const detail = document.getElementById('milestone-detail');
  if (!detail) return;
  detail.classList.remove('hidden');

  let actions = '';
  if (user.role === 'freelancer' && milestone.status === 'in_progress') {
    const checklist = await apiCall('project.php?action=contracts/qa-checklist');
    
    actions = `
      <div style="background: rgba(13,13,13,0.02); padding: 1.5rem; border-radius: 8px; margin-top: 2rem;">
        <h3 style="font-family:'Playfair Display',serif; font-weight:700; font-size:1.2rem; margin-bottom:1rem;">Mandatory QA Submission</h3>
        
        ${!milestone.qa_submission ? `
        <form id="qa-form">
          <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1.5rem;">
            ${checklist.map(item => `
              <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; cursor:pointer;">
                <input type="checkbox" name="${item.key}" required> ${item.label}
              </label>
            `).join('')}
          </div>
          <button class="btn-primary">Sign off QA Checks</button>
        </form>
        ` : `
        <div class="banner banner-success">QA Checklist signed off automatically.</div>
        `}

        <form id="deliverable-form" style="margin-top:2rem; padding-top:2rem; border-top:1px dashed var(--border-input); ${!milestone.qa_submission ? 'opacity:0.3;pointer-events:none;' : ''}">
          <h4 style="font-weight:700; margin-bottom:1rem;">Upload Artifacts</h4>
          <input type="file" name="file" required class="sh-input" style="background:#fff; margin-bottom:1rem;">
          <button class="btn-primary">Transmit Deliverable</button>
        </form>
      </div>
    `;
  }

  detail.innerHTML = `
    <div class="card" style="margin-bottom: 2rem; border-color: var(--gold);">
      <div class="card-body">
        <div style="display:flex; justify-content:space-between; align-items:start;">
          <div>
            <h2 style="font-family:'Playfair Display',serif; font-size:1.8rem; font-weight:700;">Task: ${milestone.title}</h2>
            <p style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">Map target ID: ${milestone.id}</p>
          </div>
          <span class="badge badge-gold">${milestone.status}</span>
        </div>
        ${actions}
      </div>
    </div>
  `;

  document.getElementById('qa-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.textContent = 'Verifying...';
    try {
      const formData = new FormData(e.target);
      const checklist = {};
      formData.forEach((value, key) => checklist[key] = true);
      await apiCall('project.php?action=contracts/qa-checklist/submit', 'POST', {
        milestone_id: milestone.id,
        checklist
      });
      location.reload();
    } catch(err) {
      alert(err.message);
      btn.textContent = 'Sign off QA Checks';
    }
  });

  document.getElementById('deliverable-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button');
    btn.textContent = 'Uploading...';
    try {
      const formData = new FormData(e.target);
      formData.append('milestone_id', milestone.id);
      await apiCall('project.php?action=milestones/submit', 'POST', formData);
      location.reload();
    } catch(err) {
      alert(err.message);
      btn.textContent = 'Transmit Deliverable';
    }
  });
}

async function proposeAmend(contractId) {
  const el = document.getElementById(`amend_desc_${contractId}`);
  const change_description = (el && el.value) || '';
  if (!change_description.trim()) { alert('Enter a change description'); return; }
  try {
    document.body.style.opacity = '0.5';
    await apiCall('project.php?action=contracts/amend', 'POST', { contract_id: contractId, change_description });
    location.reload();
  } catch (error) { document.body.style.opacity = '1'; alert(error.message); }
}

async function respondAmend(amendmentId, response) {
  try {
    document.body.style.opacity = '0.5';
    await apiCall('project.php?action=contracts/amend/respond', 'POST', { amendment_id: amendmentId, response });
    location.reload();
  } catch (error) { document.body.style.opacity = '1'; alert(error.message); }
}

async function actionPost(endpoint, body) {
  try {
    document.body.style.opacity = '0.5';
    await apiCall(endpoint, 'POST', body);
    location.reload();
  } catch (error) {
    document.body.style.opacity = '1';
    alert(error.message);
  }
}

window.proposeAmend = proposeAmend;
window.respondAmend = respondAmend;
window.actionPost = actionPost;
