document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const params = new URLSearchParams(location.search);
  const contractId = params.get('contract_id');
  const milestoneId = params.get('milestone_id');

  if (milestoneId) {
    try {
      const milestone = await apiCall(`project.php?action=milestones/${milestoneId}`);
      await renderMilestoneDetail(milestone, user);
      // Show the top-level QA checklist panel for freelancers with in-progress milestones
      if (user.role === 'freelancer' && milestone.status === 'in_progress') {
        const qaWrap = qs('qa-checklist-wrap');
        if (qaWrap && !milestone.qa_submission) {
          qaWrap.classList.remove('hidden');
        }
      }
    } catch (e) {
      console.error('Failed to load milestone detail', e);
    }
  }

  const contracts = contractId ? [await apiCall(`project.php?action=contracts/${contractId}`)] : await apiCall('project.php?action=contracts/active');
  qs('contract-list').innerHTML = contracts.map((contract) => renderContract(contract, user)).join('') || '<p class="text-slate-500">No active contracts.</p>';

  if (contractId) {
    const activeContract = contracts[0];
    
    // NDA Logic
    if (activeContract.status === 'pending_nda') {
      const ndaWrap = qs('nda-signing-wrap');
      const ndaContent = qs('nda-content');
      if (ndaWrap && ndaContent) {
        ndaWrap.classList.remove('hidden');
        ndaContent.textContent = activeContract.nda_content || 'Please review the NDA agreement for this project.';
        qs('sign-nda-btn').onclick = async () => {
          try {
            const endpoint = user.role === 'client' ? 'client.php?action=contracts/nda/sign' : 'project.php?action=contracts/nda/sign';
            await apiCall(endpoint, 'POST', { job_id: activeContract.job_id });
            alert('NDA Signed.');
            location.reload();
          } catch (err) { alert(err.message); }
        };
      }
    }

    if (user.role !== 'admin') {
      const wrap = qs('contract-messages-wrap');
      if (wrap) {
        wrap.classList.remove('hidden');
        await loadContractMessages(contractId);
        qs('contract-message-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const text = qs('contract_message_input').value.trim();
          if (!text) return;
          await apiCall('project.php?action=contracts/message', 'POST', { contract_id: Number(contractId), message: text });
          qs('contract_message_input').value = '';
          await loadContractMessages(contractId);
        });
      }
    }

    // Pre-fill milestone builder with current contract_id
    const buildInput = qs('build_contract_id');
    if (buildInput && !buildInput.value) {
      buildInput.value = contractId;
    }
  }

  // Wire up top-level QA checklist form (shown when freelancer opens a milestone view)
  qs('qa-checklist-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const params2 = new URLSearchParams(location.search);
    const mId = params2.get('milestone_id');
    if (!mId) { alert('No milestone selected.'); return; }
    const formData = new FormData(e.target);
    const checklist = {};
    formData.forEach((value, key) => { checklist[key] = true; });
    try {
      await apiCall('project.php?action=contracts/qa-checklist/submit', 'POST', { milestone_id: Number(mId), checklist });
      qs('qa-checklist-wrap').classList.add('hidden');
      alert('QA Checklist submitted! You may now upload your deliverable.');
    } catch (err) { alert(err.message); }
  });

  document.getElementById('add-milestone-btn')?.addEventListener('click', () => addMilestoneRow());

  qs('milestone-build-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const contractIdInput = qs('build_contract_id').value;
    if (!contractIdInput) { alert('Enter a contract ID'); return; }
    const rows = Array.from(document.querySelectorAll('.milestone-row'));
    if (rows.length === 0) { alert('Add at least one milestone'); return; }
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
      alert('Milestones saved.');
      location.reload();
    } catch (error) {
      alert(error.message);
    }
  });
});

function addMilestoneRow(data = {}) {
  const container = document.getElementById('milestone-rows');
  const idx = container.querySelectorAll('.milestone-row').length;
  const row = document.createElement('div');
  row.className = 'milestone-row glass rounded-2xl border p-4 grid gap-3 md:grid-cols-3';
  row.innerHTML = `
    <div>
      <label class="block text-xs text-slate-500 mb-1">Title</label>
      <input name="title" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Milestone title" value="${data.title || ''}" required>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Amount ($)</label>
      <input name="amount" type="number" step="0.01" min="0" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="500.00" value="${data.amount || ''}" required>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Due Date</label>
      <input name="due_date" type="datetime-local" class="w-full rounded-xl border px-3 py-2 text-sm" value="${data.due_date ? data.due_date.replace(' ', 'T').substring(0,16) : ''}" required>
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Order Index</label>
      <input name="order_index" type="number" min="1" class="w-full rounded-xl border px-3 py-2 text-sm" value="${data.order_index || (idx + 1)}">
    </div>
    <div>
      <label class="block text-xs text-slate-500 mb-1">Depends on Milestone # (optional)</label>
      <input name="dependency_milestone_id" type="number" min="1" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Leave blank if first" value="${data.dependency_milestone_id || ''}">
    </div>
    <div class="flex items-end">
      <button type="button" class="remove-milestone-btn w-full rounded-xl bg-rose-500 px-3 py-2 text-white text-sm">Remove</button>
    </div>
  `;
  row.querySelector('.remove-milestone-btn').addEventListener('click', () => row.remove());
  container.appendChild(row);
}

async function loadContractMessages(contractId) {
  try {
    const messages = await apiCall(`project.php?action=contracts/${contractId}/messages`);
    qs('contract-message-list').innerHTML = messages
      .map((m) => `<div class="rounded-xl border p-2"><span class="font-medium">${escapeHtml(m.sender_name)}</span> <span class="text-slate-500">${m.sent_at}</span><div>${escapeHtml(m.message)}</div></div>`)
      .join('') || '<p class="text-slate-500">No messages yet.</p>';
  } catch (e) {
    qs('contract-message-list').innerHTML = `<p class="text-rose-600">${escapeHtml(e.message)}</p>`;
  }
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

async function renderMilestoneDetail(milestone, user) {
  const detail = document.getElementById('milestone-detail');
  if (!detail) {
    console.error('milestone-detail element not found');
    return;
  }
  detail.classList.remove('hidden');

  let actions = '';
  if (user.role === 'freelancer' && milestone.status === 'in_progress') {
    const checklist = await apiCall('project.php?action=contracts/qa-checklist');
    actions = `
      <form id="qa-form" class="glass rounded-3xl border p-6 space-y-4">
        <h3 class="text-xl font-semibold">QA Checklist</h3>
        <div class="space-y-2">
          ${checklist.map(item => `
            <label class="flex items-center gap-2">
              <input type="checkbox" name="${item.key}" required> ${item.label}
            </label>
          `).join('')}
        </div>
        <button class="rounded-xl bg-indigo-600 px-4 py-2 text-white">Submit QA Checklist</button>
      </form>
      <form id="deliverable-form" class="mt-6 glass rounded-3xl border p-6 space-y-4">
        <h3 class="text-xl font-semibold">Submit Deliverable</h3>
        <input type="file" name="file" required class="w-full">
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-white">Upload & Submit</button>
      </form>
    `;
  }

  detail.innerHTML = `
    <div class="glass rounded-3xl border p-6">
      <h2 class="text-2xl font-semibold">${milestone.title}</h2>
      <p class="text-sm text-slate-500">Status: ${milestone.status} | Amount: $${milestone.amount}</p>
      <div class="mt-4">${actions}</div>
    </div>
  `;

  document.getElementById('qa-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const checklist = {};
    formData.forEach((value, key) => checklist[key] = true);
    await apiCall('project.php?action=contracts/qa-checklist/submit', 'POST', {
      milestone_id: milestone.id,
      checklist
    });
    alert('QA Checklist submitted!');
  });

  document.getElementById('deliverable-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('milestone_id', milestone.id);
    await apiCall('project.php?action=milestones/submit', 'POST', formData);
    location.reload();
  });
}

function renderContract(contract, user) {
  const milestones = contract.milestones || [];
  const amendments = contract.amendments || [];
  const ndaActions = contract.status === 'pending_nda' ? `
    <div class="mt-4 flex flex-wrap gap-2">
      ${user.role === 'client' ? `<button onclick="actionPost('client.php?action=contracts/nda/sign',{job_id:${contract.job_id}})" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white">Client Sign NDA</button>` : ''}
      ${user.role === 'freelancer' ? `<button onclick="actionPost('project.php?action=contracts/nda/sign',{job_id:${contract.job_id}})" class="rounded-lg bg-indigo-800 px-3 py-2 text-sm text-white">Freelancer Sign NDA</button>` : ''}
    </div>
  ` : '';

  const amendBlock =
    user.role === 'client' || user.role === 'freelancer'
      ? `
    <div class="mt-6 rounded-2xl border border-dashed p-4">
      <h4 class="font-semibold">Scope amendments</h4>
      <ul class="mt-2 space-y-2 text-sm">${amendments.map((a) => `<li>#${a.id} ${a.status} — ${escapeHtml(a.change_description || '').slice(0, 120)}${(a.change_description || '').length > 120 ? '…' : ''}
        ${a.status === 'pending' && user.id && Number(user.id) !== Number(a.proposed_by) ? `<button type="button" class="ml-2 rounded bg-emerald-600 px-2 py-1 text-xs text-white" onclick="respondAmend(${a.id},'approved')">Approve</button><button type="button" class="ml-1 rounded bg-rose-600 px-2 py-1 text-xs text-white" onclick="respondAmend(${a.id},'rejected')">Reject</button>` : ''}
      </li>`).join('') || '<li class="text-slate-500">None yet.</li>'}</ul>
      <div class="mt-3 flex gap-2">
        <input id="amend_desc_${contract.id}" class="flex-1 rounded-xl border px-3 py-2 text-sm" placeholder="Describe proposed change">
        <button type="button" class="rounded-xl bg-slate-800 px-3 py-2 text-sm text-white" onclick="proposeAmend(${contract.id})">Propose</button>
      </div>
    </div>`
      : '';

  return `<section class="glass rounded-3xl border p-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-2xl font-semibold">Contract #${contract.id}</h3>
        <p class="text-sm text-slate-500">${contract.status} | ${contract.currency || 'USD'} | Partial release: ${contract.partial_release_pct || 0}%</p>
      </div>
      <div class="flex gap-2">
        <a class="rounded-xl bg-slate-700 px-4 py-2 text-white" href="contract.html?contract_id=${contract.id}">Messages</a>
        <a class="rounded-xl bg-slate-900 px-4 py-2 text-white" href="escrow.html?contract_id=${contract.id}">Escrow</a>
      </div>
    </div>
    ${ndaActions}
    ${amendBlock}
    <div class="mt-5 grid gap-4">
      ${milestones.map((item) => `<div class="rounded-2xl border p-4">
        <div class="flex items-center justify-between"><strong>${item.title}</strong><span class="badge badge-info">${item.status}</span></div>
        <div class="mt-2 text-sm text-slate-500">Amount: $${item.amount} | Due: ${item.due_date}</div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button onclick="actionPost('escrow.php?action=lock',{milestone_id:${item.id}})" class="rounded-lg bg-amber-500 px-3 py-2 text-sm text-white">Lock Escrow</button>
          <button onclick="actionPost('project.php?action=milestones/start',{milestone_id:${item.id}})" class="rounded-lg bg-sky-600 px-3 py-2 text-sm text-white">Start</button>
          <button onclick="window.location='contract.html?contract_id=${contract.id}&milestone_id=${item.id}'" class="rounded-lg bg-slate-700 px-3 py-2 text-sm text-white">Open</button>
          ${user.role === 'client' && Number(contract.partial_release_pct) > 0 && item.status === 'submitted' ? `<button onclick="actionPost('escrow.php?action=partial-release',{milestone_id:${item.id}})" class="rounded-lg bg-violet-600 px-3 py-2 text-sm text-white">Partial release (${contract.partial_release_pct}%)</button>` : ''}
          <button onclick="actionPost('project.php?action=milestones/revision',{milestone_id:${item.id}})" class="rounded-lg bg-rose-500 px-3 py-2 text-sm text-white">Revision</button>
          <button onclick="actionPost('project.php?action=milestones/approve',{milestone_id:${item.id}})" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white">Approve</button>
          ${user.role === 'freelancer' ? `<button onclick="actionPost('project.php?action=milestones/confirm',{milestone_id:${item.id}})" class="rounded-lg bg-emerald-800 px-3 py-2 text-sm text-white">Confirm Complete</button>` : ''}
        </div>
      </div>`).join('')}
    </div>
  </section>`;
}

async function proposeAmend(contractId) {
  const el = document.getElementById(`amend_desc_${contractId}`);
  const change_description = (el && el.value) || '';
  if (!change_description.trim()) {
    alert('Enter a change description');
    return;
  }
  try {
    await apiCall('project.php?action=contracts/amend', 'POST', { contract_id: contractId, change_description });
    location.reload();
  } catch (error) {
    alert(error.message);
  }
}

async function respondAmend(amendmentId, response) {
  try {
    await apiCall('project.php?action=contracts/amend/respond', 'POST', { amendment_id: amendmentId, response });
    location.reload();
  } catch (error) {
    alert(error.message);
  }
}

async function actionPost(endpoint, body) {
  try {
    await apiCall(endpoint, 'POST', body);
    location.reload();
  } catch (error) {
    alert(error.message);
  }
}

window.proposeAmend = proposeAmend;
window.respondAmend = respondAmend;
window.actionPost = actionPost;
