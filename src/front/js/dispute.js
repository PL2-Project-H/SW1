document.addEventListener('DOMContentLoaded', async () => {
  try {
    const user = await checkSession();
    if (!user) return;
    
    const activeDisputeId = new URLSearchParams(location.search).get('dispute_id');
    const modal = qs('dispute-modal');
    qs('trigger-file-dispute')?.addEventListener('click', () => modal.classList.add('show'));
    qs('close-modal')?.addEventListener('click', () => modal.classList.remove('show'));

    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('show');
    });

    async function loadDisputeList() {
      const listEl = qs('dispute-list');
      if (!listEl) return;
      showLoading(listEl);
      try {
        const rows = await apiCall('dispute.php?action=mine');
        stopLoading(listEl);
        
        listEl.innerHTML = rows.map(d => `
          <a href="dispute.html?dispute_id=${d.id}" class="card" style="text-decoration:none; transition: border-color 0.2s; ${d.id == activeDisputeId ? 'border-color:var(--gold); background:rgba(201,168,76,0.05);' : ''}">
            <div class="card-body" style="padding: 1rem;">
              <div style="display:flex; justify-content:space-between; align-items:start;">
                <div>
                  <div style="font-weight:700; color:var(--ink);">Case #${d.id}</div>
                  <div style="font-size:0.75rem; color:var(--muted); margin-top:0.25rem;">Contract: ID ${d.contract_id}</div>
                </div>
                <span class="badge ${d.status === 'open' ? 'badge-danger' : 'badge-open'}">${d.status}</span>
              </div>
            </div>
          </a>
        `).join('') || '<div class="empty-state" style="padding: 1rem;"><p>No disputes filed.</p></div>';
      } catch (e) {
        stopLoading(listEl);
        listEl.innerHTML = `<p style="color:var(--danger); font-size:0.85rem;">${e.message}</p>`;
      }
    }

    await loadDisputeList();

    if (activeDisputeId) {
      const detailEl = qs('dispute-detail');
      showLoading(detailEl);
      
      try {
        const [dispute, messages] = await Promise.all([
          apiCall(`dispute.php?action=${activeDisputeId}`),
          apiCall(`dispute.php?action=messages/${activeDisputeId}`)
        ]);
        stopLoading(detailEl);

        detailEl.innerHTML = `
          <div class="card">
            <div class="card-header" style="background:var(--ink); color:var(--paper); border-bottom: none;">
              <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                <h2 style="font-family:'Playfair Display',serif; font-size:1.4rem; font-weight:700;">Case #${dispute.id} Safe-Room</h2>
                <span class="badge ${dispute.status === 'open' ? 'badge-danger' : 'badge-open'}" style="background:var(--paper); color:var(--ink); border:1px solid var(--paper);">${dispute.status}</span>
              </div>
            </div>
            
            <div class="card-body" style="background: rgba(13,13,13,0.02); border-bottom: 1px solid var(--border-input);">
              <h4 style="font-size:0.7rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:var(--muted); margin-bottom:0.4rem;">Filing Reason</h4>
              <p style="font-size:0.9rem; color:var(--ink); line-height:1.6;">${escapeHtml(dispute.reason || '')}</p>
            </div>

            <div class="card-body" style="padding: 1rem 1.5rem;">
              <div id="message-list" class="chat-window">
                ${(messages || []).map(msg => {
                  
                  const isSentByMe = String(msg.sender_id) === String(user.id);
                  let timeStr = '';
                  if (msg.created_at) timeStr = msg.created_at.split(' ')[1] || msg.created_at;
                  else if (msg.sent_at) timeStr = msg.sent_at.split(' ')[1] || msg.sent_at;
                  
                  return `
                    <div class="bubble ${isSentByMe ? 'bubble-sent' : 'bubble-recv'}">
                      <div class="bubble-meta">User ID ${msg.sender_id} ${timeStr ? '· '+timeStr : ''}</div>
                      <div>${escapeHtml(msg.message || '')}</div>
                    </div>
                  `;
                }).join('') || '<div class="empty-state" style="padding:2rem;"><p>Safe-room communication channel open.</p></div>'}
              </div>
            </div>

            <div class="card-footer" style="padding: 1rem; border-top: 1px solid var(--border-input);">
              <form id="chat-form" style="display:flex; gap:0.5rem;">
                <input id="chat_message" type="text" class="sh-input" placeholder="Type message into evidence track..." required style="border-radius:9999px;">
                <button type="submit" class="btn-primary" style="border-radius:9999px; padding: 0.5rem 1.5rem;">Submit</button>
              </form>
            </div>
          </div>
        `;

        const chatWindow = qs('message-list');
        if (chatWindow) chatWindow.scrollTop = chatWindow.scrollHeight;

        qs('chat-form')?.addEventListener('submit', async (event) => {
          event.preventDefault();
          const messageInput = qs('chat_message');
          const message = messageInput?.value.trim();
          if (!message) return;

          messageInput.disabled = true;
          try {
            await apiCall('dispute.php?action=message', 'POST', { dispute_id: activeDisputeId, message: message });
            location.reload();
          } catch (error) {
            messageInput.disabled = false;
            showError(qs('chat-form'), error.message);
          }
        });

      } catch (err) {
        stopLoading(detailEl);
        detailEl.innerHTML = `<div class="empty-state"><p>Could not load dispute: ${err.message}</p></div>`;
      }
    }

    qs('dispute-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const errBox = qs('form-err');
      const payload = Object.fromEntries(new FormData(event.target).entries());
      if (!payload.contract_id || !payload.reason) return;
      
      const btn = event.target.querySelector('button');
      btn.textContent = 'Processing...';

      try {
        const response = await apiCall('dispute.php?action=file', 'POST', payload);
        location.href = `dispute.html?dispute_id=${response.id}`;
      } catch (e) {
        btn.textContent = 'Construct Dispute Case';
        showError(errBox, e.message);
      }
    });

  } catch (e) {
    console.error('Dispute page error:', e);
  }
});

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
