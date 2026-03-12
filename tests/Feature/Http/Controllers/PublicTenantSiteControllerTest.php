<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicTenantSiteControllerTest extends TestCase
{
    public function test_it_proxies_unknown_public_host_paths_to_vvveb(): void
    {
        config()->set('services.vvveb.base_url', 'http://127.0.0.1:8091');
        config()->set('services.vvveb.public_host', 'localhost');

        Http::fake([
            'http://127.0.0.1:8091/test3212' => Http::response('<html>proxied site</html>', 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $response = $this->withServerVariables([
            'HTTP_ACCEPT' => 'text/html',
        ])->get('/test3212');

        $response->assertOk();
        $response->assertSee('proxied site', false);
    }

    public function test_it_does_not_proxy_other_hosts(): void
    {
        config()->set('services.vvveb.base_url', 'http://127.0.0.1:8091');
        config()->set('services.vvveb.public_host', 'dev.beatkongs.com');

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.com',
        ])->get('/some-missing-page');

        $response->assertNotFound();
    }
}