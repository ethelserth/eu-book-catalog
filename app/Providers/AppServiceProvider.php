<?php

namespace App\Providers;

use App\Clients\BiblionetClient;
use App\Clients\Contracts\BiblionetClientInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * register() is called early in the bootstrap lifecycle — before boot().
     * Use it to bind things into the container. Do NOT call other services
     * here (use boot() for that), because they may not be ready yet.
     */
    public function register(): void
    {
        // Bind the BIBLIONET client interface to the concrete implementation.
        // Constructor args are read from config (which reads from .env).
        // Any class that type-hints BiblionetClientInterface will receive
        // a fully configured BiblionetClient instance automatically.
        $this->app->bind(BiblionetClientInterface::class, function () {
            return new BiblionetClient(
                baseUrl:      config('services.biblionet.base_url'),
                clientId:     config('services.biblionet.client_id'),
                clientSecret: config('services.biblionet.client_secret'),
                rateLimit:    config('services.biblionet.rate_limit'),
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
