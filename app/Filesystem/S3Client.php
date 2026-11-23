<?php

namespace App\Filesystem;

use RuntimeException;

class S3Client
{
    /**
     * @param  array{endpoint: string, bucket: string, key: string, secret: string, region?: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function put(string $key, string $body, string $contentType = 'application/octet-stream'): array
    {
        return $this->request('PUT', $key, [], $body, ['content-type' => $contentType]);
    }

    public function get(string $key): array
    {
        return $this->request('GET', $key);
    }

    public function head(string $key): array
    {
        return $this->request('HEAD', $key);
    }

    public function delete(string $key): array
    {
        return $this->request('DELETE', $key);
    }

    public function list(string $prefix, int $maxKeys = 1000): array
    {
        return $this->request('GET', '', ['list-type' => '2', 'prefix' => $prefix, 'max-keys' => (string) $maxKeys]);
    }

    public function copy(string $source, string $destination): array
    {
        return $this->request('PUT', $destination, [], '', [
            'x-amz-copy-source' => '/'.rawurlencode($this->config['bucket']).'/'.ltrim($source, '/'),
        ]);
    }

    public function multipartUpload(string $key, string $body, string $contentType = 'application/octet-stream', int $partSize = 5 * 1024 * 1024): void
    {
        if (strlen($body) <= $partSize) {
            $response = $this->put($key, $body, $contentType);
            if (! in_array($response['status'], [200, 201], true)) {
                throw new RuntimeException('S3 上传失败');
            }

            return;
        }

        $create = $this->createMultipartUpload($key, $contentType);
        $xml = @simplexml_load_string($create['body']);
        $uploadId = $xml !== false ? (string) ($xml->UploadId ?? '') : '';
        if ($uploadId === '') {
            throw new RuntimeException('S3 分片上传初始化失败');
        }

        $parts = [];
        $offset = 0;
        $partNumber = 1;
        try {
            while ($offset < strlen($body)) {
                $chunk = substr($body, $offset, $partSize);
                $response = $this->uploadPart($key, $uploadId, $partNumber, $chunk);
                $etag = trim((string) ($response['headers']['etag'] ?? ''), '"');
                if ($etag === '') {
                    throw new RuntimeException('S3 分片上传缺少 ETag');
                }
                $parts[] = ['number' => $partNumber, 'etag' => $etag];
                $offset += strlen($chunk);
                $partNumber++;
            }

            $this->completeMultipartUpload($key, $uploadId, $parts);
        } catch (\Throwable $exception) {
            $this->abortMultipartUpload($key, $uploadId);
            throw $exception;
        }
    }

    public function createMultipartUpload(string $key, string $contentType): array
    {
        return $this->request('POST', $key, ['uploads' => ''], '', ['content-type' => $contentType]);
    }

    public function uploadPart(string $key, string $uploadId, int $partNumber, string $body): array
    {
        return $this->request('PUT', $key, [
            'partNumber' => (string) $partNumber,
            'uploadId' => $uploadId,
        ], $body);
    }

    /**
     * @param  array<int, array{number: int, etag: string}>  $parts
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): array
    {
        $xml = '<CompleteMultipartUpload>';
        foreach ($parts as $part) {
            $xml .= '<Part><PartNumber>'.$part['number'].'</PartNumber><ETag>'.$part['etag'].'</ETag></Part>';
        }
        $xml .= '</CompleteMultipartUpload>';

        return $this->request('POST', $key, ['uploadId' => $uploadId], $xml, ['content-type' => 'application/xml']);
    }

    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        $this->request('DELETE', $key, ['uploadId' => $uploadId]);
    }

    public function presignedUrl(string $key, int $ttl = 300): string
    {
        $endpoint = rtrim((string) $this->config['endpoint'], '/');
        $bucket = (string) $this->config['bucket'];
        $canonicalUri = '/'.$this->encodePath($bucket.($key !== '' ? '/'.$key : ''));
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);
        if (is_int($port)) {
            $host .= ':'.$port;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $date = $now->format('Ymd');
        $region = (string) ($this->config['region'] ?? 'us-east-1');
        $scope = $date.'/'.$region.'/s3/aws4_request';

        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->config['key'].'/'.$scope,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string) $ttl,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $canonicalRequest = implode("\n", ['GET', $canonicalUri, $canonicalQuery, 'host:'.$host."\n", 'host', 'UNSIGNED-PAYLOAD']);
        $stringToSign = implode("\n", ['AWS4-HMAC-SHA256', $amzDate, $scope, hash('sha256', $canonicalRequest)]);

        $secret = (string) $this->config['secret'];
        $signingKey = hash_hmac('sha256', $date, 'AWS4'.$secret, true);
        $signingKey = hash_hmac('sha256', $region, $signingKey, true);
        $signingKey = hash_hmac('sha256', 's3', $signingKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $signingKey, true);
        $query['X-Amz-Signature'] = hash_hmac('sha256', $stringToSign, $signingKey);

        return $endpoint.$canonicalUri.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, string>  $query
     * @param  array<string, string>  $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(string $method, string $key, array $query = [], string $body = '', array $headers = []): array
    {
        $endpoint = rtrim((string) $this->config['endpoint'], '/');
        $bucket = (string) $this->config['bucket'];
        $canonicalUri = '/'.$this->encodePath($bucket.($key !== '' ? '/'.$key : ''));

        ksort($query);
        $canonicalQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $url = $endpoint.$canonicalUri.($canonicalQuery !== '' ? '?'.$canonicalQuery : '');

        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $port = parse_url($endpoint, PHP_URL_PORT);
        if (is_int($port)) {
            $host .= ':'.$port;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $amzDate = $now->format('Ymd\THis\Z');
        $date = $now->format('Ymd');
        $payloadHash = hash('sha256', $body);

        $signed = array_merge([
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $amzDate,
        ], array_change_key_case($headers, CASE_LOWER));
        ksort($signed);

        $canonicalHeaders = '';
        foreach ($signed as $name => $value) {
            $canonicalHeaders .= $name.':'.trim((string) $value)."\n";
        }
        $signedHeaders = implode(';', array_keys($signed));
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $region = (string) ($this->config['region'] ?? 'us-east-1');
        $scope = $date.'/'.$region.'/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $secret = (string) $this->config['secret'];
        $signingKey = hash_hmac('sha256', $date, 'AWS4'.$secret, true);
        $signingKey = hash_hmac('sha256', $region, $signingKey, true);
        $signingKey = hash_hmac('sha256', 's3', $signingKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $signingKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $requestHeaders = [
            'Authorization: AWS4-HMAC-SHA256 Credential='.$this->config['key'].'/'.$scope.', SignedHeaders='.$signedHeaders.', Signature='.$signature,
            'x-amz-content-sha256: '.$payloadHash,
            'x-amz-date: '.$amzDate,
        ];
        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name.': '.$value;
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }

                return strlen($line);
            },
        ]);
        if ($body !== '' || in_array($method, ['PUT', 'POST'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($responseBody === false) {
            throw new RuntimeException('S3 请求失败：'.$error);
        }

        if ($status >= 300 && $status !== 404) {
            throw new RuntimeException('S3 响应错误 '.$status.'：'.mb_substr((string) $responseBody, 0, 300));
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => (string) $responseBody];
    }

    private function encodePath(string $path): string
    {
        return implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', ltrim($path, '/')),
        ));
    }
}
