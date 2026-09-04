<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cost Reduction Report</title>
    <style>
        @page { margin: 28px 34px 34px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: Helvetica, Arial, sans-serif; font-size: 10px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .brand { color: #147d64; font-size: 10px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
        h1 { color: #172033; font-size: 25px; line-height: 1.1; margin: 5px 0 3px; }
        .subtitle { color: #667085; font-size: 10px; }
        .user-box { background: #f2f7f5; border: 1px solid #d8e8e2; border-radius: 7px; padding: 10px 12px; text-align: right; }
        .user-label { color: #667085; font-size: 8px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .user-name { color: #172033; font-size: 13px; font-weight: bold; margin-top: 3px; }
        .period { background: #147d64; border-radius: 8px; color: #ffffff; margin: 15px 0 12px; padding: 12px 16px; }
        .period-label { color: #cde9df; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        .period-date { font-size: 17px; font-weight: bold; margin-top: 4px; }
        .period-days { color: #d9eee7; font-size: 9px; text-align: right; }
        .summary-table { border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 15px; }
        .summary-card { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 7px; padding: 10px 12px; width: 20%; }
        .summary-label { color: #667085; font-size: 8px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .summary-value { color: #172033; font-size: 15px; font-weight: bold; margin-top: 5px; }
        .summary-value.primary { color: #147d64; }
        .section-title { font-size: 13px; font-weight: bold; margin: 0 0 3px; }
        .section-note { color: #667085; font-size: 9px; margin-bottom: 8px; }
        .data-table { border: 1px solid #dde3ea; margin-bottom: 13px; table-layout: fixed; }
        .data-table th, .data-table td { border-bottom: 1px solid #e5e9ef; border-right: 1px solid #e5e9ef; padding: 7px 6px; text-align: right; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: 0; }
        .data-table th:last-child, .data-table td:last-child { border-right: 0; }
        .data-table th { background: #eaf4f1; color: #125f4f; font-size: 8px; font-weight: bold; text-transform: uppercase; }
        .data-table .name { color: #172033; font-weight: bold; text-align: left; width: 18%; }
        .score { color: #147d64; font-weight: bold; }
        .alert-list { margin: 0 0 14px 16px; padding: 0; }
        .alert-list li { margin-bottom: 4px; }
        .empty { background: #f8fafc; border: 1px solid #e3e8ef; border-radius: 8px; color: #667085; font-size: 13px; padding: 35px; text-align: center; }
        .footer { bottom: -22px; color: #98a2b3; font-size: 8px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <?php $periodDays = (int) $fromDate->diffInDays($toDate) + 1; ?>

    <table>
        <tr>
            <td>
                <div class="brand">Expense Tracking System</div>
                <h1>Cost Reduction Report</h1>
                <div class="subtitle">Database-driven category analysis and savings opportunities</div>
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
                <div class="period-date">
                    <?= e($fromDate->format('d M Y')) ?>
                    &nbsp;-&nbsp;
                    <?= e($toDate->format('d M Y')) ?>
                </div>
            </td>
            <td class="period-days">
                <?= e($periodDays) ?> day<?= $periodDays === 1 ? '' : 's' ?><br>
                Generated <?= e(now(config('cost_reduction_reports.timezone'))->format('d M Y, h:i A')) ?>
            </td>
        </tr>
    </table>

    <?php if ($rows->where('transaction_count', '>', 0)->isEmpty()): ?>
        <div class="empty">No expenses were recorded for this period.</div>
    <?php else: ?>
        <table class="summary-table">
            <tr>
                <td class="summary-card">
                    <div class="summary-label">Total expense</div>
                    <div class="summary-value primary"><?= e($summary['total_expense']) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Potential saving</div>
                    <div class="summary-value primary"><?= e($summary['potential_saving']) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Transactions</div>
                    <div class="summary-value"><?= e($summary['transaction_count']) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Active categories</div>
                    <div class="summary-value"><?= e($summary['active_category_count']) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Categories found</div>
                    <div class="summary-value"><?= e($summary['category_count']) ?></div>
                </td>
            </tr>
        </table>

        <div class="section-title">Top categories for charting</div>
        <div class="section-note">Labels come from current database categories; overflow is grouped only for visualization.</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="name">Category</th>
                    <th>Expense</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chartRows as $row): ?>
                    <tr>
                        <td class="name"><?= e($row['category_name']) ?></td>
                        <td><?= e($row['current_month_expense']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($alerts->isNotEmpty()): ?>
            <div class="section-title">Data-driven alerts</div>
            <ul class="alert-list">
                <?php foreach ($alerts->take(8) as $alert): ?>
                    <li><?= e($alert) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="section-title">Category opportunities</div>
        <div class="section-note">Opportunity score uses expense share, positive growth, frequency, and positive historical deviation.</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="name">Category</th>
                    <th>Current</th>
                    <th>Previous</th>
                    <th>Historical Avg</th>
                    <th>Transactions</th>
                    <th>Avg Txn</th>
                    <th>Share</th>
                    <th>Growth</th>
                    <th>Recurring</th>
                    <th>Score</th>
                    <th>Saving</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="name"><?= e($row['category_name']) ?></td>
                        <td><?= e($row['current_month_expense']) ?></td>
                        <td><?= e($row['previous_month_expense']) ?></td>
                        <td><?= e($row['historical_average']) ?></td>
                        <td><?= e($row['transaction_count']) ?></td>
                        <td><?= e($row['average_transaction']) ?></td>
                        <td><?= e($row['expense_percentage']) ?>%</td>
                        <td><?= e($row['growth_percentage'] ?? 'N/A') ?><?= $row['growth_percentage'] === null ? '' : '%' ?></td>
                        <td><?= e($row['recurring_commitment']) ?></td>
                        <td class="score"><?= e($row['opportunity_score']) ?></td>
                        <td><?= e($row['potential_saving']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        <?= e(config('app.name')) ?> &middot; Cost Reduction Report &middot;
        <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>
    </div>
</body>
</html>
