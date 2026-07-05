import React, { useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import '../../css/app.css';
import '../../css/reports/burn-rate-analysis.css';

const endpoint = '/api/reports/burn-rate-analysis';
const storageKey = 'expense-tracker-burn-rate-token';

const money = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function toNumber(value) {
    return Number.parseFloat(value || 0);
}

function formatMoney(value) {
    return money.format(toNumber(value));
}

function formatMonthLabel(label, fallback) {
    return label || fallback || 'Unknown';
}

function BurnRateAnalysisApp() {
    const [token, setToken] = useState(() => window.localStorage.getItem(storageKey) || '');
    const [status, setStatus] = useState('');
    const [isError, setIsError] = useState(false);
    const [loading, setLoading] = useState(false);
    const [report, setReport] = useState(null);

    const chart = useMemo(() => {
        const rows = report?.table || [];
        const labels = report?.graph?.labels || [];
        const datasets = report?.graph?.datasets || [];
        const maxValue = Math.max(
            1,
            ...datasets.flatMap((dataset) => dataset.data.map((value) => toNumber(value))),
        );

        return {
            rows,
            labels,
            datasets,
            maxValue,
            scale: [1, 0.72, 0.44, 0.16].map((step) => Math.ceil(maxValue * step)),
        };
    }, [report]);

    async function loadReport() {
        const trimmedToken = token.trim();

        if (!trimmedToken) {
            setIsError(true);
            setStatus('Bearer token is required to load the burn rate analysis.');
            return;
        }

        setLoading(true);
        setIsError(false);
        setStatus('Loading burn rate analysis...');

        try {
            const response = await fetch(endpoint, {
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${trimmedToken}`,
                },
            });

            const payload = await response.json();

            if (!response.ok || payload.isExecute !== 'success') {
                throw new Error(payload.msg || 'Failed to load burn rate analysis.');
            }

            window.localStorage.setItem(storageKey, trimmedToken);
            setReport(payload.data);
            setStatus(`Loaded ${payload.data.summary.months_tracked} month(s) of expense activity.`);
        } catch (error) {
            setReport(null);
            setIsError(true);
            setStatus(error.message || 'Unable to load burn rate analysis.');
        } finally {
            setLoading(false);
        }
    }

    const summary = report?.summary;
    const peakMonthRow = report?.table?.find((row) => row.month_label === summary?.peak_month);

    return (
        <main className="burn-rate-page">
            <section className="burn-rate-shell">
                <header className="burn-rate-hero">
                    <div className="burn-rate-hero-copy">
                        <span className="burn-rate-kicker">Expense Tracker Reports</span>
                        <h1>Burn Rate Analysis</h1>
                        <p>
                            Track how fast spending moves each month, compare total outflow against average
                            daily burn, and spot the periods where your budget heated up the most.
                        </p>
                    </div>

                    <div className="burn-rate-token-card">
                        <label htmlFor="report-token">Bearer Token</label>
                        <input
                            id="report-token"
                            type="text"
                            value={token}
                            onChange={(event) => setToken(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    loadReport();
                                }
                            }}
                            placeholder="Paste a JWT to access the authenticated report"
                        />
                        <button type="button" onClick={loadReport} disabled={loading}>
                            {loading ? 'Loading...' : 'Load Report'}
                        </button>
                        <p className={`burn-rate-status${status ? ' is-visible' : ''}${isError ? ' is-error' : ''}`}>
                            {status}
                        </p>
                    </div>
                </header>

                <section className="burn-rate-metrics">
                    <article className="metric-card sun-card">
                        <span>Months Tracked</span>
                        <strong>{summary?.months_tracked ?? 0}</strong>
                        <p>Monthly expense periods included in the analysis.</p>
                    </article>
                    <article className="metric-card ember-card">
                        <span>Total Expense</span>
                        <strong>{formatMoney(summary?.total_expense)}</strong>
                        <p>Combined expense amount across all tracked months.</p>
                    </article>
                    <article className="metric-card tide-card">
                        <span>Average Burn Rate</span>
                        <strong>{formatMoney(summary?.average_burn_rate)}</strong>
                        <p>Mean daily spending across the tracked months.</p>
                    </article>
                    <article className="metric-card dusk-card">
                        <span>Peak Month</span>
                        <strong>{summary?.peak_month ?? 'No data'}</strong>
                        <p>
                            {peakMonthRow
                                ? `${formatMoney(peakMonthRow.total_expense)} spent across ${peakMonthRow.active_days} active day(s).`
                                : 'Load the report to reveal the highest-spend month.'}
                        </p>
                    </article>
                </section>

                <section className="burn-rate-layout">
                    <article className="panel-card">
                        <div className="panel-heading">
                            <div>
                                <span className="burn-rate-kicker">Visual Trend</span>
                                <h2>Monthly burn rhythm</h2>
                            </div>
                            <p>Total expense and average daily burn share the same monthly timeline.</p>
                        </div>

                        {chart.rows.length === 0 ? (
                            <div className="empty-state">Load the report to render the chart.</div>
                        ) : (
                            <div className="chart-card">
                                <div className="chart-scale">
                                    {chart.scale.map((value, index) => (
                                        <span key={`${value}-${index}`}>{formatMoney(value)}</span>
                                    ))}
                                    <span>0.00</span>
                                </div>

                                <div className="chart-board">
                                    {chart.labels.map((label, labelIndex) => (
                                        <div className="chart-group" key={label}>
                                            <div className="chart-bars">
                                                {chart.datasets.map((dataset) => {
                                                    const barValue = toNumber(dataset.data[labelIndex]);
                                                    const barHeight = Math.max((barValue / chart.maxValue) * 100, barValue > 0 ? 10 : 0);

                                                    return (
                                                        <div className="chart-bar-wrap" key={`${label}-${dataset.label}`}>
                                                            <div
                                                                className="chart-bar"
                                                                style={{
                                                                    height: `${barHeight}%`,
                                                                    '--bar-color': dataset.color,
                                                                }}
                                                            >
                                                                <span>{`${dataset.label}: ${formatMoney(barValue)}`}</span>
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                            <div className="chart-label">{formatMonthLabel(label, chart.rows[labelIndex]?.month)}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        <div className="legend-row">
                            {(chart.datasets || []).map((dataset) => (
                                <div className="legend-item" key={dataset.label}>
                                    <span className="legend-dot" style={{ backgroundColor: dataset.color }} />
                                    <span>{dataset.label}</span>
                                </div>
                            ))}
                        </div>
                    </article>

                    <article className="panel-card">
                        <div className="panel-heading">
                            <div>
                                <span className="burn-rate-kicker">Detailed Table</span>
                                <h2>Monthly breakdown</h2>
                            </div>
                            <p>Use the table when you need precise totals, active days, and burn-rate values.</p>
                        </div>

                        <div className="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Total Expense</th>
                                        <th>Active Days</th>
                                        <th>Avg Daily Burn Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report?.table?.length ? (
                                        report.table.map((row) => (
                                            <tr key={row.month}>
                                                <td>
                                                    <strong>{row.month_label}</strong>
                                                    <small>{row.month}</small>
                                                </td>
                                                <td>{formatMoney(row.total_expense)}</td>
                                                <td>{row.active_days}</td>
                                                <td>{formatMoney(row.avg_daily_burn_rate)}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="4" className="table-empty">
                                                Load the report to populate the monthly breakdown.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </article>
                </section>
            </section>
        </main>
    );
}

const rootElement = document.getElementById('burn-rate-analysis-root');

if (rootElement) {
    createRoot(rootElement).render(<BurnRateAnalysisApp />);
}
