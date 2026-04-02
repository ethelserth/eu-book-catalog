<?php

namespace App\Providers;

use App\Clients\BiblionetClient;
use App\Clients\Contracts\BiblionetClientInterface;
use App\Clients\Contracts\OpenLibraryClientInterface;
use App\Clients\OpenLibraryClient;
use App\Models\ProviderCredential;
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
     * requests the interface. By that point, the database is available and
     * we can safely call ProviderCredential::forProvider(). Do not run
     * DB queries at the top level of register() — only inside closures.
     */
    public function register(): void
    {
        $this->app->bind(BiblionetClientInterface::class, function () {
            // Guard: during `php artisan migrate`, the table may not exist yet.
            // Schema::hasTable() is a cheap check that prevents a crash on
            // fresh deployments. Falls back to .env config when table is absent.
            $cred = Schema::hasTable('provider_credentials')
                ? ProviderCredential::forProvider('biblionet')
                : null;

            return new BiblionetClient(
                baseUrl: $cred?->credential('base_url') ?? config('services.biblionet.base_url'),
                clientId: $cred?->credential('client_id') ?? config('services.biblionet.client_id'),
                clientSecret: $cred?->credential('client_secret') ?? config('services.biblionet.client_secret'),
                rateLimit: (int) ($cred?->setting('rate_limit') ?? config('services.biblionet.rate_limit')),
            );
        });

        $this->app->bind(OpenLibraryClientInterface::class, function () {
            $cred = Schema::hasTable('provider_credentials')
                ? ProviderCredential::forProvider('openlibrary')
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
