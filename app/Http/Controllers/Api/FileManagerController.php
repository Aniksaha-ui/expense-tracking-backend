<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FileManager\BulkDownloadRequest;
use App\Http\Requests\Api\FileManager\CreateFolderRequest;
use App\Http\Requests\Api\FileManager\RenameItemRequest;
use App\Http\Requests\Api\FileManager\UploadFilesRequest;
use App\Http\Requests\Api\FileManager\UploadFileVersionRequest;
use App\Services\FileManagerService;
use App\Services\FileManagerTextEditorService;
use App\Services\FileManagerVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileManagerController extends Controller
{
    public function __construct(
        private readonly FileManagerService $fileManagerService,
        private readonly FileManagerVersionService $fileManagerVersionService,
        private readonly FileManagerTextEditorService $fileManagerTextEditorService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerService->listItems(auth()->id(), $request->integer('folder_id') ?: null));
        } catch (\Exception $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function storeFolder(CreateFolderRequest $request): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerService->createFolder($request->validated(), auth()->id()), 'Folder created successfully.', 201);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function renameFolder(RenameItemRequest $request, int $folderId): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerService->renameFolder($request->validated(), auth()->id(), $folderId), 'Folder renamed successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function destroyFolder(int $folderId): JsonResponse
    {
        try {
            $this->fileManagerService->deleteFolder(auth()->id(), $folderId);
            return $this->successResponse([], 'Folder and its contents deleted successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function uploadFiles(UploadFilesRequest $request): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerService->uploadFiles($request->validated(), auth()->id()), 'Files uploaded successfully.', 201);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function renameFile(RenameItemRequest $request, int $fileId): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerService->renameFile($request->validated(), auth()->id(), $fileId), 'File renamed successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function destroyFile(int $fileId): JsonResponse
    {
        try {
            $this->fileManagerVersionService->purgeStorage(auth()->id(), $fileId);
            $this->fileManagerService->deleteFile(auth()->id(), $fileId);
            return $this->successResponse([], 'File deleted successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function versions(int $fileId): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerVersionService->list(auth()->id(), $fileId));
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function uploadVersion(UploadFileVersionRequest $request, int $fileId): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerVersionService->upload($request->file('file'), auth()->id(), $fileId), 'New document version uploaded successfully.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function restoreVersion(int $fileId, int $versionId): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerVersionService->restore(auth()->id(), $fileId, $versionId), 'Document version restored as the latest version.');
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function previewFile(int $fileId): Response
    {
        try {
            $file = $this->fileManagerService->getFile(auth()->id(), $fileId);
            if (!Storage::disk($file->disk)->exists($file->path)) {
                return $this->errorResponse("The stored file is unavailable.", status: 404);
            }
            return response()->file(Storage::disk($file->disk)->path($file->path));
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function textContent(int $fileId): JsonResponse
    {
        try {
            return $this->successResponse($this->fileManagerTextEditorService->read(auth()->id(), $fileId));
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function updateTextContent(Request $request, int $fileId): JsonResponse
    {
        try {
            $content = $request->input("content");
            if (!is_string($content)) {
                return $this->errorResponse("Document content is required.", status: 422);
            }
            return $this->successResponse($this->fileManagerTextEditorService->save(auth()->id(), $fileId, $content), "Document saved as a new version.");
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }

    public function downloadFile(int $fileId): Response
    {
        try {
            $file = $this->fileManagerService->getFile(auth()->id(), $fileId);
            if (!Storage::disk($file->disk)->exists($file->path)) {
                return $this->errorResponse('The stored file is unavailable.', status: 404);
            }

            return Storage::disk($file->disk)->download($file->path, $file->original_name);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        }
    }

    public function downloadFiles(BulkDownloadRequest $request): Response
    {
        try {
            $files = $this->fileManagerService->filesForDownload(auth()->id(), $request->validated('file_ids'));
            if ($files->isEmpty()) {
                return $this->errorResponse('No stored files are available for download.', status: 404);
            }

            $zipPath = $this->fileManagerService->createZip($files);
            return response()->download($zipPath, 'file-manager-download.zip')->deleteFileAfterSend(true);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 422);
        }
    }
}
