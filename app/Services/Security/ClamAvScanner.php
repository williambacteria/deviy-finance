<?php

namespace App\Services\Security;

use App\Services\Security\Contracts\VirusScanner;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ClamAvScanner implements VirusScanner
{
    public function __construct(private readonly string $binary = 'clamdscan') {}

    public function name(): string
    {
        return 'clamav';
    }

    public function scan(string $path): bool
    {
        try {
            $process = new Process([$this->binary, '--no-summary', '--stdout', $path]);
            $process->setTimeout(20);
            $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException('ClamAV 不可用：'.$exception->getMessage(), 0, $exception);
        }

        if ($process->getExitCode() === 0) {
            return true;
        }

        if ($process->getExitCode() === 1) {
            return false;
        }

        throw new RuntimeException('ClamAV 扫描失败：'.$process->getErrorOutput());
    }
}
