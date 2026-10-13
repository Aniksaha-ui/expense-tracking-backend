<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
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
        $dompdf->loadHtml($html);
        $dompdf->render();

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
