document.addEventListener('DOMContentLoaded', async () => {
  await checkSession();
  const contractId = new URLSearchParams(location.search).get('contract_id');
  if (!contractId) return;
  const [balance, ledger, fees, tax] = await Promise.all([
    apiCall(`escrow.php?action=balance&contract_id=${contractId}`),
    apiCall(`escrow.php?action=ledger&contract_id=${contractId}`),
    apiCall(`escrow.php?action=fees&contract_id=${contractId}`),
    apiCall(`escrow.php?action=tax&contract_id=${contractId}`)
  ]);
  qs('balance-box').innerHTML = `<div class="metric-card glass rounded-3xl p-6">Pending: $${balance.pending_balance} | Cleared: $${balance.cleared_balance}</div>`;
  qs('fee-box').innerHTML = `<div class="metric-card glass rounded-3xl p-6">Lifetime Value: $${fees.lifetime_value} | Fee: ${fees.fee_percentage}%</div>`;
  qs('tax-box').innerHTML = `<div class="metric-card glass rounded-3xl p-6">Net: $${tax.freelancer_net} | Tax on Fee: $${tax.tax_on_fee}</div>`;
  qs('ledger-box').innerHTML = Object.entries(ledger).map(([currency, group]) => `
    <div class="glass rounded-3xl border p-5">
      <h3 class="text-xl font-semibold">${currency}</h3>
      <p class="text-sm text-slate-500">Total ${group.total}</p>
      <div class="mt-4 space-y-2">${group.transactions.map((tx) => `<div class="rounded-xl border p-3 text-sm">${tx.type} | ${tx.amount} ${tx.currency} | USD eq ${tx.usd_equivalent}</div>`).join('')}</div>
    </div>
  `).join('');
});
