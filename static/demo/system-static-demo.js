const pages = ['home', 'login', 'register', 'dashboard', 'admin_orgs', 'admin_students', 'admin_requests', 'my_org'];
let role = 'guest';
let currentPage = 'home';
let trendChart = null;
let financialRankingChart = null;

function bindPageNavButtons(scope) {
  if (!scope) return;
  scope.querySelectorAll('[data-page]').forEach((btn) => {
    btn.addEventListener('click', () => showPage(btn.getAttribute('data-page')));
  });
}

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

function initFinancialRankingChart() {
  if (financialRankingChart) return;
  const canvas = document.getElementById('financialSummaryRankingChart');
  if (!canvas || typeof Chart === 'undefined') return;

  const labels = ['Music Ensemble', 'Computing Society', 'Language Society', 'Environmental Advocates', 'Volunteer Network', 'Athletics Council', 'Entrepreneurship Circle', 'Arts Collective'];
  const balances = [58249.41, 51423.39, 46795.85, 43078.36, 42618.94, 42522.21, 42190.63, 40278.13];

  const isDark = document.body.classList.contains('theme-dark');
  const axisColor = isDark ? '#a7f3d0' : '#065f46';
  const legendColor = isDark ? '#d1fae5' : '#14532d';
  const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

  financialRankingChart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Net Balance',
          data: balances,
          backgroundColor: 'rgba(52, 211, 153, 0.75)',
          borderColor: 'rgba(16, 185, 129, 1)',
          borderWidth: 1,
          borderRadius: 6
        }
      ]
    },
    options: {
      responsive: true,
      indexAxis: 'y',
      plugins: {
        legend: { labels: { color: legendColor } }
      },
      scales: {
        x: { ticks: { color: axisColor }, grid: { color: gridColor } },
        y: { ticks: { color: axisColor }, grid: { color: 'rgba(0,0,0,0)' } }
      }
    }
  });
}

function updateFinancialChartTheme() {
  if (!financialRankingChart) return;
  const isDark = document.body.classList.contains('theme-dark');
  const axisColor = isDark ? '#a7f3d0' : '#065f46';
  const legendColor = isDark ? '#d1fae5' : '#14532d';
  const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

  financialRankingChart.options.plugins.legend.labels.color = legendColor;
  financialRankingChart.options.scales.x.ticks.color = axisColor;
  financialRankingChart.options.scales.y.ticks.color = axisColor;
  financialRankingChart.options.scales.x.grid.color = gridColor;
  financialRankingChart.update();
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
  bindPageNavButtons(el);
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
  bindPageNavButtons(el);
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
  updateFinancialChartTheme();
});

const modal = document.getElementById('privacyModal');
const openBtn = document.getElementById('openPrivacyModal');
const closeBtn = document.getElementById('closePrivacyModal');
const declineBtn = document.getElementById('declinePrivacy');
const acceptBtn = document.getElementById('acceptPrivacy');
const consent = document.getElementById('privacyConsent');

function openModal() {
  modal.classList.remove('hidden');
}

function closeModal() {
  modal.classList.add('hidden');
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

const financialSummaryModal = document.getElementById('financialSummaryModal');
const openFinancialSummaryModal = document.getElementById('openFinancialSummaryModal');
const closeFinancialSummaryModal = document.getElementById('closeFinancialSummaryModal');
const organizationsModal = document.getElementById('organizationsModal');
const openOrganizationsModal = document.getElementById('openOrganizationsModal');
const closeOrganizationsModal = document.getElementById('closeOrganizationsModal');
const announcementsModal = document.getElementById('announcementsModal');
const openAnnouncementsModalQuick = document.getElementById('openAnnouncementsModalQuick');
const closeAnnouncementsModal = document.getElementById('closeAnnouncementsModal');

if (openFinancialSummaryModal && financialSummaryModal) {
  openFinancialSummaryModal.addEventListener('click', function () {
    financialSummaryModal.classList.remove('hidden');
    initFinancialRankingChart();
    updateFinancialChartTheme();
  });
}

if (closeFinancialSummaryModal && financialSummaryModal) {
  closeFinancialSummaryModal.addEventListener('click', function () {
    financialSummaryModal.classList.add('hidden');
  });
}

if (financialSummaryModal) {
  financialSummaryModal.addEventListener('click', function (event) {
    if (event.target === financialSummaryModal) {
      financialSummaryModal.classList.add('hidden');
    }
  });
}

if (openOrganizationsModal && organizationsModal) {
  openOrganizationsModal.addEventListener('click', function () {
    organizationsModal.classList.remove('hidden');
  });
}

if (closeOrganizationsModal && organizationsModal) {
  closeOrganizationsModal.addEventListener('click', function () {
    organizationsModal.classList.add('hidden');
  });
}

if (organizationsModal) {
  organizationsModal.addEventListener('click', function (event) {
    if (event.target === organizationsModal) {
      organizationsModal.classList.add('hidden');
    }
  });
}

if (openAnnouncementsModalQuick && announcementsModal) {
  openAnnouncementsModalQuick.addEventListener('click', function () {
    announcementsModal.classList.remove('hidden');
  });
}

if (closeAnnouncementsModal && announcementsModal) {
  closeAnnouncementsModal.addEventListener('click', function () {
    announcementsModal.classList.add('hidden');
  });
}

if (announcementsModal) {
  announcementsModal.addEventListener('click', function (event) {
    if (event.target === announcementsModal) {
      announcementsModal.classList.add('hidden');
    }
  });
}

document.getElementById('registerForm').addEventListener('submit', function (event) {
  event.preventDefault();
  const firstName = document.getElementById('registerFirstName');
  const middleName = document.getElementById('registerMiddleName');
  const lastName = document.getElementById('registerLastName');
  const first = firstName ? firstName.value.trim() : '';
  const middle = middleName ? middleName.value.trim() : '';
  const last = lastName ? lastName.value.trim() : '';

  if (first === '' || last === '') {
    alert('Please provide your first and last name.');
    return;
  }

  if (!consent.checked) {
    alert('You must agree to the terms and conditions before registering.');
    return;
  }

  const fullName = [first, middle, last].filter(Boolean).join(' ');
  alert('Demo registration successful for ' + fullName + '. Redirecting to login.');
  showPage('login');
});

bindPageNavButtons(document);

const myOrgBudgetForm = document.getElementById('myOrgBudgetForm');
const myOrgBudgetList = document.getElementById('myOrgBudgetList');
const myOrgBudgetDate = document.getElementById('myOrgBudgetDate');

if (myOrgBudgetDate && !myOrgBudgetDate.value) {
  myOrgBudgetDate.value = new Date().toISOString().slice(0, 10);
}

if (myOrgBudgetForm && myOrgBudgetList) {
  myOrgBudgetForm.addEventListener('submit', function (event) {
    event.preventDefault();

    const typeEl = document.getElementById('myOrgBudgetType');
    const amountEl = document.getElementById('myOrgBudgetAmount');
    const dateEl = document.getElementById('myOrgBudgetDate');
    const descEl = document.getElementById('myOrgBudgetDesc');

    if (!typeEl || !amountEl || !dateEl || !descEl) return;

    const type = typeEl.value === 'income' ? 'income' : 'expense';
    const amount = Number.parseFloat(amountEl.value || '0');
    const date = dateEl.value || new Date().toISOString().slice(0, 10);
    const description = descEl.value.trim();

    if (!description || !Number.isFinite(amount) || amount <= 0) {
      alert('Please enter a valid budget item.');
      return;
    }

    const row = document.createElement('div');
    row.className = 'border rounded p-2 flex flex-wrap items-center justify-between gap-2';

    const amountClass = type === 'income' ? 'text-green-700' : 'text-red-700';
    row.innerHTML = `
      <div>
        <div class="font-medium"></div>
        <div class="text-xs text-gray-500">${type} · ${date}</div>
      </div>
      <div class="font-semibold ${amountClass}">₱${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>`;

    const titleEl = row.querySelector('.font-medium');
    if (titleEl) titleEl.textContent = description;

    myOrgBudgetList.insertBefore(row, myOrgBudgetList.firstChild);
    myOrgBudgetForm.reset();
    if (myOrgBudgetDate) {
      myOrgBudgetDate.value = new Date().toISOString().slice(0, 10);
    }
    alert('Budget entry saved in static demo.');
  });
}

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
