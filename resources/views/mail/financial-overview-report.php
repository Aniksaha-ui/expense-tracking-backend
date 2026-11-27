<p>Hello <?= e($userName) ?>,</p>

<p>Your financial overview and personalized Savings Offers report are attached as PDFs.</p>

<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db; margin-bottom: 12px;">
    <tr><td>From month</td><td><strong><?= e($fromDate->format('F Y')) ?></strong></td></tr>
    <tr><td>To month</td><td><strong><?= e($toDate->format('F Y')) ?></strong></td></tr>
    <tr><td>Reporting period</td><td><strong><?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?></strong></td></tr>
</table>

<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><td>Opening balance (before <?= e($fromDate->format('d M Y')) ?>)</td><td><strong>BDT <?= e(number_format((float) $summary['opening_balance'], 2)) ?></strong></td></tr>
    <tr><td>Total income</td><td><strong>BDT <?= e(number_format((float) $summary['total_income'], 2)) ?></strong></td></tr>
    <tr><td>Total costing</td><td><strong>BDT <?= e(number_format((float) $summary['total_costing'], 2)) ?></strong></td></tr>
    <tr><td>Net income</td><td><strong>BDT <?= e(number_format((float) $summary['net_income'], 2)) ?></strong></td></tr>
    <tr><td>Closing balance (as at <?= e($toDate->format('d M Y')) ?>)</td><td><strong>BDT <?= e(number_format((float) $summary['closing_balance'], 2)) ?></strong></td></tr>
</table>

<p><strong>OpenAI analysis</strong></p>
<div style="white-space: pre-line; border-left: 3px solid #2563eb; color: #334155; padding: 10px 12px; background: #f8fafc;"><?= e($analysis['text'] ?? 'Analysis was unavailable.') ?></div>

<p><strong>Transfer receipts by destination account</strong></p>
<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><th>Account</th><th>Total received</th></tr>
    <?php if (empty($transferReceiptsByAccount)) { ?><tr><td colspan="2">No transfers were received during this period.</td></tr><?php } ?>
    <?php foreach ($transferReceiptsByAccount as $receipt) { ?><tr><td><?= e($receipt['account_name']) ?></td><td><strong>BDT <?= e(number_format((float) $receipt['total_amount'], 2)) ?></strong></td></tr><?php } ?>
</table>

<p><strong>Account movement</strong></p>
<p>Money added and money out include all recorded account movements. Transfers in and out are shown separately because they move money between your own accounts and do not by themselves change your total portfolio balance.</p>
<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><th>Account</th><th>Opening</th><th>Added</th><th>Out</th><th>Trend</th><th>Closing</th></tr>
    <?php if (empty($accountActivity)) { ?><tr><td colspan="6">No account activity is available for this reporting period.</td></tr><?php } ?>
    <?php foreach ($accountActivity as $account) { ?><tr><td><?= e($account['account_name']) ?><?php if (! empty($account['institution_name'])) { ?> (<?= e($account['institution_name']) ?>)<?php } ?></td><td>BDT <?= e(number_format((float) $account['opening_balance'], 2)) ?></td><td>BDT <?= e(number_format((float) $account['money_added'], 2)) ?></td><td>BDT <?= e(number_format((float) $account['money_out'], 2)) ?></td><td><strong><?= e($account['trend']) ?> (<?= (float) $account['net_change'] >= 0 ? '+' : '-' ?>BDT <?= e(number_format(abs((float) $account['net_change']), 2)) ?>)</strong></td><td><strong>BDT <?= e(number_format((float) $account['closing_balance'], 2)) ?></strong></td></tr><?php } ?>
</table>

<?php
$comparison = $monthlyWeekComparison ?? [];
$weekColumns = $comparison['week_columns'] ?? [];
$comparisonMonths = $comparison['months'] ?? [];
$weekSummaries = $comparison['week_summaries'] ?? [];
$comparisonSummary = $comparison['summary'] ?? [];
?>
<?php if (! empty($weekColumns) && ! empty($comparisonMonths)) { ?>
<p style="margin-top: 16px;"><strong>Monthly Week-by-Week Comparison (Last <?= e($comparisonSummary['months_count'] ?? count($comparisonMonths)) ?> Months)</strong></p>
<p style="color: #475569; margin: 4px 0 8px;">
    Total spend: <strong>BDT <?= e(number_format((float) ($comparisonSummary['total_expense'] ?? 0), 2)) ?></strong>
    | Monthly avg: <strong>BDT <?= e(number_format((float) ($comparisonSummary['average_monthly_expense'] ?? 0), 2)) ?></strong>
    <?php if (! empty($comparisonSummary['peak_week_label'])) { ?>| Heaviest week: <strong><?= e($comparisonSummary['peak_week_label']) ?></strong><?php } ?>
    <?php if (! empty($comparisonSummary['highest_month_label'])) { ?>| Highest month: <strong><?= e($comparisonSummary['highest_month_label']) ?></strong><?php } ?>
</p>
<table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db; font-size: 12px;">
    <tr style="background: #eaf2ff; color: #1d4ed8;">
        <th align="left">Month</th>
        <?php foreach ($weekColumns as $column) { ?>
            <th align="right"><?= e($column['label']) ?></th>
        <?php } ?>
        <th align="right" style="background: #dbeafe;">Total</th>
    </tr>
    <?php foreach ($comparisonMonths as $monthRow) { ?>
        <tr>
            <td><strong><?= e($monthRow['month_label']) ?></strong></td>
            <?php foreach ($weekColumns as $column) { ?>
                <?php
                $cell = $monthRow['weeks'][$column['sequence'] - 1] ?? null;
                $cellAmount = $cell ? (float) ($cell['total_expense'] ?? 0) : 0.0;
                $isPeak = $cell && $cellAmount > 0 && ($monthRow['peak_week_sequence'] ?? null) === $cell['week_sequence'];
                ?>
                <td align="right" style="<?= $isPeak ? 'background: #fff1f2; color: #be123c; font-weight: bold;' : '' ?>">
                    <?php if (! $cell || $cellAmount <= 0) { ?>
                        —
                    <?php } else { ?>
                        BDT <?= e(number_format($cellAmount, 2)) ?>
                    <?php } ?>
                </td>
            <?php } ?>
            <td align="right" style="background: #f8fafc;"><strong><?= (float) ($monthRow['total_expense'] ?? 0) > 0 ? 'BDT '.e(number_format((float) $monthRow['total_expense'], 2)) : '—' ?></strong></td>
        </tr>
    <?php } ?>
    <?php if (! empty($weekSummaries)) { ?>
        <tr style="background: #f1f5f9;">
            <td><strong>Week Avg</strong></td>
            <?php foreach ($weekSummaries as $colSummary) { ?>
                <td align="right"><strong><?= (float) ($colSummary['average_expense'] ?? 0) > 0 ? 'BDT '.e(number_format((float) $colSummary['average_expense'], 2)) : '—' ?></strong></td>
            <?php } ?>
            <td align="right"><strong>BDT <?= e(number_format((float) ($comparisonSummary['total_expense'] ?? 0), 2)) ?></strong></td>
        </tr>
    <?php } ?>
</table>
<?php } ?>

<p>Regards,<br><?= e($appName) ?></p>
