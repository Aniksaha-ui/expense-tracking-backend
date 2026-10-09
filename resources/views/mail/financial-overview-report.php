<p>Hello <?= e($user->name) ?>,</p>

<p>Your financial overview and personalized Savings Offers report for <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?> are attached as PDFs.</p>

<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><td>Opening balance</td><td><strong>BDT <?= e(number_format((float) $summary['opening_balance'], 2)) ?></strong></td></tr>
    <tr><td>Total income</td><td><strong>BDT <?= e(number_format((float) $summary['total_income'], 2)) ?></strong></td></tr>
    <tr><td>Total costing</td><td><strong>BDT <?= e(number_format((float) $summary['total_costing'], 2)) ?></strong></td></tr>
    <tr><td>Net income</td><td><strong>BDT <?= e(number_format((float) $summary['net_income'], 2)) ?></strong></td></tr>
    <tr><td>Closing balance</td><td><strong>BDT <?= e(number_format((float) $summary['closing_balance'], 2)) ?></strong></td></tr>
</table>

<p><strong>OpenAI analysis</strong></p>
<div style="white-space: pre-line; border-left: 3px solid #2563eb; color: #334155; padding: 10px 12px; background: #f8fafc;"><?= e($analysis['text'] ?? 'Analysis was unavailable.') ?></div>

<p><strong>Transfer receipts by destination account</strong></p>
<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><th>Account</th><th>Total received</th></tr>
    <?php if (empty($transferReceiptsByAccount)): ?><tr><td colspan="2">No transfers were received during this period.</td></tr><?php endif; ?>
    <?php foreach ($transferReceiptsByAccount as $receipt): ?><tr><td><?= e($receipt['account_name']) ?></td><td><strong>BDT <?= e(number_format((float) $receipt['total_amount'], 2)) ?></strong></td></tr><?php endforeach; ?>
</table>

<p>Regards,<br><?= e(config('app.name')) ?></p>
