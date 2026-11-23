<?php

namespace App\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use RuntimeException;

class S3CompatibleAdapter implements FilesystemAdapter
{
    public function __construct(private readonly S3Client $client) {}

    public function fileExists(string $path): bool
    {
        return $this->client->head($path)['status'] === 200;
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->put($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $body = stream_get_contents($contents);
        if ($body === false) {
            throw UnableToWriteFile::atLocation($path, '无法读取上传流');
        }
        $this->put($path, $body, $config);
    }

    public function read(string $path): string
    {
        $response = $this->client->get($path);
        if ($response['status'] !== 200) {
            throw UnableToReadFile::fromLocation($path, 'S3 对象不存在');
        }

        return $response['body'];
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, '无法创建临时流');
        }
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $response = $this->client->delete($path);
        if (! in_array($response['status'], [200, 204, 404], true)) {
            throw UnableToDeleteFile::atLocation($path, 'S3 删除失败');
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            foreach ($this->listContents($path, true) as $item) {
                if ($item->isFile()) {
                    $this->delete($item->path());
                }
            }
        } catch (\Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, 'S3 目录删除失败', $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void {}

    public function setVisibility(string $path, string $visibility): void {}

    public function visibility(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->metadata($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = trim($path, '/');
        if ($prefix !== '') {
            $prefix .= '/';
        }

        $response = $this->client->list($prefix);
        if ($response['status'] !== 200) {
            throw new RuntimeException('S3 列表请求失败');
        }

        $xml = @simplexml_load_string($response['body']);
        if ($xml === false) {
            return;
        }

        foreach ($xml->Contents ?? [] as $item) {
            $key = (string) $item->Key;
            if ($key === $prefix) {
                continue;
            }
            $relative = $prefix !== '' ? substr($key, strlen($prefix)) : $key;
            if ($relative === '' || (! $deep && str_contains($relative, '/'))) {
                continue;
            }

            yield new FileAttributes(
                $relative,
                (int) $item->Size,
                Visibility::PRIVATE,
                strtotime((string) $item->LastModified) ?: null,
            );
        }

        foreach ($xml->CommonPrefixes ?? [] as $item) {
            $key = rtrim((string) $item->Prefix, '/');
            $relative = $prefix !== '' ? substr($key, strlen($prefix)) : $key;
            if ($relative !== '') {
                yield new DirectoryAttributes($relative);
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (\Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $response = $this->client->copy($source, $destination);
        if (! in_array($response['status'], [200, 201], true)) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }
    }

    private function put(string $path, string $contents, Config $config): void
    {
        $mime = (string) $config->get('mimetype', 'application/octet-stream');
        if (strlen($contents) > 5 * 1024 * 1024) {
            $this->client->multipartUpload($path, $contents, $mime);

            return;
        }

        $response = $this->client->put($path, $contents, $mime);
        if (! in_array($response['status'], [200, 201], true)) {
            throw UnableToWriteFile::atLocation($path, 'S3 写入失败');
        }
    }

    private function metadata(string $path): FileAttributes
    {
        $response = $this->client->head($path);
        if ($response['status'] !== 200) {
            throw UnableToRetrieveMetadata::create($path, 'metadata');
        }

        return new FileAttributes(
            $path,
            isset($response['headers']['content-length']) ? (int) $response['headers']['content-length'] : null,
            Visibility::PRIVATE,
            isset($response['headers']['last-modified']) ? strtotime($response['headers']['last-modified']) ?: null : null,
            $response['headers']['content-type'] ?? null,
        );
    }
}
