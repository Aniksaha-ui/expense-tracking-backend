<?php

return [
    'paper' => env('DOMPDF_PAPER', 'a4'),
    'orientation' => env('DOMPDF_ORIENTATION', 'portrait'),

    'options' => [
        'defaultFont' => env('DOMPDF_DEFAULT_FONT', 'Helvetica'),
        'isRemoteEnabled' => false,
        'isPhpEnabled' => false,
        'isJavascriptEnabled' => false,
        'isHtml5ParserEnabled' => true,
        'chroot' => public_path(),
        'tempDir' => storage_path('app/dompdf'),
        'fontDir' => storage_path('app/dompdf/fonts'),
        'fontCache' => storage_path('app/dompdf/fonts'),
    ],
];
