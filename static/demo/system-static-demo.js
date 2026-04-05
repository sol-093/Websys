const pages = ['home', 'login', 'register', 'dashboard', 'admin_orgs', 'admin_students', 'admin_requests', 'my_org'];
let role = 'guest';
let currentPage = 'home';
let trendChart = null;
let financialRankingChart = null;
const onboardingStorageKey = 'websys_static_onboarding_done';
const onboardingStepKey = 'websys_static_onboarding_step';

const searchData = {
  users: [
    { label: 'John Cruz', sublabel: 'STUDENT • john.cruz@campus.local', page: 'admin_students' },
    { label: 'Maria Santos', sublabel: 'OWNER • maria.santos@campus.local', page: 'admin_students' },
    { label: 'Claire Mendoza', sublabel: 'OWNER • claire.mendoza@campus.local', page: 'admin_students' }
  ],
  organizations: [
    { label: 'Computer Society', sublabel: 'Category: institutewide • Tech events and workshops', page: 'dashboard', modal: 'organizations' },
    { label: 'Debate Union', sublabel: 'Category: collegewide • Debate and speaking competitions', page: 'dashboard', modal: 'organizations' },
    { label: 'Media and Creators Guild', sublabel: 'Category: collegewide • Campus media production', page: 'dashboard', modal: 'organizations' }
  ],
  announcements: [
    { label: 'Budget Orientation', sublabel: 'Computer Society • 2026-03-07', page: 'dashboard', modal: 'announcements' },
    { label: 'Emergency Funding Resolution Approved', sublabel: 'Student Council • 2026-03-06', page: 'dashboard', modal: 'announcements' },
    { label: 'Quarterly Compliance Submission Window', sublabel: 'Administration • 2026-03-03', page: 'dashboard', modal: 'announcements' }
  ]
};

function showToast(message, type) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `
    <div class="toast-body">
      <div class="toast-accent"></div>
      <div>
        <p class="toast-title">${type || 'Info'}</p>
        <div class="toast-message">${message}</div>
      </div>
      <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
    </div>`;

  const closeBtn = toast.querySelector('.toast-close');
  closeBtn.addEventListener('click', function () {
    toast.remove();
  });

  container.appendChild(toast);
  window.setTimeout(function () {
    toast.remove();
  }, 3500);
}

function syncBodyRoleState() {
  document.body.classList.toggle('is-authenticated', role !== 'guest');
}

function uiIcon(name, classes) {
  const iconClass = classes || 'ui-icon';
  const icons = {
    dashboard: '<path d="M8.4 3H4.6C4.03995 3 3.75992 3 3.54601 3.10899C3.35785 3.20487 3.20487 3.35785 3.10899 3.54601C3 3.75992 3 4.03995 3 4.6V8.4C3 8.96005 3 9.24008 3.10899 9.45399C3.20487 9.64215 3.35785 9.79513 3.54601 9.89101C3.75992 10 4.03995 10 4.6 10H8.4C8.96005 10 9.24008 10 9.45399 9.89101C9.64215 9.79513 9.79513 9.64215 9.89101 9.45399C10 9.24008 10 8.96005 10 8.4V4.6C10 4.03995 10 3.75992 9.89101 3.54601C9.79513 3.35785 9.64215 3.20487 9.45399 3.10899C9.24008 3 8.96005 3 8.4 3Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M19.4 3H15.6C15.0399 3 14.7599 3 14.546 3.10899C14.3578 3.20487 14.2049 3.35785 14.109 3.54601C14 3.75992 14 4.03995 14 4.6V8.4C14 8.96005 14 9.24008 14.109 9.45399C14.2049 9.64215 14.3578 9.79513 14.546 9.89101C14.7599 10 15.0399 10 15.6 10H19.4C19.9601 10 20.2401 10 20.454 9.89101C20.6422 9.79513 20.7951 9.64215 20.891 9.45399C21 9.24008 21 8.96005 21 8.4V4.6C21 4.03995 21 3.75992 20.891 3.54601C20.7951 3.35785 20.6422 3.20487 20.454 3.10899C20.2401 3 19.9601 3 19.4 3Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M19.4 14H15.6C15.0399 14 14.7599 14 14.546 14.109C14.3578 14.2049 14.2049 14.3578 14.109 14.546C14 14.7599 14 15.0399 14 15.6V19.4C14 19.9601 14 20.2401 14.109 20.454C14.2049 20.6422 14.3578 20.7951 14.546 20.891C14.7599 21 15.0399 21 15.6 21H19.4C19.9601 21 20.2401 21 20.454 20.891C20.6422 20.7951 20.7951 20.6422 20.891 20.454C21 20.2401 21 19.9601 21 19.4V15.6C21 15.0399 21 14.7599 20.891 14.546C20.7951 14.3578 20.6422 14.2049 20.454 14.109C20.2401 14 19.9601 14 19.4 14Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M8.4 14H4.6C4.03995 14 3.75992 14 3.54601 14.109C3.35785 14.2049 3.20487 14.3578 3.10899 14.546C3 14.7599 3 15.0399 3 15.6V19.4C3 19.9601 3 20.2401 3.10899 20.454C3.20487 20.6422 3.35785 20.7951 3.54601 20.891C3.75992 21 4.03995 21 4.6 21H8.4C8.96005 21 9.24008 21 9.45399 20.891C9.64215 20.7951 9.79513 20.6422 9.89101 20.454C10 20.2401 10 19.9601 10 19.4V15.6C10 15.0399 10 14.7599 9.89101 14.546C9.79513 14.3578 9.64215 14.2049 9.45399 14.109C9.24008 14 8.96005 14 8.4 14Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />',
    register: '<path d="M12 15.5H7.5C6.10444 15.5 5.40665 15.5 4.83886 15.6722C3.56045 16.06 2.56004 17.0605 2.17224 18.3389C2 18.9067 2 19.6044 2 21M19 21V15M16 18H22M14.5 7.5C14.5 9.98528 12.4853 12 10 12C7.51472 12 5.5 9.98528 5.5 7.5C5.5 5.01472 7.51472 3 10 3C12.4853 3 14.5 5.01472 14.5 7.5Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />',
    login: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5.25a1.5 1.5 0 01-1.5-1.5v-15a1.5 1.5 0 011.5-1.5H9" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 16.5L9.75 12l4.5-4.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 12H21" />',
    search: '<circle cx="10.5" cy="10.5" r="5.25" /><path stroke-linecap="round" stroke-linejoin="round" d="M14.25 14.25L19.5 19.5" />'
  };

  const pathMarkup = icons[name] || icons.login;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="${iconClass}" aria-hidden="true">${pathMarkup}</svg>`;
}

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
  if (role === 'student' && page === 'dashboard') {
    window.setTimeout(function () {
      startOnboardingTour(false);
    }, 200);
  }
}

function setRole(nextRole) {
  role = nextRole;
  syncBodyRoleState();
  if (nextRole === 'guest') showPage('home');
  if (nextRole === 'student' || nextRole === 'owner' || nextRole === 'admin') showPage('dashboard');
  renderNav();
  renderHomeActions();
  renderQuickActions();
  applyDashboardRoleVisibility();
  updateDashboardWelcome();
  setActiveRoleTag();
  syncReplayVisibility();
}

function syncReplayVisibility() {
  const replayItem = document.getElementById('replayOnboardingItem');
  if (!replayItem) return;
  replayItem.classList.toggle('hidden', role !== 'student');
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
  const income = [420000, 310000, 680000, 250000, 910000, 1200000];
  const expense = [190000, 540000, 360000, 720000, 640000, 980000];

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

  const labels = ['Media and Creators Guild', 'Computing Society', 'Language Society', 'Volunteer Network', 'Athletics Council', 'Environmental Advocates', 'Debate Union', 'Entrepreneurship Circle'];
  const balances = [1284950.0, 992300.0, 744500.0, 530200.0, 184400.0, 74200.0, -95200.0, -318500.0];

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
  const links = [...common, { key: 'dashboard', label: 'Dashboard' }, { key: 'organizations', label: 'Organizations', className: 'nav-organizations-link' }];
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
  const extraClass = link.className ? ' ' + link.className : '';
  return `<button class="nav-link ${activeClass}${extraClass}" data-page="${link.key}">${link.label}</button>`;
}

function renderNav() {
  const desktop = document.getElementById('desktopNav');
  const mobile = document.getElementById('mobileNav');
  const links = navLinksByRole();

  let extraDesktop = '';
  let extraMobile = '';
  if (role !== 'guest') {
    extraDesktop = `<span class="text-sm text-emerald-800">Hi, System ${role.charAt(0).toUpperCase() + role.slice(1)}</span>
      <button type="button" id="globalSearchOpen" class="global-search-trigger" aria-label="Open global search" title="Search (Ctrl+K)">${uiIcon('search', 'ui-icon ui-icon-sm')}</button>
      <label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>
      <button class="bg-emerald-800 text-white px-3 py-1 rounded" data-logout="1">Logout</button>`;

    extraMobile = `<span class="text-sm text-emerald-800">Hi, System ${role.charAt(0).toUpperCase() + role.slice(1)}</span>
      <button type="button" id="globalSearchOpenMobile" class="global-search-trigger" aria-label="Open global search" title="Search (Ctrl+K)">${uiIcon('search', 'ui-icon ui-icon-sm')}</button>
      <button class="bg-emerald-800 text-white px-3 py-2 rounded text-center" data-logout="1">Logout</button>`;
  } else {
    extraDesktop = `<label for="themeToggle" class="theme-switch" title="Toggle dark mode"></label>`;
  }

  desktop.innerHTML = links.map(linkHtml).join('') + extraDesktop;
  mobile.innerHTML = links.map(linkHtml).join('') + extraMobile;

  bindNavClicks(desktop);
  bindNavClicks(mobile);
  bindGlobalSearchTriggers();
}

function bindNavClicks(container) {
  container.querySelectorAll('[data-page]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const page = btn.getAttribute('data-page');
      if (page === 'organizations') {
        showPage('dashboard');
        organizationsModal.classList.remove('hidden');
        return;
      }
      showPage(page);
    });
  });
  container.querySelectorAll('[data-logout]').forEach((btn) => {
    btn.addEventListener('click', () => {
      setRole('guest');
      showToast('Logged out from static demo mode.', 'Success');
    });
  });
}

function renderHomeActions() {
  const el = document.getElementById('homeActions');
  if (role === 'guest') {
    el.innerHTML = `
      <button class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]" data-page="register"><span class="icon-label">${uiIcon('register', 'ui-icon ui-icon-sm')}<span>Get Started</span></span></button>
      <button class="border border-emerald-200/50 text-emerald-800 px-5 py-2.5 rounded-lg hover:bg-white/30" data-page="login"><span class="icon-label">${uiIcon('login', 'ui-icon ui-icon-sm')}<span>Login</span></span></button>`;
  } else {
    el.innerHTML = `<button class="bg-emerald-500 text-slate-900 font-semibold px-5 py-2.5 rounded-lg transition shadow-[0_0_22px_rgba(45,212,191,0.45)] hover:shadow-[0_0_30px_rgba(45,212,191,0.62)]" data-page="dashboard"><span class="icon-label">${uiIcon('dashboard', 'ui-icon ui-icon-sm')}<span>Open Dashboard</span></span></button>`;
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
  mobileNavMenu.classList.remove('is-open');
  if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
}

if (navToggle && mobileNavMenu) {
  navToggle.addEventListener('click', function () {
    const isOpen = mobileNavMenu.classList.contains('is-open');
    mobileNavMenu.classList.toggle('is-open', !isOpen);
    navToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 768) closeMobileMenu();
  });
}

function bindGlobalSearchTriggers() {
  const desktopBtn = document.getElementById('globalSearchOpen');
  const mobileBtn = document.getElementById('globalSearchOpenMobile');
  if (desktopBtn) {
    desktopBtn.addEventListener('click', openGlobalSearchModal);
  }
  if (mobileBtn) {
    mobileBtn.addEventListener('click', openGlobalSearchModal);
  }
}

function bindFooterActions() {
  document.querySelectorAll('footer [data-page]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const page = btn.getAttribute('data-page');
      if (page === 'organizations') {
        showPage('dashboard');
        organizationsModal.classList.remove('hidden');
        return;
      }
      showPage(page);
    });
  });

  const replayBtn = document.getElementById('replayOnboardingBtn');
  if (replayBtn) {
    replayBtn.addEventListener('click', function () {
      localStorage.removeItem(onboardingStorageKey);
      sessionStorage.removeItem(onboardingStepKey);
      showPage('dashboard');
      startOnboardingTour(true);
    });
  }
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

const globalSearchModal = document.getElementById('globalSearchModal');
const globalSearchClose = document.getElementById('globalSearchClose');
const globalSearchInput = document.getElementById('globalSearchInput');

if (globalSearchClose) {
  globalSearchClose.addEventListener('click', closeGlobalSearchModal);
}

if (globalSearchModal) {
  globalSearchModal.addEventListener('click', function (event) {
    if (event.target === globalSearchModal) {
      closeGlobalSearchModal();
    }
  });
}

if (globalSearchInput) {
  globalSearchInput.addEventListener('input', function () {
    runStaticSearch(globalSearchInput.value);
  });
}

document.addEventListener('keydown', function (event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    openGlobalSearchModal();
    return;
  }

  if (event.key === 'Escape') {
    closeGlobalSearchModal();
  }
});

function openGlobalSearchModal() {
  const modal = document.getElementById('globalSearchModal');
  const input = document.getElementById('globalSearchInput');
  if (!modal || !input || role === 'guest') return;
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
  window.setTimeout(function () {
    input.focus();
    input.select();
  }, 0);
}

function closeGlobalSearchModal() {
  const modal = document.getElementById('globalSearchModal');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.setAttribute('aria-hidden', 'true');
}

function runStaticSearch(query) {
  const status = document.getElementById('globalSearchStatus');
  const resultsContainer = document.getElementById('globalSearchResults');
  if (!status || !resultsContainer) return;

  const text = query.trim().toLowerCase();
  resultsContainer.innerHTML = '';

  if (text.length < 2) {
    status.textContent = 'Type at least 2 characters to search.';
    return;
  }

  const merged = [];
  searchData.users.forEach(function (item) { merged.push({ type: 'User', icon: 'U', item: item }); });
  searchData.organizations.forEach(function (item) { merged.push({ type: 'Organization', icon: 'O', item: item }); });
  searchData.announcements.forEach(function (item) { merged.push({ type: 'Announcement', icon: 'A', item: item }); });

  const matched = merged.filter(function (entry) {
    const haystack = (entry.item.label + ' ' + entry.item.sublabel).toLowerCase();
    return haystack.indexOf(text) !== -1;
  }).slice(0, 10);

  if (matched.length === 0) {
    status.textContent = 'No results found for "' + query.trim() + '".';
    resultsContainer.innerHTML = '<div class="global-search-empty">No results found.</div>';
    return;
  }

  status.textContent = 'Showing ' + matched.length + ' result' + (matched.length === 1 ? '' : 's') + ' for "' + query.trim() + '".';

  matched.forEach(function (entry) {
    const result = document.createElement('button');
    result.type = 'button';
    result.className = 'global-search-result';
    result.innerHTML =
      '<span class="global-search-result-icon">' + entry.icon + '</span>' +
      '<span class="min-w-0 flex-1">' +
        '<span class="block font-semibold text-slate-800 truncate">' + entry.item.label + '</span>' +
        '<span class="block text-sm global-search-result-meta truncate">' + entry.item.sublabel + '</span>' +
      '</span>' +
      '<span class="global-search-shortcut">' + entry.type + '</span>';

    result.addEventListener('click', function () {
      showPage(entry.item.page || 'dashboard');
      if (entry.item.modal === 'organizations') {
        organizationsModal.classList.remove('hidden');
      }
      if (entry.item.modal === 'announcements') {
        announcementsModal.classList.remove('hidden');
      }
      closeGlobalSearchModal();
    });

    resultsContainer.appendChild(result);
  });
}

function initPasswordToggles() {
  document.querySelectorAll('input[data-password-toggle]').forEach(function (input) {
    const parent = input.parentElement;
    if (!parent || parent.classList.contains('password-toggle-wrap')) return;

    const wrap = document.createElement('div');
    wrap.className = 'password-toggle-wrap';
    parent.insertBefore(wrap, input);
    wrap.appendChild(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle-btn';
    button.setAttribute('aria-label', 'Toggle password visibility');
    button.innerHTML = uiIcon('search', 'ui-icon ui-icon-sm');
    button.addEventListener('click', function () {
      input.type = input.type === 'password' ? 'text' : 'password';
    });
    wrap.appendChild(button);
  });
}

function startOnboardingTour(forceRestart) {
  if (role !== 'student' || currentPage !== 'dashboard') {
    return;
  }

  if (!forceRestart && localStorage.getItem(onboardingStorageKey) === '1') {
    return;
  }

  const existingLayer = document.querySelector('.onboarding-layer');
  if (existingLayer) {
    existingLayer.remove();
  }

  const steps = [
    { target: '#dashboardWelcomeMessage', title: 'Welcome', body: 'This area gives you a quick read on organizations and financial status.' },
    { target: '.nav-organizations-link', title: 'Browse organizations', body: 'Use this shortcut to inspect available organizations.' },
    { target: '[data-tour="join-button"]:not([disabled])', title: 'Request to join', body: 'Use join buttons to request membership in eligible groups.' },
    { target: '#dashboardAnnouncementsSection', title: 'Announcements', body: 'Watch this panel for important updates and activity.' },
    { target: '#dashboardBudgetTransparencySection', title: 'Budget transparency', body: 'Use this section to understand spending and retained balance.' }
  ];

  let stepIndex = 0;
  const root = document.createElement('div');
  root.className = 'onboarding-layer';
  root.innerHTML = '<div class="onboarding-backdrop" aria-hidden="true"></div>';
  const focus = document.createElement('div');
  focus.className = 'onboarding-focus';
  const tooltip = document.createElement('div');
  tooltip.className = 'onboarding-tooltip';

  root.appendChild(focus);
  root.appendChild(tooltip);
  document.body.appendChild(root);

  const getStepTarget = function (selector) {
    if (selector === '.nav-organizations-link' && window.matchMedia('(max-width: 767px)').matches) {
      const menu = document.getElementById('mobileNavMenu');
      if (menu) {
        menu.classList.add('is-open');
      }
    }
    return document.querySelector(selector);
  };

  const renderStep = function () {
    const step = steps[stepIndex];
    if (!step) {
      localStorage.setItem(onboardingStorageKey, '1');
      sessionStorage.removeItem(onboardingStepKey);
      root.remove();
      showToast('Onboarding completed.', 'Success');
      return;
    }

    const target = getStepTarget(step.target);
    if (!target) {
      stepIndex += 1;
      renderStep();
      return;
    }

    const rect = target.getBoundingClientRect();
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    focus.style.top = Math.max(8, rect.top - 6) + 'px';
    focus.style.left = Math.max(8, rect.left - 6) + 'px';
    focus.style.width = Math.min(window.innerWidth - 16, rect.width + 12) + 'px';
    focus.style.height = Math.min(window.innerHeight - 16, rect.height + 12) + 'px';

    const top = Math.min(window.innerHeight - 190, rect.bottom + 14);
    const left = Math.max(12, Math.min(rect.left, window.innerWidth - 340));
    tooltip.style.top = top + 'px';
    tooltip.style.left = left + 'px';

    tooltip.innerHTML =
      '<p class="onboarding-title">' + step.title + '</p>' +
      '<p class="onboarding-body">' + step.body + '</p>' +
      '<div class="onboarding-actions"><button type="button" class="onboarding-button" id="onboardingNextBtn">' + (stepIndex === steps.length - 1 ? 'Done' : 'Next') + '</button></div>' +
      '<span class="onboarding-tooltip-code">Step ' + (stepIndex + 1) + ' of ' + steps.length + '</span>';

    const nextBtn = document.getElementById('onboardingNextBtn');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        stepIndex += 1;
        sessionStorage.setItem(onboardingStepKey, String(stepIndex));
        renderStep();
      });
    }
  };

  renderStep();
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
    showToast('Please provide your first and last name.', 'Error');
    return;
  }

  if (!consent.checked) {
    showToast('You must agree to the terms and conditions before registering.', 'Error');
    return;
  }

  const fullName = [first, middle, last].filter(Boolean).join(' ');
  showToast('Demo registration successful for ' + fullName + '. Redirecting to login.', 'Success');
  showPage('login');
});

bindFooterActions();

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
    row.className = 'demo-budget-row flex flex-wrap items-center justify-between gap-2';

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
syncBodyRoleState();
renderHomeActions();
renderQuickActions();
applyDashboardRoleVisibility();
updateDashboardWelcome();
setActiveRoleTag();
syncReplayVisibility();
initPasswordToggles();
initTrendChart();
