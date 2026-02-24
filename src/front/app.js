async function api(path, method = 'GET', data = null) {
  const opts = { method, credentials: 'include', headers: {} };
  if (data) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(data);
  }
  const res = await fetch('/api/' + path, opts);
  return res.json();
}

async function me() {
  const r = await api('auth_me.php');
  return r.data;
}

function navHtml(user) {
  const common = [
    ['Home', 'index.html'],
    ['Profile', 'profile.html'],
    ['Courses', 'courses.html'],
    ['Calendar', 'calendar.html'],
    ['Messages', 'messages.html'],
    ['Notifications', 'notifications.html'],
    ['Assessments', 'assessments.html'],
    ['Teacher Eval', 'evaluate_teacher.html']
  ];
  if (user && user.role === 'student') {
    common.push(['Transcript', 'transcript.html']);
    common.push(['Certificate', 'certificate.html']);
  }
  if (user && user.role === 'faculty') {
    common.push(['Faculty Ratings', 'faculty_ratings.html']);
    common.push(['Grade Assessment', 'grade_assessment.html']);
  }
  if (user && user.role === 'admin') {
    common.push(['Eval Questions', 'eval_questions_admin.html']);
    common.push(['Admin Reports', 'admin_reports.html']);
  }
  common.push(['Logout', 'login.html?logout=1']);
  return '<nav>' + common.map(i => `<a href="${i[1]}">${i[0]}</a>`).join('') + '</nav>';
}

function headerHtml(user, title) {
  return `
    <div class="app-header">
      <div class="bar">
        <div class="brand">LMS Platform</div>
        <div class="user-chip">${user.name} (${user.role})</div>
      </div>
      ${navHtml(user)}
      <h1 class="page-title">${title}</h1>
    </div>
  `;
}

async function initPage(title) {
  if (location.search.includes('logout=1')) {
    await api('auth_logout.php', 'POST');
  }
  const user = await me();
  const el = document.getElementById('app');
  if (!user && !location.pathname.endsWith('login.html') && !location.pathname.endsWith('register.html')) {
    location.href = 'login.html';
    return null;
  }
  if (el && user) {
    el.insertAdjacentHTML('afterbegin', headerHtml(user, title));
  }
  return user;
}
