<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileManagerTextEditorService
{
    private const DISK = 'local';
    private const MAX_BYTES = 1024 * 1024;

    public function read(int $userId, int $fileId): array
    {
        $file = $this->ownedTextFile($userId, $fileId);

        if (!Storage::disk($file->disk)->exists($file->path)) {
            throw new RuntimeException('The stored file is unavailable.');
        }

        return ['content' => Storage::disk($file->disk)->get($file->path), 'file' => $file];
    }

    public function save(int $userId, int $fileId, string $content): object
    {
        if (strlen($content) > self::MAX_BYTES) {
            throw new RuntimeException('Text documents are limited to 1 MB.');
        }

        $file = $this->ownedTextFile($userId, $fileId);
        app(FileManagerVersionService::class)->list($userId, $fileId);
        $nextVersion = (int) DB::table('file_manager_file_versions')->where('file_id', $fileId)->max('version') + 1;
        $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $path = 'file-manager/'.$userId.'/'.Str::uuid()->toString().($extension ? '.'.$extension : '');

        Storage::disk(self::DISK)->put($path, $content);

        DB::transaction(function () use ($file, $fileId, $userId, $content, $path, $nextVersion) {
            DB::table('file_manager_file_versions')->insert([
                'file_id' => $fileId, 'user_id' => $userId, 'version' => $nextVersion,
                'original_name' => $file->original_name, 'path' => $path,
                'mime_type' => $file->mime_type, 'size' => strlen($content), 'created_at' => now(),
            ]);
            DB::table('file_manager_files')->where('id', $fileId)->update([
                'path' => $path, 'size' => strlen($content), 'updated_at' => now(),
            ]);
        });

        return DB::table('file_manager_files')->where('id', $fileId)->first();
    }

    private function ownedTextFile(int $userId, int $fileId): object
    {
        $file = DB::table('file_manager_files')->where('id', $fileId)->where('user_id', $userId)->first();
        if (!$file) {
            throw new RuntimeException('File not found.');
        }
        if (!$this->isTextFile($file)) {
            throw new RuntimeException('Only text-based documents can be edited in the browser.');
        }
        if ($file->size > self::MAX_BYTES) {
            throw new RuntimeException('Text documents larger than 1 MB cannot be edited in the browser.');
        }

        return $file;
    }

    private function isTextFile(object $file): bool
    {
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        return str_starts_with((string) $file->mime_type, 'text/')
            || in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'html', 'css', 'js'], true);
    }
}
