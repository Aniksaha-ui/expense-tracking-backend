<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Current vs Previous Month Analysis</title>
    <style>
        :root {
            --bg: #f4efe6;
            --panel: rgba(255, 251, 245, 0.92);
            --ink: #1f2937;
            --muted: #6b7280;
            --line: rgba(148, 163, 184, 0.35);
            --income: #0f766e;
            --expense: #c2410c;
            --recurring: #7c3aed;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.16), transparent 32%),
                radial-gradient(circle at top right, rgba(124, 58, 237, 0.12), transparent 30%),
                linear-gradient(180deg, #f9f4ea 0%, var(--bg) 100%);
        }

        .shell {
            width: min(1180px, calc(100% - 32px));
            margin: 32px auto;
        }

        .hero, .panel {
            background: var(--panel);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 28px;
            box-shadow: var(--shadow);
        }

        .hero {
            padding: 32px;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto -80px -80px auto;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(194, 65, 12, 0.18), transparent 70%);
        }

        h1 {
            margin: 0 0 8px;
            font-size: clamp(2rem, 4vw, 3.5rem);
            line-height: 0.95;
        }

        .subtitle {
            max-width: 720px;
            margin: 0;
            color: var(--muted);
            font-size: 1rem;
            line-height: 1.6;
        }

        .token-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            margin-top: 24px;
        }

        .token-row input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid var(--line);
            font-size: 0.95rem;
            background: rgba(255, 255, 255, 0.8);
        }

        .token-row button {
            border: 0;
            border-radius: 16px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #0f766e, #155e75);
            color: white;
            font-weight: 700;
            cursor: pointer;
        }

        .grid {
            display: grid;
            gap: 20px;
            margin-top: 24px;
        }

        .metrics {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .metric-card {
            padding: 22px;
        }

        .eyebrow {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .metric-value {
            font-size: 1.9rem;
            line-height: 1;
        }

        .metric-note {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        .layout {
            grid-template-columns: minmax(0, 1.5fr) minmax(320px, 0.9fr);
            align-items: start;
        }

        .panel {
            padding: 24px;
        }

        .panel h2 {
            margin: 0 0 6px;
            font-size: 1.35rem;
        }

        .panel p {
            margin: 0 0 20px;
            color: var(--muted);
        }

        .chart {
            display: grid;
            grid-template-columns: 56px 1fr;
            gap: 18px;
            align-items: end;
            min-height: 360px;
        }

        .chart-scale {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 320px;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .chart-board {
            position: relative;
            height: 320px;
            padding: 12px 8px 0;
            border-left: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-around;
            gap: 18px;
        }

        .chart-board::before,
        .chart-board::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            border-top: 1px dashed rgba(148, 163, 184, 0.3);
        }

        .chart-board::before { top: 33%; }
        .chart-board::after { top: 66%; }

        .month-group {
            display: flex;
            flex-direction: column;
            justify-content: end;
            align-items: center;
            width: 100%;
            max-width: 220px;
            gap: 14px;
        }

        .bar-cluster {
            height: 100%;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: end;
            gap: 14px;
        }

        .bar {
            width: min(64px, 20%);
            min-width: 42px;
            border-radius: 18px 18px 6px 6px;
            position: relative;
            box-shadow: inset 0 -12px 16px rgba(255, 255, 255, 0.18);
            transition: transform 180ms ease;
        }

        .bar:hover { transform: translateY(-4px); }

        .bar span {
            position: absolute;
            left: 50%;
            bottom: calc(100% + 10px);
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.88);
            color: white;
            padding: 6px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .month-label {
            text-align: center;
            font-size: 0.95rem;
            color: var(--ink);
        }

        .legend {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .legend-swatch {
            width: 12px;
            height: 12px;
            border-radius: 999px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        th {
            color: var(--muted);
            font-weight: 600;
        }

        .money {
            font-variant-numeric: tabular-nums;
        }

        .status {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 16px;
            color: #7c2d12;
            background: rgba(251, 191, 36, 0.18);
            border: 1px solid rgba(251, 191, 36, 0.3);
            display: none;
        }

        .status.is-visible { display: block; }

        .empty {
            display: none;
            padding: 24px;
            text-align: center;
            color: var(--muted);
            border: 1px dashed var(--line);
            border-radius: 18px;
        }

        .empty.is-visible { display: block; }

        @media (max-width: 960px) {
            .metrics,
            .layout,
            .token-row {
                grid-template-columns: 1fr;
            }

            .chart {
                grid-template-columns: 1fr;
            }

            .chart-scale {
                display: none;
            }

            .chart-board {
                min-height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <section class="hero">
            <span class="eyebrow">Expense Tracker Reports</span>
            <h1>Current vs Previous Month Analysis</h1>
            <p class="subtitle">
                Compare income, expense, and recurring totals for the last two months. This screen uses the authenticated API and renders both a graph and a table from the same backend response.
            </p>

            <div class="token-row">
                <input id="tokenInput" type="text" placeholder="Paste Bearer token to load the report">
                <button id="loadButton" type="button">Load Report</button>
            </div>

            <div id="statusMessage" class="status"></div>
        </section>

        <section class="grid metrics" id="metricCards">
            <article class="panel metric-card">
                <span class="eyebrow">Previous Net</span>
                <div class="metric-value" id="previousNet">0.00</div>
                <div class="metric-note" id="previousPeriod">Previous month</div>
            </article>
            <article class="panel metric-card">
                <span class="eyebrow">Current Net</span>
                <div class="metric-value" id="currentNet">0.00</div>
                <div class="metric-note" id="currentPeriod">Current month</div>
            </article>
            <article class="panel metric-card">
                <span class="eyebrow">Income Change</span>
                <div class="metric-value" id="incomeChange">0.00</div>
                <div class="metric-note">Current minus previous</div>
            </article>
            <article class="panel metric-card">
                <span class="eyebrow">Outflow Change</span>
                <div class="metric-value" id="outflowChange">0.00</div>
                <div class="metric-note">Expense + recurring delta</div>
            </article>
        </section>

        <section class="grid layout">
            <article class="panel">
                <h2>Graph View</h2>
                <p>Grouped bars show how each metric moved across the previous and current month.</p>
                <div id="chartEmpty" class="empty is-visible">Load the report to see the chart.</div>
                <div class="chart" id="chartWrap" style="display:none;">
                    <div class="chart-scale" id="chartScale"></div>
                    <div class="chart-board" id="chartBoard"></div>
                </div>
                <div class="legend" id="chartLegend"></div>
            </article>

            <article class="panel">
                <h2>Table View</h2>
                <p>The same API data is also shown as a comparison table for precise values.</p>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Income</th>
                                <th>Expense</th>
                                <th>Recurring</th>
                                <th>Total Outflow</th>
                                <th>Net</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--muted);">Load the report to populate the table.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>

    <script>
        const endpoint = '/api/reports/current-vs-previous-month-analysis';
        const storageKey = 'expense-tracker-report-token';

        const tokenInput = document.getElementById('tokenInput');
        const loadButton = document.getElementById('loadButton');
        const statusMessage = document.getElementById('statusMessage');
        const tableBody = document.getElementById('tableBody');
        const chartWrap = document.getElementById('chartWrap');
        const chartBoard = document.getElementById('chartBoard');
        const chartScale = document.getElementById('chartScale');
        const chartLegend = document.getElementById('chartLegend');
        const chartEmpty = document.getElementById('chartEmpty');

        const previousNet = document.getElementById('previousNet');
        const currentNet = document.getElementById('currentNet');
        const incomeChange = document.getElementById('incomeChange');
        const outflowChange = document.getElementById('outflowChange');
        const previousPeriod = document.getElementById('previousPeriod');
        const currentPeriod = document.getElementById('currentPeriod');

        tokenInput.value = window.localStorage.getItem(storageKey) || '';

        const money = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        function setStatus(message = '', visible = false) {
            statusMessage.textContent = message;
            statusMessage.classList.toggle('is-visible', visible);
        }

        function toNumber(value) {
            return Number.parseFloat(value || 0);
        }

        function formatMoney(value) {
            const parsed = toNumber(value);
            return `${parsed < 0 ? '-' : ''}${money.format(Math.abs(parsed))}`;
        }

        function formatDelta(value) {
            const parsed = toNumber(value);
            const prefix = parsed > 0 ? '+' : '';
            return `${prefix}${formatMoney(parsed)}`;
        }

        function buildTable(rows) {
            tableBody.innerHTML = rows.map((row) => `
                <tr>
                    <td>
                        <strong>${row.period_label}</strong><br>
                        <span style="color:var(--muted); font-size:0.85rem;">${row.from_date} to ${row.to_date}</span>
                    </td>
                    <td class="money">${formatMoney(row.income)}</td>
                    <td class="money">${formatMoney(row.expense)}</td>
                    <td class="money">${formatMoney(row.recurring)}</td>
                    <td class="money">${formatMoney(row.total_outflow)}</td>
                    <td class="money">${formatMoney(row.net)}</td>
                </tr>
            `).join('');
        }

        function buildChart(graph) {
            const maxValue = Math.max(1, ...graph.datasets.flatMap((dataset) => dataset.data));
            const chartTop = Math.ceil(maxValue / 100) * 100;
            const scaleSteps = [chartTop, chartTop * 0.66, chartTop * 0.33, 0];

            chartScale.innerHTML = scaleSteps.map((value) => `<span>${money.format(value)}</span>`).join('');

            chartBoard.innerHTML = graph.labels.map((label, labelIndex) => `
                <div class="month-group">
                    <div class="bar-cluster">
                        ${graph.datasets.map((dataset) => {
                            const barValue = dataset.data[labelIndex];
                            const barHeight = Math.max((barValue / chartTop) * 100, barValue > 0 ? 8 : 0);

                            return `
                                <div class="bar" style="height:${barHeight}%; background:${dataset.color};">
                                    <span>${dataset.label}: ${formatMoney(barValue)}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                    <div class="month-label">${label}</div>
                </div>
            `).join('');

            chartLegend.innerHTML = graph.datasets.map((dataset) => `
                <div class="legend-item">
                    <span class="legend-swatch" style="background:${dataset.color};"></span>
                    <span>${dataset.label}</span>
                </div>
            `).join('');

            chartEmpty.classList.remove('is-visible');
            chartWrap.style.display = 'grid';
        }

        function updateMetrics(rows) {
            const previous = rows.find((row) => row.period_key === 'previous_month') || rows[0];
            const current = rows.find((row) => row.period_key === 'current_month') || rows[1];

            previousNet.textContent = formatMoney(previous.net);
            currentNet.textContent = formatMoney(current.net);
            incomeChange.textContent = formatDelta(toNumber(current.income) - toNumber(previous.income));
            outflowChange.textContent = formatDelta(toNumber(current.total_outflow) - toNumber(previous.total_outflow));
            previousPeriod.textContent = previous.period_label;
            currentPeriod.textContent = current.period_label;
        }

        async function loadReport() {
            const token = tokenInput.value.trim();

            if (! token) {
                setStatus('Bearer token is required to load the report.', true);
                return;
            }

            setStatus('Loading report...', true);
            loadButton.disabled = true;
            chartWrap.style.display = 'none';
            chartEmpty.classList.add('is-visible');

            try {
                const response = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`,
                    },
                });

                const payload = await response.json();

                if (! response.ok || payload.isExecute !== 'success') {
                    throw new Error(payload.msg || 'Failed to load report.');
                }

                window.localStorage.setItem(storageKey, token);
                buildTable(payload.data.table);
                buildChart(payload.data.graph);
                updateMetrics(payload.data.table);
                setStatus(`Loaded ${payload.data.months.previous_month.label} and ${payload.data.months.current_month.label} successfully.`, true);
            } catch (error) {
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--muted);">Unable to load the report.</td></tr>';
                chartLegend.innerHTML = '';
                setStatus(error.message, true);
            } finally {
                loadButton.disabled = false;
            }
        }

        loadButton.addEventListener('click', loadReport);
        tokenInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                loadReport();
            }
        });
    </script>
</body>
</html>
