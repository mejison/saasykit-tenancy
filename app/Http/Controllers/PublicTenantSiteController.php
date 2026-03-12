<?php

namespace App\Http\Controllers;

use App\Models\TenantDomain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicTenantSiteController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($this->shouldProxy($request), 404);

        $baseUrl = rtrim((string) config('services.vvveb.base_url'), '/');

        abort_if($baseUrl === '', 404, 'Vvveb public site endpoint is not configured.');

        try {
            $remoteResponse = Http::withHeaders($this->forwardHeaders($request))
                ->timeout(15)
                ->withOptions(['http_errors' => false])
                ->send($request->method(), $baseUrl.$request->getRequestUri());
        } catch (ConnectionException $exception) {
            abort(502, 'Vvveb public site is unavailable.');
        }

        $response = response($remoteResponse->body(), $remoteResponse->status());

        foreach ($remoteResponse->headers() as $header => $values) {
            if (in_array(strtolower($header), ['content-length', 'connection', 'transfer-encoding', 'host'], true)) {
                continue;
            }

            foreach ($values as $value) {
                $response->headers->set($header, $value, false);
            }
        }

        return $response;
    }

    private function shouldProxy(Request $request): bool
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $host = $request->getHost();
        $publicHost = (string) config('services.vvveb.public_host');

        if ($publicHost !== '' && strcasecmp($host, $publicHost) === 0) {
            return true;
        }

        try {
            return TenantDomain::query()
                ->whereRaw('lower(host) = ?', [mb_strtolower($host)])
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    private function forwardHeaders(Request $request): array
    {
        return array_filter([
            'Host' => $request->getHost(),
            'Accept' => $request->header('Accept', 'text/html,application/xhtml+xml'),
            'Accept-Language' => $request->header('Accept-Language'),
            'User-Agent' => $request->userAgent(),
            'X-Forwarded-Host' => $request->getHost(),
            'X-Forwarded-Proto' => $request->getScheme(),
        ], static fn ($value) => filled($value));
    }
}