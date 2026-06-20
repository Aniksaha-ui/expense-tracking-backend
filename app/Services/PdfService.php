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
        $dompdf->loadHtml(View::make($view, $data)->render());
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
