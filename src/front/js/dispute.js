document.addEventListener('DOMContentLoaded', async () => {
  await checkSession();
  const disputeId = new URLSearchParams(location.search).get('dispute_id');
  if (disputeId) {
    const [dispute, messages] = await Promise.all([
      apiCall(`dispute.php?action=${disputeId}`),
      apiCall(`dispute.php?action=messages/${disputeId}`)
    ]);
    qs('dispute-detail').innerHTML = `<div class="glass rounded-3xl border p-6"><h2 class="text-2xl font-semibold">Dispute #${dispute.id}</h2><p class="mt-2 text-slate-600">${dispute.reason}</p><p class="mt-2 text-sm text-slate-500">${dispute.status}</p></div>`;
    qs('message-list').innerHTML = messages.map((msg) => `<div class="rounded-2xl border p-3 text-sm">${msg.message}</div>`).join('');
    qs('chat-form').addEventListener('submit', async (event) => {
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
