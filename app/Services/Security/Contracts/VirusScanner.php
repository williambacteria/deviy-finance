<?php

namespace App\Services\Security\Contracts;

interface VirusScanner
{
    public function name(): string;

    /**
     * @throws \RuntimeException when the scanner is unavailable
     */
    public function scan(string $path): bool;
}
