<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class FileManagerService
{
    private const DISK = 'local';

    public function listItems(int $userId, ?int $folderId): array
    {
        $folder = $folderId ? $this->getFolder($userId, $folderId) : null;

        return [
            'folder' => $folder,
            'breadcrumbs' => $folder ? $this->breadcrumbs($userId, $folder) : [],
            'folders' => DB::table('file_manager_folders')
                ->where('user_id', $userId)
                ->when($folderId, fn ($query) => $query->where('parent_id', $folderId), fn ($query) => $query->whereNull('parent_id'))
                ->orderBy('name')
                ->get(),
            'files' => DB::table('file_manager_files')
                ->where('user_id', $userId)
                ->when($folderId, fn ($query) => $query->where('folder_id', $folderId), fn ($query) => $query->whereNull('folder_id'))
                ->orderByDesc('updated_at')
                ->get(),
        ];
    }

    public function createFolder(array $data, int $userId): object
    {
        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            $this->getFolder($userId, (int) $parentId);
        }

        $name = $this->cleanName($data['name']);
        $this->ensureFolderNameAvailable($userId, $parentId, $name);

        $id = DB::table('file_manager_folders')->insertGetId([
            'user_id' => $userId, 'parent_id' => $parentId, 'name' => $name,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->getFolder($userId, $id);
    }

    public function renameFolder(array $data, int $userId, int $folderId): object
    {
        $folder = $this->getFolder($userId, $folderId);
        $name = $this->cleanName($data['name']);
        $this->ensureFolderNameAvailable($userId, $folder->parent_id, $name, $folderId);

        DB::table('file_manager_folders')->where('id', $folderId)->update(['name' => $name, 'updated_at' => now()]);

        return $this->getFolder($userId, $folderId);
    }

    public function deleteFolder(int $userId, int $folderId): void
    {
        $this->getFolder($userId, $folderId);
        $folderIds = $this->descendantFolderIds($userId, $folderId);
        $files = DB::table('file_manager_files')->where('user_id', $userId)->whereIn('folder_id', $folderIds)->get();

        DB::transaction(function () use ($folderIds) {
            DB::table('file_manager_files')->whereIn('folder_id', $folderIds)->delete();
            foreach (array_reverse($folderIds) as $id) {
                DB::table('file_manager_folders')->where('id', $id)->delete();
            }
        });

        foreach ($files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }
    }

    public function uploadFiles(array $data, int $userId): Collection
    {
        $folderId = $data['folder_id'] ?? null;
        if ($folderId) {
            $this->getFolder($userId, (int) $folderId);
        }

        return collect($data['files'])->map(function (UploadedFile $file) use ($userId, $folderId) {
            $extension = $file->getClientOriginalExtension();
            $storedName = Str::uuid()->toString().($extension ? '.'.$extension : '');
            $path = $file->storeAs('file-manager/'.$userId, $storedName, self::DISK);

            $id = DB::table('file_manager_files')->insertGetId([
                'user_id' => $userId,
                'folder_id' => $folderId,
                'name' => $this->cleanName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                'original_name' => $file->getClientOriginalName(),
                'disk' => self::DISK,
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $storedFile = $this->getFile($userId, $id);
            $this->storeVersion($storedFile, 1);

            return $storedFile;
        });
    }

    public function renameFile(array $data, int $userId, int $fileId): object
    {
        $file = $this->getFile($userId, $fileId);
        $name = $this->cleanName($data['name']);
        $extension = pathinfo($file->original_name, PATHINFO_EXTENSION);
        $originalName = $name.($extension ? '.'.$extension : '');

        DB::table('file_manager_files')->where('id', $fileId)->update([
            'name' => $name, 'original_name' => $originalName, 'updated_at' => now(),
        ]);

        return $this->getFile($userId, $fileId);
    }

    public function deleteFile(int $userId, int $fileId): void
    {
        $file = $this->getFile($userId, $fileId);
        DB::table('file_manager_files')->where('id', $fileId)->delete();
        Storage::disk($file->disk)->delete($file->path);
    }

    public function getFile(int $userId, int $fileId): object
    {
        $file = DB::table('file_manager_files')->where('user_id', $userId)->where('id', $fileId)->first();
        if (!$file) {
            throw new RuntimeException('File not found.');
        }

        return $file;
    }

    public function filesForDownload(int $userId, array $fileIds): Collection
    {
        $files = DB::table('file_manager_files')->where('user_id', $userId)->whereIn('id', array_unique($fileIds))->get();
        if ($files->count() !== count(array_unique($fileIds))) {
            throw new RuntimeException('One or more selected files could not be found.');
        }

        return $files->filter(fn ($file) => Storage::disk($file->disk)->exists($file->path))->values();
    }

    public function createZip(Collection $files): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'file-manager-');
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the download archive.');
        }

        foreach ($files as $file) {
            $zip->addFile(Storage::disk($file->disk)->path($file->path), $file->original_name);
        }

        $zip->close();

        return $zipPath;
    }

    private function storeVersion(object $file, int $version): void
    {
        DB::table("file_manager_file_versions")->insert([
            "file_id" => $file->id,
            "user_id" => $file->user_id,
            "version" => $version,
            "original_name" => $file->original_name,
            "path" => $file->path,
            "mime_type" => $file->mime_type,
            "size" => $file->size,
            "created_at" => now(),
        ]);
    }

    private function getFolder(int $userId, int $folderId): object
    {
        $folder = DB::table('file_manager_folders')->where('user_id', $userId)->where('id', $folderId)->first();
        if (!$folder) {
            throw new RuntimeException('Folder not found.');
        }

        return $folder;
    }

    private function breadcrumbs(int $userId, object $folder): array
    {
        $items = [];
        while ($folder) {
            array_unshift($items, ['id' => $folder->id, 'name' => $folder->name]);
            $folder = $folder->parent_id ? $this->getFolder($userId, $folder->parent_id) : null;
        }

        return $items;
    }

    private function descendantFolderIds(int $userId, int $folderId): array
    {
        $ids = [$folderId];
        $frontier = [$folderId];

        while ($frontier !== []) {
            $frontier = DB::table('file_manager_folders')
                ->where('user_id', $userId)->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    private function ensureFolderNameAvailable(int $userId, ?int $parentId, string $name, ?int $exceptId = null): void
    {
        $query = DB::table('file_manager_folders')->where('user_id', $userId)->where('name', $name);
        $parentId ? $query->where('parent_id', $parentId) : $query->whereNull('parent_id');
        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }
        if ($query->exists()) {
            throw new RuntimeException('A folder with this name already exists here.');
        }
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
