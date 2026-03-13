<?php

namespace App\Client;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VvvebClient
{
    private const DEFAULT_CREATE_SITE_ENDPOINT = '/rest/beatkongs/sites/provision';


    private function request()
    {
        $token = (string) config('services.vvveb.api_token');

        $request = Http::acceptJson();

        if ($token !== '') {
            // Some PHP/FastCGI setups do not forward the Authorization header reliably.
            // Send both Authorization (via withToken) and an explicit Bearer header.
            $request = $request->withToken($token)->withHeaders([
                'Bearer' => $token,
            ]);
        }

        return $request;
    }

    private function addTokenToUrl(string $url): string
    {
        $token = (string) config('services.vvveb.api_token');

        if ($token === '') {
            return $url;
        }

        // Fallback for environments that strip auth headers.
        if (str_contains($url, '_token=')) {
            return $url;
        }

        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'_token='.rawurlencode($token);
    }

    public function createSite(array $payload): Response
    {
        return $this->request()
            ->post($this->getApiUrl(config('services.vvveb.create_site_endpoint', self::DEFAULT_CREATE_SITE_ENDPOINT)), $payload);
    }

    public function getSite(string $siteId): Response
    {
        return $this->request()
            ->get($this->getApiUrl($this->getSiteEndpoint($siteId)));
    }

    private function getSiteEndpoint(string $siteId): string
    {
        return rtrim(config('services.vvveb.site_endpoint', '/rest/beatkongs/sites'), '/').'/'.$siteId;
    }

    private function getApiUrl(string $path): string
    {
        if ($this->isAbsoluteUrl($path)) {
            return $this->addTokenToUrl($path);
        }

        $baseUrl = $this->resolveBaseUrl();

        if (! $baseUrl) {
            throw new RuntimeException('Vvveb API base URL is not configured. Set VVVEB_BASE_URL or provide an absolute VVVEB_BUILDER_URL.');
        }

        return $this->addTokenToUrl(rtrim($baseUrl, '/').'/'.ltrim($path, '/'));
    }

    private function resolveBaseUrl(): ?string
    {
        $baseUrl = config('services.vvveb.base_url');

        if (filled($baseUrl)) {
            return $baseUrl;
        }

        $builderUrl = config('services.vvveb.builder_url');

        if (! filled($builderUrl) || ! $this->isAbsoluteUrl($builderUrl)) {
            return null;
        }

        $parts = parse_url($builderUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $baseUrl = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $baseUrl .= ':'.$parts['port'];
        }

        return $baseUrl;
    }

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }
}