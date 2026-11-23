<?php

namespace App\Filesystem;

class S3UrlGenerator
{
    public function url(string $path, int $ttl = 300): string
    {
        $disk = (array) config('filesystems.disks.s3', []);

        return (new S3Client([
            'endpoint' => (string) ($disk['endpoint'] ?? 'https://s3.amazonaws.com'),
            'bucket' => (string) ($disk['bucket'] ?? ''),
            'key' => (string) ($disk['key'] ?? ''),
            'secret' => (string) ($disk['secret'] ?? ''),
            'region' => (string) ($disk['region'] ?? 'us-east-1'),
        ]))->presignedUrl($path, $ttl);
    }
}
