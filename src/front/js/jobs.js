document.addEventListener('DOMContentLoaded', async () => {
  const user = await checkSession();
  if (!user) return;

  const resultBox = qs('job-results');
  const specialistBox = qs('specialist-results');
  const filters = ['search_keyword', 'search_niche', 'search_budget'];
  const postToggleBtn = qs('post-job-toggle');
  const formWrap = qs('job-form-wrap');

  const runSearch = debounce(async () => {
    showLoading(resultBox);
    try {
      if (user.role === 'freelancer') {
        const query = new URLSearchParams({
          niche: qs('search_niche').value,
          keyword: qs('search_keyword').value,
          max_budget: qs('search_budget').value
        });
        const jobs = await apiCall(`client.php?action=jobs/browse&${query.toString()}`);
        stopLoading(resultBox);
        resultBox.innerHTML = jobs.map((job) => renderJob(job)).join('') || empty();
      } else if (user.role === 'client') {
        const query = new URLSearchParams({
          niche: qs('search_niche').value,
          keyword: qs('search_keyword').value,
          max_budget: qs('search_budget').value
        });
        const jobs = await apiCall(`client.php?action=jobs/browse&${query.toString()}`);
        stopLoading(resultBox);
        resultBox.innerHTML = jobs.map((job) => renderClientBrowseJob(job)).join('') || empty();
      } else {
        const jobs = await apiCall('client.php?action=jobs/mine');
        stopLoading(resultBox);
        resultBox.innerHTML = jobs.map((job) => renderClientJob(job)).join('') || empty();
      }
    } catch (e) {
      stopLoading(resultBox);
      qs('job-results').innerHTML = `<div class="empty-state"><p>${e.message}</p></div>`;
    }
  }, 300);

  filters.forEach((id) => qs(id)?.addEventListener('input', runSearch));
  runSearch();

  if (user.role === 'client') {
    postToggleBtn.classList.remove('hidden');
    qs('search-panel').classList.remove('hidden');

    postToggleBtn.addEventListener('click', () => {
      formWrap.classList.toggle('show');
    });

    qs('job-form').addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(event.target).entries());
      const errBox = qs('form-err');
      const submitBtn = event.target.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.textContent = 'Publishing...';
      try {
        await apiCall('client.php?action=jobs/create', 'POST', data);
        showSuccess(formWrap, 'Job posted successfully!');
        setTimeout(() => location.reload(), 1500);
      } catch (err) {
        showError(errBox, err.message);
        if (submitBtn) submitBtn.textContent = 'Publish Listing';
      }
    });

    qs('job-niche').addEventListener('change', toggleNicheFields);
    toggleNicheFields();

    const specBtn = qs('find-specialists-btn');
    if (specBtn) {
      specBtn.classList.remove('hidden');
      specBtn.addEventListener('click', async () => {
        const rankingQuery = new URLSearchParams({
          niche: qs('search_niche').value || 'other',
          keywords: qs('search_keyword').value
        });
        showLoading(specialistBox);
        specialistBox.classList.remove('hidden');
        try {
          const ranked = await apiCall(`freelancer.php?action=search&${rankingQuery.toString()}`);
          stopLoading(specialistBox);
          specialistBox.innerHTML = ranked.map((f) => renderSpecialist(f)).join('') || emptySpecialists();
        } catch(e) {
          stopLoading(specialistBox);
          specialistBox.innerHTML = `<div class="empty-state"><p>${e.message}</p></div>`;
        }
      });
    }
  } else {
    qs('search-panel').classList.remove('hidden');
  }
});


function _baseJobCard(job, badgeHtml, extraFooter = '') {
  return `
    <a href="job-detail.html?job_id=${job.id}" class="card job-card">
      <div class="card-body">
        <div style="position: absolute; top: 1.5rem; right: 1.5rem;">
          ${badgeHtml}
        </div>
        <h3 class="job-card-title">${job.title}</h3>
        <p class="job-card-desc">${job.description || 'No description provided.'}</p>
        <div class="job-card-footer">
          <div style="font-size: 0.8rem; color: var(--muted);">${extraFooter}</div>
          <div class="job-card-budget">$${parseFloat(job.budget).toLocaleString()} <span style="font-size:0.6em;font-family:'DM Sans',sans-serif;">${job.currency || 'USD'}</span></div>
        </div>
      </div>
    </a>
  `;
}

function renderJob(job) {
  return _baseJobCard(job, `<span class="badge badge-gold">${job.niche}</span>`);
}

function renderClientJob(job) {
  return _baseJobCard(job, `<span class="badge ${job.status==='open' ? 'badge-open' : 'badge-purple'}">${job.status}</span>`, `Your Listing`);
}

function renderClientBrowseJob(job) {
  return _baseJobCard(job, `<span class="badge badge-gold">${job.niche}</span>`, `Client: ${job.client_name || 'N/A'}`);
}

function renderSpecialist(freelancer) {
  return `
    <article class="card">
      <div class="card-body">
        <div style="display:flex; justify-content:space-between; align-items:start;">
          <div>
            <h3 style="font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;">${freelancer.name}</h3>
            <div style="font-size:0.75rem; letter-spacing:0.1em; text-transform:uppercase; color:var(--gold); margin-top:0.25rem;">${freelancer.niche}</div>
          </div>
          <span class="badge badge-info">Score: ${parseFloat(freelancer.score).toFixed(2)}</span>
        </div>
        <p style="margin-top:1rem; font-size:0.9rem; color:var(--muted); line-height:1.6;">${freelancer.bio || 'No bio provided.'}</p>
        <div style="margin-top:1.5rem; font-size:0.85rem; color:var(--ink); font-weight:500;">Rate: $${freelancer.hourly_rate || 0}/hr</div>
      </div>
    </article>
  `;
}

function toggleNicheFields() {
  const value = qs('job-niche').value;
  ['translation-fields', 'data-fields', 'legal-fields'].forEach(id => {
    qs(id).classList.remove('show');
  });
  if (value === 'translation') qs('translation-fields').classList.add('show');
  if (value === 'data_science') qs('data-fields').classList.add('show');
  if (value === 'legal') qs('legal-fields').classList.add('show');
}

function empty() {
  return `
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <p>No listings mathing your search parameters.</p>
    </div>
  `;
}

function emptySpecialists() {
  return `
    <div class="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
      <p>No specialists found matching these niche queries.</p>
    </div>
  `;
}
