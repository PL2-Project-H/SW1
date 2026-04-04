document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const contractId = new URLSearchParams(location.search).get('contract_id');
  if (!contractId) return;
  const [balance, ledger, fees, tax, contract] = await Promise.all([
    apiCall(`escrow.php?action=balance&contract_id=${contractId}`),
    apiCall(`escrow.php?action=ledger&contract_id=${contractId}`),
    apiCall(`escrow.php?action=fees&contract_id=${contractId}`),
    apiCall(`escrow.php?action=tax&contract_id=${contractId}`),
    apiCall(`project.php?action=contracts/${contractId}`)
  ]);
  qs('balance-box').innerHTML = `<div class="metric-card glass rounded-3xl p-6">Pending: $${balance.pending_balance} | Cleared: $${balance.cleared_balance}</div>`;
  qs('fee-box').innerHTML = `<div class="metric-card glass rounded-3xl p-6">Lifetime Value: $${fees.lifetime_value} | Fee: ${fees.fee_percentage}%</div>`;
  qs('tax-box').innerHTML = `<div class="metric-card glass rounded-3xl p-6">Net: $${tax.freelancer_net} | Tax on Fee: $${tax.tax_on_fee}</div>`;
  const pct = Number(contract.partial_release_pct || 0);
  const milestones = contract.milestones || [];
  if (user.role === 'client' && pct > 0 && milestones.length) {
    qs('escrow-milestones').innerHTML = `
      <div class="glass rounded-3xl border p-6">
        <h3 class="text-lg font-semibold">Partial release (${pct}%)</h3>
        <p class="mt-1 text-sm text-slate-600">After a milestone is submitted, trigger the configured partial release.</p>
        <div class="mt-4 flex flex-wrap gap-2">
          ${milestones
            .filter((m) => m.status === 'submitted')
            .map(
              (m) =>
                `<button onclick="escrowPartial(${m.id})" class="rounded-lg bg-violet-600 px-3 py-2 text-sm text-white">Milestone #${m.id} — ${m.title}</button>`
            )
            .join('') || '<span class="text-slate-500 text-sm">No milestones in submitted state.</span>'}
        </div>
      </div>`;
  }
  qs('ledger-box').innerHTML = Object.entries(ledger).map(([currency, group]) => `
    <div class="glass rounded-3xl border p-5">
      <h3 class="text-xl font-semibold">${currency}</h3>
      <p class="text-sm text-slate-500">Total ${group.total}</p>
      <div class="mt-4 space-y-2">${group.transactions.map((tx) => `<div class="rounded-xl border p-3 text-sm">${tx.type} | ${tx.amount} ${tx.currency} | USD eq ${tx.usd_equivalent}</div>`).join('')}</div>
    </div>
  `).join('');
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
