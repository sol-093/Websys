(function () {
    const canvas = document.getElementById('trendChart');
    if (!canvas) return;

    const dataNode = document.getElementById('dashboard-client-data');
    if (!dataNode) return;

    let payload = {};
    try {
        payload = JSON.parse(dataNode.textContent || '{}');
    } catch (err) {
        return;
    }

    const labels = Array.isArray(payload.trendLabels) ? payload.trendLabels : [];
    const income = Array.isArray(payload.trendIncome) ? payload.trendIncome : [];
    const expense = Array.isArray(payload.trendExpense) ? payload.trendExpense : [];
    const summaryRankingLabels = Array.isArray(payload.summaryRankingLabels) ? payload.summaryRankingLabels : [];
    const summaryRankingBalances = Array.isArray(payload.summaryRankingBalances) ? payload.summaryRankingBalances : [];

    const isDark = document.body.classList.contains('theme-dark');
    const axisColor = isDark ? '#a7f3d0' : '#065f46';
    const legendColor = isDark ? '#a7f3d0' : '#065f46';
    const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

    const chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
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

    let financialRankingChart = null;

    const createFinancialCharts = function () {
        if (financialRankingChart) {
            return;
        }

        const rankingCanvas = document.getElementById('financialSummaryRankingChart');
        if (!rankingCanvas || summaryRankingLabels.length === 0) {
            return;
        }

        financialRankingChart = new Chart(rankingCanvas, {
            type: 'bar',
            data: {
                labels: summaryRankingLabels,
                datasets: [
                    {
                        label: 'Net Balance',
                        data: summaryRankingBalances,
                        backgroundColor: summaryRankingBalances.map(function (value) {
                            return value >= 0 ? 'rgba(52, 211, 153, 0.75)' : 'rgba(248, 113, 113, 0.72)';
                        }),
                        borderColor: summaryRankingBalances.map(function (value) {
                            return value >= 0 ? 'rgba(16, 185, 129, 1)' : 'rgba(239, 68, 68, 1)';
                        }),
                        borderWidth: 1,
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        labels: { color: legendColor }
                    }
                },
                scales: {
                    x: {
                        ticks: { color: axisColor },
                        grid: { color: gridColor }
                    },
                    y: {
                        ticks: { color: axisColor },
                        grid: { color: 'rgba(0,0,0,0)' }
                    }
                }
            }
        });
    };

    const applyThemeToCharts = function () {
        const dark = document.body.classList.contains('theme-dark');
        const nextAxis = dark ? '#a7f3d0' : '#065f46';
        const nextLegend = dark ? '#a7f3d0' : '#065f46';
        const nextGrid = dark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

        chart.options.plugins.legend.labels.color = nextLegend;
        chart.options.scales.x.ticks.color = nextAxis;
        chart.options.scales.y.ticks.color = nextAxis;
        chart.options.scales.x.grid.color = nextGrid;
        chart.options.scales.y.grid.color = nextGrid;
        chart.update();

        if (financialRankingChart) {
            financialRankingChart.options.plugins.legend.labels.color = nextLegend;
            financialRankingChart.options.scales.x.ticks.color = nextAxis;
            financialRankingChart.options.scales.y.ticks.color = nextAxis;
            financialRankingChart.options.scales.x.grid.color = nextGrid;
            financialRankingChart.update();
        }
    };

    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('change', function () {
            applyThemeToCharts();
        });
    }

    // Listen to theme class changes so charts update instantly regardless of which toggle control changed it.
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function (mutations) {
            const hasClassChange = mutations.some(function (mutation) {
                return mutation.type === 'attributes' && mutation.attributeName === 'class';
            });

            if (hasClassChange) {
                applyThemeToCharts();
            }
        });

        observer.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    }

    const organizationsModal = document.getElementById('organizationsModal');
    const announcementsModal = document.getElementById('announcementsModal');
    const financialSummaryModal = document.getElementById('financialSummaryModal');
    const openOrganizations = [
        document.getElementById('openOrganizationsModal'),
        document.getElementById('openOrganizationsModalQuick'),
    ].filter(Boolean);
    const openAnnouncements = [
        document.getElementById('openAnnouncementsModalQuick'),
    ].filter(Boolean);
    const openFinancialSummary = [
        document.getElementById('openFinancialSummaryModal'),
    ].filter(Boolean);
    const closeOrganizationsModal = document.getElementById('closeOrganizationsModal');
    const closeAnnouncementsModal = document.getElementById('closeAnnouncementsModal');
    const closeFinancialSummaryModal = document.getElementById('closeFinancialSummaryModal');

    const showModal = function (modal) {
        if (!modal) return;
        modal.classList.remove('hidden');
    };

    const hideModal = function (modal) {
        if (!modal) return;
        modal.classList.add('hidden');
    };

    openOrganizations.forEach(function (button) {
        button.addEventListener('click', function () {
            showModal(organizationsModal);
        });
    });

    openAnnouncements.forEach(function (button) {
        button.addEventListener('click', function () {
            showModal(announcementsModal);
        });
    });

    openFinancialSummary.forEach(function (button) {
        button.addEventListener('click', function () {
            showModal(financialSummaryModal);
            createFinancialCharts();
            applyThemeToCharts();
        });
    });

    if (closeOrganizationsModal) {
        closeOrganizationsModal.addEventListener('click', function () {
            hideModal(organizationsModal);
        });
    }

    if (closeAnnouncementsModal) {
        closeAnnouncementsModal.addEventListener('click', function () {
            hideModal(announcementsModal);
        });
    }

    if (closeFinancialSummaryModal) {
        closeFinancialSummaryModal.addEventListener('click', function () {
            hideModal(financialSummaryModal);
        });
    }

    if (organizationsModal) {
        organizationsModal.addEventListener('click', function (event) {
            if (event.target === organizationsModal) {
                hideModal(organizationsModal);
            }
        });
    }

    if (announcementsModal) {
        announcementsModal.addEventListener('click', function (event) {
            if (event.target === announcementsModal) {
                hideModal(announcementsModal);
            }
        });
    }

    if (financialSummaryModal) {
        financialSummaryModal.addEventListener('click', function (event) {
            if (event.target === financialSummaryModal) {
                hideModal(financialSummaryModal);
            }
        });
    }
})();
