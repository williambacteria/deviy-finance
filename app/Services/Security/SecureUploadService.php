<?php

namespace App\Services\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SecureUploadService
{
    public function __construct(
        private readonly Contracts\VirusScanner $scanner,
        private readonly QuarantineService $quarantine,
    ) {}

    /** @var array<string, string> */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'application/zip' => 'zip',
        'text/plain' => 'txt',
    ];

    /**
     * @return array{disk: string, path: string, name: string, mime: string, size: int}
     */
    public function store(UploadedFile $file, string $directory = 'attachments', ?string $disk = null): array
    {
        $disk = $disk ?? (string) config('deviy.upload_disk', 'local');
        if (! array_key_exists($disk, config('filesystems.disks', []))) {
            throw new RuntimeException('上传磁盘未配置：'.$disk);
        }

        $maxBytes = (int) config('deviy.upload_max_bytes', 20 * 1024 * 1024);
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'file' => '文件大小不能超过 '.round($maxBytes / 1024 / 1024, 1).' MB。',
            ]);
        }

        $mime = (string) $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if (! isset(self::ALLOWED[$mime]) || self::ALLOWED[$mime] !== $extension) {
            throw ValidationException::withMessages([
                'file' => '不支持的文件类型，仅允许 JPG/PNG/WebP/PDF/ZIP/TXT，且扩展名必须与内容一致。',
            ]);
        }

        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([
                'file' => '图片文件内容无效。',
            ]);
        }

        try {
            if (! $this->scanner->scan($file->getRealPath())) {
                try {
                    $this->quarantine->store(
                        $file,
                        $this->scanner->name(),
                        '病毒扫描判定为不安全文件',
                        auth()->id() ?? auth('admin')->id(),
                    );
                } catch (Throwable $quarantineException) {
                    Log::error('Quarantine failed', ['error' => $quarantineException->getMessage()]);
                }

                throw ValidationException::withMessages([
                    'file' => '文件未通过安全扫描，已拒绝上传。',
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Virus scan unavailable', ['error' => $exception->getMessage()]);
            throw ValidationException::withMessages([
                'file' => '文件安全扫描暂时不可用，请稍后重试。',
            ]);
        }

        $name = preg_replace('/[^\pL\pN._-]+/u', '_', basename($file->getClientOriginalName())) ?: 'file.'.$extension;
        $name = mb_substr($name, 0, 120);
        $path = trim($directory, '/').'/'.Str::uuid().'.'.self::ALLOWED[$mime];

        if (! Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path))) {
            throw new RuntimeException('文件保存失败。');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $name,
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ];
    }
}
