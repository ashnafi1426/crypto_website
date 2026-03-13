<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Contracts\WalletManagerInterface;
use App\Services\Contracts\TradingEngineInterface;
use App\Services\Contracts\PriceSimulatorInterface;
use App\Services\Contracts\AdminPanelInterface;
use App\Services\Contracts\AuthenticationServiceInterface;
use App\Services\WalletManager;
use App\Services\TradingEngine;
use App\Services\PriceSimulator;
use App\Services\AdminPanelService;
use App\Services\AuthenticationService;

class TradingServiceProvider extends ServiceProvider
{
    /**
     * Register services for the crypto exchange backend.
     */
    public function register(): void
    {
        // Bind service interfaces to their implementations
        $this->app->singleton(AuthenticationServiceInterface::class, AuthenticationService::class);
        $this->app->singleton(WalletManagerInterface::class, WalletManager::class);
        $this->app->singleton(TradingEngineInterface::class, TradingEngine::class);
        $this->app->singleton(PriceSimulatorInterface::class, PriceSimulator::class);
        $this->app->singleton(AdminPanelInterface::class, AdminPanelService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}