const pages = ['home', 'login', 'register', 'dashboard', 'admin_orgs', 'admin_students', 'admin_requests', 'my_org'];
let role = 'guest';
let currentPage = 'home';
let trendChart = null;

function showPage(page) {
  currentPage = page;
  pages.forEach((p) => {
    const el = document.getElementById('page-' + p);
    if (!el) return;
    el.classList.toggle('active', p === page);
  });
  renderNav();
  closeMobileMenu();
}

function setRole(nextRole) {
  role = nextRole;
  if (nextRole === 'guest') showPage('home');
  if (nextRole === 'student' || nextRole === 'owner' || nextRole === 'admin') showPage('dashboard');
  renderNav();
  renderHomeActions();
  renderQuickActions();
  applyDashboardRoleVisibility();
  updateDashboardWelcome();
  setActiveRoleTag();
}

function updateDashboardWelcome() {
  const nameEl = document.getElementById('dashboardWelcomeName');
  if (!nameEl) return;
  if (role === 'guest') {
    nameEl.textContent = 'Guest User';
    return;
  }
  nameEl.textContent = 'System ' + role.charAt(0).toUpperCase() + role.slice(1);
}

function applyDashboardRoleVisibility() {
  const pending = document.getElementById('dashboardPendingAssignments');
  const joinButtons = document.querySelectorAll('.dashboard-join-btn');
  const canSeeAssignments = role === 'student' || role === 'owner';
  const canJoinOrgs = role === 'student' || role === 'owner';

  if (pending) {
    pending.classList.toggle('hidden', !canSeeAssignments);
  }

  joinButtons.forEach((button) => {
    button.classList.toggle('hidden', !canJoinOrgs);
  });
}

function initTrendChart() {
  const canvas = document.getElementById('trendChart');
  if (!canvas || typeof Chart === 'undefined') return;

  const labels = ['2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02'];
  const income = [12000, 14500, 16000, 17500, 19000, 21000];
  const expense = [8000, 9200, 9800, 11000, 12000, 13000];

  const isDark = document.body.classList.contains('theme-dark');
  const axisColor = isDark ? '#a7f3d0' : '#065f46';
  const legendColor = isDark ? '#d1fae5' : '#14532d';
  const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

  trendChart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Income',
          data: income,
          borderColor: '#34d399',
          backgroundColor: 'rgba(52, 211, 153, 0.2)',
          fill: true,
          tension: 0.35
        },
        {
          label: 'Expense',
          data: expense,
          borderColor: '#f87171',
          backgroundColor: 'rgba(248, 113, 113, 0.16)',
          fill: true,
          tension: 0.35
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { labels: { color: legendColor } }
      },
      scales: {
        x: { ticks: { color: axisColor }, grid: { color: gridColor } },
        y: { ticks: { color: axisColor }, grid: { color: gridColor } }
      }
    }
  });
}

function updateTrendChartTheme() {
  if (!trendChart) return;
  const isDark = document.body.classList.contains('theme-dark');
  const axisColor = isDark ? '#a7f3d0' : '#065f46';
  const legendColor = isDark ? '#d1fae5' : '#14532d';
  const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

  trendChart.options.plugins.legend.labels.color = legendColor;
  trendChart.options.scales.x.ticks.color = axisColor;
  trendChart.options.scales.y.ticks.color = axisColor;
  trendChart.options.scales.x.grid.color = gridColor;
  trendChart.options.scales.y.grid.color = gridColor;
  trendChart.update();
}

function navLinksByRole() {
  const common = [{ key: 'home', label: 'Home' }];
  if (role === 'guest') {
    return [...common, { key: 'login', label: 'Login' }, { key: 'register', label: 'Register', button: true }];
  }
  const links = [...common, { key: 'dashboard', label: 'Dashboard' }];
  if (role === 'admin') {
    links.push({ key: 'admin_orgs', label: 'Manage Orgs' });
    links.push({ key: 'admin_students', label: 'Students' });
    links.push({ key: 'admin_requests', label: 'Requests' });
  }
  if (role === 'owner' || role === 'admin') {
    links.push({ key: 'my_org', label: 'My Organization' });
  }
  return links;
}

function linkHtml(link) {
  const activeClass = currentPage === link.key ? 'nav-link-active' : '';
  if (link.button) {
    return `<button class="bg-emerald-600 text-white px-3 py-1 rounded hover:bg-emerald-700 shadow-sm" data-page="${link.key}">${link.label}</button>`;
  }
  return `<button class="nav-link ${activeClass}" data-page="${link.key}">${link.label}</button>`;
}

function renderNav() {
  const desktop = document.getElementById('desktopNav');
  const mobile = document.getElementById('mobileNav');
  const links = navLinksByRole();

  let extraDesktop = '';
  let extraMobile = '';
  if (role !== 'guest') {
    extraDesktop = `<span class="text-sm text-emerald-800">Hi, System ${role.charAt(0).toUpperCase() + role.slice(1)}</span>
      <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
      <button class="bg-emerald-800 text-white px-3 py-1 rounded" data-logout="1">Logout</button>`;

    extraMobile = `<span class="text-sm text-emerald-800">Hi, System ${role.charAt(0).toUpperCase() + role.slice(1)}</span>
      <button class="bg-emerald-800 text-white px-3 py-2 rounded text-center" data-logout="1">Logout</button>`;
  } else {
    extraDesktop = `<label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>`;
  }

  desktop.innerHTML = links.map(linkHtml).join('') + extraDesktop;
  mobile.innerHTML = links.map(linkHtml).join('') + extraMobile;

  bindNavClicks(desktop);
  bindNavClicks(mobile);
}

function bindNavClicks(container) {
  container.querySelectorAll('[data-page]').forEach((btn) => {
    btn.addEventListener('click', () => showPage(btn.getAttribute('data-page')));
  });
  container.querySelectorAll('[data-logout]').forEach((btn) => {
    btn.addEventListener('click', () => setRole('guest'));
  });
}

function renderHomeActions() {
  const el = document.getElementById('homeActions');
  if (role === 'guest') {
    el.innerHTML = `
      <button class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition" data-page="register">Get Started</button>
      <button class="border border-emerald-200/50 text-emerald-800 px-5 py-2.5 rounded-lg hover:bg-white/30" data-page="login">Login</button>`;
  } else {
    el.innerHTML = `<button class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition" data-page="dashboard">Open Dashboard</button>`;
  }
  el.querySelectorAll('[data-page]').forEach((btn) => btn.addEventListener('click', () => showPage(btn.getAttribute('data-page'))));
}

function renderQuickActions() {
  const el = document.getElementById('dashboardQuickActions');
  const base = [`<button class="bg-emerald-700 text-white px-3 py-2 rounded text-sm" data-page="dashboard">Refresh Dashboard</button>`];
  if (role === 'admin') {
    base.push(`<button class="bg-emerald-700 text-white px-3 py-2 rounded text-sm" data-page="admin_orgs">Manage Organizations</button>`);
    base.push(`<button class="bg-emerald-700 text-white px-3 py-2 rounded text-sm" data-page="admin_students">Filter Students</button>`);
    base.push(`<button class="bg-emerald-700 text-white px-3 py-2 rounded text-sm" data-page="admin_requests">Review Requests</button>`);
    base.push(`<button class="bg-emerald-700 text-white px-3 py-2 rounded text-sm" data-page="my_org">Go to My Organization</button>`);
  }
  if (role === 'owner') {
    base.push(`<button class="bg-emerald-700 text-white px-3 py-2 rounded text-sm" data-page="my_org">Go to My Organization</button>`);
  }
  el.innerHTML = base.join('');
  el.querySelectorAll('[data-page]').forEach((btn) => btn.addEventListener('click', () => showPage(btn.getAttribute('data-page'))));
}

function setActiveRoleTag() {
  document.querySelectorAll('#roleControls [data-role]').forEach((btn) => {
    btn.classList.toggle('bg-emerald-600', btn.getAttribute('data-role') === role);
    btn.classList.toggle('text-white', btn.getAttribute('data-role') === role);
  });
}

const navToggle = document.getElementById('navMenuToggle');
const mobileNavMenu = document.getElementById('mobileNavMenu');

function closeMobileMenu() {
  if (!mobileNavMenu) return;
  mobileNavMenu.classList.add('hidden');
  if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
}

if (navToggle && mobileNavMenu) {
  navToggle.addEventListener('click', function () {
    const isOpen = !mobileNavMenu.classList.contains('hidden');
    mobileNavMenu.classList.toggle('hidden', isOpen);
    navToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 768) closeMobileMenu();
  });
}

const themeToggle = document.getElementById('themeToggle');
const savedTheme = localStorage.getItem('websys-theme');
if (savedTheme === 'dark') {
  document.body.classList.add('theme-dark');
  themeToggle.checked = true;
}

themeToggle.addEventListener('change', function () {
  document.body.classList.toggle('theme-dark', themeToggle.checked);
  localStorage.setItem('websys-theme', themeToggle.checked ? 'dark' : 'light');
  updateTrendChartTheme();
});

const modal = document.getElementById('privacyModal');
const openBtn = document.getElementById('openPrivacyModal');
const closeBtn = document.getElementById('closePrivacyModal');
const declineBtn = document.getElementById('declinePrivacy');
const acceptBtn = document.getElementById('acceptPrivacy');
const consent = document.getElementById('privacyConsent');

function openModal() {
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeModal() {
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

openBtn.addEventListener('click', openModal);
closeBtn.addEventListener('click', closeModal);
declineBtn.addEventListener('click', closeModal);
acceptBtn.addEventListener('click', function () {
  consent.checked = true;
  closeModal();
});

modal.addEventListener('click', function (event) {
  if (event.target === modal) closeModal();
});

document.getElementById('registerForm').addEventListener('submit', function (event) {
  event.preventDefault();
  if (!consent.checked) {
    alert('You must agree to the Data Privacy Consent before registering.');
    return;
  }
  alert('Demo registration successful. Redirecting to login.');
  showPage('login');
});

document.querySelectorAll('#roleControls [data-role]').forEach((btn) => {
  btn.addEventListener('click', () => setRole(btn.getAttribute('data-role')));
});

renderNav();
renderHomeActions();
renderQuickActions();
applyDashboardRoleVisibility();
updateDashboardWelcome();
setActiveRoleTag();
initTrendChart();
