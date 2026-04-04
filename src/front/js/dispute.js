document.addEventListener('DOMContentLoaded', async () => {
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
    const [dispute, messages] = await Promise.all([
      apiCall(`dispute.php?action=${disputeId}`),
      apiCall(`dispute.php?action=messages/${disputeId}`)
    ]);
    qs('dispute-detail').innerHTML = `<div class="glass rounded-3xl border p-6"><h2 class="text-2xl font-semibold">Dispute #${dispute.id}</h2><p class="mt-2 text-slate-600">${dispute.reason}</p><p class="mt-2 text-sm text-slate-500">${dispute.status}</p></div>`;
    qs('message-list').innerHTML = messages.map((msg) => `<div class="rounded-2xl border p-3 text-sm">${msg.message}</div>`).join('');
    const chatForm = qs('chat-form');
    chatForm.classList.remove('hidden');
    chatForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      await apiCall('dispute.php?action=message', 'POST', { dispute_id: disputeId, message: qs('chat_message').value });
      location.reload();
    });
  }

  qs('dispute-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = Object.fromEntries(new FormData(event.target).entries());
    const response = await apiCall('dispute.php?action=file', 'POST', payload);
    location.href = `dispute.html?dispute_id=${response.id}`;
  });
});
