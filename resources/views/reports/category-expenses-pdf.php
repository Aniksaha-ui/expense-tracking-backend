<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Category-wise Expense Report</title>
    <style>
        @page { margin: 28px 34px 34px; }
        * { box-sizing: border-box; }
        body {
            color: #172033;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            margin: 0;
        }
        .header-table, .summary-table, .category-table { border-collapse: separate; width: 100%; }
        .brand {
            color: #147d64;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }
        h1 {
            color: #172033;
            font-size: 25px;
            line-height: 1.1;
            margin: 5px 0 3px;
        }
        .subtitle { color: #667085; font-size: 10px; }
        .user-box {
            background: #f2f7f5;
            border: 1px solid #d8e8e2;
            border-radius: 7px;
            padding: 10px 12px;
            text-align: right;
        }
        .user-label {
            color: #667085;
            font-size: 8px;
            letter-spacing: .8px;
            text-transform: uppercase;
        }
        .user-name { color: #172033; font-size: 13px; font-weight: bold; margin-top: 3px; }
        .period {
            background: #147d64;
            border-radius: 8px;
            color: #ffffff;
            margin: 15px 0 12px;
            padding: 12px 16px;
        }
        .period-label {
            color: #cde9df;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .period-date { font-size: 17px; font-weight: bold; margin-top: 4px; }
        .period-days { color: #d9eee7; font-size: 9px; text-align: right; }
        .summary-table { border-spacing: 7px 0; margin: 0 -7px 15px; }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #e3e8ef;
            border-radius: 7px;
            padding: 10px 12px;
            width: 25%;
        }
        .summary-label {
            color: #667085;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .6px;
            text-transform: uppercase;
        }
        .summary-value { color: #172033; font-size: 17px; font-weight: bold; margin-top: 5px; }
        .summary-value.primary { color: #147d64; }
        .section-title { font-size: 13px; font-weight: bold; margin: 0 0 3px; }
        .section-note { color: #667085; font-size: 9px; margin-bottom: 8px; }
        .category-group { margin-bottom: 11px; page-break-inside: avoid; }
        .category-table {
            border: 1px solid #dde3ea;
            border-spacing: 0;
            table-layout: fixed;
        }
        .category-table th, .category-table td {
            border-bottom: 1px solid #e5e9ef;
            border-right: 1px solid #e5e9ef;
            padding: 9px 8px;
            text-align: center;
            vertical-align: middle;
        }
        .category-table tr:last-child td { border-bottom: 0; }
        .category-table th:last-child, .category-table td:last-child { border-right: 0; }
        .category-table th {
            background: #eaf4f1;
            color: #125f4f;
            font-size: 10px;
            font-weight: bold;
        }
        .category-table .metric {
            background: #f8fafc;
            color: #667085;
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .4px;
            text-align: left;
            text-transform: uppercase;
            width: 18%;
        }
        .amount { color: #172033; font-size: 14px; font-weight: bold; }
        .share { color: #147d64; font-weight: bold; }
        .empty {
            background: #f8fafc;
            border: 1px solid #e3e8ef;
            border-radius: 8px;
            color: #667085;
            font-size: 13px;
            padding: 35px;
            text-align: center;
        }
        .footer {
            bottom: -22px;
            color: #98a2b3;
            font-size: 8px;
            left: 0;
            position: fixed;
            right: 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php
        $periodDays = (int) $fromDate->diffInDays($toDate) + 1;
        $categoryGroups = $rows->chunk(5);
    ?>

    <table class="header-table">
        <tr>
            <td>
                <div class="brand">Expense Tracking System</div>
                <h1>Category-wise Expense Report</h1>
                <div class="subtitle">A clear breakdown of spending activity by category</div>
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
                    &nbsp;&mdash;&nbsp;
                    <?= e($toDate->format('d M Y')) ?>
                </div>
            </td>
            <td class="period-days">
                <?= e($periodDays) ?> day<?= $periodDays === 1 ? '' : 's' ?><br>
                Generated <?= e(now(config('expense_reports.timezone'))->format('d M Y, h:i A')) ?>
            </td>
        </tr>
    </table>

    <?php if ($rows->isEmpty()): ?>
        <div class="empty">No expenses were recorded for this period.</div>
    <?php else: ?>
        <table class="summary-table">
            <tr>
                <td class="summary-card">
                    <div class="summary-label">Total expense</div>
                    <div class="summary-value primary"><?= e($total) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Transactions</div>
                    <div class="summary-value"><?= e($transactionCount) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Categories</div>
                    <div class="summary-value"><?= e($categoryCount) ?></div>
                </td>
                <td class="summary-card">
                    <div class="summary-label">Average expense</div>
                    <div class="summary-value"><?= e($averageExpense) ?></div>
                </td>
            </tr>
        </table>

        <div class="section-title">Expense distribution</div>
        <div class="section-note">Categories are arranged as columns for quick comparison.</div>

        <?php foreach ($categoryGroups as $group): ?>
            <div class="category-group">
                <table class="category-table">
                    <thead>
                        <tr>
                            <th class="metric">Metric</th>
                            <?php foreach ($group as $row): ?>
                                <th><?= e($row['category_name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="metric">Amount</td>
                            <?php foreach ($group as $row): ?>
                                <td class="amount"><?= e($row['total_amount']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="metric">Transactions</td>
                            <?php foreach ($group as $row): ?>
                                <td><?= e($row['transaction_count']) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td class="metric">Share of total</td>
                            <?php foreach ($group as $row): ?>
                                <td class="share"><?= e($row['percentage']) ?>%</td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="footer">
        <?= e(config('app.name')) ?> &middot; Category-wise Expense Report &middot;
        <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>
    </div>
</body>
</html>
