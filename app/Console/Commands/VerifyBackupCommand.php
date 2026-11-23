<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('backup:verify {file?} {--restore}')]
#[Description('校验 gzip 备份完整性，可选恢复到临时库并校验表数量')]
class VerifyBackupCommand extends Command
{
    public function handle(): int
    {
        $file = (string) ($this->argument('file') ?: $this->latestBackup());
        if ($file === '' || ! is_file($file)) {
            $this->error('备份文件不存在。');

            return self::FAILURE;
        }

        try {
            $tables = $this->inspect($file);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("备份完整：{$file}，CREATE TABLE {$tables} 个");
        if (! $this->option('restore')) {
            return self::SUCCESS;
        }

        try {
            $restored = $this->restoreAndCount($file);
        } catch (RuntimeException $exception) {
            $this->error('恢复演练失败：'.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("恢复演练成功：临时库表数量 {$restored}");

        return self::SUCCESS;
    }

    private function latestBackup(): string
    {
        $files = glob(rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'deviy-*.sql.gz') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return (string) ($files[0] ?? '');
    }

    private function inspect(string $file): int
    {
        $handle = gzopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('无法打开 gzip 备份。');
        }

        $tables = 0;
        $bytes = 0;
        $tail = '';
        while (! gzeof($handle)) {
            $chunk = gzread($handle, 1024 * 1024);
            if ($chunk === false) {
                gzclose($handle);
                throw new RuntimeException('gzip 解压失败，备份可能损坏。');
            }
            $bytes += strlen($chunk);
            $tables += substr_count($tail.$chunk, 'CREATE TABLE');
            $tail = substr($chunk, -20);
        }
        gzclose($handle);

        if ($bytes < 100 || $tables === 0) {
            throw new RuntimeException('备份内容不完整：未找到 CREATE TABLE。');
        }

        return $tables;
    }

    private function restoreAndCount(string $file): int
    {
        $binary = (string) config('backup.client_binary');
        if ($binary === '' || (! is_file($binary) && ! $this->onPath($binary))) {
            throw new RuntimeException('找不到 mariadb 客户端：'.$binary);
        }

        $sql = tempnam(sys_get_temp_dir(), 'deviy-restore-');
        if ($sql === false) {
            throw new RuntimeException('无法创建临时 SQL 文件。');
        }

        $database = 'deviy_verify_'.now()->format('YmdHis');
        $connection = (array) config('database.connections.mysql');

        try {
            $this->decompress($file, $sql);
            $this->executeClient($binary, $this->connectionArgs($connection), 'CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $restore = $this->executeClient(
                $binary,
                array_merge($this->connectionArgs($connection), ['--database='.$database]),
                null,
                $sql,
            );
            unset($restore);

            $output = $this->executeClient($binary, array_merge($this->connectionArgs($connection), ['--batch', '--skip-column-names']), "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='".$database."'");
            $count = (int) trim($output);

            return $count;
        } finally {
            @unlink($sql);
            try {
                $this->executeClient($binary, $this->connectionArgs($connection), 'DROP DATABASE IF EXISTS `'.$database.'`');
            } catch (RuntimeException) {
                // 临时库清理失败不覆盖主结果。
            }
        }
    }

    private function decompress(string $source, string $target): void
    {
        $in = gzopen($source, 'rb');
        $out = fopen($target, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('无法解压备份。');
        }
        while (! gzeof($in)) {
            $chunk = gzread($in, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            fwrite($out, $chunk);
        }
        gzclose($in);
        fclose($out);
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<int, string>
     */
    private function connectionArgs(array $connection): array
    {
        return [
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? ''),
        ];
    }

    /**
     * @param  array<int, string>  $args
     */
    private function executeClient(string $binary, array $args, ?string $sql = null, ?string $stdinFile = null): string
    {
        $command = array_merge([$binary], $args);
        if ($sql !== null) {
            $command[] = '--execute='.$sql;
        }

        $descriptors = [
            0 => $stdinFile !== null ? ['file', $stdinFile, 'r'] : ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $environment = getenv();
        $environment['MYSQL_PWD'] = (string) config('database.connections.mysql.password', '');

        $process = proc_open($command, $descriptors, $pipes, null, $environment);
        if (! is_resource($process)) {
            throw new RuntimeException('无法启动 mariadb 客户端。');
        }
        if ($stdinFile === null) {
            fclose($pipes[0]);
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            throw new RuntimeException(trim((string) $error) ?: 'mariadb 客户端退出码 '.$exit);
        }

        return (string) $output;
    }

    private function onPath(string $binary): bool
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $path) {
            if (is_file(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary)) {
                return true;
            }
        }

        return false;
    }
}
