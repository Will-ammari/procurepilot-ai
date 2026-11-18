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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('login', static function (Request $request): array {
            $identity = Str::lower(trim((string) $request->input('email', '')));

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
