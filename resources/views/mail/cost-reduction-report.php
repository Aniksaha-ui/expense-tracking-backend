<p>Hello <?= e($user->name) ?>,</p>

<p>
    Your cost reduction report for
    <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>
    is attached as a PDF.
</p>

<p>
    Total expense: <strong><?= e($summary['total_expense']) ?></strong><br>
    Estimated potential saving: <strong><?= e($summary['potential_saving']) ?></strong>
</p>

<p>Regards,<br><?= e(config('app.name')) ?></p>
