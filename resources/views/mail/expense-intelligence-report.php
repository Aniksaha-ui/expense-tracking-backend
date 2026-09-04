<p>Hello <?= e($user->name) ?>,</p>

<p>
    Your <?= e($title) ?> for
    <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>
    is attached as a PDF.
</p>

<p>
    Total income: <strong><?= e($summary['total_income'] ?? '0.00') ?></strong><br>
    Total expense: <strong><?= e($summary['total_expense'] ?? '0.00') ?></strong><br>
    Net cash flow: <strong><?= e($summary['net_cash_flow'] ?? $summary['net_savings'] ?? '0.00') ?></strong>
</p>

<p>Regards,<br><?= e(config('app.name')) ?></p>
