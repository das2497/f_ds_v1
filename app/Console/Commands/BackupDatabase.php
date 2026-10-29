<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

#[Signature('app:backup-database')]
#[Description('Dumps the database to storage/app/backups — tries mysqldump first, falls back to a pure-PHP SQL export for shared hosting with no shell access.')]
class BackupDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filename = 'backup-'.now()->format('Y-m-d_His').'.sql';
        $path = storage_path("app/backups/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        if ($this->dumpWithMysqldump($path) || $this->dumpWithPhp($path)) {
            $this->info("Backup saved: storage/app/backups/{$filename} (".$this->humanSize(filesize($path)).')');

            return self::SUCCESS;
        }

        $this->error('Backup failed — neither mysqldump nor the PHP fallback could complete.');

        return self::FAILURE;
    }

    private function dumpWithMysqldump(string $path): bool
    {
        $config = config('database.connections.mysql');

        $command = [
            'mysqldump',
            '-h', $config['host'],
            '-P', (string) $config['port'],
            '-u', $config['username'],
            '--single-transaction',
            '--no-tablespaces',
            $config['database'],
        ];

        $result = Process::timeout(300)
            ->env(['MYSQL_PWD' => $config['password']])
            ->run($command);

        if (! $result->successful()) {
            return false;
        }

        file_put_contents($path, $result->output());

        return filesize($path) > 0;
    }

    /**
     * Shared hosting without shell_exec/mysqldump still needs a way to back
     * up — this writes a plain INSERT-statement SQL dump straight from
     * Eloquent's own connection.
     */
    private function dumpWithPhp(string $path): bool
    {
        $database = config('database.connections.mysql.database');
        $tables = DB::select('SHOW TABLES');
        $key = "Tables_in_{$database}";

        $handle = fopen($path, 'w');
        fwrite($handle, '-- Felixes/Shobley manual backup (PHP fallback) — '.now()->toDateTimeString()."\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $row) {
            $table = $row->$key;

            $create = DB::select("SHOW CREATE TABLE `{$table}`")[0]->{'Create Table'};
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

            $writeRows = function ($rows) use ($handle, $table) {
                foreach ($rows as $row) {
                    $values = collect((array) $row)->map(fn ($v) => $v === null ? 'NULL' : DB::connection()->getPdo()->quote((string) $v))->implode(', ');
                    fwrite($handle, "INSERT INTO `{$table}` VALUES ({$values});\n");
                }
            };

            // Not every table has an auto-incrementing `id` (permission
            // pivot tables, password_reset_tokens, sessions) — chunkById
            // needs one, so those small reference tables are dumped in a
            // single pass instead.
            if (Schema::hasColumn($table, 'id')) {
                DB::table($table)->chunkById(500, $writeRows, 'id');
            } else {
                $writeRows(DB::table($table)->get());
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return filesize($path) > 0;
    }

    private function humanSize(int $bytes): string
    {
        return $bytes > 1048576 ? round($bytes / 1048576, 1).' MB' : round($bytes / 1024, 1).' KB';
    }
}
