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
    <?php if (empty($transferReceiptsByAccount)): ?><tr><td colspan="2">No transfers were received during this period.</td></tr><?php endif; ?>
    <?php foreach ($transferReceiptsByAccount as $receipt): ?><tr><td><?= e($receipt['account_name']) ?></td><td><strong>BDT <?= e(number_format((float) $receipt['total_amount'], 2)) ?></strong></td></tr><?php endforeach; ?>
</table>

<p><strong>Account movement</strong></p>
<p>Money added and money out include all recorded account movements. Transfers in and out are shown separately because they move money between your own accounts and do not by themselves change your total portfolio balance.</p>
<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><th>Account</th><th>Opening</th><th>Added</th><th>Out</th><th>Trend</th><th>Closing</th></tr>
    <?php if (empty($accountActivity)): ?><tr><td colspan="6">No account activity is available for this reporting period.</td></tr><?php endif; ?>
    <?php foreach ($accountActivity as $account): ?><tr><td><?= e($account['account_name']) ?><?php if (!empty($account['institution_name'])): ?> (<?= e($account['institution_name']) ?>)<?php endif; ?></td><td>BDT <?= e(number_format((float) $account['opening_balance'], 2)) ?></td><td>BDT <?= e(number_format((float) $account['money_added'], 2)) ?></td><td>BDT <?= e(number_format((float) $account['money_out'], 2)) ?></td><td><strong><?= e($account['trend']) ?> (<?= (float) $account['net_change'] >= 0 ? '+' : '-' ?>BDT <?= e(number_format(abs((float) $account['net_change']), 2)) ?>)</strong></td><td><strong>BDT <?= e(number_format((float) $account['closing_balance'], 2)) ?></strong></td></tr><?php endforeach; ?>
</table>

<p>Regards,<br><?= e($appName) ?></p>
