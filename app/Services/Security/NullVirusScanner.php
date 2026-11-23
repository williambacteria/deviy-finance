<?php

namespace App\Services\Security;

use App\Services\Security\Contracts\VirusScanner;

class NullVirusScanner implements VirusScanner
{
    public function name(): string
    {
        return 'none';
    }

    public function scan(string $path): bool
    {
        return true;
    }
}
