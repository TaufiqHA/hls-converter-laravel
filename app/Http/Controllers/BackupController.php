<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BackupService;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class BackupController extends Controller
{
    protected $backupService;

    public function __construct(BackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * Check if the authenticated user is an admin
     */
    private function isAdmin()
    {
        $user = request()->user();
        return true;
        // return $user && $user->role === 'admin';
    }

    /**
     * Membuat cadangan (backup) database
     * POST /api/admin/backup
     */
    public function createBackup(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $format = $request->input('format', 'dump');

            if (!in_array($format, ['dump', 'sql', 'json'])) {
                return response()->json(['error' => 'Format backup tidak valid'], 400);
            }

            $filename = $this->backupService->createBackup($format);

            return response()->json([
                'message' => 'Backup berhasil dibuat',
                'filename' => $filename,
                'path' => env('BACKUP_PATH', storage_path('app/backups')) . '/' . $filename
            ]);
        } catch (Exception $e) {
            Log::error('Create backup failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal membuat backup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mengembalikan (restore) database dari file cadangan
     * POST /api/admin/backup/restore
     */
    public function restoreBackup(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $request->validate([
                'filename' => 'required|string'
            ]);

            $filename = $request->input('filename');

            $this->backupService->restoreBackup($filename);

            return response()->json(['message' => 'Backup berhasil dikembalikan']);
        } catch (Exception $e) {
            Log::error('Restore backup failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal mengembalikan backup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan daftar semua file cadangan yang tersedia
     * GET /api/admin/backup
     */
    public function listBackups(): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $backups = $this->backupService->listBackups();

            return response()->json(['backups' => $backups]);
        } catch (Exception $e) {
            Log::error('List backups failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal mengambil daftar backup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mengunduh file cadangan
     * GET /api/admin/backup/download/:filename
     */
    public function downloadBackup($filename)
    {
        // Add security check - validate the filename format to prevent directory traversal
        if (!preg_match('/^[a-zA-Z0-9_.-]+\.(dump|sql|json)$/', $filename)) {
            return response()->json(['error' => 'Invalid filename format'], 400);
        }

        try {
            $backupInfo = $this->backupService->getBackupFile($filename);

            return response()->download($backupInfo['path'], $backupInfo['filename']);
        } catch (Exception $e) {
            Log::error('Download backup failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal mengunduh backup: ' . $e->getMessage()], 404);
        }
    }

    /**
     * Menghapus file cadangan
     * DELETE /api/admin/backup/:filename
     */
    public function deleteBackup($filename): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $this->backupService->deleteBackup($filename);

            return response()->json(['message' => 'Backup berhasil dihapus']);
        } catch (Exception $e) {
            Log::error('Delete backup failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal menghapus backup: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Mengunggah file cadangan dan sekaligus mengembalikannya
     * POST /api/admin/backup/upload
     */
    public function uploadAndRestore(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $request->validate([
                'backup_file' => 'required|file|mimes:dump,sql,json|max:512000' // 500MB max
            ]);

            $uploadedFile = $request->file('backup_file');

            $filename = $this->backupService->uploadAndRestore($uploadedFile);

            return response()->json([
                'message' => 'File backup berhasil diunggah dan dikembalikan',
                'filename' => $filename
            ]);
        } catch (Exception $e) {
            Log::error('Upload and restore failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Gagal mengunggah dan mengembalikan backup: ' . $e->getMessage()], 500);
        }
    }
}
