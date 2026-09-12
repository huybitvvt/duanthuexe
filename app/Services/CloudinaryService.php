<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class CloudinaryService
{
    private $client;
    private $cloudName;
    private $apiKey;
    private $apiSecret;
    private $defaultFolder;

    public function __construct()
    {
        $this->cloudName = config('services.cloudinary.cloud_name');
        $this->apiKey = config('services.cloudinary.api_key');
        $this->apiSecret = config('services.cloudinary.api_secret');
        $this->defaultFolder = config('services.cloudinary.folder', 'himoto/vehicles');
        $this->client = new Client([
            'base_uri' => 'https://api.cloudinary.com',
            'connect_timeout' => 10,
            'timeout' => 60,
        ]);
    }

    public function upload(UploadedFile $file, $folder = null)
    {
        $this->ensureConfigured();

        $timestamp = time();
        $folder = $this->sanitizeFolder($folder ?: $this->defaultFolder);
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $publicId = trim(Str::slug($originalName) . '-' . Str::lower(Str::random(8)), '-');
        $signedParameters = [
            'folder' => $folder,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $stream = fopen($file->getPathname(), 'r');
        if ($stream === false) {
            throw new RuntimeException('Không thể đọc file ảnh đã tải lên.');
        }

        try {
            $response = $this->client->post($this->endpoint('image/upload'), [
                'multipart' => [
                    ['name' => 'file', 'contents' => $stream, 'filename' => $file->getClientOriginalName()],
                    ['name' => 'api_key', 'contents' => $this->apiKey],
                    ['name' => 'timestamp', 'contents' => (string) $timestamp],
                    ['name' => 'signature', 'contents' => $this->sign($signedParameters)],
                    ['name' => 'folder', 'contents' => $folder],
                    ['name' => 'public_id', 'contents' => $publicId],
                ],
            ]);
        } finally {
            fclose($stream);
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (empty($payload['public_id']) || empty($payload['secure_url'])) {
            throw new RuntimeException('Cloudinary không trả về định danh hoặc URL ảnh.');
        }

        return $payload;
    }

    public function destroy($publicId)
    {
        $this->ensureConfigured();

        $timestamp = time();
        $signedParameters = [
            'invalidate' => 'true',
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];

        $response = $this->client->post($this->endpoint('image/destroy'), [
            'form_params' => [
                'api_key' => $this->apiKey,
                'timestamp' => $timestamp,
                'signature' => $this->sign($signedParameters),
                'public_id' => $publicId,
                'invalidate' => 'true',
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        return in_array(isset($payload['result']) ? $payload['result'] : null, ['ok', 'not found'], true);
    }

    private function endpoint($action)
    {
        return '/v1_1/' . rawurlencode($this->cloudName) . '/' . $action;
    }

    private function sign(array $parameters)
    {
        ksort($parameters);
        $parts = [];
        foreach ($parameters as $key => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = $key . '=' . $value;
            }
        }

        return sha1(implode('&', $parts) . $this->apiSecret);
    }

    private function sanitizeFolder($folder)
    {
        $segments = array_filter(explode('/', str_replace('\\', '/', (string) $folder)));
        $segments = array_map(function ($segment) {
            return Str::slug($segment);
        }, $segments);

        return implode('/', array_filter($segments)) ?: 'himoto/vehicles';
    }

    private function ensureConfigured()
    {
        if (!$this->cloudName || !$this->apiKey || !$this->apiSecret) {
            throw new RuntimeException('Thiếu cấu hình Cloudinary trên backend.');
        }
    }
}
