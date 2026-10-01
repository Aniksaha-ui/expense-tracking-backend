<p>Hello <?= e($user->name) ?>,</p>

<p>Your financial overview for <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?> is attached as a PDF.</p>

<table cellpadding="7" cellspacing="0" border="1" style="border-collapse: collapse; border-color: #d1d5db;">
    <tr><td>Opening balance</td><td><strong><?= e($summary['opening_balance']) ?></strong></td></tr>
    <tr><td>Total income</td><td><strong><?= e($summary['total_income']) ?></strong></td></tr>
    <tr><td>Total costing</td><td><strong><?= e($summary['total_costing']) ?></strong></td></tr>
    <tr><td>Net income</td><td><strong><?= e($summary['net_income']) ?></strong></td></tr>
    <tr><td>Closing balance</td><td><strong><?= e($summary['closing_balance']) ?></strong></td></tr>
</table>

<p>Regards,<br><?= e(config('app.name')) ?></p>
