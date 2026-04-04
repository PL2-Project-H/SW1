document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  const resultBox = qs('job-results');
  const filters = ['search_keyword', 'search_niche', 'search_budget'];

  const runSearch = debounce(async () => {
    if (user.role === 'freelancer') {
      const query = new URLSearchParams({
        niche: qs('search_niche').value,
        keyword: qs('search_keyword').value,
        max_budget: qs('search_budget').value
      });
      const rankingQuery = new URLSearchParams({
        niche: qs('search_niche').value || 'other',
        keywords: qs('search_keyword').value
      });
      const [ranked, jobs] = await Promise.all([
        apiCall(`freelancer.php?action=search&${rankingQuery.toString()}`),
        apiCall(`client.php?action=jobs/browse&${query.toString()}`)
      ]);
      resultBox.innerHTML =
        jobs
          .map((job) => renderJob(job, ranked))
          .join('') || empty();
    } else if (user.role === 'client') {
      const query = new URLSearchParams({
        niche: qs('search_niche').value,
        keyword: qs('search_keyword').value,
        max_budget: qs('search_budget').value
      });
      const jobs = await apiCall(`client.php?action=jobs/browse&${query.toString()}`);
      resultBox.innerHTML =
        jobs
          .map((job) => renderClientBrowseJob(job))
          .join('') || empty();
    } else {
      const jobs = await apiCall('client.php?action=jobs/mine');
      resultBox.innerHTML = jobs.map((job) => renderClientJob(job)).join('') || empty();
    }
  }, 300);

  filters.forEach((id) => qs(id)?.addEventListener('input', runSearch));
  runSearch();

  if (user.role === 'client') {
    qs('job-form-wrap').classList.remove('hidden');
    qs('search-panel').classList.remove('hidden');
    qs('job-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(event.target).entries());
      await apiCall('client.php?action=jobs/create', 'POST', data);
      location.reload();
    });
    qs('job-niche').addEventListener('change', toggleNicheFields);
    toggleNicheFields();
  } else {
    qs('search-panel').classList.remove('hidden');
  }
});

function renderJob(job, ranked) {
  const matches = ranked.slice(0, 3).map((row) => row.name).join(', ');
  return `<a href="job-detail.html?job_id=${job.id}" class="glass block rounded-3xl border p-5">
    <div class="flex items-center justify-between"><h3 class="text-xl font-semibold">${job.title}</h3><span class="badge badge-info">${job.niche}</span></div>
    <p class="mt-3 text-sm text-slate-600">${job.description}</p>
    <div class="mt-4 flex items-center justify-between text-sm text-slate-500"><span>$${job.budget} ${job.currency || 'USD'}</span><span>Suggested specialists: ${matches || 'Run search'}</span></div>
  </a>`;
}

function renderClientJob(job) {
  return `<a href="job-detail.html?job_id=${job.id}" class="glass block rounded-3xl border p-5">
    <div class="flex items-center justify-between"><h3 class="text-xl font-semibold">${job.title}</h3><span class="badge badge-warning">${job.status}</span></div>
    <p class="mt-3 text-sm text-slate-600">${job.description}</p>
    <div class="mt-4 text-sm text-slate-500">$${job.budget} ${job.currency || 'USD'}</div>
  </a>`;
}

function renderClientBrowseJob(job) {
  return `<a href="job-detail.html?job_id=${job.id}" class="glass block rounded-3xl border p-5">
    <div class="flex items-center justify-between"><h3 class="text-xl font-semibold">${job.title}</h3><span class="badge badge-info">${job.niche}</span></div>
    <p class="mt-3 text-sm text-slate-600">${job.description}</p>
    <div class="mt-4 text-sm text-slate-500">$${job.budget} ${job.currency || 'USD'} · Client: ${job.client_name || ''}</div>
  </a>`;
}

function toggleNicheFields() {
  const value = qs('job-niche').value;
  ['translation-fields', 'data-fields', 'legal-fields'].forEach((id) => qs(id).classList.add('hidden'));
  if (value === 'translation') qs('translation-fields').classList.remove('hidden');
  if (value === 'data_science') qs('data-fields').classList.remove('hidden');
  if (value === 'legal') qs('legal-fields').classList.remove('hidden');
}

function empty() {
  return '<div class="rounded-3xl border border-dashed p-8 text-center text-slate-500">No results found.</div>';
}
