<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 20px 25px 28px;
        }

        body {
            color: #172033;
            font-family: Helvetica, sans-serif;
            font-size: 8.5px;
            line-height: 1.35;
        }

        h1 {
            font-size: 21px;
            margin: 3px 0 4px;
        }

        h2 {
            border-left: 4px solid #2563eb;
            font-size: 12px;
            margin: 12px 0 6px;
            padding-left: 7px;
        }

        h3 {
            font-size: 9px;
            margin: 0 0 4px;
        }

        p {
            margin: 3px 0;
        }

        .hero {
            background: #102445;
            color: #fff;
            padding: 14px 17px;
        }

        .hero-meta {
            color: #dbeafe;
            font-size: 8px;
        }

        .period {
            float: right;
            text-align: right;
        }

        .clear {
            clear: both;
        }

        .muted {
            color: #64748b;
        }

        .notice {
            background: #fff8e6;
            border: 1px solid #f4ce75;
            color: #76500a;
            margin: 6px 0;
            padding: 6px 8px;
        }

        .cards,
        .two-col {
            border-collapse: separate;
            border-spacing: 5px;
            margin: 3px -5px;
            width: calc(100% + 10px);
        }

        .cards td,
        .two-col td {
            background: #f8fafc;
            border: 1px solid #dbe4ed;
            padding: 7px;
            vertical-align: top;
        }

        .cards td {
            width: 16.66%;
        }

        .two-col td {
            width: 50%;
        }

        .label {
            color: #64748b;
            font-size: 7px;
            text-transform: uppercase;
        }

        .metric {
            display: block;
            font-size: 11px;
            font-weight: bold;
            margin-top: 2px;
        }

        .positive {
            color: #087d53;
        }

        .negative {
            color: #be123c;
        }

        .table {
            border-collapse: collapse;
            margin: 5px 0;
            width: 100%;
        }

        .table th,
        .table td {
            border: 1px solid #dbe4ed;
            padding: 5px 6px;
            vertical-align: middle;
        }

        .table th {
            background: #eaf2ff;
            color: #1d4ed8;
            font-size: 7.5px;
            text-align: left;
            text-transform: uppercase;
        }

        .right {
            text-align: right !important;
        }

        .bar-track {
            background: #e5eaf1;
            height: 8px;
            min-width: 70px;
        }

        .bar {
            background: #2563eb;
            display: block;
            height: 8px;
        }

        .bar-expense {
            background: #dc4c64;
        }

        .bar-income {
            background: #159b66;
        }

        .bar-week {
            background: #7c3aed;
        }

        .ai-card {
            border: 1px solid #c7d7ee;
            margin: 5px 0;
            padding: 7px 8px;
        }

        .ai-heading {
            color: #1d4ed8;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .ai-card ul,
        .ai-card ol {
            margin: 4px 0 0 15px;
            padding: 0;
        }

        .ai-card li {
            margin: 2px 0;
        }

        .tag {
            background: #dbeafe;
            color: #1e40af;
            font-size: 7px;
            padding: 2px 4px;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            bottom: -18px;
            color: #94a3b8;
            font-size: 7px;
            left: 0;
            position: fixed;
            right: 0;
            text-align: center;
        }
    </style>
</head>

<body><?php
        $money = static fn($value): string => 'BDT ' . number_format((float) $value, 2);
        $summary = $summary ?? [];
        $ai = $analysis['sections'] ?? [];
        $fi = $financialIntelligence ?? [];
        $supplementary = $supplementary ?? [];
        $metrics = $fi['metrics'] ?? [];
        $forecast = $fi['forecast'] ?? [];
        $categoryRows = array_slice($periodCategoryCosting ?? [], 0, 8);
        $monthlyRows = collect($rows ?? [])->values()->take(-6);
        $weeklyRows = array_slice($fi['weekly_spending']['rows'] ?? [], 0, 6);
        $accounts = $supplementary['accounts'] ?? [];
        $accountActivity = $supplementary['account_activity'] ?? [];
        $goals = $supplementary['goals'] ?? [];
        $topExpenses = $supplementary['top_expenses'] ?? [];
        $dataQuality = $supplementary['data_quality'] ?? [];
        $singleMonthCategories = $supplementary['single_month_categories'] ?? [];
        $totalExpense = max(0.0, (float) ($summary['total_costing'] ?? 0));
        $categoryMax = max(1.0, ...array_map(static fn($row): float => (float) ($row['total_amount'] ?? 0), $categoryRows ?: [['total_amount' => 1]]));
        $monthlyMax = max(1.0, ...(array_map(static fn($row): float => max((float) ($row['total_income'] ?? 0), (float) ($row['total_costing'] ?? 0)), $monthlyRows->all() ?: [['total_income' => 1, 'total_costing' => 1]])));
        $weeklyMax = max(1.0, ...array_map(static fn($row): float => (float) ($row['total_expenses'] ?? 0), $weeklyRows ?: [['total_expenses' => 1]]));
        $partial = ! $fromDate->isStartOfMonth() || ! $toDate->isEndOfMonth();
        $netCashFlow = (float) ($summary['net_income'] ?? 0);
        $safeList = static fn($items): array => array_values(array_filter(is_array($items) ? $items : [], static fn($item): bool => trim((string) $item) !== ''));
        ?>
    <div class="hero">
        <div class="period"><b>REPORTING PERIOD</b><br><?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?><br><span class="hero-meta"><?= $partial ? 'Partial period / month-to-date' : 'Complete period' ?></span></div>
        <div class="hero-meta">EXPENSE TRACKING SYSTEM</div>
        <h1>Financial Intelligence Report</h1>
        <div>Actual ledger performance, category analytics, and OpenAI-assisted observations.</div>
        <div class="clear"></div>
    </div>
    <?php if ($partial): ?><div class="notice"><b>Period note:</b> figures for a partial month are actual recorded values only. They are not treated as a full-month forecast or permanent spending change.</div><?php endif; ?>
    <table class="cards">
        <tr>
            <?php foreach ([['Opening balance', 'opening_balance', ''], ['Closing balance', 'closing_balance', ''], ['Income', 'total_income', 'positive'], ['Expenses', 'total_costing', 'negative'], ['Net cash flow', 'net_income', $netCashFlow >= 0 ? 'positive' : 'negative'], ['Transfers out', 'total_bank_transfers', '']] as [$label, $key, $class]): ?>
                <td><span class="label"><?= e($label) ?></span><span class="metric <?= e($class) ?>"><?= $key === 'net_income' && $netCashFlow > 0 ? '+' : '' ?><?= $money($summary[$key] ?? 0) ?></span></td>
            <?php endforeach; ?>
        </tr>
    </table>
    <h2>OpenAI executive summary</h2>
    <div class="ai-card"><span class="tag"><?= !empty($analysis['available']) ? 'OPENAI ANALYSIS' : 'DATA-BASED FALLBACK' ?></span>
        <p><?= e($ai['executive_summary'] ?? 'No OpenAI executive summary was available for this report. Review the recorded metrics and category analysis below.') ?></p>
    </div>
    <table class="two-col">
        <tr>
            <td>
                <h2>Category spending analysis</h2>
                <table class="table">
                    <tr>
                        <th>Category</th>
                        <th class="right">Amount</th>
                        <th class="right">Share</th>
                        <th>Distribution</th>
                    </tr>
                    <?php foreach ($categoryRows as $row): $amount = (float) ($row['total_amount'] ?? 0);
                        $share = $totalExpense > 0 ? $amount / $totalExpense * 100 : 0; ?><tr>
                            <td><?= e($row['category_name'] ?? 'Uncategorized') ?></td>
                            <td class="right"><?= $money($amount) ?></td>
                            <td class="right"><?= e(number_format($share, 1)) ?>%</td>
                            <td>
                                <div class="bar-track"><span class="bar bar-expense" style="width:<?= e(min(100, $amount / $categoryMax * 100)) ?>%"></span></div>
                            </td>
                        </tr><?php endforeach; ?>
                    <?php if ($categoryRows === []): ?><tr>
                            <td colspan="4">No categorized expenses were recorded in this period.</td>
                        </tr><?php endif; ?>
                </table>
            </td>
            <td>
                <h2>Weekly expense pattern</h2>
                <p class="muted"><?= e($fi['weekly_spending']['note'] ?? 'Weekly totals for the selected reporting period.') ?></p>
                <table class="table">
                    <tr>
                        <th>Week ending</th>
                        <th class="right">Expense</th>
                        <th>Weekly level</th>
                    </tr>
                    <?php foreach ($weeklyRows as $row): $amount = (float) ($row['total_expenses'] ?? 0); ?><tr>
                            <td><?= e(isset($row['week_end']) ? \Carbon\Carbon::parse($row['week_end'])->format('d M') : '-') ?></td>
                            <td class="right"><?= $money($amount) ?></td>
                            <td>
                                <div class="bar-track"><span class="bar bar-week" style="width:<?= e(min(100, $amount / $weeklyMax * 100)) ?>%"></span></div>
                            </td>
                        </tr><?php endforeach; ?>
                    <?php if ($weeklyRows === []): ?><tr>
                            <td colspan="3">No weekly expense data is available.</td>
                        </tr><?php endif; ?>
                </table>
            </td>
        </tr>
    </table>
    <h2>Income and expense trend</h2>
    <table class="table">
        <tr>
            <th>Period</th>
            <th class="right">Income</th>
            <th class="right">Expenses</th>
            <th class="right">Net cash flow</th>
            <th>Visual comparison</th>
        </tr>
        <?php foreach ($monthlyRows as $row): $income = (float) ($row['total_income'] ?? 0);
            $expense = (float) ($row['total_costing'] ?? 0); ?><tr>
                <td><?= e(\Carbon\Carbon::parse($row['date'])->format('M Y')) ?></td>
                <td class="right"><?= $money($income) ?></td>
                <td class="right"><?= $money($expense) ?></td>
                <td class="right <?= (float) ($row['net_income'] ?? 0) >= 0 ? 'positive' : 'negative' ?>"><?= $money($row['net_income'] ?? 0) ?></td>
                <td>
                    <div class="bar-track"><span class="bar bar-income" style="width:<?= e(min(100, $income / $monthlyMax * 100)) ?>%"></span></div>
                    <div class="bar-track" style="margin-top:2px"><span class="bar bar-expense" style="width:<?= e(min(100, $expense / $monthlyMax * 100)) ?>%"></span></div>
                </td>
            </tr><?php endforeach; ?>
        <?php if ($monthlyRows->isEmpty()): ?><tr>
                <td colspan="5">No income or expense trend data is available.</td>
            </tr><?php endif; ?>
    </table>
    <div class="page-break"></div>
    <h2>Single-month category activity</h2>
    <p class="muted">These categories have recorded spending in only one month of the available category history. They are signals to review, not proof of a one-time expense.</p>
    <table class="table">
        <tr>
            <th>Category</th>
            <th>Recorded month</th>
            <th class="right">Recorded amount</th>
            <th>Interpretation</th>
        </tr>
        <?php foreach ($singleMonthCategories as $category): ?><tr>
                <td><?= e($category['category_name'] ?? 'Uncategorized') ?></td>
                <td><?= !empty($category['month']) ? e(\Carbon\Carbon::parse($category['month'])->format('M Y')) : '-' ?></td>
                <td class="right"><?= $money($category['amount'] ?? 0) ?></td>
                <td><?= e($category['observation'] ?? '') ?></td>
            </tr><?php endforeach; ?>
        <?php if (empty($singleMonthCategories)): ?><tr>
                <td colspan="4">No category with spending in only one reporting month was detected.</td>
            </tr><?php endif; ?>
    </table>
    <h2>OpenAI findings and interpretation</h2>
    <table class="two-col">
        <tr>
            <td>
                <div class="ai-card">
                    <div class="ai-heading">Key findings</div>
                    <ul><?php foreach ($safeList($ai['key_findings'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['key_findings'] ?? []))): ?><li>No additional OpenAI findings were returned.</li><?php endif; ?></ul>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Spending analysis</div>
                    <ul><?php foreach ($safeList($ai['spending_analysis'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['spending_analysis'] ?? []))): ?><li>No detailed spending interpretation was returned.</li><?php endif; ?></ul>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Spending pressure and replenishment risk</div>
                    <ul><?php foreach ($safeList($ai['spending_pressure_analysis'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['spending_pressure_analysis'] ?? []))): ?><li>No spending-pressure interpretation was returned.</li><?php endif; ?></ul>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Account movement analysis</div>
                    <ul><?php foreach ($safeList($ai['account_analysis'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['account_analysis'] ?? []))): ?><li>No account-level interpretation was returned.</li><?php endif; ?></ul>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Watchouts</div>
                    <ul><?php foreach ($safeList($ai['watchouts'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['watchouts'] ?? []))): ?><li>No specific watchout was identified from the available data.</li><?php endif; ?></ul>
                </div>
            </td>
            <td>
                <div class="ai-card">
                    <div class="ai-heading">Recommendations</div>
                    <ol><?php foreach ($safeList($ai['recommendations'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['recommendations'] ?? []))): ?><li>No OpenAI recommendation was returned.</li><?php endif; ?></ol>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Data limitations</div>
                    <ul><?php foreach ($safeList($ai['limitations'] ?? []) as $item): ?><li><?= e($item) ?></li><?php endforeach; ?><?php if (empty($safeList($ai['limitations'] ?? []))): ?><li>Insights reflect only transactions recorded for the selected period.</li><?php endif; ?></ul>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Recorded recurring spending</div>
                    <p><?= $money($metrics['recurring_expense_total'] ?? 0) ?> across <?= e($metrics['recurring_expense_count'] ?? 0) ?> recurring entries. This is based on recorded items and does not assume unrecorded obligations.</p>
                </div>
            </td>
        </tr>
    </table>
    <h2>Forecast and practical actions</h2>
    <table class="two-col">
        <tr>
            <td>
                <?php if (!empty($forecast['available'])): $estimate = $forecast['estimated_month_end'] ?? []; ?><div class="ai-card">
                        <div class="ai-heading">Estimated month end - not actual</div>
                        <p>Income: <b><?= $money($estimate['income'] ?? 0) ?></b> &nbsp; Expenses: <b><?= $money($estimate['expenses'] ?? 0) ?></b> &nbsp; Net: <b><?= $money($estimate['net_cash_flow'] ?? 0) ?></b></p>
                        <p class="muted"><?= e($forecast['method'] ?? '') ?> <?= e($forecast['limitations'][0] ?? '') ?></p>
                    </div><?php else: ?><div class="ai-card">
                        <div class="ai-heading">Forecast unavailable</div>
                        <p><?= e($forecast['reason'] ?? 'There is not enough completed history for a reliable numerical forecast.') ?></p>
                    </div><?php endif; ?>
            </td>
            <td>
                <div class="ai-card">
                    <div class="ai-heading">Evidence-based actions</div>
                    <ul><?php foreach (array_slice($fi['recommendations'] ?? [], 0, 3) as $item): ?><li><b><?= e($item['title'] ?? 'Recommendation') ?>.</b> <?= e($item['recommended_action'] ?? '') ?></li><?php endforeach; ?><?php if (empty($fi['recommendations'])): ?><li>Review category totals and cash flow before changing financial commitments.</li><?php endif; ?></ul>
                </div>
            </td>
        </tr>
    </table>
    <h2>Accounts and savings goals</h2>
    <table class="two-col">
        <tr>
            <td>
                <table class="table">
                    <tr>
                        <th>Account movement</th>
                        <th class="right">Opening</th>
                        <th class="right">Added</th>
                        <th class="right">Out</th>
                        <th class="right">Trend</th>
                        <th class="right">Closing</th>
                    </tr><?php foreach ($accountActivity as $account): ?><tr>
                            <td><?= e($account['account_name'] ?? '-') ?><?php if (!empty($account['institution_name'])): ?><br><span class="muted"><?= e($account['institution_name']) ?></span><?php endif; ?></td>
                            <td class="right"><?= $money($account['opening_balance'] ?? 0) ?></td>
                            <td class="right positive"><?= $money($account['money_added'] ?? 0) ?></td>
                            <td class="right negative"><?= $money($account['money_out'] ?? 0) ?></td>
                            <td class="right <?= (float) ($account['net_change'] ?? 0) >= 0 ? 'positive' : 'negative' ?>"><?= e($account['trend'] ?? 'No change') ?><br><?= (float) ($account['net_change'] ?? 0) >= 0 ? '+' : '-' ?><?= $money(abs((float) ($account['net_change'] ?? 0))) ?></td>
                            <td class="right"><b><?= $money($account['closing_balance'] ?? 0) ?></b></td>
                        </tr><?php endforeach; ?><?php if (empty($accountActivity)): ?><tr>
                            <td colspan="6">No account ledger is available at this report cutoff.</td>
                        </tr><?php endif; ?>
                </table>
                <p class="muted">Added and out include all recorded account movement. Transfers are included in these figures because they change an individual account, but do not by themselves change the total portfolio balance.</p>
            </td>
            <td>
                <table class="table">
                    <tr>
                        <th>Savings goal</th>
                        <th class="right">Progress</th>
                    </tr><?php foreach ($goals as $goal): ?><tr>
                            <td><?= e($goal['name'] ?? '-') ?><br><span class="muted"><?= $money($goal['current_saved_amount'] ?? 0) ?> of <?= $money($goal['target_amount'] ?? 0) ?><?= !empty($goal['target_date']) ? ' | Target ' . e(\Carbon\Carbon::parse($goal['target_date'])->format('d M Y')) : '' ?></span></td>
                            <td class="right"><?= e(number_format((float) ($goal['progress_percent'] ?? 0), 1)) ?>%<div class="bar-track"><span class="bar" style="width:<?= e((float) ($goal['progress_percent'] ?? 0)) ?>%"></span></div>
                            </td>
                        </tr><?php endforeach; ?><?php if (empty($goals)): ?><tr>
                            <td colspan="2">No savings goals are configured.</td>
                        </tr><?php endif; ?>
                </table>
            </td>
        </tr>
    </table>
    <h2>Largest expenses and data quality</h2>
    <table class="two-col">
        <tr>
            <td>
                <table class="table">
                    <tr>
                        <th>Largest recorded expense</th>
                        <th class="right">Amount</th>
                    </tr><?php foreach ($topExpenses as $expense): ?><tr>
                            <td><?= e($expense['category_name'] ?? 'Uncategorized') ?><?php if (!empty($expense['note'])): ?><br><span class="muted"><?= e($expense['note']) ?></span><?php endif; ?></td>
                            <td class="right"><?= $money($expense['amount'] ?? 0) ?></td>
                        </tr><?php endforeach; ?><?php if (empty($topExpenses)): ?><tr>
                            <td colspan="2">No expenses were recorded in this period.</td>
                        </tr><?php endif; ?>
                </table>
            </td>
            <td>
                <div class="ai-card">
                    <div class="ai-heading">Record quality</div>
                    <p><?= e($dataQuality['expense_count'] ?? 0) ?> expense entries were reviewed. <?= e($dataQuality['uncategorized_expense_count'] ?? 0) ?> (<?= e(number_format((float) ($dataQuality['uncategorized_percent'] ?? 0), 1)) ?>%) have no category.</p>
                    <p class="muted">Categorizing these entries improves the accuracy of category analytics and future OpenAI observations.</p>
                </div>
                <div class="ai-card">
                    <div class="ai-heading">Transfer receipts</div>
                    <p><?php if (empty($transferReceiptsByAccount)): ?>No transfers were received in this period.<?php else: ?><?php foreach (array_slice($transferReceiptsByAccount, 0, 3) as $receipt): ?><b><?= e($receipt['account_name'] ?? '-') ?>:</b> <?= $money($receipt['total_amount'] ?? 0) ?><br><?php endforeach; ?><?php endif; ?></p>
                </div>
            </td>
        </tr>
    </table>
    <div class="footer">Financial Intelligence Report | <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?> | Generated from recorded transactions and configured OpenAI analysis</div>
</body>

</html>
