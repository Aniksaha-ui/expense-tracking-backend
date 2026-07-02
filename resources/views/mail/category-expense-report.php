<p>Hello <?= e($user->name) ?>,</p>

<p>
    Your category-wise expense report for
    <?= e($fromDate->format('d M Y')) ?> to <?= e($toDate->format('d M Y')) ?>
    is attached as a PDF.
</p>

<p>Total expense: <strong><?= e($total) ?></strong></p>

<p>Regards,<br><?= e(config('app.name')) ?></p>
