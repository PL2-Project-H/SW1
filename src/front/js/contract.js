document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const params = new URLSearchParams(location.search);
  const contractId = params.get('contract_id');
  const milestoneId = params.get('milestone_id');

  if (milestoneId) {
    try {
      const milestone = await apiCall(`project.php?action=milestones/${milestoneId}`);
      // Use a small timeout to ensure DOM is fully ready for manipulation if needed, 
      // although DOMContentLoaded should be enough.
      setTimeout(() => renderMilestoneDetail(milestone, user), 50);
    } catch (e) {
      console.error('Failed to load milestone detail', e);
    }
  }

  const contracts = contractId ? [await apiCall(`project.php?action=contracts/${contractId}`)] : await apiCall('project.php?action=contracts/active');
  qs('contract-list').innerHTML = contracts.map((contract) => renderContract(contract, user)).join('') || '<p class="text-slate-500">No active contracts.</p>';

  qs('milestone-build-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    await apiCall('project.php?action=contracts/milestones/build', 'POST', {
      contract_id: qs('build_contract_id').value,
      milestones: JSON.parse(qs('milestones_json').value || '[]')
    });
    location.reload();
  });
});

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
  const ndaActions = contract.status === 'pending_nda' ? `
    <div class="mt-4 flex flex-wrap gap-2">
      ${user.role === 'client' ? `<button onclick="actionPost('client.php?action=contracts/nda/sign',{job_id:${contract.job_id}})" class="rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white">Client Sign NDA</button>` : ''}
      ${user.role === 'freelancer' ? `<button onclick="actionPost('project.php?action=contracts/nda/sign',{job_id:${contract.job_id}})" class="rounded-lg bg-indigo-800 px-3 py-2 text-sm text-white">Freelancer Sign NDA</button>` : ''}
    </div>
  ` : '';
  return `<section class="glass rounded-3xl border p-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-2xl font-semibold">Contract #${contract.id}</h3>
        <p class="text-sm text-slate-500">${contract.status}</p>
      </div>
      <a class="rounded-xl bg-slate-900 px-4 py-2 text-white" href="escrow.html?contract_id=${contract.id}">Escrow</a>
    </div>
    ${ndaActions}
    <div class="mt-5 grid gap-4">
      ${milestones.map((item) => `<div class="rounded-2xl border p-4">
        <div class="flex items-center justify-between"><strong>${item.title}</strong><span class="badge badge-info">${item.status}</span></div>
        <div class="mt-2 text-sm text-slate-500">Amount: $${item.amount} | Due: ${item.due_date}</div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button onclick="actionPost('escrow.php?action=lock',{milestone_id:${item.id}})" class="rounded-lg bg-amber-500 px-3 py-2 text-sm text-white">Lock Escrow</button>
          <button onclick="actionPost('project.php?action=milestones/start',{milestone_id:${item.id}})" class="rounded-lg bg-sky-600 px-3 py-2 text-sm text-white">Start</button>
          <button onclick="window.location='contract.html?contract_id=${contract.id}&milestone_id=${item.id}'" class="rounded-lg bg-slate-700 px-3 py-2 text-sm text-white">Open</button>
          <button onclick="actionPost('project.php?action=milestones/revision',{milestone_id:${item.id}})" class="rounded-lg bg-rose-500 px-3 py-2 text-sm text-white">Revision</button>
          <button onclick="actionPost('project.php?action=milestones/approve',{milestone_id:${item.id}})" class="rounded-lg bg-emerald-600 px-3 py-2 text-sm text-white">Approve</button>
          ${user.role === 'freelancer' ? `<button onclick="actionPost('project.php?action=milestones/confirm',{milestone_id:${item.id}})" class="rounded-lg bg-emerald-800 px-3 py-2 text-sm text-white">Confirm Complete</button>` : ''}
        </div>
      </div>`).join('')}
    </div>
  </section>`;
}

async function actionPost(endpoint, body) {
  try {
    await apiCall(endpoint, 'POST', body);
    location.reload();
  } catch (error) {
    alert(error.message);
  }
}
