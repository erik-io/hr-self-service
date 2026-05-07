<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Leave\LeaveDurationCalculatorInterface;
use App\Services\Leave\LeaveDurationService;
use App\Services\Leave\LeaveEntitlementCalculatorInterface;
use App\Services\Leave\LeaveEntitlementService;
use App\Services\Leave\LeaveRequestService;
use App\Services\Leave\LeaveRequestServiceInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LeaveEntitlementCalculatorInterface::class, LeaveEntitlementService::class);
        $this->app->bind(LeaveRequestServiceInterface::class, LeaveRequestService::class);
        $this->app->bind(LeaveDurationCalculatorInterface::class, LeaveDurationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
