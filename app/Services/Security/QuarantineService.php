<?php

namespace App\Services\Security;

use App\Models\QuarantineFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class QuarantineService
{
    public function store(UploadedFile $file, string $scanner, string $reason, ?int $userId = null): QuarantineFile
    {
        $extension = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $file->getClientOriginalExtension()) ?: 'bin');
        $filename = Str::uuid().'.'.$extension;
        $path = 'quarantine/'.$filename;

        if (! Storage::disk('local')->putFileAs('quarantine', $file, $filename)) {
            throw new RuntimeException('隔离文件保存失败。');
        }

        return QuarantineFile::create([
            'user_id' => $userId,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 160),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'disk' => 'local',
            'path' => $path,
            'scanner' => $scanner,
            'reason' => mb_substr($reason, 0, 200),
            'ip_address' => request()->ip(),
        ]);
    }

    public function delete(QuarantineFile $file): void
    {
        Storage::disk($file->disk ?: 'local')->delete($file->path);
        $file->delete();
    }
}
