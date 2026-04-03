<?php

declare(strict_types=1);

namespace App\Providers;

use App\Clients\Contracts\OpenLibraryClientInterface;
use App\Clients\OpenLibraryClient;
use App\Enums\ProviderType;
use App\Models\ProviderCredential;
use Ethelserth\Biblionet\BiblionetClient as LibraryBiblionetClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * register() is called early in the bootstrap lifecycle — before boot().
     * Use it to bind things into the container. Do NOT call other services
     * here (use boot() for that), because they may not be ready yet.
     *
     * IMPORTANT: The closures below run LAZILY — only when something first
     * requests the bound class. By that point, the database is available and
     * we can safely call ProviderCredential::forProvider(). Do not run
     * DB queries at the top level of register() — only inside closures.
     */
    public function register(): void
    {
        // Override the library's singleton so credentials come from the DB
        // (admin Settings → Data Providers) rather than .env.
        // Our AppServiceProvider runs after the library's BiblionetServiceProvider,
        // so this binding wins.
        $this->app->singleton(LibraryBiblionetClient::class, function () {
            $cred = Schema::hasTable('provider_credentials')
                ? ProviderCredential::forProvider(ProviderType::Biblionet)
                : null;

            $username = $cred?->credential('username') ?? config('biblionet.username', '');
            $password = $cred?->credential('password') ?? config('biblionet.password', '');
            $timeout = (int) ($cred?->setting('timeout') ?? config('biblionet.timeout', 30));

            $factory = new HttpFactory;

            return new LibraryBiblionetClient(
                httpClient: new GuzzleClient(['timeout' => $timeout]),
                requestFactory: $factory,
                streamFactory: $factory,
                username: $username ?? '',
                password: $password ?? '',
            );
        });

        $this->app->bind(OpenLibraryClientInterface::class, function () {
            $cred = Schema::hasTable('provider_credentials')
                ? ProviderCredential::forProvider(ProviderType::OpenLibrary)
                : null;

            return new OpenLibraryClient(
                baseUrl: config('services.openlibrary.base_url', 'https://openlibrary.org'),
                userAgent: $cred?->credential('user_agent') ?? config('services.openlibrary.user_agent', 'EUCatalog/1.0'),
                rateLimit: (int) ($cred?->setting('rate_limit') ?? config('services.openlibrary.rate_limit', 3)),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // super_admin bypasses all Gate checks — they can do everything without
        // needing explicit permission assignments. Other roles use syncPermissions().
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
