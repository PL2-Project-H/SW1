document.addEventListener('DOMContentLoaded', async () => {
  try {
    await checkSession();
    const disputeId = new URLSearchParams(location.search).get('dispute_id');

    async function loadDisputeList() {
      const listEl = qs('dispute-list');
      if (!listEl) return;
      try {
        const rows = await apiCall('dispute.php?action=mine');
        listEl.innerHTML =
          rows
            .map(
              (d) =>
                `<a href="dispute.html?dispute_id=${d.id}" class="block rounded-xl border p-3 hover:bg-slate-50">Dispute #${d.id} — ${d.status}</a>`
            )
            .join('') || '<p class="text-slate-500">No disputes yet.</p>';
      } catch (e) {
        listEl.innerHTML = `<p class="text-rose-600">${e.message}</p>`;
      }
    }

    await loadDisputeList();

    if (disputeId) {
      const detailEl = qs('dispute-detail');
      const messageListEl = qs('message-list');
      if (!detailEl || !messageListEl) return;

      try {
        const [dispute, messages] = await Promise.all([
          apiCall(`dispute.php?action=${disputeId}`),
          apiCall(`dispute.php?action=messages/${disputeId}`)
        ]);
        
        detailEl.innerHTML = `<div class="glass rounded-3xl border p-6">
          <h2 class="text-2xl font-semibold">Dispute #${dispute.id}</h2>
          <p class="mt-2 text-slate-600">${dispute.reason || ''}</p>
          <p class="mt-2 text-sm text-slate-500 font-medium uppercase tracking-wide">${dispute.status}</p>
        </div>`;
        
        messageListEl.innerHTML = (messages || []).map((msg) => `<div class="rounded-2xl border p-3 text-sm">${msg.message || ''}</div>`).join('') || '<p class="text-sm text-slate-500">No messages yet.</p>';

        const chatForm = qs('chat-form');
        if (chatForm) {
          chatForm.classList.remove('hidden');
        }
      } catch (err) {
        detailEl.innerHTML = `<div class="glass rounded-3xl border border-rose-200 p-6 text-rose-700">Could not load dispute: ${err.message}</div>`;
      }
    }

    const chatForm = qs('chat-form');
    chatForm?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const activeDisputeId = new URLSearchParams(location.search).get('dispute_id');
      if (!activeDisputeId) return;
      
      const messageInput = qs('chat_message');
      const message = messageInput?.value.trim();
      if (!message) return;

      try {
        await apiCall('dispute.php?action=message', 'POST', { dispute_id: activeDisputeId, message: message });
        const messageListEl = qs('message-list');
        if (messageListEl) {
          const newMessage = document.createElement('div');
          newMessage.className = 'rounded-2xl border p-3 text-sm';
          newMessage.textContent = message;
          messageListEl.appendChild(newMessage);
        }
        messageInput.value = '';
      } catch (error) {
        alert(error.message);
      }
    });

    qs('dispute-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = Object.fromEntries(new FormData(event.target).entries());
      if (!payload.contract_id || !payload.reason) {
        alert('Contract ID and reason are required.');
        return;
      }
      try {
        const response = await apiCall('dispute.php?action=file', 'POST', payload);
        location.href = `dispute.html?dispute_id=${response.id}`;
      } catch (e) {
        alert(e.message);
      }
    });
  } catch (e) {
    console.error('Dispute page error:', e);
    const body = document.querySelector('.shell') || document.body;
    body.innerHTML += `<div class="glass rounded-3xl border border-rose-200 p-6 mt-6 text-rose-700">Page error: ${e.message}</div>`;
  }
});
