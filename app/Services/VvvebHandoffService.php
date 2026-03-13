<?php

namespace App\Services;

use Illuminate\Support\Str;

class VvvebHandoffService
{
    public function buildHandoffUrl(
        string $tenantUuid,
        string|int $siteId,
        int $userId,
        ?string $builderUrl = null,
        ?string $nextQuery = null,
    ): ?string {
        $secret = (string) config('services.vvveb.handoff_secret');

        if ($secret === '') {
            return null;
        }

        $builderUrl = $builderUrl ?: (string) config('services.vvveb.builder_url');
        $builderBase = $this->normalizeBaseUrl($builderUrl);

        if (! $builderBase) {
            return null;
        }

        $ttl = (int) config('services.vvveb.handoff_ttl', 90);
        $now = time();

        $payload = [
            'tenant_uuid' => $tenantUuid,
            'site_id' => (int) $siteId,
            'user_id' => (string) $userId,
            'iat' => $now,
            'exp' => $now + max(30, $ttl),
            'jti' => (string) Str::uuid(),
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($payloadJson)) {
            return null;
        }

        $payloadB64 = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $payloadB64, $secret, true);
        $token = $payloadB64.'.'.$this->base64UrlEncode($signature);

        $query = [
            'module' => 'beatkongs/handoff',
            'token' => $token,
        ];

        if ($nextQuery) {
            $query['next'] = $nextQuery;
        }

        return $builderBase.'?'.http_build_query($query);
    }

    private function normalizeBaseUrl(string $builderUrl): ?string
    {
        $builderUrl = trim($builderUrl);

        if ($builderUrl === '' || ! preg_match('#^https?://#i', $builderUrl)) {
            return null;
        }

        $parts = parse_url($builderUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $base = $parts['scheme'].'://'.$parts['host'];

        if (! empty($parts['port'])) {
            $base .= ':'.$parts['port'];
        }

        $path = $parts['path'] ?? '';

        if ($path === '') {
            return rtrim($base, '/');
        }

        return $base.$path;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
