<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class PdfService
{
    public function render(
        string $view,
        array $data = [],
        ?string $paper = null,
        ?string $orientation = null,
    ): string
    {
        $options = new Options();

        foreach (config('dompdf.options', []) as $name => $value) {
            $options->set($name, $value);
        }
        if (in_array($view, ['reports.financial-overview-pdf', 'reports.savings-offers-pdf'], true)) {
            $options->setDefaultFont('Helvetica');
        }

        $this->ensureWritableDirectories($options);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper(
            $paper ?? config('dompdf.paper', 'a4'),
            $orientation ?? config('dompdf.orientation', 'portrait')
        );
        // User-entered category, account, and note data can contain malformed
        // byte sequences. Dompdf's font registration calls iconv and fails on
        // those sequences, preventing the entire scheduled report from sending.
        // JSON's substitute flag preserves valid Unicode while replacing only
        // invalid UTF-8 bytes with the standard replacement character.
        $html = View::make($view, $data)->render();
        $encodedHtml = json_encode($html, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encodedHtml !== false) {
            $html = json_decode($encodedHtml, true, 512, JSON_THROW_ON_ERROR);
        }
        // Dompdf's SVG/font stack can still invoke iconv for legacy malformed
        // labels. Scheduled attachments must be deliverable even when old
        // records contain invalid multibyte data, so keep their renderer input
        // strictly ASCII-safe. This affects presentation text only, never data.
        if (in_array($view, ['reports.financial-overview-pdf', 'reports.savings-offers-pdf'], true)) {
            $html = preg_replace('/[^\x00-\x7F]/', '?', $html) ?? $html;
        }
        $dompdf->loadHtml($html);
        try {
            $dompdf->render();
        } catch (\Throwable $exception) {
            // A malformed legacy string must never prevent scheduled report
            // delivery. Retry using the built-in Helvetica font and plain,
            // ASCII-safe document text; the original exception is retained in
            // logs for cleanup of the offending source record.
            Log::warning('PDF renderer fell back to a safe document.', ['view' => $view, 'exception' => $exception]);
            $fallback = '<!doctype html><html><body style="font-family:Helvetica,sans-serif;font-size:11px;line-height:1.45"><h1>Financial report</h1><pre style="white-space:pre-wrap">'.htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</pre></body></html>';
            // Do not reuse configured font directories/cache for the retry:
            // a corrupt or partial custom font is one possible iconv source.
            $fallbackOptions = new Options;
            $fallbackOptions->setDefaultFont('Helvetica');
            $fallbackOptions->setIsRemoteEnabled(false);
            $fallbackOptions->setIsPhpEnabled(false);
            $fallbackOptions->setIsJavascriptEnabled(false);
            $dompdf = new Dompdf($fallbackOptions);
            $dompdf->setPaper($paper ?? config('dompdf.paper', 'a4'), $orientation ?? config('dompdf.orientation', 'portrait'));
            $dompdf->loadHtml($fallback);
            $dompdf->render();
        }

        return $dompdf->output();
    }

    private function ensureWritableDirectories(Options $options): void
    {
        foreach ([$options->getTempDir(), $options->getFontDir(), $options->getFontCache()] as $directory) {
            if ($directory && ! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }
        }
    }
}
