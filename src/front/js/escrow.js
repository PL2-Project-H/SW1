document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const contractId = new URLSearchParams(location.search).get('contract_id');
  
  if (!contractId) {
    document.body.innerHTML += '<div class="shell px-6 py-6"><p class="text-rose-600 mt-20 text-center text-lg">No contract selected. <a href="contract.html" class="underline">Go to Contracts</a></p></div>';
    return;
  }

  try {
    const [balance, ledger, fees, tax, contract] = await Promise.allSettled([
      apiCall(`escrow.php?action=balance&contract_id=${contractId}`),
      apiCall(`escrow.php?action=ledger&contract_id=${contractId}`),
      apiCall(`escrow.php?action=fees&contract_id=${contractId}`),
      apiCall(`escrow.php?action=tax&contract_id=${contractId}`),
      apiCall(`project.php?action=contracts/${contractId}`)
    ]);

    const resolved = (result) => result.status === 'fulfilled' ? result.value : null;

    const balanceData = resolved(balance);
    const ledgerData = resolved(ledger);
    const feesData = resolved(fees);
    const taxData = resolved(tax);
    const contractData = resolved(contract);

    qs('balance-box').innerHTML = balanceData
      ? `<div class="metric-card glass rounded-3xl p-6"><p class="text-sm text-slate-500">Pending Payout</p><p class="text-2xl font-semibold mt-2">$${balanceData.pending_balance}</p><p class="text-sm text-slate-500 mt-2">Cleared: $${balanceData.cleared_balance}</p></div>`
      : `<div class="metric-card glass rounded-3xl p-6 text-rose-600">Balance unavailable</div>`;

    qs('fee-box').innerHTML = feesData
      ? `<div class="metric-card glass rounded-3xl p-6"><p class="text-sm text-slate-500">Platform Fee</p><p class="text-2xl font-semibold mt-2">${feesData.fee_percentage}%</p><p class="text-sm text-slate-500 mt-2">Lifetime value: $${feesData.lifetime_value}</p></div>`
      : `<div class="metric-card glass rounded-3xl p-6 text-rose-600">Fee data unavailable</div>`;

    qs('tax-box').innerHTML = taxData
      ? `<div class="metric-card glass rounded-3xl p-6"><p class="text-sm text-slate-500">Freelancer Net</p><p class="text-2xl font-semibold mt-2">$${taxData.freelancer_net}</p><p class="text-sm text-slate-500 mt-2">Tax on fee: $${taxData.tax_on_fee}</p></div>`
      : `<div class="metric-card glass rounded-3xl p-6 text-rose-600">Tax data unavailable</div>`;

    if (contractData) {
      const pct = Number(contractData.partial_release_pct || 0);
      const milestones = contractData.milestones || [];
      const escrowMilestonesEl = qs('escrow-milestones');
      if (user.role === 'client' && pct > 0 && milestones.length) {
        escrowMilestonesEl.innerHTML = `
          <div class="glass rounded-3xl border p-6">
            <h3 class="text-lg font-semibold">Partial Release (${pct}%)</h3>
            <div class="mt-4 flex flex-wrap gap-2">
              ${milestones.filter(m => m.status === 'submitted').map(m =>
                `<button onclick="escrowPartial(${m.id})" class="rounded-lg bg-violet-600 px-3 py-2 text-sm text-white">Milestone #${m.id} — ${m.title}</button>`
              ).join('') || '<span class="text-slate-500 text-sm">No submitted milestones.</span>'}
            </div>
          </div>`;
      }
    }

    if (ledgerData) {
      qs('ledger-box').innerHTML = Object.entries(ledgerData).map(([currency, group]) => `
        <div class="glass rounded-3xl border p-5">
          <h3 class="text-xl font-semibold">${currency} Ledger</h3>
          <p class="text-sm text-slate-500">Total: ${group.total} ${currency}</p>
          <div class="mt-4 space-y-2">
            ${group.transactions.map(tx => `
              <div class="rounded-xl border p-3 text-sm flex justify-between">
                <span class="font-medium capitalize">${tx.type}</span>
                <span>${tx.amount} ${tx.currency}</span>
                <span class="text-slate-500">≈ $${tx.usd_equivalent} USD</span>
                <span class="badge ${tx.status === 'cleared' ? 'badge-open' : 'badge-warning'}">${tx.status}</span>
              </div>`).join('')}
          </div>
        </div>
      `).join('') || '<p class="text-slate-500">No transactions yet.</p>';
    }

  } catch (err) {
    document.getElementById('balance-box').innerHTML = `<div class="glass rounded-3xl p-6 text-rose-600">Failed to load escrow data: ${err.message}</div>`;
  }
});

async function escrowPartial(milestoneId) {
  try {
    await apiCall('escrow.php?action=partial-release', 'POST', { milestone_id: milestoneId });
    location.reload();
  } catch (e) {
    alert(e.message);
  }
}

window.escrowPartial = escrowPartial;
