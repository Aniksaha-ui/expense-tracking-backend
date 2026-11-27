<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorageUrl
{
    /**
     * Resolve a path stored on the public disk to an absolute URL.
     * Uses the active HTTP request host/base path when available so local dev
     * (`http://127.0.0.1:8000`) and production subfolder deployments both work.
     */
    public static function resolve(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $trimmed = trim($path);

        if (preg_match('/^(https?:)?\/\//i', $trimmed) || str_starts_with($trimmed, 'mailto:')) {
            return $trimmed;
        }

        $relative = ltrim($trimmed, '/');

        if (! app()->runningInConsole() && app()->bound('request') && request()->getHttpHost() !== '') {
            return url('storage/'.$relative);
        }

        return Storage::disk('public')->url($relative);
    }
}
