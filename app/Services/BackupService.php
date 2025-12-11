<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class BackupService
{
    protected $backupPath;

    public function __construct()
    {
        $this->backupPath = env('BACKUP_PATH', storage_path('app/backups'));
        $this->ensureBackupDir();
    }

    /**
     * Membuat backup database PostgreSQL dalam format custom (.dump) menggunakan pg_dump
     */
    public function createBackup($format = 'dump')
    {
        $dbConfig = $this->getDbConfig();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.{$format}";

        $filepath = $this->backupPath . '/' . $filename;

        if ($format === 'dump' || $format === 'sql') {
            // Prepare command options based on format
            $formatOption = ($format === 'dump') ? '--format=custom' : '--format=plain';

            // Escape the database connection parameters to prevent command injection
            $host = escapeshellarg($dbConfig['host']);
            $port = (int)$dbConfig['port']; // Cast to integer to prevent injection
            $username = escapeshellarg($dbConfig['username']);
            $database = escapeshellarg($dbConfig['database']);
            $filepathArg = escapeshellarg($filepath);

            // Use environment variable for password to avoid exposing it in command
            $command = sprintf(
                'pg_dump --host=%s --port=%d --username=%s --dbname=%s %s --file=%s',
                $host, $port, $username, $database, $formatOption, $filepathArg
            );

            // Set password in environment to avoid showing it in command line
            $env = $_ENV;
            $env['PGPASSWORD'] = $dbConfig['password'];

            // Execute the command with environment variables
            $process = proc_open(
                $command,
                [
                    0 => ['pipe', 'r'], // stdin
                    1 => ['pipe', 'w'], // stdout
                    2 => ['pipe', 'w']  // stderr
                ],
                $pipes,
                null,
                $env
            );

            if (is_resource($process)) {
                // Close stdin
                fclose($pipes[0]);

                // Read output and error
                $output = stream_get_contents($pipes[1]);
                $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                $returnVar = proc_close($process);

                if ($returnVar !== 0) {
                    Log::error('Backup gagal', [
                        'command' => $command,
                        'output' => $output,
                        'error' => $error,
                        'return' => $returnVar
                    ]);
                    throw new Exception('Gagal membuat backup database: ' . $error);
                }
            } else {
                throw new Exception('Tidak dapat menjalankan perintah backup');
            }
        } elseif ($format === 'json') {
            return $this->createJsonBackup();
        } else {
            throw new Exception("Format backup tidak didukung: {$format}");
        }

        return $filename;
    }

    /**
     * Mengembalikan database dari file backup
     */
    public function restoreBackup($filename)
    {
        $filepath = $this->backupPath . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new Exception('File backup tidak ditemukan');
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        $dbConfig = $this->getDbConfig();

        if ($extension === 'dump') {
            return $this->restoreDumpBackup($filepath, $dbConfig);
        } elseif ($extension === 'sql') {
            return $this->restoreSqlBackup($filepath, $dbConfig);
        } elseif ($extension === 'json') {
            return $this->restoreJsonBackup($filename);
        } else {
            throw new Exception('Format file backup tidak didukung');
        }
    }

    /**
     * Restore database from SQL dump backup using secure process execution
     */
    private function restoreDumpBackup($filepath, $dbConfig)
    {
        // Escape parameters to prevent command injection
        $host = escapeshellarg($dbConfig['host']);
        $port = (int)$dbConfig['port'];
        $username = escapeshellarg($dbConfig['username']);
        $database = escapeshellarg($dbConfig['database']);
        $filepathArg = escapeshellarg($filepath);

        $command = sprintf(
            'pg_restore --host=%s --port=%d --username=%s --dbname=%s --clean --if-exists %s',
            $host, $port, $username, $database, $filepathArg
        );

        // Set password in environment to avoid showing it in command line
        $env = $_ENV;
        $env['PGPASSWORD'] = $dbConfig['password'];

        // Execute the command with environment variables
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w']  // stderr
            ],
            $pipes,
            null,
            $env
        );

        if (is_resource($process)) {
            // Close stdin
            fclose($pipes[0]);

            // Read output and error
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnVar = proc_close($process);

            if ($returnVar !== 0) {
                Log::error('Restore dump backup gagal', [
                    'command' => $command,
                    'output' => $output,
                    'error' => $error,
                    'return' => $returnVar
                ]);
                throw new Exception('Gagal mengembalikan backup database dump: ' . $error);
            }

            return true;
        } else {
            throw new Exception('Tidak dapat menjalankan perintah restore dump backup');
        }
    }

    /**
     * Restore database from SQL backup using secure process execution
     */
    private function restoreSqlBackup($filepath, $dbConfig)
    {
        // Escape parameters to prevent command injection
        $host = escapeshellarg($dbConfig['host']);
        $port = (int)$dbConfig['port'];
        $username = escapeshellarg($dbConfig['username']);
        $database = escapeshellarg($dbConfig['database']);
        $filepathArg = escapeshellarg($filepath);

        $command = sprintf(
            'psql --host=%s --port=%d --username=%s --dbname=%s -f %s',
            $host, $port, $username, $database, $filepathArg
        );

        // Set password in environment to avoid showing it in command line
        $env = $_ENV;
        $env['PGPASSWORD'] = $dbConfig['password'];

        // Execute the command with environment variables
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w']  // stderr
            ],
            $pipes,
            null,
            $env
        );

        if (is_resource($process)) {
            // Close stdin
            fclose($pipes[0]);

            // Read output and error
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnVar = proc_close($process);

            if ($returnVar !== 0) {
                Log::error('Restore SQL backup gagal', [
                    'command' => $command,
                    'output' => $output,
                    'error' => $error,
                    'return' => $returnVar
                ]);
                throw new Exception('Gagal mengembalikan backup database SQL: ' . $error);
            }

            return true;
        } else {
            throw new Exception('Tidak dapat menjalankan perintah restore SQL backup');
        }
    }

    /**
     * Membuat backup database dalam format JSON
     */
    public function createJsonBackup()
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}.json";
        $filepath = $this->backupPath . '/' . $filename;

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

        $data = [];
        foreach ($tables as $table) {
            $tableName = $table->tablename;
            $records = DB::table($tableName)->get();
            $data[$tableName] = $records;
        }

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));

        return $filename;
    }

    /**
     * Mengembalikan database dari file backup JSON
     */
    public function restoreJsonBackup($filename)
    {
        $filepath = $this->backupPath . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new Exception('File backup JSON tidak ditemukan');
        }

        $data = json_decode(file_get_contents($filepath), true);

        if (!$data) {
            throw new Exception('Data JSON tidak valid');
        }

        DB::beginTransaction();

        try {
            foreach ($data as $tableName => $records) {
                DB::table($tableName)->truncate();
                foreach ($records as $record) {
                    DB::table($tableName)->insert((array) $record);
                }
            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Mengembalikan daftar semua file backup yang tersedia
     */
    public function listBackups()
    {
        $files = scandir($this->backupPath);
        $backups = [];

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && in_array(pathinfo($file, PATHINFO_EXTENSION), ['dump', 'sql', 'json'])) {
                $filepath = $this->backupPath . '/' . $file;
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($filepath),
                    'created_at' => date('Y-m-d H:i:s', filemtime($filepath)),
                ];
            }
        }

        return $backups;
    }

    /**
     * Mengembalikan informasi dan stream file backup untuk didownload
     */
    public function getBackupFile($filename)
    {
        $filepath = $this->backupPath . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new Exception('File backup tidak ditemukan');
        }

        return [
            'path' => $filepath,
            'filename' => $filename,
            'size' => filesize($filepath),
        ];
    }

    /**
     * Menghapus file backup tertentu dari sistem file
     */
    public function deleteBackup($filename)
    {
        $filepath = $this->backupPath . '/' . $filename;

        if (!file_exists($filepath)) {
            throw new Exception('File backup tidak ditemukan');
        }

        unlink($filepath);

        return true;
    }

    /**
     * Mengambil konfigurasi koneksi database dari environment variables
     */
    protected function getDbConfig()
    {
        return [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 5432),
            'database' => env('DB_DATABASE'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
        ];
    }

    /**
     * Memastikan direktori backup sudah ada atau membuatnya jika belum ada
     */
    protected function ensureBackupDir()
    {
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Mengunggah dan mengembalikan backup dari file yang diupload
     */
    public function uploadAndRestore($uploadedFile)
    {
        $filename = time() . '_' . $uploadedFile->getClientOriginalName();
        $filepath = $this->backupPath . '/' . $filename;

        $uploadedFile->move($this->backupPath, $filename);

        $this->restoreBackup($filename);

        return $filename;
    }
}
