<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Vendor;
use App\Policies\DepartmentPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\QuotePolicy;
use App\Policies\VendorPolicy;
use App\Services\Monitoring\ApiRequestTelemetry;
use App\Services\Outbox\OutboxTransport;
use App\Services\Outbox\QueueOutboxTransport;
use App\Services\Security\ApiResponseSecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OutboxTransport::class, QueueOutboxTransport::class);
    }

    public function boot(): void
    {
        Event::listen(RequestHandled::class, ApiRequestTelemetry::class);
        Event::listen(RequestHandled::class, ApiResponseSecurityHeaders::class);

        RateLimiter::for('login', static function (Request $request): array {
            $identity = hash('sha256', Str::lower(trim((string) $request->input('email', ''))));

            return [
                Limit::perMinute(5)->by($identity.'|'.$request->ip()),
                Limit::perMinute(30)->by('ip|'.$request->ip()),
            ];
        });

        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(PurchaseRequest::class, PurchaseRequestPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
    }
}
