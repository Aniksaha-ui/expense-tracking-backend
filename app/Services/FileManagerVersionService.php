<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FileManagerVersionService
{
    private const DISK = 'local';

    public function list(int $userId, int $fileId): Collection
    {
        $file = $this->ownedFile($userId, $fileId);
        $this->ensureInitialVersion($file);

        return DB::table('file_manager_file_versions')
            ->where('user_id', $userId)
            ->where('file_id', $fileId)
            ->orderByDesc('version')
            ->get();
    }

    public function upload(UploadedFile $upload, int $userId, int $fileId): object
    {
        $file = $this->ownedFile($userId, $fileId);
        $this->ensureInitialVersion($file);

        $extension = $upload->getClientOriginalExtension();
        $storedName = Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $upload->storeAs('file-manager/'.$userId, $storedName, self::DISK);
        $originalName = $upload->getClientOriginalName();
        $nextVersion = (int) DB::table('file_manager_file_versions')->where('file_id', $fileId)->max('version') + 1;

        DB::transaction(function () use ($fileId, $userId, $upload, $path, $originalName, $nextVersion) {
            DB::table('file_manager_file_versions')->insert([
                'file_id' => $fileId, 'user_id' => $userId, 'version' => $nextVersion,
                'original_name' => $originalName, 'path' => $path,
                'mime_type' => $upload->getClientMimeType(), 'size' => $upload->getSize(), 'created_at' => now(),
            ]);
            DB::table('file_manager_files')->where('id', $fileId)->update([
                'name' => $this->cleanName(pathinfo($originalName, PATHINFO_FILENAME)),
                'original_name' => $originalName, 'path' => $path,
                'mime_type' => $upload->getClientMimeType(), 'size' => $upload->getSize(), 'updated_at' => now(),
            ]);
        });

        return $this->ownedFile($userId, $fileId);
    }

    public function restore(int $userId, int $fileId, int $versionId): object
    {
        $file = $this->ownedFile($userId, $fileId);
        $this->ensureInitialVersion($file);
        $version = DB::table('file_manager_file_versions')
            ->where('id', $versionId)->where('file_id', $fileId)->where('user_id', $userId)->first();

        if (!$version || !Storage::disk(self::DISK)->exists($version->path)) {
            throw new RuntimeException('Version not found or unavailable.');
        }

        $extension = pathinfo($version->original_name, PATHINFO_EXTENSION);
        $path = 'file-manager/'.$userId.'/'.Str::uuid()->toString().($extension ? '.'.$extension : '');
        Storage::disk(self::DISK)->copy($version->path, $path);
        $nextVersion = (int) DB::table('file_manager_file_versions')->where('file_id', $fileId)->max('version') + 1;

        DB::transaction(function () use ($fileId, $userId, $version, $path, $nextVersion) {
            DB::table('file_manager_file_versions')->insert([
                'file_id' => $fileId, 'user_id' => $userId, 'version' => $nextVersion,
                'original_name' => $version->original_name, 'path' => $path,
                'mime_type' => $version->mime_type, 'size' => $version->size, 'created_at' => now(),
            ]);
            DB::table('file_manager_files')->where('id', $fileId)->update([
                'name' => $this->cleanName(pathinfo($version->original_name, PATHINFO_FILENAME)),
                'original_name' => $version->original_name, 'path' => $path,
                'mime_type' => $version->mime_type, 'size' => $version->size, 'updated_at' => now(),
            ]);
        });

        return $this->ownedFile($userId, $fileId);
    }

    public function purgeStorage(int $userId, int $fileId): void
    {
        $paths = DB::table('file_manager_file_versions')
            ->where('user_id', $userId)->where('file_id', $fileId)->pluck('path')->all();

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete(array_unique($paths));
        }
    }

    private function ensureInitialVersion(object $file): void
    {
        if (DB::table('file_manager_file_versions')->where('file_id', $file->id)->exists()) {
            return;
        }

        DB::table('file_manager_file_versions')->insert([
            'file_id' => $file->id, 'user_id' => $file->user_id, 'version' => 1,
            'original_name' => $file->original_name, 'path' => $file->path,
            'mime_type' => $file->mime_type, 'size' => $file->size, 'created_at' => $file->created_at,
        ]);
    }

    private function ownedFile(int $userId, int $fileId): object
    {
        $file = DB::table('file_manager_files')->where('id', $fileId)->where('user_id', $userId)->first();
        if (!$file) {
            throw new RuntimeException('File not found.');
        }

        return $file;
    }

    private function cleanName(string $name): string
    {
        $name = trim(Str::squish($name));
        if ($name === '' || $name === '.' || $name === '..') {
            throw new RuntimeException('Please enter a valid name.');
        }

        return $name;
    }
}
