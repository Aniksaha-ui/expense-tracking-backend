<!DOCTYPE html>
<?php
// An empty selected-period table is safe during a rolling deployment where an
// already-running worker still supplies the previous view payload.
$periodCategoryCosting = $periodCategoryCosting ?? [];
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Overview</title>
    <style>
        @font-face {
            font-family: 'Noto Sans Bengali';
            font-style: normal;
            font-weight: 400;
            src: url('file://<?= e(public_path('fonts/NotoSansBengali-Regular.ttf')) ?>') format('truetype');
        }
        @font-face {
            font-family: 'Noto Sans Bengali';
            font-style: normal;
            font-weight: 700;
            src: url('file://<?= e(public_path('fonts/NotoSansBengali-Bold.ttf')) ?>') format('truetype');
        }
        @page { margin: 24px 28px 34px; }
        * { box-sizing: border-box; }
        body { background: #ffffff; color: #1e293b; font-family: 'Noto Sans Bengali', DejaVu Sans, sans-serif; font-size: 10px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        h1 { color: #ffffff; font-size: 25px; font-weight: 800; letter-spacing: -.4px; margin: 2px 0 4px; } h2 { color: #172033; font-weight: 800; } .muted { color: #94a3b8; }
        .report-header { background: #12213d; border-radius: 10px; color: #ffffff; margin-bottom: 14px; padding: 17px 19px; }
        .report-header td { vertical-align: top; } .report-header .period-cell { text-align: right; width: 220px; }
        .brand { color: #7dd3fc; font-size: 8px; font-weight: bold; letter-spacing: 1.6px; text-transform: uppercase; }
        .subtitle { color: #cbd5e1; font-size: 10px; }
        .period { background: #1d4ed8; border: 1px solid #3b82f6; border-radius: 7px; color: white; display: inline-block; padding: 8px 11px; text-align: left; }
        .period small { color: #bfdbfe; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .period strong { display: block; font-size: 11px; margin-top: 3px; }
        .summary { border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 18px; }
        .summary td { background: #f8fafc; border: 1px solid #d8e3f1; border-radius: 8px; padding: 10px 11px; width: 16.66%; }
        .summary label { color: #64748b; display: block; font-size: 7px; font-weight: bold; letter-spacing: .7px; text-transform: uppercase; }
        .summary strong { color: #172033; display: block; font-size: 13px; margin-top: 5px; }
        h2 { border-left: 3px solid #2563eb; font-size: 13px; margin: 17px 0 4px; padding-left: 7px; }
        .section-note { color: #64748b; font-size: 9px; margin: 0 0 8px 10px; }
        .chart { background: #fbfcfe; border: 1px solid #dbe4ed; border-radius: 8px; margin: 8px 0 15px; padding: 9px; page-break-inside: avoid; }
        .chart img { display: block; width: 100%; }
        .balance-grid { background: #fbfcfe; border: 1px solid #dbe4ed; border-radius: 8px; margin: 8px 0 15px; overflow: hidden; page-break-inside: avoid; }
        .balance-grid th, .balance-grid td { border-bottom: 1px solid #e5e9ef; padding: 8px 10px; text-align: right; }
        .balance-grid th { background: #eff6ff; color: #1d4ed8; font-size: 8px; letter-spacing: .4px; text-transform: uppercase; }
        .balance-grid td { color: #334155; font-size: 9px; } .balance-grid th:first-child, .balance-grid td:first-child { text-align: left; }
        .balance-grid tr:last-child td { border-bottom: 0; } .balance-positive { color: #15803d !important; font-weight: bold; } .balance-negative { color: #be123c !important; font-weight: bold; }
        .flow-chart { background: #fbfcfe; border: 1px solid #dbe4ed; border-radius: 8px; margin: 8px 0 15px; padding: 12px 14px; page-break-inside: avoid; }
        .flow-chart h3 { color: #172033; font-size: 12px; margin: 0 0 8px; }
        .flow-legend { color: #475467; font-size: 8px; margin-bottom: 10px; }
        .flow-key { display: inline-block; height: 8px; margin: 0 4px 0 12px; vertical-align: middle; width: 8px; }
        .flow-key:first-child { margin-left: 0; }
        .flow-row { border-top: 1px solid #e8eef5; margin: 0; padding: 7px 0; }
        .flow-row:last-child { padding-bottom: 0; } .flow-row:nth-of-type(3) { border-top: 0; }
        .flow-label { color: #172033; font-size: 9px; font-weight: bold; margin-bottom: 4px; }
        .flow-line { height: 10px; margin: 3px 0; position: relative; }
        .flow-track { background: #e8eef3; border-radius: 5px; height: 10px; width: 74%; }
        .flow-bar { border-radius: 5px; height: 10px; left: 0; position: absolute; top: 0; }
        .flow-value { font-size: 8px; left: 76%; position: absolute; top: -1px; white-space: nowrap; }
        .data { border: 1px solid #dbe3ef; border-radius: 7px; margin-top: 8px; } .data th, .data td { border-bottom: 1px solid #e5e9ef; border-right: 1px solid #e5e9ef; padding: 7px 6px; text-align: right; }
        .data th { background: #eff6ff; color: #1d4ed8; font-size: 8px; text-transform: uppercase; } .data td { color: #334155; } .data td:first-child, .data th:first-child { text-align: left; }
        .data tr:last-child td { border-bottom: 0; } .data th:last-child, .data td:last-child { border-right: 0; }
        .category-section { page-break-before: always; } .analysis-grid { border-collapse: separate; border-spacing: 8px; margin: 0 -8px 10px; } .analysis-grid td { background: #f8fafc; border: 1px solid #dbe3ef; border-top: 3px solid #2563eb; padding: 10px; vertical-align: top; width: 50%; } .analysis-grid strong { color: #1d4ed8; display: block; font-size: 9px; letter-spacing: .5px; margin-bottom: 6px; text-transform: uppercase; } .analysis-grid ul { margin: 0; padding-left: 14px; } .analysis-grid li { line-height: 1.5; margin-bottom: 4px; }
        .footer { bottom: -21px; color: #94a3b8; font-size: 8px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <?php
        $svgText = fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $svgMoney = fn ($value): string => number_format((float) $value, 2);
        $englishText = function ($value, string $fallback): string {
            $text = trim((string) $value);

            return $text === '' || preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1 ? $fallback : $text;
        };
        $chartRows = collect($rows)->values();
        // Dompdf does not resolve custom @font-face declarations inside a data-URI
        // SVG. Use its built-in Helvetica for the English-only chart labels.
        $svgDataUri = fn (string $svg): string => 'data:image/svg+xml;base64,'.base64_encode(str_replace(
            ['<style>text{font-family:Helvetica,Arial}</style>', 'font-family="Noto Sans Bengali, DejaVu Sans"'],
            ['', 'font-family="Helvetica, Arial"'],
            $svg,
        ));
        $axisLabel = fn (array $row): string => \Carbon\Carbon::parse($row['date'])->format('M Y');

        $balanceChart = null;
        if ($chartRows->isNotEmpty()) {
            $width = 940; $height = 255; $left = 64; $right = 38; $top = 55; $bottom = 43;
            $plotWidth = $width - $left - $right; $plotHeight = $height - $top - $bottom;
            $values = $chartRows->flatMap(fn (array $row): array => [(float) $row['opening_balance'], (float) $row['closing_balance']]);
            $min = min(0, (float) $values->min()); $max = max(1, (float) $values->max());
            $range = max(1, $max - $min); $count = $chartRows->count();
            $point = function (float $value, int $index) use ($left, $top, $plotHeight, $plotWidth, $count, $min, $range): array {
                return [$left + ($index * ($plotWidth / max($count - 1, 1))), $top + $plotHeight - (($value - $min) / $range * $plotHeight)];
            };
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><style>text{font-family:Helvetica,Arial}</style><rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Noto Sans Bengali, DejaVu Sans" font-size="17" font-weight="700">Opening balance vs closing balance</text>';
            $svg .= '<rect x="18" y="36" width="11" height="11" fill="#60a5fa"/><text x="35" y="46" fill="#475467" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10">Opening</text><rect x="108" y="36" width="11" height="11" fill="#2563eb"/><text x="125" y="46" fill="#475467" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10">Closing</text>';
            for ($grid = 0; $grid <= 4; $grid++) { $y = $top + ($grid * $plotHeight / 4); $value = $max - ($grid * $range / 4); $svg .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.($width - $right).'" y2="'.$y.'" stroke="#dbe4ed" stroke-width="1"/><text x="8" y="'.($y + 3).'" fill="#667085" font-family="Noto Sans Bengali, DejaVu Sans" font-size="8">BDT '.number_format($value, 0).'</text>'; }
            $openingPoints = []; $closingPoints = [];
            foreach ($chartRows as $index => $row) { [$x1, $y1] = $point((float) $row['opening_balance'], $index); [$x2, $y2] = $point((float) $row['closing_balance'], $index); $openingPoints[] = $x1.','.$y1; $closingPoints[] = $x2.','.$y2; $svg .= '<text x="'.($x1 - 16).'" y="'.($height - 15).'" fill="#667085" font-family="Noto Sans Bengali, DejaVu Sans" font-size="8">'.$svgText($axisLabel($row)).'</text>'; }
            $svg .= '<polyline fill="none" stroke="#60a5fa" stroke-width="3" points="'.implode(' ', $openingPoints).'"/><polyline fill="none" stroke="#2563eb" stroke-width="3" points="'.implode(' ', $closingPoints).'"/>';
            foreach ($chartRows as $index => $row) { [$x1, $y1] = $point((float) $row['opening_balance'], $index); [$x2, $y2] = $point((float) $row['closing_balance'], $index); $svg .= '<circle cx="'.$x1.'" cy="'.$y1.'" r="3" fill="#60a5fa"/><circle cx="'.$x2.'" cy="'.$y2.'" r="3" fill="#2563eb"/>'; }
            $balanceChart = $svgDataUri($svg.'</svg>');
        }

        $flowChart = null;
        $flowChartMax = 1;
        if ($chartRows->isNotEmpty()) {
            $max = max(1, (float) $chartRows->max(fn (array $row): float => max((float) $row['total_income'], (float) $row['total_costing'])));
            $flowChartMax = $max;
            $width = 940; $rowHeight = 30; $height = 76 + ($chartRows->count() * $rowHeight); $labelWidth = 185; $plotWidth = 540; $valueX = 755;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><style>text{font-family:Helvetica,Arial}</style><rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Noto Sans Bengali, DejaVu Sans" font-size="17" font-weight="700">Income vs costing by month</text><rect x="18" y="38" width="11" height="11" fill="#16a34a"/><text x="35" y="48" fill="#475467" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10">Income</text><rect x="105" y="38" width="11" height="11" fill="#e11d48"/><text x="122" y="48" fill="#475467" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10">Costing</text>';
            foreach ($chartRows as $index => $row) { $y = 61 + ($index * $rowHeight); $income = (float) $row['total_income']; $costing = (float) $row['total_costing']; $incomeWidth = max(2, (int) round($income / $max * $plotWidth)); $costingWidth = max(2, (int) round($costing / $max * $plotWidth)); $svg .= '<text x="18" y="'.($y + 14).'" fill="#172033" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10" font-weight="700">'.$svgText($axisLabel($row)).'</text><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$plotWidth.'" height="10" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$incomeWidth.'" height="10" fill="#16a34a"/><rect x="'.$labelWidth.'" y="'.($y + 13).'" width="'.$plotWidth.'" height="10" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.($y + 13).'" width="'.$costingWidth.'" height="10" fill="#e11d48"/><text x="'.$valueX.'" y="'.($y + 9).'" fill="#15803d" font-family="Noto Sans Bengali, DejaVu Sans" font-size="9">BDT '.$svgMoney($income).'</text><text x="'.$valueX.'" y="'.($y + 22).'" fill="#be123c" font-family="Noto Sans Bengali, DejaVu Sans" font-size="9">BDT '.$svgMoney($costing).'</text>'; }
            $flowChart = $svgDataUri($svg.'</svg>');
        }

        $categoryMonths = $categoryCostingComparison['months'] ?? [];
        $categoryRows = $categoryCostingComparison['categories'] ?? [];
        $categoryChart = null;
        if (! empty($categoryRows) && ! empty($categoryMonths)) {
            $latestIndex = count($categoryMonths) - 1;
            $previousIndex = $latestIndex - 1;
            $chartCategories = array_slice($categoryRows, 0, 8);
            $max = max(1, ...array_map(fn (array $row): float => max((float) ($row['amounts'][$latestIndex] ?? 0), (float) ($row['amounts'][$previousIndex] ?? 0)), $chartCategories));
            $width = 940; $rowHeight = 34; $height = 85 + (count($chartCategories) * $rowHeight); $labelWidth = 190; $plotWidth = 500; $valueX = 710;
            $latestLabel = \Carbon\Carbon::parse($categoryMonths[$latestIndex])->format('M Y');
            $previousLabel = $previousIndex >= 0 ? \Carbon\Carbon::parse($categoryMonths[$previousIndex])->format('M Y') : 'No prior month';
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><style>text{font-family:Helvetica,Arial}</style><rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Noto Sans Bengali, DejaVu Sans" font-size="17" font-weight="700">Category costing: latest month vs prior month</text><rect x="18" y="38" width="11" height="11" fill="#2563eb"/><text x="35" y="48" fill="#475467" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10">'.$svgText($latestLabel).'</text><rect x="150" y="38" width="11" height="11" fill="#94a3b8"/><text x="167" y="48" fill="#475467" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10">'.$svgText($previousLabel).'</text>';
            foreach ($chartCategories as $index => $row) {
                $y = 64 + ($index * $rowHeight); $latest = (float) ($row['amounts'][$latestIndex] ?? 0); $previous = (float) ($row['amounts'][$previousIndex] ?? 0);
                $latestWidth = max(0, (int) round($latest / $max * $plotWidth)); $previousWidth = max(0, (int) round($previous / $max * $plotWidth));
                $label = $englishText($row['category_name'], 'Category '.($index + 1));
                $svg .= '<text x="18" y="'.($y + 14).'" fill="#172033" font-family="Noto Sans Bengali, DejaVu Sans" font-size="10" font-weight="700">'.$svgText(mb_strimwidth($label, 0, 25, '…')).'</text><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$plotWidth.'" height="10" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$latestWidth.'" height="10" fill="#2563eb"/><rect x="'.$labelWidth.'" y="'.($y + 14).'" width="'.$plotWidth.'" height="10" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.($y + 14).'" width="'.$previousWidth.'" height="10" fill="#94a3b8"/><text x="'.$valueX.'" y="'.($y + 9).'" fill="#1d4ed8" font-family="Noto Sans Bengali, DejaVu Sans" font-size="9">BDT '.$svgMoney($latest).'</text><text x="'.$valueX.'" y="'.($y + 23).'" fill="#64748b" font-family="Noto Sans Bengali, DejaVu Sans" font-size="9">BDT '.$svgMoney($previous).'</text>';
            }
            $categoryChart = $svgDataUri($svg.'</svg>');
        }

    ?>
    <div class="report-header"><table><tr><td><div class="brand">Expense Tracking System</div><h1>Financial Overview</h1><div class="subtitle">Balance, income, and costing performance report</div></td><td class="period-cell"><div class="period"><small>Reporting period</small><strong><?= e($fromDate->format('d M Y')) ?> — <?= e($toDate->format('d M Y')) ?></strong></div></td></tr></table></div>
    <table class="summary"><tr>
        <?php foreach (['Opening Balance' => 'opening_balance', 'Total Income' => 'total_income', 'Total Costing' => 'total_costing', 'Bank-to-bank Transfers' => 'total_bank_transfers', 'Net Income' => 'net_income', 'Closing Balance' => 'closing_balance'] as $label => $key): ?>
            <td><label><?= e($label) ?></label><strong>BDT <?= e(number_format((float) ($summary[$key] ?? 0), 2)) ?></strong></td>
        <?php endforeach; ?>
    </tr></table>
    <h2>Balance movement</h2><div class="section-note">Compare each reporting period’s opening and closing portfolio balance.</div>
    <?php if ($chartRows->isNotEmpty()): ?><div class="balance-grid"><table><thead><tr><th>Month</th><th>Opening balance</th><th>Closing balance</th><th>Net change</th></tr></thead><tbody><?php foreach ($chartRows as $row): ?><?php $change = (float) $row['closing_balance'] - (float) $row['opening_balance']; ?><tr><td><?= e($axisLabel($row)) ?></td><td>BDT <?= e($svgMoney($row['opening_balance'])) ?></td><td>BDT <?= e($svgMoney($row['closing_balance'])) ?></td><td class="<?= $change >= 0 ? 'balance-positive' : 'balance-negative' ?>"><?= $change >= 0 ? '+' : '−' ?>BDT <?= e($svgMoney(abs($change))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="section-note">No balance movement is available for this period.</div><?php endif; ?>
    <h2>Income and costing movement</h2><div class="section-note">Compare earned income against costs for each reporting period.</div>
    <?php if ($chartRows->isNotEmpty()): ?><div class="flow-chart"><h3>Income vs costing by month</h3><div class="flow-legend"><span class="flow-key" style="background:#16a34a"></span>Income <span class="flow-key" style="background:#e11d48"></span>Costing</div><?php foreach ($chartRows as $row): ?><?php $income = (float) $row['total_income']; $costing = (float) $row['total_costing']; ?><div class="flow-row"><div class="flow-label"><?= e($axisLabel($row)) ?></div><div class="flow-line"><div class="flow-track"></div><div class="flow-bar" style="background:#16a34a; width:<?= e(number_format($income / $flowChartMax * 74, 2, '.', '')) ?>%"></div><span class="flow-value" style="color:#15803d">BDT <?= e($svgMoney($income)) ?></span></div><div class="flow-line"><div class="flow-track"></div><div class="flow-bar" style="background:#e11d48; width:<?= e(number_format($costing / $flowChartMax * 74, 2, '.', '')) ?>%"></div><span class="flow-value" style="color:#be123c">BDT <?= e($svgMoney($costing)) ?></span></div></div><?php endforeach; ?></div><?php else: ?><div class="section-note">No income or costing movement is available for this period.</div><?php endif; ?>
    <h2>Transfer receipts by account</h2><div class="section-note">Total transfers received by each destination account in this reporting period.</div>
    <table class="data"><thead><tr><th>Destination account</th><th>Total received</th></tr></thead><tbody>
        <?php if (empty($transferReceiptsByAccount)): ?><tr><td colspan="2" style="text-align:center; padding:20px;">No transfers were received during this period.</td></tr><?php endif; ?>
        <?php foreach ($transferReceiptsByAccount as $index => $receipt): ?><tr><td><?= e($englishText($receipt['account_name'], 'Account '.($index + 1))) ?></td><td>BDT <?= e(number_format((float) $receipt['total_amount'], 2)) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <h2>Monthly financial ledger</h2><div class="muted">Opening and closing balances with income, costing, and bank-to-bank transfers by period.</div>
    <table class="data"><thead><tr><th>Month</th><th>Opening</th><th>Income</th><th>Costing</th><th>Transfers</th><th>Net Income</th><th>Closing</th><th>Entries</th></tr></thead><tbody>
        <?php if (empty($rows)): ?><tr><td colspan="8" style="text-align:center; padding:20px;">No transactions were recorded for this period.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr><td><?= e(\Carbon\Carbon::parse($row['date'])->format('M Y')) ?></td><td>BDT <?= e(number_format((float) $row['opening_balance'], 2)) ?></td><td>BDT <?= e(number_format((float) $row['total_income'], 2)) ?></td><td>BDT <?= e(number_format((float) $row['total_costing'], 2)) ?></td><td>BDT <?= e(number_format((float) $row['total_bank_transfers'], 2)) ?></td><td>BDT <?= e(number_format((float) $row['net_income'], 2)) ?></td><td>BDT <?= e(number_format((float) $row['closing_balance'], 2)) ?></td><td><?= e($row['transaction_count']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div class="category-section"><h2>Category-wise costing comparison</h2><div class="section-note">Compare expense and recurring costing by category across the reporting months. This is the final section of the report.</div>
    <?php if ($categoryChart): ?><div class="section-note">The category comparison is summarized in the table below.</div><?php else: ?><div class="section-note">No category costing was recorded for this period.</div><?php endif; ?>
    <table class="data"><thead><tr><th>Category</th><?php foreach ($categoryMonths as $month): ?><th><?= e(\Carbon\Carbon::parse($month)->format('M Y')) ?></th><?php endforeach; ?><th>Total</th><th>Latest change</th></tr></thead><tbody>
        <?php if (empty($categoryRows)): ?><tr><td colspan="<?= e(count($categoryMonths) + 3) ?>" style="text-align:center; padding:20px;">No category costing was recorded for this period.</td></tr><?php endif; ?>
        <?php foreach ($categoryRows as $index => $category): ?><tr><td><?= e($englishText($category['category_name'], 'Category '.($index + 1))) ?></td><?php foreach ($category['amounts'] as $amount): ?><td>BDT <?= e(number_format((float) $amount, 2)) ?></td><?php endforeach; ?><td><strong>BDT <?= e(number_format((float) $category['total_amount'], 2)) ?></strong></td><td><?= (float) $category['change_amount'] >= 0 ? '+' : '' ?>BDT <?= e(number_format((float) $category['change_amount'], 2)) ?><?= $category['change_percentage'] === null ? '' : ' ('.e($category['change_percentage']).'%)' ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <h2>OpenAI category-wise costing analysis</h2><div class="section-note">Narrative analysis based only on the figures in this report.</div>
    <h2>Selected period: category cost reduction opportunities</h2><div class="section-note">Highest-cost categories from <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>, used to inform the suggestions below.</div>
    <table class="data"><thead><tr><th>Category</th><th>Period costing</th><th>Transactions</th><th>Average transaction</th></tr></thead><tbody><?php if (empty($periodCategoryCosting)): ?><tr><td colspan="4" style="text-align:center; padding:20px;">No category costing was recorded for the selected period.</td></tr><?php endif; ?><?php foreach ($periodCategoryCosting as $index => $item): ?><tr><td><?= e($englishText($item['category_name'], 'Category '.($index + 1))) ?></td><td>BDT <?= e(number_format((float) $item['total_amount'], 2)) ?></td><td><?= e($item['transaction_count']) ?></td><td>BDT <?= e(number_format((float) $item['average_transaction_amount'], 2)) ?></td></tr><?php endforeach; ?></tbody></table>
    <?php $analysisCards = []; foreach (['snapshot' => 'Snapshot', 'trends' => 'Trends', 'category_comparison' => 'Category comparison', 'watchouts' => 'Watchouts', 'next_steps' => 'Next steps', 'cost_reduction_suggestions' => 'Cost reduction suggestions'] as $key => $label) { if (! empty($analysis['sections'][$key])) $analysisCards[] = ['label' => $label, 'items' => $analysis['sections'][$key]]; } ?>
    <table class="analysis-grid"><?php foreach (array_chunk($analysisCards, 2) as $cardRow): ?><tr><?php foreach ($cardRow as $card): ?><td><strong><?= e($card['label']) ?></strong><ul><?php foreach ($card['items'] as $item): ?><li><?= e($englishText($item, 'English analysis is unavailable for this item.')) ?></li><?php endforeach; ?></ul></td><?php endforeach; ?><?php if (count($cardRow) === 1): ?><td></td><?php endif; ?></tr><?php endforeach; ?></table>
    </div>
    <div class="footer"><?= e(config('app.name')) ?> · Financial Overview · <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?></div>
</body>
</html>
