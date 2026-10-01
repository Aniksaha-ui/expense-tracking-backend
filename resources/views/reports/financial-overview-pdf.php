<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Overview</title>
    <style>
        @page { margin: 30px; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: Helvetica, Arial, sans-serif; font-size: 10px; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        h1 { font-size: 25px; margin: 5px 0; } .muted { color: #667085; }
        .brand { color: #2563eb; font-size: 10px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
        .period { background: #2563eb; border-radius: 7px; color: white; margin: 16px 0; padding: 13px 16px; }
        .period small { color: #dbeafe; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .period strong { display: block; font-size: 16px; margin-top: 4px; }
        .summary { border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 17px; }
        .summary td { background: #f8fafc; border: 1px solid #dbe3ef; border-radius: 7px; padding: 10px; width: 20%; }
        .summary label { color: #667085; display: block; font-size: 8px; font-weight: bold; letter-spacing: .6px; text-transform: uppercase; }
        .summary strong { display: block; font-size: 14px; margin-top: 5px; }
        h2 { font-size: 13px; margin: 16px 0 4px; }
        .section-note { color: #667085; font-size: 9px; margin: 0 0 8px; }
        .chart { background: #fbfcfe; border: 1px solid #dbe4ed; margin: 8px 0 15px; padding: 9px; page-break-inside: avoid; }
        .chart img { display: block; width: 100%; }
        .data { border: 1px solid #dbe3ef; margin-top: 8px; } .data th, .data td { border-bottom: 1px solid #e5e9ef; border-right: 1px solid #e5e9ef; padding: 7px 6px; text-align: right; }
        .data th { background: #eff6ff; color: #1d4ed8; font-size: 8px; text-transform: uppercase; } .data td:first-child, .data th:first-child { text-align: left; }
        .data tr:last-child td { border-bottom: 0; } .data th:last-child, .data td:last-child { border-right: 0; }
        .footer { bottom: -18px; color: #98a2b3; font-size: 8px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    <?php
        $svgText = fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $svgMoney = fn ($value): string => number_format((float) $value, 2);
        $chartRows = collect($rows)->values();
        $categoryRows = collect($categories)->take(12)->values();
        $svgDataUri = fn (string $svg): string => 'data:image/svg+xml;base64,'.base64_encode($svg);
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
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Helvetica, Arial" font-size="17" font-weight="700">Opening balance vs closing balance</text>';
            $svg .= '<rect x="18" y="36" width="11" height="11" fill="#60a5fa"/><text x="35" y="46" fill="#475467" font-family="Helvetica, Arial" font-size="10">Opening</text><rect x="108" y="36" width="11" height="11" fill="#2563eb"/><text x="125" y="46" fill="#475467" font-family="Helvetica, Arial" font-size="10">Closing</text>';
            for ($grid = 0; $grid <= 4; $grid++) { $y = $top + ($grid * $plotHeight / 4); $value = $max - ($grid * $range / 4); $svg .= '<line x1="'.$left.'" y1="'.$y.'" x2="'.($width - $right).'" y2="'.$y.'" stroke="#dbe4ed" stroke-width="1"/><text x="8" y="'.($y + 3).'" fill="#667085" font-family="Helvetica, Arial" font-size="8">'.number_format($value, 0).'</text>'; }
            $openingPoints = []; $closingPoints = [];
            foreach ($chartRows as $index => $row) { [$x1, $y1] = $point((float) $row['opening_balance'], $index); [$x2, $y2] = $point((float) $row['closing_balance'], $index); $openingPoints[] = $x1.','.$y1; $closingPoints[] = $x2.','.$y2; $svg .= '<text x="'.($x1 - 16).'" y="'.($height - 15).'" fill="#667085" font-family="Helvetica, Arial" font-size="8">'.$svgText($axisLabel($row)).'</text>'; }
            $svg .= '<polyline fill="none" stroke="#60a5fa" stroke-width="3" points="'.implode(' ', $openingPoints).'"/><polyline fill="none" stroke="#2563eb" stroke-width="3" points="'.implode(' ', $closingPoints).'"/>';
            foreach ($chartRows as $index => $row) { [$x1, $y1] = $point((float) $row['opening_balance'], $index); [$x2, $y2] = $point((float) $row['closing_balance'], $index); $svg .= '<circle cx="'.$x1.'" cy="'.$y1.'" r="3" fill="#60a5fa"/><circle cx="'.$x2.'" cy="'.$y2.'" r="3" fill="#2563eb"/>'; }
            $balanceChart = $svgDataUri($svg.'</svg>');
        }

        $flowChart = null;
        if ($chartRows->isNotEmpty()) {
            $max = max(1, (float) $chartRows->max(fn (array $row): float => max((float) $row['total_income'], (float) $row['total_costing'])));
            $width = 940; $rowHeight = 30; $height = 76 + ($chartRows->count() * $rowHeight); $labelWidth = 185; $plotWidth = 540; $valueX = 755;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><rect width="100%" height="100%" fill="#fbfcfe"/>';
            $svg .= '<text x="18" y="25" fill="#172033" font-family="Helvetica, Arial" font-size="17" font-weight="700">Income vs costing by month</text><rect x="18" y="38" width="11" height="11" fill="#16a34a"/><text x="35" y="48" fill="#475467" font-family="Helvetica, Arial" font-size="10">Income</text><rect x="105" y="38" width="11" height="11" fill="#e11d48"/><text x="122" y="48" fill="#475467" font-family="Helvetica, Arial" font-size="10">Costing</text>';
            foreach ($chartRows as $index => $row) { $y = 61 + ($index * $rowHeight); $income = (float) $row['total_income']; $costing = (float) $row['total_costing']; $incomeWidth = max(2, (int) round($income / $max * $plotWidth)); $costingWidth = max(2, (int) round($costing / $max * $plotWidth)); $svg .= '<text x="18" y="'.($y + 14).'" fill="#172033" font-family="Helvetica, Arial" font-size="10" font-weight="700">'.$svgText($axisLabel($row)).'</text><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$plotWidth.'" height="10" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$incomeWidth.'" height="10" fill="#16a34a"/><rect x="'.$labelWidth.'" y="'.($y + 13).'" width="'.$plotWidth.'" height="10" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.($y + 13).'" width="'.$costingWidth.'" height="10" fill="#e11d48"/><text x="'.$valueX.'" y="'.($y + 9).'" fill="#15803d" font-family="Helvetica, Arial" font-size="9">BDT '.$svgMoney($income).'</text><text x="'.$valueX.'" y="'.($y + 22).'" fill="#be123c" font-family="Helvetica, Arial" font-size="9">BDT '.$svgMoney($costing).'</text>'; }
            $flowChart = $svgDataUri($svg.'</svg>');
        }

        $categoryChart = null;
        if ($categoryRows->isNotEmpty()) {
            $max = max(1, (float) $categoryRows->max(fn (array $row): float => (float) $row['amount'])); $width = 940; $rowHeight = 25; $height = 65 + ($categoryRows->count() * $rowHeight); $labelWidth = 220; $plotWidth = 525; $valueX = 765;
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><rect width="100%" height="100%" fill="#fbfcfe"/><text x="18" y="25" fill="#172033" font-family="Helvetica, Arial" font-size="17" font-weight="700">Costing by category</text><text x="18" y="44" fill="#667085" font-family="Helvetica, Arial" font-size="10">Top categories ranked by total spend.</text>';
            foreach ($categoryRows as $index => $row) { $y = 54 + ($index * $rowHeight); $amount = (float) $row['amount']; $barWidth = max(2, (int) round($amount / $max * $plotWidth)); $svg .= '<text x="18" y="'.($y + 12).'" fill="#172033" font-family="Helvetica, Arial" font-size="10" font-weight="700">'.$svgText($row['category']).'</text><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$plotWidth.'" height="14" fill="#e8eef3"/><rect x="'.$labelWidth.'" y="'.$y.'" width="'.$barWidth.'" height="14" fill="#7c3aed"/><text x="'.$valueX.'" y="'.($y + 12).'" fill="#475467" font-family="Helvetica, Arial" font-size="10" font-weight="700">BDT '.$svgMoney($amount).'</text>'; }
            $categoryChart = $svgDataUri($svg.'</svg>');
        }
    ?>
    <div class="brand">Expense Tracking System</div>
    <h1>Financial Overview</h1>
    <div class="muted">Balance, income, and costing performance report</div>
    <div class="period"><small>Reporting period</small><strong><?= e($fromDate->format('d M Y')) ?> — <?= e($toDate->format('d M Y')) ?></strong></div>
    <table class="summary"><tr>
        <?php foreach (['Opening Balance' => 'opening_balance', 'Total Income' => 'total_income', 'Total Costing' => 'total_costing', 'Net Income' => 'net_income', 'Closing Balance' => 'closing_balance'] as $label => $key): ?>
            <td><label><?= e($label) ?></label><strong>BDT <?= e(number_format((float) ($summary[$key] ?? 0), 2)) ?></strong></td>
        <?php endforeach; ?>
    </tr></table>
    <h2>Balance movement</h2><div class="section-note">Compare each reporting period’s opening and closing portfolio balance.</div>
    <?php if ($balanceChart): ?><div class="chart"><img src="<?= e($balanceChart) ?>" alt="Opening and closing balance chart"></div><?php else: ?><div class="section-note">No balance movement is available for this period.</div><?php endif; ?>
    <h2>Income and costing movement</h2><div class="section-note">Compare earned income against costs for each reporting period.</div>
    <?php if ($flowChart): ?><div class="chart"><img src="<?= e($flowChart) ?>" alt="Income and costing chart"></div><?php else: ?><div class="section-note">No income or costing movement is available for this period.</div><?php endif; ?>
    <h2>Monthly financial ledger</h2><div class="muted">Opening and closing balances with income and costing by period.</div>
    <table class="data"><thead><tr><th>Month</th><th>Opening</th><th>Income</th><th>Costing</th><th>Net Income</th><th>Closing</th><th>Entries</th></tr></thead><tbody>
        <?php if (empty($rows)): ?><tr><td colspan="7" style="text-align:center; padding:20px;">No transactions were recorded for this period.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr><td><?= e(\Carbon\Carbon::parse($row['date'])->format('M Y')) ?></td><td><?= e($row['opening_balance']) ?></td><td><?= e($row['total_income']) ?></td><td><?= e($row['total_costing']) ?></td><td><?= e($row['net_income']) ?></td><td><?= e($row['closing_balance']) ?></td><td><?= e($row['transaction_count']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <h2>Costing by category</h2>
    <div class="section-note">The chart ranks the top 12 categories; the table below lists every category in the selected period.</div>
    <?php if ($categoryChart): ?><div class="chart"><img src="<?= e($categoryChart) ?>" alt="Costing by category chart"></div><?php endif; ?>
    <table class="data"><thead><tr><th>Category</th><th>Costing</th></tr></thead><tbody>
        <?php if (empty($categories)): ?><tr><td colspan="2" style="text-align:center; padding:20px;">No costing categories were recorded for this period.</td></tr><?php endif; ?>
        <?php foreach ($categories as $category): ?><tr><td><?= e($category['category']) ?></td><td><?= e($category['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <div class="footer"><?= e(config('app.name')) ?> · Financial Overview · <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?></div>
</body>
</html>
