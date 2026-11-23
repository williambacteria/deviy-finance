<?php

namespace App\Console\Commands;

use App\Services\OpsAlertService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('backup:database {--keep=} {--dry-run}')]
#[Description('使用 mariadb-dump 备份数据库并按天数保留，可上传 S3')]
class BackupDatabaseCommand extends Command
{
    public function handle(OpsAlertService $alerts): int
    {
        $directory = (string) config('backup.path');
        $filename = 'deviy-'.now()->format('Ymd-His').'.sql.gz';
        $target = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;

        if ($this->option('dry-run')) {
            $this->info('将备份到：'.$target);

            return self::SUCCESS;
        }

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return $this->backupFailed('无法创建备份目录：'.$directory, $alerts);
        }

        $binary = (string) config('backup.dump_binary');
        if ($binary === '' || (! is_file($binary) && ! $this->onPath($binary))) {
            return $this->backupFailed('找不到 mariadb-dump：'.$binary, $alerts);
        }

        $connection = (array) config('database.connections.mysql');
        $temp = tempnam(sys_get_temp_dir(), 'deviy-db-');
        if ($temp === false) {
            return $this->backupFailed('无法创建临时文件。', $alerts);
        }

        try {
            $this->dump($binary, $connection, $temp);
            $this->gzip($temp, $target);
        } catch (RuntimeException $exception) {
            @unlink($temp);

            return $this->backupFailed($exception->getMessage(), $alerts);
        } finally {
            @unlink($temp);
        }

        $disk = (string) config('backup.disk', 'local');
        if ($disk !== 'local') {
            $stream = fopen($target, 'rb');
            $ok = $stream !== false && Storage::disk($disk)->put(trim((string) config('backup.prefix'), '/').'/'.$filename, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (! $ok) {
                return $this->backupFailed('备份已生成，但上传到磁盘 '.$disk.' 失败。', $alerts);
            }
            $this->info('备份已上传：'.$disk.'/'.trim((string) config('backup.prefix'), '/').'/'.$filename);
        }

        $keep = max(1, (int) ($this->option('keep') ?: config('backup.keep_days', 7)));
        $this->prune($directory, $keep);
        $alerts->resolveSourceExcept('backup', []);

        $this->info('数据库备份完成：'.$target);

        return self::SUCCESS;
    }

    private function backupFailed(string $message, OpsAlertService $alerts): int
    {
        $this->error($message);
        $alerts->raise('backup:failed', 'backup', 'critical', '数据库备份失败', $message);

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function dump(string $binary, array $connection, string $temp): void
    {
        $command = [
            $binary,
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--result-file='.$temp,
            (string) ($connection['database'] ?? ''),
        ];

        $environment = getenv();
        $environment['MYSQL_PWD'] = (string) ($connection['password'] ?? '');

        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $environment,
        );
        if (! is_resource($process)) {
            throw new RuntimeException('无法启动 mariadb-dump。');
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('mariadb-dump 失败：'.trim((string) $error));
        }
    }

    private function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb9');
        if ($in === false || $out === false) {
            throw new RuntimeException('无法写入备份文件。');
        }

        while (! feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }

        fclose($in);
        gzclose($out);
    }

    private function prune(string $directory, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'deviy-*.sql.gz') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function onPath(string $binary): bool
    {
        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));
        foreach ($paths as $path) {
            if (is_file(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }
}
