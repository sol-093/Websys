/* ================================================
   INVOLVE - DASHBOARD PAGE BEHAVIOR
   ================================================

    SECTION MAP:
   1. Live Timestamp
   2. Progress Bars
   3. Chart.js Rendering and Empty States
   4. Dark Mode Chart Refresh
   5. Dashboard Modals
   6. Transaction History Filters

    WORK GUIDE:
   - Edit this file for dashboard-only browser behavior.
   ================================================ */

(function () {
    const initDashboardLiveTimestamp = function () {
        const stamp = document.getElementById('dashboardLiveTimestamp');
        if (!stamp) {
            return;
        }

        const formatter = new Intl.DateTimeFormat(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });

        const render = function () {
            const now = new Date();
            const parts = formatter.formatToParts(now);
            const getPart = function (type) {
                const part = parts.find(function (item) {
                    return item.type === type;
                });
                return part ? part.value : '';
            };

            const text = getPart('weekday') + ', ' + getPart('month') + ' ' + getPart('day') + ', ' + getPart('year') + ' | ' + getPart('hour') + ':' + getPart('minute') + ' ' + getPart('dayPeriod').toUpperCase();
            stamp.textContent = text;
        };

        render();
        window.setInterval(render, 60000);
    };

    initDashboardLiveTimestamp();

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
    const summaryExpenseLabels = Array.isArray(payload.summaryExpenseLabels) ? payload.summaryExpenseLabels : [];
    const summaryExpenseValues = Array.isArray(payload.summaryExpenseValues) ? payload.summaryExpenseValues : [];

    const isDark = document.body.classList.contains('theme-dark');
    const axisColor = isDark ? '#a7f3d0' : '#065f46';
    const legendColor = isDark ? '#a7f3d0' : '#065f46';
    const gridColor = isDark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';
    const formatTooltipMoney = function (value) {
        const amount = Number.parseFloat(value);
        const safeAmount = Number.isFinite(amount) ? amount : 0;
        return 'PHP' + new Intl.NumberFormat(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(safeAmount);
    };

    const buildSystemChartTooltip = function () {
        return {
            enabled: true,
            displayColors: true,
            backgroundColor: 'rgba(4, 24, 18, 0.96)',
            borderColor: function (context) {
                const item = context.tooltip && context.tooltip.dataPoints ? context.tooltip.dataPoints[0] : null;
                return item && item.dataset && item.dataset.label === 'Expense'
                    ? 'rgba(251, 113, 133, 0.58)'
                    : 'rgba(110, 231, 183, 0.58)';
            },
            borderWidth: 1,
            titleColor: '#d1fae5',
            bodyColor: '#d1fae5',
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 12, weight: '600' },
            padding: { top: 8, right: 10, bottom: 8, left: 10 },
            cornerRadius: 8,
            caretSize: 7,
            caretPadding: 8,
            boxPadding: 4,
            boxWidth: 10,
            boxHeight: 10,
            callbacks: {
                label: function (context) {
                    const label = context.dataset && context.dataset.label ? context.dataset.label : 'Value';
                    return label + ': ' + formatTooltipMoney(context.parsed.y);
                },
                labelColor: function (context) {
                    const isExpense = context.dataset && context.dataset.label === 'Expense';
                    const color = isExpense ? '#fb7185' : '#6ee7b7';
                    return {
                        borderColor: color,
                        backgroundColor: color,
                        borderWidth: 1,
                        borderRadius: 2,
                    };
                },
            },
        };
    };

    const canRenderCharts = typeof Chart !== 'undefined';
    let chart = null;

    const trendFallback = document.getElementById('trendChartFallback');
    const rankingFallback = document.getElementById('financialSummaryRankingFallback');

    const setFallbackVisibility = function (fallbackNode, canvasNode, shouldShow) {
        if (fallbackNode) {
            fallbackNode.classList.toggle('hidden', !shouldShow);
        }
        if (canvasNode) {
            canvasNode.classList.toggle('hidden', shouldShow);
        }
    };

    const initDashboardProgressBars = function () {
        const progressBars = Array.from(document.querySelectorAll('.dashboard-progress > span[data-width]'));
        if (progressBars.length === 0) {
            return;
        }

        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        progressBars.forEach(function (bar) {
            const targetWidth = Number.parseFloat(bar.getAttribute('data-width') || '0');
            const safeWidth = Number.isFinite(targetWidth) ? Math.max(0, Math.min(100, targetWidth)) : 0;

            bar.style.width = '0%';

            if (reduceMotion) {
                bar.style.transition = 'none';
                bar.style.width = safeWidth + '%';
                return;
            }

            window.setTimeout(function () {
                window.requestAnimationFrame(function () {
                    bar.style.width = safeWidth + '%';
                });
            }, 50);
        });
    };

    const initDashboardRankingToggle = function () {
        const chart = document.getElementById('dashboardRankingChart');
        const toggle = document.getElementById('dashboardRankingToggle');
        if (!chart || !toggle) {
            return;
        }

        const balanceRows = chart.querySelector('[data-chart-mode="balance"]');
        const expenseRows = chart.querySelector('[data-chart-mode="expense"]');
        const balanceAxis = document.querySelector('[data-chart-axis="balance"]');
        const expenseAxis = document.querySelector('[data-chart-axis="expense"]');
        if (!balanceRows || !expenseRows) {
            return;
        }

        let animationTimer = 0;
        const setMode = function (nextMode) {
            const showExpense = nextMode === 'expense';
            chart.classList.remove('is-chart-animating');
            chart.classList.toggle('is-expense-mode', showExpense);
            toggle.classList.toggle('is-expense-mode', showExpense);
            toggle.textContent = showExpense ? 'Top Expenses' : 'Top Net Balance';
            toggle.setAttribute('aria-pressed', showExpense ? 'true' : 'false');

            balanceRows.hidden = showExpense;
            expenseRows.hidden = !showExpense;
            if (balanceAxis) {
                balanceAxis.hidden = showExpense;
            }
            if (expenseAxis) {
                expenseAxis.hidden = !showExpense;
            }

            window.clearTimeout(animationTimer);
            window.requestAnimationFrame(function () {
                chart.classList.add('is-chart-animating');
                animationTimer = window.setTimeout(function () {
                    chart.classList.remove('is-chart-animating');
                }, 620);
            });
        };

        const toggleMode = function () {
            setMode(chart.classList.contains('is-expense-mode') ? 'balance' : 'expense');
        };

        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            toggleMode();
        });

        toggle.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            toggleMode();
        });
    };

    const hasData = function (datasets) {
        if (!Array.isArray(datasets)) {
            return false;
        }

        return datasets.some(function (dataset) {
            if (!Array.isArray(dataset)) {
                return false;
            }

            return dataset.some(function (value) {
                const numeric = Number(value);
                return Number.isFinite(numeric) && numeric > 0;
            });
        });
    };

    const createEmptyStateNode = function (id) {
        const node = document.createElement('div');
        node.id = id;
        node.setAttribute('data-chart-empty-state', '1');
        node.className = 'mt-3 rounded border border-emerald-200/40 bg-emerald-50/30 px-4 py-4 flex flex-col items-center justify-center text-center gap-1';
        node.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-emerald-600" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19.5h16"></path><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V10"></path><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V6"></path><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v-3"></path></svg><p class="m-0 text-sm font-semibold" data-empty-title>No data yet</p><p class="m-0 text-xs" data-empty-subtext>Transactions will appear here once recorded.</p>';
        return node;
    };

    const ensureEmptyStateNode = function (canvasNode) {
        if (!canvasNode) {
            return null;
        }

        const nodeId = canvasNode.id + 'EmptyState';
        let emptyNode = document.getElementById(nodeId);
        if (!emptyNode) {
            emptyNode = createEmptyStateNode(nodeId);
            canvasNode.insertAdjacentElement('afterend', emptyNode);
        }

        return emptyNode;
    };

    const applyEmptyStateTheme = function () {
        const dark = document.body.classList.contains('theme-dark');
        document.querySelectorAll('[data-chart-empty-state="1"]').forEach(function (node) {
            const titleNode = node.querySelector('[data-empty-title]');
            const subTextNode = node.querySelector('[data-empty-subtext]');
            node.classList.toggle('bg-emerald-50/30', !dark);
            node.classList.toggle('border-emerald-200/40', !dark);
            node.classList.toggle('bg-emerald-900/20', dark);
            node.classList.toggle('border-emerald-300/30', dark);
            if (titleNode) {
                titleNode.classList.toggle('text-slate-700', !dark);
                titleNode.classList.toggle('text-emerald-100', dark);
            }
            if (subTextNode) {
                subTextNode.classList.toggle('text-gray-500', !dark);
                subTextNode.classList.toggle('text-emerald-200/75', dark);
            }
        });
    };

    const setChartEmptyState = function (canvasNode, shouldShowEmpty) {
        if (!canvasNode) {
            return;
        }

        const emptyNode = ensureEmptyStateNode(canvasNode);
        if (emptyNode) {
            emptyNode.classList.toggle('hidden', !shouldShowEmpty);
        }
        canvasNode.classList.toggle('hidden', shouldShowEmpty);
        applyEmptyStateTheme();
    };

    setFallbackVisibility(trendFallback, canvas, !canRenderCharts);

    const trendHasData = hasData([income, expense]);
    if (canRenderCharts) {
        if (trendHasData) {
            setChartEmptyState(canvas, false);
        } else {
            setFallbackVisibility(trendFallback, canvas, false);
            setChartEmptyState(canvas, true);
        }
    }

    if (canRenderCharts && trendHasData) {
        chart = new Chart(canvas, {
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
                        tension: 0.35,
                        pointHitRadius: 18,
                        pointHoverRadius: 7
                    },
                    {
                        label: 'Expense',
                        data: expense,
                        borderColor: '#f87171',
                        backgroundColor: 'rgba(248, 113, 113, 0.16)',
                        fill: true,
                        tension: 0.35,
                        pointHitRadius: 18,
                        pointHoverRadius: 7
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                hover: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                plugins: {
                    legend: { labels: { color: legendColor } },
                    tooltip: buildSystemChartTooltip()
                },
                scales: {
                    x: { ticks: { color: axisColor }, grid: { color: gridColor } },
                    y: { ticks: { color: axisColor }, grid: { color: gridColor } }
                }
            }
        });
    }

    let financialRankingChart = null;
    let financialRankingMode = 'balance';

    const isDashboardMobile = function () {
        return window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
    };

    const isDashboardCompact = function () {
        return window.matchMedia && window.matchMedia('(max-width: 1279px)').matches;
    };

    const shortenChartLabel = function (label, maxLength) {
        const value = String(label || '');
        if (value.length <= maxLength) {
            return value;
        }

        return value.slice(0, Math.max(0, maxLength - 3)).trimEnd() + '...';
    };

    const createFinancialCharts = function () {
        if (financialRankingChart || !canRenderCharts) {
            return;
        }

        const rankingCanvas = document.getElementById('financialSummaryRankingChart');
        if (!rankingCanvas) {
            return;
        }

        const rankingHasData = summaryRankingLabels.length > 0 && hasData([summaryRankingBalances]);
        if (!rankingHasData) {
            setFallbackVisibility(rankingFallback, rankingCanvas, false);
            setChartEmptyState(rankingCanvas, true);
            return;
        }

        setFallbackVisibility(rankingFallback, rankingCanvas, false);
        setChartEmptyState(rankingCanvas, false);

        const mobileChart = isDashboardMobile();
        const compactChart = isDashboardCompact();
        const rankingFontSize = mobileChart ? 10 : (compactChart ? 11 : 12);
        const rankingLabelLength = mobileChart ? 16 : (compactChart ? 24 : 28);
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
                maintainAspectRatio: false,
                indexAxis: 'y',
                layout: {
                    padding: mobileChart
                        ? { left: 0, right: 8, top: 4, bottom: 0 }
                        : { left: 0, right: 12, top: 4, bottom: 0 }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: legendColor,
                            boxWidth: 28,
                            font: {
                                size: rankingFontSize
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: axisColor,
                            maxTicksLimit: mobileChart ? 4 : (compactChart ? 5 : 6),
                            font: {
                                size: rankingFontSize
                            }
                        },
                        grid: { color: gridColor }
                    },
                    y: {
                        ticks: {
                            color: axisColor,
                            autoSkip: false,
                            font: {
                                size: rankingFontSize
                            },
                            callback: function (value) {
                                const label = this.getLabelForValue(value);
                                return shortenChartLabel(label, rankingLabelLength);
                            }
                        },
                        grid: { color: 'rgba(0,0,0,0)' }
                    }
                }
            }
        });
    };

    const updateFinancialRankingMode = function (mode) {
        if (!financialRankingChart) {
            createFinancialCharts();
        }

        if (!financialRankingChart) {
            return;
        }

        const nextMode = mode === 'expense' ? 'expense' : 'balance';
        const toggle = document.getElementById('financialSummaryRankingToggle');
        const values = nextMode === 'expense' ? summaryExpenseValues : summaryRankingBalances;
        const labelsForMode = nextMode === 'expense' ? summaryExpenseLabels : summaryRankingLabels;
        const chartHasData = labelsForMode.length > 0 && hasData([values]);

        if (!chartHasData) {
            return;
        }

        financialRankingMode = nextMode;
        financialRankingChart.data.labels = labelsForMode;
        financialRankingChart.data.datasets[0].label = nextMode === 'expense' ? 'Top Expenses' : 'Net Balance';
        financialRankingChart.data.datasets[0].data = values;
        financialRankingChart.data.datasets[0].backgroundColor = values.map(function (value) {
            if (nextMode === 'expense') {
                return 'rgba(248, 113, 113, 0.75)';
            }
            return value >= 0 ? 'rgba(52, 211, 153, 0.75)' : 'rgba(248, 113, 113, 0.72)';
        });
        financialRankingChart.data.datasets[0].borderColor = values.map(function (value) {
            if (nextMode === 'expense') {
                return 'rgba(220, 38, 38, 1)';
            }
            return value >= 0 ? 'rgba(16, 185, 129, 1)' : 'rgba(239, 68, 68, 1)';
        });
        financialRankingChart.update();

        if (toggle) {
            toggle.textContent = nextMode === 'expense' ? 'Top Organizations by Expense' : 'Top Organizations by Net Balance';
            toggle.setAttribute('aria-pressed', nextMode === 'expense' ? 'true' : 'false');
        }
    };

    const initFinancialRankingToggle = function () {
        document.addEventListener('click', function (event) {
            const toggle = event.target instanceof Element ? event.target.closest('#financialSummaryRankingToggle') : null;
            if (!toggle) {
                return;
            }

            event.preventDefault();
            updateFinancialRankingMode(financialRankingMode === 'balance' ? 'expense' : 'balance');
        });
    };

    const applyThemeToCharts = function () {
        const dark = document.body.classList.contains('theme-dark');
        const nextAxis = dark ? '#a7f3d0' : '#065f46';
        const nextLegend = dark ? '#a7f3d0' : '#065f46';
        const nextGrid = dark ? 'rgba(167,243,208,0.12)' : 'rgba(16,185,129,0.16)';

        if (chart) {
            chart.options.plugins.legend.labels.color = nextLegend;
            chart.options.scales.x.ticks.color = nextAxis;
            chart.options.scales.y.ticks.color = nextAxis;
            chart.options.scales.x.grid.color = nextGrid;
            chart.options.scales.y.grid.color = nextGrid;
            chart.update();
        }

        if (financialRankingChart) {
            financialRankingChart.options.plugins.legend.labels.color = nextLegend;
            financialRankingChart.options.scales.x.ticks.color = nextAxis;
            financialRankingChart.options.scales.y.ticks.color = nextAxis;
            financialRankingChart.options.scales.x.grid.color = nextGrid;
            if (financialRankingMode === 'expense') {
                financialRankingChart.data.datasets[0].backgroundColor = financialRankingChart.data.datasets[0].data.map(function () {
                    return 'rgba(248, 113, 113, 0.75)';
                });
                financialRankingChart.data.datasets[0].borderColor = financialRankingChart.data.datasets[0].data.map(function () {
                    return 'rgba(220, 38, 38, 1)';
                });
            }
            financialRankingChart.update();
        }
    };

    if (!canRenderCharts) {
        const rankingCanvas = document.getElementById('financialSummaryRankingChart');
        setFallbackVisibility(rankingFallback, rankingCanvas, true);
    }

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
                applyEmptyStateTheme();
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

    const debounce = function (fn, delay) {
        let timerId = null;
        return function () {
            const args = arguments;
            if (timerId !== null) {
                window.clearTimeout(timerId);
            }
            timerId = window.setTimeout(function () {
                fn.apply(null, args);
            }, delay);
        };
    };

    const initTransactionHistoryFilters = function () {
        const tables = Array.from(document.querySelectorAll('.dashboard-table'));
        const transactionsTable = tables.find(function (table) {
            const headerCells = Array.from(table.querySelectorAll('thead th')).map(function (cell) {
                return (cell.textContent || '').toLowerCase();
            });

            return headerCells.includes('organization') && headerCells.includes('amount') && headerCells.includes('type');
        });

        if (!transactionsTable) {
            return;
        }

        const transactionsPanel = transactionsTable.closest('.dashboard-panel');
        const filterForm = transactionsPanel ? transactionsPanel.querySelector('form') : null;
        const tbody = transactionsTable.querySelector('tbody');
        if (!tbody) {
            return;
        }

        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (rows.length === 0) {
            return;
        }

        const totalCount = rows.length;
        const countLabel = transactionsPanel
            ? (transactionsPanel.querySelector('[data-tx-results-count]') || transactionsPanel.querySelector('.dashboard-stamp'))
            : null;
        if (countLabel) {
            countLabel.setAttribute('data-tx-results-count', '1');
        }

        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'hidden';
        noResultsRow.setAttribute('data-tx-no-results', '1');
        noResultsRow.innerHTML = '<td colspan="5" class="py-3 text-center text-sm text-gray-500">No results found.</td>';
        tbody.appendChild(noResultsRow);

        rows.forEach(function (row) {
            const cells = row.querySelectorAll('td');
            if (!row.dataset.type && cells[2]) {
                row.dataset.type = (cells[2].textContent || '').trim().toLowerCase();
            }
            if (!row.dataset.date && cells[0]) {
                row.dataset.date = (cells[0].textContent || '').trim();
            }
            if (!row.dataset.organization && cells[1]) {
                row.dataset.organization = (cells[1].textContent || '').trim();
            }

            const orgText = cells[1] ? (cells[1].textContent || '') : '';
            const amountText = cells[3] ? (cells[3].textContent || '') : '';
            const descriptionText = row.dataset.description || (cells[4] ? (cells[4].textContent || '') : '');
            row.dataset.searchText = (orgText + ' ' + descriptionText + ' ' + amountText).toLowerCase();
        });

        const searchInput = transactionsPanel
            ? transactionsPanel.querySelector('input[type="search"], input[data-tx-search], input[name="search"], input[name="q"]')
            : null;

        const getActiveFilterValue = function (nameCandidates) {
            if (!transactionsPanel) {
                return '';
            }

            for (let i = 0; i < nameCandidates.length; i++) {
                const candidate = nameCandidates[i];
                const input = transactionsPanel.querySelector('input[name="' + candidate + '"]');
                if (input && typeof input.value === 'string') {
                    return input.value.trim().toLowerCase();
                }
            }

            return '';
        };

        const getMobileLimit = function () {
            return window.matchMedia && window.matchMedia('(max-width: 767px)').matches ? 5 : null;
        };

        const applyFilters = function () {
            const typeFilter = getActiveFilterValue(['tx_type', 'type', 'filter_type']);
            const dateFilter = getActiveFilterValue(['tx_date', 'date', 'filter_date']);
            const orgFilter = getActiveFilterValue(['org', 'organization', 'filter_org']);
            const searchValue = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const mobileLimit = getMobileLimit();

            let visibleCount = 0;
            let displayedCount = 0;

            rows.forEach(function (row) {
                const rowType = (row.dataset.type || '').toLowerCase();
                const rowDate = (row.dataset.date || '').toLowerCase();
                const rowOrg = (row.dataset.organization || '').toLowerCase();
                const rowText = (row.dataset.searchText || '').toLowerCase();

                const matchesType = typeFilter === '' || typeFilter === 'all' || rowType === typeFilter;
                const matchesDate = dateFilter === '' || dateFilter === 'all' || rowDate.indexOf(dateFilter) !== -1;
                const matchesOrg = orgFilter === '' || orgFilter === 'all' || rowOrg.indexOf(orgFilter) !== -1;
                const matchesSearch = searchValue === '' || rowText.indexOf(searchValue) !== -1;

                const shouldShow = matchesType && matchesDate && matchesOrg && matchesSearch;
                if (shouldShow) {
                    visibleCount++;
                }

                const withinMobileLimit = mobileLimit === null || displayedCount < mobileLimit;
                row.style.display = shouldShow && withinMobileLimit ? '' : 'none';
                if (shouldShow && withinMobileLimit) {
                    displayedCount++;
                }
            });

            noResultsRow.classList.toggle('hidden', visibleCount > 0);

            if (countLabel) {
                countLabel.textContent = 'Showing ' + displayedCount + ' of ' + visibleCount;
            }
        };

        const debouncedApplyFilters = debounce(applyFilters, 250);

        if (searchInput) {
            searchInput.addEventListener('input', debouncedApplyFilters);
        }

        if (transactionsPanel) {
            transactionsPanel.querySelectorAll('input[data-dropdown-value]').forEach(function (input) {
                input.addEventListener('change', applyFilters);

                if (typeof MutationObserver !== 'undefined') {
                    const observer = new MutationObserver(function (mutations) {
                        const hasValueChange = mutations.some(function (mutation) {
                            return mutation.type === 'attributes' && mutation.attributeName === 'value';
                        });

                        if (hasValueChange) {
                            applyFilters();
                        }
                    });
                    observer.observe(input, { attributes: true, attributeFilter: ['value'] });
                }
            });

            transactionsPanel.addEventListener('click', function (event) {
                const optionButton = event.target.closest('[data-dropdown-option]');
                if (!optionButton) {
                    return;
                }

                window.setTimeout(applyFilters, 0);
            });
        }

        if (filterForm) {
            filterForm.addEventListener('submit', function (event) {
                event.preventDefault();
                applyFilters();
            });
        }

        if (window.matchMedia) {
            const mobileQuery = window.matchMedia('(max-width: 767px)');
            if (typeof mobileQuery.addEventListener === 'function') {
                mobileQuery.addEventListener('change', applyFilters);
            } else if (typeof mobileQuery.addListener === 'function') {
                mobileQuery.addListener(applyFilters);
            }
        }

        applyFilters();
    };

    initTransactionHistoryFilters();
    initDashboardProgressBars();
    initDashboardRankingToggle();
    initFinancialRankingToggle();

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
