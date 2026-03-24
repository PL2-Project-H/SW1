document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const params = new URLSearchParams(location.search);
  const contractId = params.get('contract_id');
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
