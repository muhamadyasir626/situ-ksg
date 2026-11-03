<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HttpsUrlGenerationTest extends TestCase
{
    #[DataProvider('environments')]
    public function test_livewire_endpoints_use_the_configured_scheme(string $environment, string $appUrl, string $scheme): void
    {
        $this->app['env'] = $environment;
        config(['app.url' => $appUrl]);

        // TLS terminates upstream, so the application receives an HTTP request.
        URL::setRequest(Request::create('http://situ-ksg.test/login'));
        URL::forceScheme(null);

        (new AppServiceProvider($this->app))->boot();

        $scripts = FrontendAssets::scripts();

        $this->assertStringContainsString('data-update-uri="'.$scheme.'://situ-ksg.test/', $scripts);
        $this->assertStringContainsString('data-module-url="'.$scheme.'://situ-ksg.test/', $scripts);
    }

    public static function environments(): array
    {
        return [
            'production' => ['production', 'http://situ-ksg.test', 'https'],
            'https staging' => ['staging', 'https://situ-ksg.test', 'https'],
            'https local' => ['local', 'https://situ-ksg.test', 'https'],
            'http local' => ['local', 'http://situ-ksg.test', 'http'],
        ];
    }
}
