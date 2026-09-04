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
        .guide-table { border: 1px solid #d8e8e2; margin-bottom: 11px; table-layout: fixed; }
        .guide-table th, .guide-table td { border-bottom: 1px solid #e5e9ef; padding: 6px 7px; text-align: left; vertical-align: top; }
        .guide-table th { background: #f2f7f5; color: #125f4f; font-size: 7px; font-weight: bold; text-transform: uppercase; width: 22%; }
        .guide-table tr:last-child th, .guide-table tr:last-child td { border-bottom: 0; }
        .chart-box { background: #fbfcfe; border: 1px solid #dbe4ed; margin-bottom: 10px; padding: 8px 9px; page-break-inside: avoid; }
        .svg-chart { border: 1px solid #e5e9ef; display: block; margin-bottom: 7px; width: 100%; }
        .bar-table { border-collapse: separate; border-spacing: 0 5px; margin-bottom: 0; }
        .bar-label { color: #172033; font-weight: bold; width: 24%; }
        .bar-fill-cell { background: #147d64; color: #147d64; height: 12px; line-height: 12px; }
        .bar-empty-cell { background: #e9eef4; color: #e9eef4; height: 12px; line-height: 12px; }
        .bar-value { color: #475467; font-weight: bold; text-align: right; width: 14%; }
        .bar-text { color: #147d64; font-family: Courier, monospace; font-size: 9px; font-weight: bold; margin-top: 2px; }
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
            return max(1, min(100, (int) round(((float) $value / $max) * 100)));
        };
        $barCells = function ($value, $max) use ($barWidth): array {
            $filled = $barWidth($value, $max);

            return [$filled, max(0, 100 - $filled)];
        };
        $textBar = function ($value, $max): string {
            $length = max(1, min(30, (int) round(((float) $value / max((float) $max, 1)) * 30)));

            return str_repeat('|', $length);
        };
        $svgText = fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $svgMoney = fn ($value): string => number_format((float) $value, 2);
        $svgBarChart = function ($rows, string $valueKey, string $labelKey, string $title) use ($svgText, $svgMoney) {
            $rows = collect($rows)->values()->take(12);
            $rowHeight = 34;
            $width = 920;
            $height = max(160, 72 + ($rows->count() * $rowHeight));
            $labelWidth = 185;
            $plotWidth = 555;
            $valueX = $labelWidth + $plotWidth + 28;
            $max = max(1, (float) $rows->max(fn ($row) => (float) data_get($row, $valueKey, 0)));
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">';
            $svg .= '<rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Helvetica, Arial" font-size="17" font-weight="700">'.$svgText($title).'</text>';
            $svg .= '<line x1="'.$labelWidth.'" y1="45" x2="'.($labelWidth + $plotWidth).'" y2="45" stroke="#d8e2eb" stroke-width="1"/>';
            $svg .= '<line x1="'.$labelWidth.'" y1="45" x2="'.$labelWidth.'" y2="'.($height - 18).'" stroke="#d8e2eb" stroke-width="1"/>';

            foreach ($rows as $index => $row) {
                $y = 58 + ($index * $rowHeight);
                $value = (float) data_get($row, $valueKey, 0);
                $barWidth = max(2, (int) round(($value / $max) * $plotWidth));
                $label = $svgText(data_get($row, $labelKey, data_get($row, 'category_name', 'N/A')));
                $svg .= '<text x="18" y="'.($y + 14).'" fill="#172033" font-family="Helvetica, Arial" font-size="12" font-weight="700">'.$label.'</text>';
                $svg .= '<rect x="'.$labelWidth.'" y="'.$y.'" width="'.$plotWidth.'" height="18" fill="#e9eef4"/>';
                $svg .= '<rect x="'.$labelWidth.'" y="'.$y.'" width="'.$barWidth.'" height="18" fill="#147d64"/>';
                $svg .= '<text x="'.$valueX.'" y="'.($y + 14).'" fill="#475467" font-family="Helvetica, Arial" font-size="12" font-weight="700">'.$svgMoney($value).'</text>';
            }

            $svg .= '</svg>';

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
        };
        $svgLineChart = function ($rows, string $valueKey, string $labelKey, string $title) use ($svgText, $svgMoney) {
            $rows = collect($rows)->values()->take(12);
            $width = 920;
            $height = 250;
            $left = 58;
            $right = 32;
            $top = 48;
            $bottom = 45;
            $plotWidth = $width - $left - $right;
            $plotHeight = $height - $top - $bottom;
            $max = max(1, (float) $rows->max(fn ($row) => (float) data_get($row, $valueKey, 0)));
            $count = max(1, $rows->count() - 1);
            $points = [];
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">';
            $svg .= '<rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Helvetica, Arial" font-size="17" font-weight="700">'.$svgText($title).'</text>';

            for ($grid = 0; $grid <= 4; $grid++) {
                $y = $top + (($plotHeight / 4) * $grid);
                $svg .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.($width - $right).'" y2="'.$y.'" stroke="#e5e9ef" stroke-width="1"/>';
            }

            foreach ($rows as $index => $row) {
                $value = (float) data_get($row, $valueKey, 0);
                $x = $left + (($plotWidth / $count) * $index);
                $y = $top + $plotHeight - (($value / $max) * $plotHeight);
                $points[] = round($x, 1).','.round($y, 1);
            }

            $svg .= '<polyline points="'.implode(' ', $points).'" fill="none" stroke="#147d64" stroke-width="4"/>';

            foreach ($rows as $index => $row) {
                $value = (float) data_get($row, $valueKey, 0);
                $x = $left + (($plotWidth / $count) * $index);
                $y = $top + $plotHeight - (($value / $max) * $plotHeight);
                $svg .= '<circle cx="'.round($x, 1).'" cy="'.round($y, 1).'" r="5" fill="#147d64"/>';
                $svg .= '<text x="'.round($x - 18, 1).'" y="'.round($height - 18, 1).'" fill="#667085" font-family="Helvetica, Arial" font-size="10">'.$svgText(data_get($row, $labelKey, '')).'</text>';
            }

            $svg .= '<text x="'.$left.'" y="'.($top - 10).'" fill="#475467" font-family="Helvetica, Arial" font-size="11">Max: '.$svgMoney($max).'</text>';
            $svg .= '</svg>';

            return 'data:image/svg+xml;base64,'.base64_encode($svg);
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

        <?php if (! empty($report['definitions'])): ?>
            <div class="section-title">How to read this report</div>
            <div class="section-note">These definitions explain the metrics and calculations used in this PDF.</div>
            <table class="guide-table">
                <?php foreach ($report['definitions'] as $definition): ?>
                    <tr>
                        <th><?= e($definition['name']) ?></th>
                        <td><?= e($definition['definition']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (! empty($report['section_guides'])): ?>
            <div class="section-title">Report section guide</div>
            <table class="guide-table">
                <?php foreach ($report['section_guides'] as $guide): ?>
                    <tr>
                        <th><?= e($guide['section']) ?></th>
                        <td><?= e($guide['definition']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

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

        <div class="section-title"><?= $frequency === 'monthly' ? 'Monthly trend chart' : (in_array($frequency, ['weekly', 'bi-weekly'], true) ? 'Daily spending trend chart' : 'Category spending chart') ?></div>
        <?php
            $graphRows = $frequency === 'monthly' ? $report['trend_rows'] : (in_array($frequency, ['weekly', 'bi-weekly'], true) ? $report['chart_rows'] : $report['chart_rows']);
            $graphKey = $frequency === 'monthly' || in_array($frequency, ['weekly', 'bi-weekly'], true) ? 'expense' : 'current_expense';
            $maxGraphValue = $maxChartValue($graphRows, $graphKey);
        ?>
        <div class="chart-box">
            <img class="svg-chart" src="<?= e($frequency === 'monthly' ? $svgLineChart($graphRows, $graphKey, 'label', 'Expense trend') : $svgBarChart($graphRows, $graphKey, 'label', 'Expense trend')) ?>" alt="Expense trend chart">
            <table class="bar-table">
                <?php foreach ($graphRows as $row): ?>
                    <?php [$filled, $empty] = $barCells($row[$graphKey] ?? 0, $maxGraphValue); ?>
                    <tr>
                        <td class="bar-label"><?= e($row['label'] ?? $row['category_name']) ?></td>
                        <td>
                            <table>
                                <tr>
                                    <td class="bar-fill-cell" bgcolor="#147d64" width="<?= e($filled) ?>%">&nbsp;</td>
                                    <td class="bar-empty-cell" bgcolor="#e9eef4" width="<?= e($empty) ?>%">&nbsp;</td>
                                </tr>
                            </table>
                            <div class="bar-text"><?= e($textBar($row[$graphKey] ?? 0, $maxGraphValue)) ?></div>
                        </td>
                        <td class="bar-value"><?= e($row[$graphKey] ?? '0.00') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php if (! empty($report['top_categories']) && $report['top_categories']->isNotEmpty()): ?>
            <div class="section-title">Top spending category chart</div>
            <?php $topCategoryMax = $maxChartValue($report['top_categories'], 'current_expense'); ?>
            <div class="chart-box">
                <img class="svg-chart" src="<?= e($svgBarChart($report['top_categories'], 'current_expense', 'category_name', 'Top spending categories')) ?>" alt="Top spending category chart">
                <table class="bar-table">
                    <?php foreach ($report['top_categories'] as $row): ?>
                        <?php [$filled, $empty] = $barCells($row['current_expense'], $topCategoryMax); ?>
                        <tr>
                            <td class="bar-label"><?= e($row['category_name']) ?></td>
                            <td>
                                <table>
                                    <tr>
                                        <td class="bar-fill-cell" bgcolor="#147d64" width="<?= e($filled) ?>%">&nbsp;</td>
                                        <td class="bar-empty-cell" bgcolor="#e9eef4" width="<?= e($empty) ?>%">&nbsp;</td>
                                    </tr>
                                </table>
                                <div class="bar-text"><?= e($textBar($row['current_expense'], $topCategoryMax)) ?></div>
                            </td>
                            <td class="bar-value"><?= e($row['current_expense']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <div class="section-title">Category share chart</div>
            <div class="chart-box">
                <img class="svg-chart" src="<?= e($svgBarChart($report['top_categories'], 'expense_share', 'category_name', 'Category share percentage')) ?>" alt="Category share chart">
                <table class="bar-table">
                    <?php foreach ($report['top_categories'] as $row): ?>
                        <?php [$filled, $empty] = $barCells($row['expense_share'], 100); ?>
                        <tr>
                            <td class="bar-label"><?= e($row['category_name']) ?></td>
                            <td>
                                <table>
                                    <tr>
                                        <td class="bar-fill-cell" bgcolor="#147d64" width="<?= e($filled) ?>%">&nbsp;</td>
                                        <td class="bar-empty-cell" bgcolor="#e9eef4" width="<?= e($empty) ?>%">&nbsp;</td>
                                    </tr>
                                </table>
                                <div class="bar-text"><?= e($textBar($row['expense_share'], 100)) ?></div>
                            </td>
                            <td class="bar-value"><?= e($row['expense_share']) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <?php if (! empty($report['comparison'])): ?>
            <div class="section-title">Period comparison</div>
            <table class="data-table">
                <tr><th>Current</th><th>Previous</th><th>Difference</th><th>Growth</th></tr>
                <tr>
                    <td><?= e($report['comparison']['current_expense']) ?></td>
                    <td><?= e($report['comparison']['previous_expense']) ?></td>
                    <td><?= e($report['comparison']['difference']) ?></td>
                    <td><?= e($report['comparison']['growth_percentage'] ?? 'N/A') ?><?= $report['comparison']['growth_percentage'] === null ? '' : '%' ?></td>
                </tr>
            </table>
        <?php endif; ?>

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

        <?php if (! empty($report['opportunities']) && $report['opportunities']->isNotEmpty()): ?>
            <div class="section-title">Cost reduction opportunity ranking</div>
            <table class="data-table">
                <tr><th class="name">Category</th><th>Current</th><th>Growth</th><th>Frequency</th><th>Score</th><th>Estimated Saving</th></tr>
                <?php foreach ($report['opportunities'] as $row): ?>
                    <tr>
                        <td class="name"><?= e($row['category_name']) ?></td>
                        <td><?= e($row['current_expense']) ?></td>
                        <td><?= e($row['growth_percentage'] ?? 'N/A') ?><?= $row['growth_percentage'] === null ? '' : '%' ?></td>
                        <td><?= e($row['transaction_count']) ?></td>
                        <td class="score"><?= e($row['opportunity_score']) ?>/100</td>
                        <td><?= e($row['potential_saving']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (! empty($report['account_rows']) && $report['account_rows']->isNotEmpty()): ?>
            <div class="section-title">Account-based spending section</div>
            <table class="data-table">
                <tr><th class="name">Account</th><th>Type</th><th>Transactions</th><th>Total Expense</th><th>Average</th></tr>
                <?php foreach ($report['account_rows'] as $row): ?>
                    <tr>
                        <td class="name"><?= e($row['account_name']) ?></td>
                        <td><?= e($row['account_type']) ?></td>
                        <td><?= e($row['transaction_count']) ?></td>
                        <td><?= e($row['total_expense']) ?></td>
                        <td><?= e($row['average_expense']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

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

        <?php if (! empty($report['leakage']['repeated_similar_expenses']) && $report['leakage']['repeated_similar_expenses']->isNotEmpty()): ?>
            <div class="section-title">Repeated similar expenses</div>
            <table class="data-table">
                <tr><th class="name">Category</th><th>Note Pattern</th><th>Count</th><th>Total</th><th>Average</th></tr>
                <?php foreach ($report['leakage']['repeated_similar_expenses'] as $row): ?>
                    <tr>
                        <td class="name"><?= e($row['category_name'] ?? 'Uncategorized') ?></td>
                        <td class="left"><?= e($row['note_pattern'] ?: 'No note') ?></td>
                        <td><?= e($row['transaction_count']) ?></td>
                        <td><?= e($row['total_amount']) ?></td>
                        <td><?= e($row['average_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (! empty($report['anomalies']) && $report['anomalies']->isNotEmpty()): ?>
            <div class="section-title">Expense anomaly detection</div>
            <table class="data-table">
                <tr><th>Date</th><th class="name">Category</th><th>Transaction</th><th>Category Avg</th><th>Deviation</th><th>Note</th></tr>
                <?php foreach ($report['anomalies']->take(8) as $row): ?>
                    <tr>
                        <td class="left"><?= e($row['transaction_date']) ?></td>
                        <td class="name"><?= e($row['category_name']) ?></td>
                        <td><?= e($row['amount']) ?></td>
                        <td><?= e($row['category_average']) ?></td>
                        <td><?= e($row['deviation_percentage']) ?>%</td>
                        <td class="left"><?= e($row['note'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <?php if (! empty($report['plan'])): ?>
            <div class="section-title"><?= $frequency === 'bi-weekly' ? 'Next bi-week spending plan' : 'Next week spending plan' ?></div>
            <table class="data-table">
                <tr><th>Historical Period Avg</th><th>Current Period</th><th>Target Low</th><th>Target High</th></tr>
                <tr>
                    <td><?= e($report['plan']['last_4_week_average'] ?? $report['plan']['last_3_bi_week_average'] ?? '0.00') ?></td>
                    <td><?= e($report['plan']['current_week'] ?? $report['plan']['current_bi_week'] ?? '0.00') ?></td>
                    <td><?= e($report['plan']['target_low']) ?></td>
                    <td><?= e($report['plan']['target_high']) ?></td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if ($frequency === 'monthly'): ?>
            <?php if (! empty($report['recurring'])): ?>
                <div class="section-title">Recurring expense analysis</div>
                <table class="data-table">
                    <tr><th>Active Recurring</th><th>Monthly Commitment</th><th>Income Commitment</th><th>Largest Recurring</th><th>Largest Amount</th></tr>
                    <tr>
                        <td><?= e($report['recurring']['active_count']) ?></td>
                        <td><?= e($report['recurring']['monthly_commitment']) ?></td>
                        <td><?= e($report['recurring']['income_commitment_percentage']) ?>%</td>
                        <td><?= e($report['recurring']['largest']['title'] ?? 'N/A') ?></td>
                        <td><?= e($report['recurring']['largest']['amount'] ?? '0.00') ?></td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php if (! empty($report['behavior'])): ?>
                <div class="section-title">Spending behavior analysis</div>
                <table class="data-table">
                    <tr><th>Highest Spending Day</th><th>Day Avg</th><th>High Spending Period</th><th>Period Total</th><th>Period Txns</th></tr>
                    <tr>
                        <td><?= e($report['behavior']['highest_spending_day']['label'] ?? 'N/A') ?></td>
                        <td><?= e($report['behavior']['highest_spending_day']['average_expense'] ?? '0.00') ?></td>
                        <td><?= e($report['behavior']['highest_spending_period']['label'] ?? 'N/A') ?></td>
                        <td><?= e($report['behavior']['highest_spending_period']['total_expense'] ?? '0.00') ?></td>
                        <td><?= e($report['behavior']['highest_spending_period']['transaction_count'] ?? 0) ?></td>
                    </tr>
                </table>
            <?php endif; ?>

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

        <?php if (! empty($report['calculation_hints'])): ?>
            <div class="section-title">Calculation hints</div>
            <ul class="list">
                <?php foreach ($report['calculation_hints'] as $hint): ?>
                    <li><?= e($hint) ?></li>
                <?php endforeach; ?>
            </ul>
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
