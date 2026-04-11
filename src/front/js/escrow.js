document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  if (!user) return;
  const contractId = new URLSearchParams(location.search).get('contract_id');
  
  const statsGrid = qs('stats-grid');
  const ledgerBox = qs('ledger-box');

  if (!contractId) {
    statsGrid.innerHTML = ``;
    ledgerBox.innerHTML = `<div class="empty-state"><p>No contract context selected.</p></div>`;
    return;
  }

  showLoading(statsGrid);

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

    stopLoading(statsGrid);

    
    statsGrid.innerHTML = `
      ${balanceData ? `
        <div class="hcard">
          <div class="hcard-eyebrow">Pending Escrow</div>
          <div class="hcard-amount" style="font-size:2.2rem; margin-top:0.5rem;">$${parseFloat(balanceData.pending_balance).toLocaleString()}</div>
          <div class="hcard-footer" style="padding-top:0.75rem; border-top:1px solid var(--border);">
            <div style="font-size:0.85rem; color:var(--muted);">Cleared: $${parseFloat(balanceData.cleared_balance).toLocaleString()}</div>
          </div>
        </div>
      ` : ''}

      ${feesData ? `
        <div class="hcard">
          <div class="hcard-eyebrow">Platform Fee</div>
          <div class="hcard-amount" style="font-size:2.2rem; margin-top:0.5rem;">${feesData.fee_percentage}%</div>
          <div class="hcard-footer" style="padding-top:0.75rem; border-top:1px solid var(--border);">
            <div style="font-size:0.85rem; color:var(--muted);">Lifetime Value: $${parseFloat(feesData.lifetime_value).toLocaleString()}</div>
          </div>
        </div>
      ` : ''}

      ${taxData ? `
        <div class="hcard">
          <div class="hcard-eyebrow">Projected Freelancer Net</div>
          <div class="hcard-amount" style="font-size:2.2rem; margin-top:0.5rem;">$${parseFloat(taxData.freelancer_net).toLocaleString()}</div>
          <div class="hcard-footer" style="padding-top:0.75rem; border-top:1px solid var(--border);">
            <div style="font-size:0.85rem; color:var(--muted);">Tax on fee: $${parseFloat(taxData.tax_on_fee).toLocaleString()}</div>
          </div>
        </div>
      ` : ''}
    `;

    if (contractData) {
      const pct = Number(contractData.partial_release_pct || 0);
      const milestones = contractData.milestones || [];
      const escrowMilestonesEl = qs('escrow-milestones');
      if (user.role === 'client' && pct > 0 && milestones.length) {
        escrowMilestonesEl.innerHTML = `
          <div class="card" style="margin-bottom: 2rem;">
            <div class="card-body">
              <h3 style="font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:700; margin-bottom:1rem;">Partial Release Configuration (${pct}%)</h3>
              <div style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                ${milestones.filter(m => m.status === 'submitted').map(m =>
                  `<button onclick="escrowPartial(${m.id})" class="btn-primary" style="background:#8b5cf6;">Release Phase #${m.id}</button>`
                ).join('') || '<span style="font-size:0.85rem; color:var(--muted);">No payloads available for direct partial release.</span>'}
              </div>
            </div>
          </div>`;
      }
    }

    if (ledgerData && Object.keys(ledgerData).length > 0) {
      ledgerBox.innerHTML = Object.entries(ledgerData).map(([currency, group]) => `
        <div style="margin-bottom: 2rem;">
          <h3 style="font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:700;">${currency} Audit Trail</h3>
          <p style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">Cumulative Total: ${parseFloat(group.total).toLocaleString()} ${currency}</p>
          
          <div class="timeline">
            ${group.transactions.map(tx => `
              <div class="timeline-item ${tx.status === 'cleared' ? 'cleared' : ''}">
                <div class="card" style="padding: 1rem; border-radius: 8px;">
                  <div style="display:flex; justify-content:space-between; align-items:start;">
                    <div>
                      <div style="font-weight:700; font-size:1.1rem; text-transform:capitalize;">${tx.type}</div>
                      <div style="font-size:0.85rem; color:var(--muted); margin-top:0.25rem;">Approx. $${parseFloat(tx.usd_equivalent).toLocaleString()} USD</div>
                    </div>
                    <div style="text-align:right;">
                      <div style="font-family:'Playfair Display',serif; font-size:1.2rem; font-weight:700;">${parseFloat(tx.amount).toLocaleString()}</div>
                      <span class="badge ${tx.status === 'cleared' ? 'badge-open' : 'badge-warning'}" style="margin-top:0.25rem;">${tx.status}</span>
                    </div>
                  </div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      `).join('');
    } else {
      ledgerBox.innerHTML = `<div class="empty-state"><p>No transactions registered on this ledger.</p></div>`;
    }

  } catch (err) {
    stopLoading(statsGrid);
    statsGrid.innerHTML = `<div class="empty-state"><p>Failed to load escrow data: ${err.message}</p></div>`;
  }
});

async function escrowPartial(milestoneId) {
  try {
    document.body.style.opacity = '0.5';
    await apiCall('escrow.php?action=partial-release', 'POST', { milestone_id: milestoneId });
    location.reload();
  } catch (e) {
    document.body.style.opacity = '1';
    alert(e.message);
  }
}

window.escrowPartial = escrowPartial;
