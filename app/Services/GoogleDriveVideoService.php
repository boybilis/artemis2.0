<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveVideoService
{
    public function enabled(): bool
    {
        return (bool) config('services.google_drive.streaming_enabled');
    }

    public function fileIdFromUrl(?string $url): ?string
    {
        if (! is_string($url) || ! str_contains(strtolower($url), 'drive.google.com')) return null;
        foreach (['~/file/d/([a-zA-Z0-9_-]+)~', '~[?&]id=([a-zA-Z0-9_-]+)~'] as $pattern) {
            if (preg_match($pattern, $url, $matches)) return $matches[1];
        }
        return null;
    }

    public function authorizedVideo(string $fileId): array
    {
        if (! $this->enabled()) throw new RuntimeException('Private Google Drive streaming is disabled.');
        $allowedFolder = trim((string) config('services.google_drive.folder_id'));
        if ($allowedFolder === '') throw new RuntimeException('The approved Google Drive folder is not configured.');

        $file = $this->metadata($fileId, 'id,name,mimeType,size,parents,trashed,capabilities(canDownload)');
        if (($file['trashed'] ?? false) || ! str_starts_with((string) ($file['mimeType'] ?? ''), 'video/')) {
            abort(404, 'The requested Drive item is not an available video.');
        }
        if (($file['capabilities']['canDownload'] ?? false) !== true) abort(403, 'This Drive video cannot be streamed.');
        if (! $this->belongsToFolder($file, $allowedFolder)) abort(403, 'This video is outside the approved Artemis folder.');
        if (! isset($file['size']) || (int) $file['size'] < 1) abort(422, 'Google Drive did not provide the video size.');

        return $file;
    }

    public function stream(string $fileId, int $start, int $end): void
    {
        if (function_exists('set_time_limit')) @set_time_limit(0);
        while (ob_get_level() > 0) @ob_end_flush();

        $handle = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media&supportsAllDrives=true');
        curl_setopt_array($handle, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken(),
                "Range: bytes={$start}-{$end}",
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk): int {
                echo $chunk;
                if (function_exists('ob_flush')) @ob_flush();
                flush();
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($success === false || ! in_array($status, [200, 206], true)) {
            report(new RuntimeException('Google Drive stream failed with status ' . $status . ($error ? ': ' . $error : '')));
        }
    }

    private function belongsToFolder(array $file, string $allowedFolder): bool
    {
        $pending = array_values((array) ($file['parents'] ?? []));
        $visited = [];
        while ($pending !== [] && count($visited) < 50) {
            $parentId = array_shift($pending);
            if ($parentId === $allowedFolder) return true;
            if (isset($visited[$parentId])) continue;
            $visited[$parentId] = true;
            $parent = $this->metadata($parentId, 'id,parents,trashed');
            if (! ($parent['trashed'] ?? false)) array_push($pending, ...(array) ($parent['parents'] ?? []));
        }
        return false;
    }

    private function metadata(string $fileId, string $fields): array
    {
        $cacheKey = 'google_drive_metadata:' . hash('sha256', $fileId . '|' . $fields);
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($fileId, $fields) {
            $response = Http::withToken($this->accessToken())->timeout(20)->get(
                'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId),
                ['fields' => $fields, 'supportsAllDrives' => 'true']
            );
            if ($response->status() === 404) abort(404, 'The private Drive video was not found or has not been shared with Artemis.');
            $response->throw();
            return $response->json();
        });
    }

    private function accessToken(): string
    {
        return Cache::remember('google_drive_service_access_token', now()->addMinutes(50), function () {
            $credentials = $this->credentials();
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/drive.readonly',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = $header . '.' . $claims;
            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Unable to sign the Google service-account request.');
            }
            $assertion = $unsigned . '.' . $this->base64Url($signature);
            $response = Http::asForm()->timeout(20)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json();
            if (empty($response['access_token'])) throw new RuntimeException('Google did not return an access token.');
            return $response['access_token'];
        });
    }

    private function credentials(): array
    {
        $path = (string) config('services.google_drive.credentials');
        if (! preg_match('~^(?:[a-zA-Z]:[\\\\/]|/)~', $path)) $path = base_path($path);
        if (! is_file($path) || ! is_readable($path)) throw new RuntimeException('Google Drive credentials are missing or unreadable.');
        $credentials = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (($credentials['type'] ?? null) !== 'service_account' || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('The Google Drive credential file is not a valid service-account key.');
        }
        return $credentials;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
