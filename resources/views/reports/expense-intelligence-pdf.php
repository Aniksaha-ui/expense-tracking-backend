<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?></title>
    <style>
        @page { margin: 26px 30px 34px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: Helvetica, Arial, sans-serif; font-size: 9px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .brand { color: #147d64; font-size: 9px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { color: #172033; font-size: 22px; line-height: 1.1; margin: 4px 0 3px; }
        .subtitle { color: #667085; font-size: 9px; }
        .user-box { background: #f2f7f5; border: 1px solid #d8e8e2; border-radius: 7px; padding: 9px 11px; text-align: right; }
        .user-label { color: #667085; font-size: 7px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .user-name { color: #172033; font-size: 12px; font-weight: bold; margin-top: 3px; }
        .period { background: #147d64; border-radius: 8px; color: #ffffff; margin: 13px 0 11px; padding: 11px 14px; }
        .period-label { color: #cde9df; font-size: 7px; font-weight: bold; letter-spacing: 1.1px; text-transform: uppercase; }
        .period-date { font-size: 15px; font-weight: bold; margin-top: 4px; }
        .period-days { color: #d9eee7; font-size: 8px; text-align: right; }
        .summary-table { border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
        .summary-card { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 7px; padding: 8px 9px; }
        .summary-label { color: #667085; font-size: 7px; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .summary-value { color: #172033; font-size: 12px; font-weight: bold; margin-top: 4px; }
        .summary-value.primary { color: #147d64; }
        .section-title { font-size: 12px; font-weight: bold; margin: 11px 0 3px; }
        .section-note { color: #667085; font-size: 8px; margin-bottom: 6px; }
        .data-table { border: 1px solid #dde3ea; margin-bottom: 10px; table-layout: fixed; }
        .data-table th, .data-table td { border-bottom: 1px solid #e5e9ef; border-right: 1px solid #e5e9ef; padding: 6px 5px; text-align: right; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: 0; }
        .data-table th:last-child, .data-table td:last-child { border-right: 0; }
        .data-table th { background: #eaf4f1; color: #125f4f; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .data-table .name, .data-table .left { text-align: left; }
        .score { color: #147d64; font-weight: bold; }
        .bar-table { border-collapse: separate; border-spacing: 0 4px; margin-bottom: 9px; }
        .bar-label { color: #172033; font-weight: bold; width: 24%; }
        .bar-track { background: #e9eef4; border-radius: 5px; height: 9px; width: 100%; }
        .bar-fill { background: #147d64; border-radius: 5px; height: 9px; }
        .bar-value { color: #475467; font-weight: bold; text-align: right; width: 14%; }
        .list { margin: 0 0 9px 15px; padding: 0; }
        .list li { margin-bottom: 3px; }
        .empty { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 8px; color: #667085; font-size: 12px; padding: 28px; text-align: center; }
        .footer { bottom: -22px; color: #98a2b3; font-size: 8px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <?php
        $summary = $report['summary'];
        $periodDays = (int) $fromDate->diffInDays($toDate) + 1;
        $maxChartValue = function ($rows, $key) {
            $max = 0.0;
            foreach ($rows as $row) {
                $max = max($max, (float) ($row[$key] ?? 0));
            }
            return $max > 0 ? $max : 1;
        };
        $barWidth = function ($value, $max): int {
            return max(2, (int) round(((float) $value / $max) * 100));
        };
    ?>

    <table>
        <tr>
            <td>
                <div class="brand">Expense Tracking System</div>
                <h1><?= e($title) ?></h1>
                <div class="subtitle">Database-driven spending intelligence, category analysis, and recommendations</div>
            </td>
            <td style="width: 30%;">
                <div class="user-box">
                    <div class="user-label">Report prepared for</div>
                    <div class="user-name"><?= e($user->name) ?></div>
                    <div class="subtitle"><?= e($user->email) ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="period">
        <tr>
            <td>
                <div class="period-label">Reporting period</div>
                <div class="period-date"><?= e($fromDate->format('d M Y')) ?> - <?= e($toDate->format('d M Y')) ?></div>
            </td>
            <td class="period-days">
                <?= e($periodDays) ?> day<?= $periodDays === 1 ? '' : 's' ?><br>
                Generated <?= e(now(config('expense_intelligence_reports.timezone'))->format('d M Y, h:i A')) ?>
            </td>
        </tr>
    </table>

    <?php if (($summary['transaction_count'] ?? 0) === 0 && ($summary['total_expense'] ?? '0.00') === '0.00'): ?>
        <div class="empty">No expense data was found for this period.</div>
    <?php else: ?>
        <table class="summary-table">
            <tr>
                <td class="summary-card"><div class="summary-label">Income</div><div class="summary-value"><?= e($summary['total_income'] ?? '0.00') ?></div></td>
                <td class="summary-card"><div class="summary-label">Expense</div><div class="summary-value primary"><?= e($summary['total_expense'] ?? '0.00') ?></div></td>
                <td class="summary-card"><div class="summary-label">Net</div><div class="summary-value"><?= e($summary['net_cash_flow'] ?? $summary['net_savings'] ?? '0.00') ?></div></td>
                <td class="summary-card"><div class="summary-label">Transactions</div><div class="summary-value"><?= e($summary['transaction_count'] ?? 0) ?></div></td>
                <td class="summary-card"><div class="summary-label">Avg Txn</div><div class="summary-value"><?= e($summary['average_transaction'] ?? '0.00') ?></div></td>
                <td class="summary-card"><div class="summary-label">Categories</div><div class="summary-value"><?= e($summary['categories_used'] ?? $summary['active_category_count'] ?? 0) ?></div></td>
            </tr>
        </table>

        <?php if ($frequency === 'daily'): ?>
            <div class="section-title">Today at a glance</div>
            <table class="data-table">
                <tr><th>Historical Daily Avg</th><th>Difference</th><th>Increase</th><th>Largest Expense</th></tr>
                <tr>
                    <td><?= e($summary['historical_daily_average']) ?></td>
                    <td><?= e($summary['historical_difference']) ?></td>
                    <td><?= e($summary['historical_increase_percentage'] ?? 'N/A') ?><?= $summary['historical_increase_percentage'] === null ? '' : '%' ?></td>
                    <td><?= e($summary['largest_transaction']['amount'] ?? '0.00') ?></td>
                </tr>
            </table>
        <?php endif; ?>

        <div class="section-title"><?= $frequency === 'monthly' ? 'Monthly trend graph' : ($frequency === 'weekly' ? 'Daily spending trend graph' : 'Category spending graph') ?></div>
        <?php
            $graphRows = $frequency === 'monthly' ? $report['trend_rows'] : ($frequency === 'weekly' ? $report['chart_rows'] : $report['chart_rows']);
            $graphKey = $frequency === 'monthly' || $frequency === 'weekly' ? 'expense' : 'current_expense';
            $maxGraphValue = $maxChartValue($graphRows, $graphKey);
        ?>
        <table class="bar-table">
            <?php foreach ($graphRows as $row): ?>
                <tr>
                    <td class="bar-label"><?= e($row['label'] ?? $row['category_name']) ?></td>
                    <td><div class="bar-track"><div class="bar-fill" style="width: <?= e($barWidth($row[$graphKey] ?? 0, $maxGraphValue)) ?>%;"></div></div></td>
                    <td class="bar-value"><?= e($row[$graphKey] ?? '0.00') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="section-title">Category analysis</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="name">Category</th>
                    <th>Current</th>
                    <th>Previous</th>
                    <th>Historical Avg</th>
                    <th>Diff</th>
                    <th>Growth</th>
                    <th>Share</th>
                    <th>Count</th>
                    <th>Avg</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($report['category_rows'] ?? collect())->take(14) as $row): ?>
                    <tr>
                        <td class="name"><?= e($row['category_name']) ?></td>
                        <td><?= e($row['current_expense']) ?></td>
                        <td><?= e($row['previous_expense']) ?></td>
                        <td><?= e($row['historical_average']) ?></td>
                        <td><?= e($row['difference']) ?></td>
                        <td><?= e($row['growth_percentage'] ?? 'N/A') ?><?= $row['growth_percentage'] === null ? '' : '%' ?></td>
                        <td><?= e($row['expense_share']) ?>%</td>
                        <td><?= e($row['transaction_count']) ?></td>
                        <td><?= e($row['average_transaction']) ?></td>
                        <td class="score"><?= e($row['opportunity_score']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (! empty($report['largest_expenses']) && $report['largest_expenses']->isNotEmpty()): ?>
            <div class="section-title">Largest expenses</div>
            <table class="data-table">
                <tr><th>Date</th><th>Category</th><th>Account</th><th>Note</th><th>Amount</th></tr>
                <?php foreach ($report['largest_expenses'] as $expense): ?>
                    <tr>
                        <td class="left"><?= e($expense['transaction_date']) ?></td>
                        <td class="left"><?= e($expense['category_name'] ?? 'Uncategorized') ?></td>
                        <td class="left"><?= e($expense['account_name'] ?? 'N/A') ?></td>
                        <td class="left"><?= e($expense['note'] ?? '') ?></td>
                        <td><?= e($expense['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <div class="section-title">Money leakage</div>
        <table class="data-table">
            <tr><th>Small Txn Threshold</th><th>Small Txn Count</th><th>Total</th><th>Average</th><th>Cash Withdrawals</th></tr>
            <tr>
                <td><?= e($report['leakage']['threshold'] ?? '0.00') ?></td>
                <td><?= e($report['leakage']['transaction_count'] ?? 0) ?></td>
                <td><?= e($report['leakage']['total_amount'] ?? '0.00') ?></td>
                <td><?= e($report['leakage']['average_amount'] ?? '0.00') ?></td>
                <td><?= e($report['leakage']['cash_withdrawals'] ?? 'N/A') ?></td>
            </tr>
        </table>

        <?php if ($frequency === 'monthly'): ?>
            <div class="section-title">Next month forecast and budget</div>
            <div class="section-note"><?= e($report['forecast']['basis']) ?></div>
            <table class="data-table">
                <tr><th>Expected</th><th>Range Low</th><th>Range High</th><th>Expense Target</th><th>Savings Target</th><th>Discretionary Limit</th></tr>
                <tr>
                    <td><?= e($report['forecast']['expected']) ?></td>
                    <td><?= e($report['forecast']['range_low']) ?></td>
                    <td><?= e($report['forecast']['range_high']) ?></td>
                    <td><?= e($report['budget']['expense_target']) ?></td>
                    <td><?= e($report['budget']['savings_target']) ?></td>
                    <td><?= e($report['budget']['discretionary_limit']) ?></td>
                </tr>
            </table>
            <div class="section-title">Potential savings estimate</div>
            <table class="data-table">
                <tr><th>Monthly Low</th><th>Monthly High</th><th>Annual Low</th><th>Annual High</th></tr>
                <tr>
                    <td><?= e($report['potential_savings']['low']) ?></td>
                    <td><?= e($report['potential_savings']['high']) ?></td>
                    <td><?= e($report['potential_savings']['annual_low']) ?></td>
                    <td><?= e($report['potential_savings']['annual_high']) ?></td>
                </tr>
            </table>
        <?php endif; ?>

        <?php foreach (['insights' => 'Insights', 'alerts' => 'Alerts', 'risks' => 'Next month risk areas', 'recommendations' => 'Recommendations / Action plan'] as $key => $label): ?>
            <?php if (! empty($report[$key])): ?>
                <div class="section-title"><?= e($label) ?></div>
                <ul class="list">
                    <?php foreach ($report[$key] as $item): ?>
                        <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        <?= e(config('app.name')) ?> &middot; <?= e($title) ?> &middot;
        <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>
    </div>
</body>
</html>
