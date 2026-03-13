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
        // Register queue event listeners for monitoring and failure handling
        $this->registerQueueEventListeners();
    }

    /**
     * Register queue event listeners
     */
    private function registerQueueEventListeners(): void
    {
        $queueFailureHandler = app(\App\Services\QueueFailureHandler::class);

        // Listen for job processing events
        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) use ($queueFailureHandler) {
            $queueFailureHandler->handleJobProcessing($event);
        });

        // Listen for job success events
        \Illuminate\Support\Facades\Queue::after(function (\Illuminate\Queue\Events\JobProcessed $event) use ($queueFailureHandler) {
            $queueFailureHandler->handleJobProcessed($event);
        });

        // Listen for job failure events
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) use ($queueFailureHandler) {
            $queueFailureHandler->handleJobFailed($event);
        });
    }
}